<?php

namespace Xelqun\Forms;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;
use Throwable;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\AcceptsUploads;
use Xelqun\Forms\Contracts\ContainsRows;
use Xelqun\Forms\Contracts\EmbedsUploads;
use Xelqun\Forms\Contracts\HasSearchResults;
use Xelqun\Forms\Contracts\NeedsServer;
use Xelqun\Forms\Eloquent\ModelPersister;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Exceptions\InvalidFormToken;
use Xelqun\Forms\Schema\SchemaVersion;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Uploads\UploadBatch;
use Xelqun\Forms\Validation\FormValidator;

/**
 * A form definition. Extend it and implement schema() for forms that talk to
 * the server (live fields, closures, uploads). Use Form::make([...]) for
 * simple static forms.
 *
 * @api Anything public or protected here, unless it says @internal.
 *
 * @template TModel of Model = Model
 */
class Form
{
    /** @var array<int, Component>|null */
    private ?array $mountedComponents = null;

    /** @var array<int, Component>|null */
    private ?array $anonymousComponents = null;

    /** @var array<string, mixed> */
    private array $state = [StatePath::ROOT => []];

    /** @var array<string, mixed> */
    private array $initialState = [StatePath::ROOT => []];

    /** @var array<int, string> */
    private array $setPaths = [];

    /** @var array<int, string> */
    private array $refreshedKeys = [];

    private ?string $formId = null;

    private bool $hasUnsavedChangesAlerts = false;

    private bool $hasFrontendValidation = false;

    private readonly string $operation;

    /** @var array<string, Model> The record each row or related group was filled from, by container. */
    private array $filledRecords = [];

    /**
     * @param  TModel|null  $record
     * @param  array<string, mixed>  $context  Scalar values that are needed to rebuild the form (kept in the token).
     *                                         Set "operation" for one beyond create and edit, such as "view".
     *                                         "disabled" is reserved for disabled().
     */
    final public function __construct(
        protected ?Model $record = null,
        protected array $context = [],
    ) {
        $this->operation = is_string($context['operation'] ?? null)
            ? $context['operation']
            : ($record?->getKey() === null ? 'create' : 'edit');

        $this->boot();
    }

    /**
     * Create the form and fill its state from the record or defaults.
     *
     * Pass an array of components to build an anonymous, static form.
     *
     * @param  TModel|array<int, Component>|null  $record
     * @param  array<string, mixed>  $context
     */
    public static function make(Model|array|null $record = null, array $context = []): static
    {
        if (is_array($record)) {
            $form = new static(null, $context);
            $form->anonymousComponents = array_values($record);

            return $form->fill();
        }

        return (new static($record, $context))->fill();
    }

    protected function boot(): void {}

    /**
     * Find the record a token names. A form that may only reach some records
     * overrides this, so requests made after the page loaded stay inside them.
     */
    public function resolveRecord(int|string $key): Model
    {
        $model = $this->model() ?? throw InvalidFormToken::make('the form has no model to load the record from');

        /** @var Model $instance */
        $instance = new $model;

        return $instance->newQuery()->findOrFail($key);
    }

    /**
     * @return array<int, Component>
     */
    public function schema(): array
    {
        return $this->anonymousComponents ?? [];
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return true;
    }

    /**
     * The Eloquent model class this form creates.
     *
     * @return class-string<TModel>|null
     */
    public function model(): ?string
    {
        return null;
    }

    public function id(string $id): static
    {
        $this->formId = $id;

        return $this;
    }

    /**
     * Warn before the user leaves with changes that aren't saved. For a form
     * class, call this from boot().
     */
    public function unsavedChangesAlerts(bool $condition = true): static
    {
        $this->hasUnsavedChangesAlerts = $condition;

        return $this;
    }

    /**
     * Check the simple rules in the browser before calling the server: required,
     * email, numeric, integer, and lengths and limits set with the typed helpers.
     * The server still checks everything. The user sees those errors first, and
     * errors only the server can find after fixing them, so it isn't the default.
     * For a form class, call this from boot().
     */
    public function frontendValidation(bool $condition = true): static
    {
        $this->hasFrontendValidation = $condition;

        return $this;
    }

    public function hasFrontendValidation(): bool
    {
        return $this->hasFrontendValidation;
    }

    /**
     * Show the form without letting anyone change it, as on a View page. Every
     * field is disabled, and save() refuses. Actions in an Actions layout still
     * work, because they answer to their own authorize().
     *
     * It lives in the context because that travels in the token. Every later
     * request rebuilds the form, and a plain property would be gone by then.
     */
    public function disabled(bool $condition = true): static
    {
        $this->context['disabled'] = $condition;

        return $this;
    }

    public function isDisabled(): bool
    {
        return ($this->context['disabled'] ?? false) === true;
    }

    public function getId(): string
    {
        return $this->formId ?? ($this->isAnonymous() ? 'form' : Str::kebab(class_basename(static::class)));
    }

    public function isAnonymous(): bool
    {
        return $this->anonymousComponents !== null;
    }

    /**
     * @return TModel|null
     */
    public function getRecord(): ?Model
    {
        return $this->record;
    }

    /**
     * What this form is doing: "create", "edit" or the context's "operation".
     * Fixed when the form is built, so saving a new record leaves it on "create"
     * and a request can never change it. Closures can ask for $operation.
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Put the record back after a rollback. The model in memory can look saved
     * while the database is not, and a retry would then write nothing.
     *
     * @internal Xelqun's own packages call this, so it changes only in a major version.
     *
     * @param  TModel|null  $record
     */
    public function setRecord(?Model $record): void
    {
        $this->record = $record;
    }

    /**
     * The connection this form's record is saved on.
     */
    public function getConnection(): Connection
    {
        return $this->record !== null || $this->model() !== null
            ? $this->getModelInstance()->getConnection()
            : DB::connection();
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return TModel
     */
    public function getModelInstance(): Model
    {
        if ($this->record !== null) {
            return $this->record;
        }

        $model = $this->model() ?? throw new FormDefinitionException(static::class.' must define model(). Relationships, unique() and exists() read its table.');

        return new $model;
    }

    /**
     * The model a component reads relationships from: the closest ancestor's
     * child model, or the form's model.
     *
     * @internal
     */
    public function getModelInstanceFor(Component $component): Model
    {
        $ancestors = [];

        for ($parent = $component->getParent(); $parent !== null; $parent = $parent->getParent()) {
            array_unshift($ancestors, $parent);
        }

        $model = $this->getModelInstance();

        foreach ($ancestors as $ancestor) {
            $model = $ancestor->getChildModel($model) ?? $model;
        }

        return $model;
    }

    /**
     * The record a component's value came from: the form's, or the one its row or
     * related group was filled from. It is null for a row added in the browser
     * and for a posted row key the form never filled, so the record a browser
     * names can't be trusted here.
     */
    public function getRecordFor(Component $component): ?Model
    {
        return $component->getContainer() === StatePath::ROOT
            ? $this->record
            : ($this->filledRecords[$component->getContainer()] ?? null);
    }

    /**
     * @return array<int, Component>
     */
    public function getComponents(): array
    {
        if ($this->mountedComponents === null) {
            $this->mountedComponents = array_values($this->schema());

            foreach ($this->mountedComponents as $index => $component) {
                $component->mount($this, null, StatePath::ROOT, $this->getRootPosition($component, $index));
            }

            $this->ensureUniqueStatePaths($this->mountedComponents);
        }

        return $this->mountedComponents;
    }

    /**
     * What identifies a top-level component in its key.
     *
     * @internal
     */
    protected function getRootPosition(Component $component, int $index): string
    {
        return (string) $index;
    }

    /**
     * State, validation, live patches and the browser all address a field by
     * its path, so two fields on one path would silently overwrite each other.
     *
     * @param  array<int, Component>  $components
     */
    private function ensureUniqueStatePaths(array $components): void
    {
        $paths = [];

        self::eachField($components, function (Field $field) use (&$paths): void {
            $path = $field->getStatePath();

            if (isset($paths[$path])) {
                throw new FormDefinitionException(
                    "Two fields use the state path [{$path}]. Give them different names. To change a field's label, options or rules, use closures on one field.",
                );
            }

            $paths[$path] = true;

            foreach ($field->getTemplateGroups() as $group) {
                $this->ensureUniqueStatePaths($group);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * The form data without the "data" wrapper.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->state[StatePath::ROOT];
    }

    /**
     * Replace the form data, e.g. with the state posted by the browser.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->state = [StatePath::ROOT => $data];
        $this->setPaths = [];

        $this->walk(function (Component $component): void {
            if ($component instanceof ContainsRows) {
                $component->normalizePostedRows();
            }
        });

        return $this;
    }

    public function getStateValue(string $path): mixed
    {
        return StatePath::get($this->state, $path);
    }

    public function setStateValue(string $path, mixed $value): void
    {
        data_set($this->state, $path, $value);
    }

    public function forgetStateValue(string $path): void
    {
        Arr::forget($this->state, $path);
    }

    /**
     * @internal
     */
    public function markAsSet(string $path): void
    {
        if (! in_array($path, $this->setPaths, true)) {
            $this->setPaths[] = $path;
        }
    }

    /**
     * Send this component to the browser again with the next patch, for a change
     * no state shows, such as a new option in a select.
     */
    public function refreshComponent(Component $component): void
    {
        $this->refreshedKeys[] = $component->getKey();
    }

    /**
     * @internal
     *
     * @return array<int, string>
     */
    public function getRefreshedKeys(): array
    {
        return $this->refreshedKeys;
    }

    /**
     * Paths written by server closures during this request.
     *
     * @internal
     *
     * @return array<int, string>
     */
    public function getSetPaths(): array
    {
        return $this->setPaths;
    }

    /**
     * Values the field had when the form was filled. Used to make sure a
     * submitted file path really belonged to the record.
     *
     * @internal
     *
     * @return array<int, mixed>
     */
    public function getInitialValues(string $pattern): array
    {
        $values = StatePath::get($this->initialState, $pattern);

        return str_contains($pattern, '*') ? (array) $values : [$values];
    }

    /**
     * @internal
     */
    public function hasInitialValue(string $path): bool
    {
        return Arr::has($this->initialState, $path);
    }

    /**
     * The value a path had when the form was filled, before any browser input.
     *
     * @internal
     */
    public function getInitialValue(string $path): mixed
    {
        return data_get($this->initialState, $path);
    }

    /**
     * Fill the state from the record, or from field defaults.
     */
    public function fill(): static
    {
        $this->state = [StatePath::ROOT => []];
        $this->filledRecords = [];

        self::fillFields($this->getComponents(), $this->getFillSource());

        $this->walk(function (Component $component): void {
            if ($component instanceof Field) {
                $component->callAfterStateHydrated();
            }
        });

        $this->recomputeComputedFields();
        $this->initialState = $this->state;
        $this->setPaths = [];

        return $this;
    }

    /**
     * @return Model|array<string, mixed>|null
     */
    protected function getFillSource(): Model|array|null
    {
        return $this->record;
    }

    /**
     * Fill every field in the components, looking through layouts but not into
     * repeater rows. A component can give its children another source.
     *
     * @param  array<int, Component>  $components
     * @param  Model|array<string, mixed>|null  $source
     *
     * @internal
     */
    public static function fillFields(array $components, Model|array|null $source): void
    {
        foreach ($components as $component) {
            if ($source instanceof Model) {
                $component->getForm()->filledRecords[$component->getContainer()] = $source;
            }

            if ($component instanceof Field) {
                $component->fillState($source);
            }

            self::fillFields($component->getChildComponents(), $component->getChildSource($source));
        }
    }

    /**
     * Call the callback for every field in the components, looking through
     * layouts but not into repeater rows.
     *
     * @param  array<int, Component>  $components
     * @param  Closure(Field): mixed  $callback
     */
    public static function eachField(array $components, Closure $callback): void
    {
        foreach ($components as $component) {
            if ($component instanceof Field) {
                $callback($component);
            }

            self::eachField($component->getChildComponents(), $callback);
        }
    }

    /**
     * Depth-first walk over the whole tree, including repeater rows built from
     * the current state. Return false from the callback to skip children.
     *
     * @param  Closure(Component): mixed  $callback
     * @param  array<int, Component>|null  $components
     */
    public function walk(Closure $callback, ?array $components = null): void
    {
        foreach ($components ?? $this->getComponents() as $component) {
            if ($callback($component) === false) {
                continue;
            }

            if ($component instanceof ContainsRows) {
                foreach ($component->getRows() as $rowComponents) {
                    $this->walk($callback, $rowComponents);
                }
            }

            $this->walk($callback, $component->getChildComponents());
        }
    }

    /**
     * Find a component by its schema key. Repeater rows are built on demand,
     * so a row's fields can be found even before the row exists in state.
     *
     * @param  array<int, Component>|null  $components
     */
    public function findComponent(string $key, ?array $components = null): ?Component
    {
        foreach ($components ?? $this->getComponents() as $component) {
            if ($component->getKey() === $key) {
                return $component;
            }

            if ($component instanceof ContainsRows) {
                $prefix = $component->getStatePath().'.';

                if (str_starts_with($key, $prefix)) {
                    $rowKey = explode('#', explode('.', substr($key, strlen($prefix)))[0])[0];

                    if ($rowKey !== '' && StatePath::isSafeRowKey($rowKey)) {
                        return $this->findComponent($key, $component->getRowComponents($rowKey));
                    }
                }
            }

            $found = $this->findComponent($key, $component->getChildComponents());

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Recalculate every computed field with the server-side evaluator. Runs a
     * few passes so computed values that depend on other computed values settle.
     *
     * @internal
     */
    public function recomputeComputedFields(): void
    {
        for ($pass = 0; $pass < 5; $pass++) {
            $changed = false;

            $this->walk(function (Component $component) use (&$changed): void {
                if (! $component instanceof Field || ($expression = $component->getComputed()) === null) {
                    return;
                }

                $value = $expression->evaluate($this->state, $component->getContainer());

                if ($component->getState() !== $value) {
                    $this->setStateValue($component->getStatePath(), $value);
                    $changed = true;
                }
            });

            if (! $changed) {
                return;
            }
        }
    }

    /**
     * Serialize the form to the versioned JSON schema rendered by the Vue package.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $components = [];

        foreach ($this->getComponents() as $component) {
            if (($node = $component->toNode()) !== null) {
                $components[] = $node;
            }
        }

        if ($this->isAnonymous()) {
            $this->ensureStatic();
        }

        return [
            '$xq' => SchemaVersion::CURRENT,
            'pkg' => SchemaVersion::PACKAGE,
            'id' => $this->getId(),
            'token' => $this->isAnonymous() ? null : $this->makeToken(),
            'endpoints' => [
                'update' => route('xelqun.forms.update', absolute: false),
                'upload' => route('xelqun.forms.upload', absolute: false),
                'search' => route('xelqun.forms.search', absolute: false),
                'validate' => route('xelqun.forms.validate', absolute: false),
                'action' => route('xelqun.forms.action', absolute: false),
            ],
            // Laravel writes pt_BR, and browsers throw on anything but pt-BR.
            'locale' => str_replace('_', '-', app()->getLocale()),
            ...$this->getClientMessages(),
            ...($this->hasUnsavedChangesAlerts ? ['unsavedChangesAlert' => true] : []),
            ...($this->isDisabled() ? ['disabled' => true] : []),
            'state' => [StatePath::ROOT => $this->getJsonData()],
            'components' => $components,
        ];
    }

    /**
     * Text the browser shows, in this locale. It is written in English and uses
     * that as the key, so only lines that differ have to travel.
     *
     * @return array{messages?: array<string, string>}
     */
    private function getClientMessages(): array
    {
        $lines = trans('xelqun-forms::client');
        $messages = is_array($lines)
            ? array_filter($lines, fn (string $text, string $key): bool => $text !== $key, ARRAY_FILTER_USE_BOTH)
            : [];

        return $messages === [] ? [] : ['messages' => $messages];
    }

    /**
     * The data the browser receives: no value of a field hidden on the server,
     * and empty repeaters cast to objects, so JSON keeps "{}" instead of "[]".
     *
     * @return array<string, mixed>|stdClass
     */
    public function getJsonData(): array|stdClass
    {
        $state = $this->state;

        $this->walk(function (Component $component) use (&$state): void {
            if (! $component instanceof Field) {
                return;
            }

            if ($component->isHiddenOnServer()) {
                Arr::forget($state, $component->getStatePath());

                // An empty array becomes "[]" in JSON, and the browser can't add keys to that.
                if ($component->getContainer() !== StatePath::ROOT && data_get($state, $component->getContainer()) === []) {
                    data_set($state, $component->getContainer(), new stdClass);
                }

                return;
            }

            if ($component instanceof ContainsRows && $component->getState() === []) {
                data_set($state, $component->getStatePath(), new stdClass);
            }
        });

        return $state[StatePath::ROOT] === [] ? new stdClass : $state[StatePath::ROOT];
    }

    /**
     * Anonymous forms cannot be rebuilt by the server, so they may not use
     * anything that needs a round-trip.
     */
    private function ensureStatic(): void
    {
        $this->walk(function (Component $component): void {
            $needsServer = ($component instanceof AcceptsUploads && ! $component instanceof EmbedsUploads)
                || ($component instanceof HasSearchResults && $component->isAsync())
                || ($component instanceof NeedsServer && $component->needsServer());

            if ($needsServer) {
                throw new FormDefinitionException(
                    "The field [{$component->getKey()}] needs the server (live, uploads or async search). Define the form as a class extending ".self::class.'.',
                );
            }
        });
    }

    public function makeToken(): string
    {
        return app(FormTokenEncrypter::class)->encrypt($this->toToken());
    }

    /**
     * @internal
     */
    public function toToken(): FormToken
    {
        return new FormToken(
            formClass: static::class,
            recordKey: $this->record?->getKey(),
            userId: auth()->id(),
            context: $this->context,
        );
    }

    /**
     * What an upload is tied to, along with the field. See AcceptsUploads.
     *
     * @internal Xelqun's own packages call this, so it changes only in a major version.
     */
    public function getUploadScope(): string
    {
        return static::class;
    }

    /**
     * Validate submitted data and return it cleaned: hidden and disabled
     * values dropped, computed values recomputed, repeater rows as lists and
     * uploads moved to their final disk.
     *
     * Uploads are final as soon as this returns. If you write the data yourself
     * and that can fail, call this inside DB::transaction() so a rollback undoes
     * them and the user can submit again. save() does this for you.
     *
     * @param  Request|array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(Request|array $input): array
    {
        return app(FormValidator::class)->toPlainData($this, $this->validateKeyed($input));
    }

    /**
     * Like validate() but repeater rows keep their keys.
     *
     * @param  Request|array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @internal
     */
    public function validateKeyed(Request|array $input): array
    {
        $uploads = app(UploadBatch::class);
        $data = $uploads->promote($this, $this->validateInput($input));
        $uploads->settleWithTransaction($this->getConnection());

        return $data;
    }

    /**
     * Authorize and validate without touching any storage: uploads are still tokens.
     *
     * @param  Request|array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateInput(Request|array $input): array
    {
        abort_unless($this->authorize(auth()->user(), $this->record), 403);

        $data = $input instanceof Request ? $input->input(StatePath::ROOT, []) : ($input[StatePath::ROOT] ?? $input);

        return app(FormValidator::class)->validate($this, is_array($data) ? $data : []);
    }

    /**
     * Validate, then create or update the record and its relationships.
     *
     * @param  Request|array<string, mixed>  $input
     * @return TModel
     */
    public function save(Request|array $input): Model
    {
        if ($this->isDisabled()) {
            throw new LogicException('The form ['.static::class.'] is disabled, so it can\'t be saved.');
        }

        $uploads = app(UploadBatch::class);
        $data = $uploads->promote($this, $this->validateInput($input));

        try {
            $uploads->markRemovedFiles($this, $data);
            $this->record = app(ModelPersister::class)->save($this, $data, $uploads);
        } catch (Throwable $exception) {
            $uploads->discardUnlessSettled();

            throw $exception;
        }

        return $this->record;
    }

    /**
     * Runs inside save()'s transaction, before anything is written. Throwing
     * rolls the save back.
     *
     * @param  array<string, mixed>  $data  Validated data. Repeater rows keep their keys.
     */
    public function beforeSave(array $data): void {}

    /**
     * Runs inside save()'s transaction, after the record and its relationships
     * are written. Throwing rolls the save back.
     *
     * @param  TModel  $record
     * @param  array<string, mixed>  $data  Validated data. Repeater rows keep their keys.
     */
    public function afterSave(Model $record, array $data): void {}

    /**
     * Runs once the outermost transaction has committed, so it is the place for
     * emails and queued jobs. A save that rolls back never gets here. Nothing can
     * undo the save now, so an exception here is reported, not thrown: save()
     * still returns the record.
     *
     * @param  TModel  $record
     * @param  array<string, mixed>  $data  Validated data. Repeater rows keep their keys.
     */
    public function afterCommit(Model $record, array $data): void {}

    /**
     * Adjust attributes before save() writes them to the model.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function mutateBeforeSave(array $attributes): array
    {
        return $attributes;
    }
}
