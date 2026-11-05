<?php

namespace Xelqun\Forms;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use stdClass;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Eloquent\ModelPersister;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Schema\SchemaVersion;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Validation\FormValidator;

/**
 * A form definition. Extend it and implement schema() for forms that talk to
 * the server (live fields, closures, uploads). Use Form::make([...]) for
 * simple static forms.
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

    private ?string $formId = null;

    /**
     * @param  TModel|null  $record
     * @param  array<string, mixed>  $context  Scalar values that are needed to rebuild the form (kept in the token).
     */
    final public function __construct(
        protected ?Model $record = null,
        protected array $context = [],
    ) {
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

        $model = $this->model() ?? throw new FormDefinitionException(static::class.' must define model() to use relationships.');

        return new $model;
    }

    /**
     * The model a component reads relationships from: the related model of
     * the closest repeater relationship, or the form's model.
     */
    public function getModelInstanceFor(Component $component): Model
    {
        $parent = $component->getParent();

        while ($parent !== null) {
            if ($parent instanceof Repeater && $parent->getRelationshipName() !== null) {
                return $parent->getRelationshipInstance($this->getModelInstanceFor($parent))->getRelated();
            }

            $parent = $parent->getParent();
        }

        return $this->getModelInstance();
    }

    /**
     * @return array<int, Component>
     */
    public function getComponents(): array
    {
        if ($this->mountedComponents === null) {
            $this->mountedComponents = array_values($this->schema());

            foreach ($this->mountedComponents as $index => $component) {
                $component->mount($this, null, StatePath::ROOT, (string) $index);
            }
        }

        return $this->mountedComponents;
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
            if ($component instanceof Repeater) {
                $component->discardUnsafeRows();
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

    public function markAsSet(string $path): void
    {
        if (! in_array($path, $this->setPaths, true)) {
            $this->setPaths[] = $path;
        }
    }

    /**
     * Paths written by server closures during this request.
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
     * @return array<int, mixed>
     */
    public function getInitialValues(string $pattern): array
    {
        $values = StatePath::get($this->initialState, $pattern);

        return str_contains($pattern, '*') ? (array) $values : [$values];
    }

    /**
     * The value a path had when the form was filled, before any browser input.
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

        self::eachField($this->getComponents(), fn (Field $field) => $field->fillState($this->record));

        $this->recomputeComputedFields();
        $this->initialState = $this->state;
        $this->setPaths = [];

        return $this;
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

            if ($component instanceof Repeater) {
                foreach ($component->getRows() as $rowComponents) {
                    $this->walk($callback, $rowComponents);
                }

                continue;
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

            if ($component instanceof Repeater) {
                $prefix = $component->getStatePath().'.';

                if (str_starts_with($key, $prefix)) {
                    $rowKey = explode('#', explode('.', substr($key, strlen($prefix)))[0])[0];

                    if ($rowKey !== '' && $rowKey !== StatePath::ROW_PLACEHOLDER && Repeater::isSafeRowKey($rowKey)) {
                        return $this->findComponent($key, $component->getRowComponents($rowKey));
                    }
                }

                continue;
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
            ],
            'state' => [StatePath::ROOT => $this->getJsonData()],
            'components' => $components,
        ];
    }

    /**
     * The data with empty repeaters cast to objects, so JSON keeps "{}" instead of "[]".
     *
     * @return array<string, mixed>|stdClass
     */
    public function getJsonData(): array|stdClass
    {
        $state = $this->state;

        $this->walk(function (Component $component) use (&$state): void {
            if ($component instanceof Repeater && $component->getState() === []) {
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
            $needsServer = $component instanceof FileUpload
                || ($component instanceof Select && $component->isAsync())
                || ($component instanceof Field && $component->getLiveConfig() !== null);

            if ($needsServer) {
                throw new FormDefinitionException(
                    "The field [{$component->getKey()}] needs the server (live, uploads or async search). Define the form as a class extending ".self::class.'.',
                );
            }
        });
    }

    public function makeToken(): string
    {
        return app(FormTokenEncrypter::class)->encrypt(new FormToken(
            formClass: static::class,
            recordKey: $this->record?->getKey(),
            userId: auth()->id(),
            context: $this->context,
        ));
    }

    /**
     * Validate submitted data and return it cleaned: hidden and disabled
     * values dropped, computed values recomputed, repeater rows as lists and
     * uploads moved to their final disk.
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
     */
    public function validateKeyed(Request|array $input): array
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
        $data = $this->validateKeyed($input);
        $this->record = app(ModelPersister::class)->save($this, $data);

        return $this->record;
    }

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
