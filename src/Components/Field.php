<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Forms\Components\Concerns\HasValidationRules;
use Xelqun\Forms\Conditions\Condition;
use Xelqun\Forms\Conditions\ConditionalText;
use Xelqun\Forms\Contracts\ContainsRows;
use Xelqun\Forms\Contracts\NeedsServer;
use Xelqun\Forms\Expressions\Expression;
use Xelqun\Forms\Support\StatePath;

/**
 * Base class for every component that owns a piece of state. Extend this to
 * build a field.
 *
 * @api Anything public or protected here, unless it says @internal.
 */
abstract class Field extends Component implements NeedsServer
{
    use HasValidationRules;

    protected string|Closure|ConditionalText|null $label = null;

    protected string|Closure|ConditionalText|null $hint = null;

    protected string|Closure|ConditionalText|null $placeholder = null;

    protected mixed $default = null;

    protected bool|Closure $required = false;

    protected ?Condition $requiredWhen = null;

    protected bool|Closure $disabled = false;

    protected ?Condition $disabledWhen = null;

    protected bool|Closure $readOnly = false;

    protected bool|Closure $autofocus = false;

    protected bool|Closure $hiddenLabel = false;

    protected bool|Closure $inlineLabel = false;

    /** @var array<int, mixed> */
    protected array $rules = [];

    /** @var array<string, string|Closure> */
    protected array $validationMessages = [];

    protected string|Closure|null $validationAttribute = null;

    /** @var array{mode: string, ms: int|null}|null */
    protected ?array $live = null;

    /** @var array<int, Closure> */
    protected array $afterStateUpdated = [];

    /** @var array<int, Closure> */
    protected array $afterStateHydrated = [];

    protected ?Closure $dehydrateStateUsing = null;

    /** @var array<int, string> */
    protected array $dependsOn = [];

    protected bool|Closure|null $dehydrated = null;

    protected ?Expression $computed = null;

    final public function __construct(protected string $name)
    {
        if ($name === '' || str_contains($name, '.')) {
            throw new \InvalidArgumentException("Field names must be non-empty and contain no dots, [{$name}] given.");
        }
    }

    public static function make(string $name): static
    {
        $field = new static($name);
        $field->setUp();

        return $field->configure();
    }

    protected function setUp(): void {}

    public function label(string|Closure|ConditionalText|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function hint(string|Closure|ConditionalText|null $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function placeholder(string|Closure|ConditionalText|null $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        $this->required = $condition;

        return $this;
    }

    /**
     * Require the field only while the condition is true (checked in the browser and on submit).
     */
    public function requiredWhen(Condition $condition): static
    {
        $this->requiredWhen = $condition;

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->disabled = $condition;
        unset($this->operationRules['disabled']);

        return $this;
    }

    /**
     * Disable the field on these operations, such as "edit". Replaces disabled(),
     * and locks like disabled(true): no closure has to run to know.
     *
     * @param  string|array<int, string>  $operations
     */
    public function disabledOn(string|array $operations): static
    {
        $this->operationRules['disabled'] = static fn (string $operation): bool => in_array($operation, (array) $operations, true);

        return $this;
    }

    public function disabledWhen(Condition $condition): static
    {
        $this->disabledWhen = $condition;

        return $this;
    }

    /**
     * Show the value without letting anyone change it. The server drops what the
     * browser posts and skips the rules. Nothing is written over a stored value:
     * only a new record or row saves it, as its default. On a field with rows
     * this covers every field in them. Controls with no read-only mode render
     * as disabled.
     */
    public function readOnly(bool|Closure $condition = true): static
    {
        $this->readOnly = $condition;

        return $this;
    }

    /**
     * Focus this field when the form opens.
     */
    public function autofocus(bool|Closure $condition = true): static
    {
        $this->autofocus = $condition;

        return $this;
    }

    /**
     * Hide the label on screen. Screen readers still announce it.
     */
    public function hiddenLabel(bool|Closure $condition = true): static
    {
        $this->hiddenLabel = $condition;

        return $this;
    }

    /**
     * Put the label beside the field. Checkboxes and toggles already work this way.
     */
    public function inlineLabel(bool|Closure $condition = true): static
    {
        $this->inlineLabel = $condition;

        return $this;
    }

    /**
     * Add Laravel validation rules.
     *
     * @param  string|array<int, mixed>  $rules
     */
    public function rules(string|array $rules): static
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        array_push($this->rules, ...array_values($rules));

        return $this;
    }

    /**
     * Error messages for this field, by rule name, such as ['required' => 'Who is
     * this for?']. Laravel's placeholders work here, including :attribute.
     *
     * @param  array<string, string|Closure>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    /**
     * What error messages call this field. Defaults to its label.
     */
    public function validationAttribute(string|Closure|null $attribute): static
    {
        $this->validationAttribute = $attribute;

        return $this;
    }

    /**
     * Send changes of this field to the server. By default the server is only
     * called when a server closure depends on this field.
     */
    public function live(bool $onBlur = false, ?int $debounce = null): static
    {
        $this->live = match (true) {
            $onBlur => ['mode' => 'blur', 'ms' => null],
            $debounce !== null => ['mode' => 'debounce', 'ms' => $debounce],
            default => $this->getDefaultLiveConfig(),
        };

        return $this;
    }

    /**
     * Run a server closure after the user changes this field. Implies live().
     */
    public function afterStateUpdated(Closure $callback): static
    {
        $this->afterStateUpdated[] = $callback;

        return $this;
    }

    /**
     * Run a server closure after the form is filled, with every field's state in
     * place. Every request rebuilds the form, so this runs each time: it must
     * give the same result for the same record, and do nothing else.
     *
     * shortcut: a row added in the browser gets its plain default, with no hook, until rows are built on the server.
     */
    public function afterStateHydrated(Closure $callback): static
    {
        $this->afterStateHydrated[] = $callback;

        return $this;
    }

    /**
     * Change the value the form shows. The same rules as afterStateHydrated()
     * apply, and dehydrateStateUsing() is the way back.
     */
    public function formatStateUsing(Closure $callback): static
    {
        return $this->afterStateHydrated(static fn (Field $component) => $component->state($component->evaluate($callback)));
    }

    /**
     * Change the value after validation, before it is returned or saved. A
     * field with rows ignores this, because its value is built from its rows.
     */
    public function dehydrateStateUsing(Closure $callback): static
    {
        $this->dehydrateStateUsing = $callback;

        return $this;
    }

    /**
     * Change this field's value from a server closure.
     */
    public function state(mixed $state): static
    {
        $form = $this->getForm();
        $path = $this->getStatePath();

        $form->setStateValue($path, $state);
        $form->markAsSet($path);

        return $this;
    }

    /**
     * Declare dependencies the automatic tracking cannot see, e.g. a branch
     * of a closure that has not run yet.
     *
     * @param  array<int, string>  $paths
     */
    public function dependsOn(array $paths): static
    {
        array_push($this->dependsOn, ...$paths);

        return $this;
    }

    /**
     * Whether the value is included in the submitted data.
     */
    public function dehydrated(bool|Closure $condition = true): static
    {
        $this->dehydrated = $condition;

        return $this;
    }

    /**
     * Whether the value is saved. Use saved(false) for a field with no column,
     * or disabled()->saved() to keep a disabled field's server value. Rules still
     * run on a field that isn't saved, unless it is disabled.
     */
    public function saved(bool|Closure $condition = true): static
    {
        return $this->dehydrated($condition);
    }

    /**
     * Calculate the value in the browser, e.g. computed('qty * price').
     * The server recomputes it on submit.
     */
    public function computed(string $expression): static
    {
        $this->computed = Expression::make($expression);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatePath(): string
    {
        return $this->container.'.'.$this->name;
    }

    /**
     * The state path with every repeater row key replaced by "*".
     */
    public function getStatePathPattern(): string
    {
        return $this->containerPattern.'.'.$this->name;
    }

    public function getKey(): string
    {
        return $this->getStatePath();
    }

    public function getLabel(): string
    {
        $label = $this->resolveText($this->label);

        return is_string($label) ? $label : $this->getDefaultLabel();
    }

    protected function getDefaultLabel(): string
    {
        return Str::headline(Str::endsWith($this->name, '_id') ? Str::beforeLast($this->name, '_id') : $this->name);
    }

    protected function resolveText(string|Closure|ConditionalText|null $text): ?string
    {
        return $text instanceof ConditionalText
            ? $text->resolve($this->getForm()->getState(), $this->container)
            : $this->evaluate($text);
    }

    /**
     * Conditional text for the browser. Conditions read no state through Get,
     * so they add no dependency and cause no update request.
     *
     * @return array<string, array{when: array<string, mixed>, text: string, otherwise: ?string}>
     */
    protected function getTextRules(): array
    {
        $rules = [];

        foreach (['label' => $this->label, 'hint' => $this->hint, 'placeholder' => $this->placeholder] as $key => $text) {
            if ($text instanceof ConditionalText) {
                $rules[$key] = $text->toArray($this->container);
            }
        }

        if (isset($rules['label'])) {
            $rules['label']['otherwise'] ??= $this->getDefaultLabel();
        }

        return $rules;
    }

    public function getState(): mixed
    {
        return $this->getForm()->getStateValue($this->getStatePath());
    }

    public function getDefaultState(): mixed
    {
        return $this->evaluate($this->default);
    }

    /**
     * Convert a model attribute into form state.
     */
    public function formatStateFromRecord(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * Fill this field's state from a model or an array row, or from its
     * default when there is no source.
     *
     * @param  Model|array<string, mixed>|null  $source
     */
    public function fillState(Model|array|null $source): void
    {
        $value = match (true) {
            $source instanceof Model && $source->exists => $this->formatStateFromRecord($source->getAttribute($this->name)),
            is_array($source) => $this->formatStateFromRecord($source[$this->name] ?? $this->getDefaultState()),
            default => $this->getDefaultState(),
        };

        $this->getForm()->setStateValue($this->getStatePath(), $value);
    }

    /**
     * The value this field had before any browser input. A null is a real
     * value here. Only a row added in the browser has none, so it gets the default.
     *
     * @internal
     */
    public function getServerState(): mixed
    {
        $form = $this->getForm();
        $path = $this->getStatePath();

        return $form->hasInitialValue($path) ? $form->getInitialValue($path) : $this->getDefaultState();
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->required);
    }

    public function isRequiredInState(): bool
    {
        return $this->isRequired()
            || ($this->requiredWhen?->evaluate($this->getForm()->getState(), $this->container) ?? false);
    }

    public function isDisabled(): bool
    {
        return (bool) $this->evaluate($this->disabled);
    }

    public function isDisabledStatically(): bool
    {
        return $this->disabled === true;
    }

    /**
     * Whether the browser may use this field's endpoints: search, upload and hooks.
     */
    public function acceptsInput(): bool
    {
        return ! $this->isHiddenOnServer() && ! $this->isDisabled() && ! $this->isReadOnly();
    }

    /**
     * Whether this field or a field containing it is read-only.
     */
    public function isReadOnly(): bool
    {
        for ($component = $this; $component !== null; $component = $component->getParent()) {
            if ($component instanceof self && $component->evaluate($component->readOnly)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The browser merges patches and never removes a key, so a field that could
     * be read-only has to say so either way.
     */
    private function canBeReadOnly(): bool
    {
        for ($component = $this; $component !== null; $component = $component->getParent()) {
            if ($component instanceof self && $component->readOnly !== false) {
                return true;
            }
        }

        return false;
    }

    public function getChildModel(Model $parent): ?Model
    {
        return $this instanceof ContainsRows ? $this->getRowModel($parent) : null;
    }

    /**
     * Whether a read-only field's value goes out with the saved data. A new
     * record or row needs its default. A stored value stays where it is, because
     * the form may hold a formatted copy, except in a row with no model: that row
     * is written whole, so leaving the key out would lose the value.
     */
    private function keepsReadOnlyValue(): bool
    {
        $form = $this->getForm();
        $record = $form->getRecord();

        if (! $record?->exists || ! $form->hasInitialValue($this->getStatePath())) {
            return true;
        }

        return $this->container !== StatePath::ROOT && $form->getModelInstanceFor($this) === $record;
    }

    public function isReadOnlyStatically(): bool
    {
        return $this->readOnly === true;
    }

    public function isDisabledInState(): bool
    {
        return $this->isDisabled()
            || ($this->disabledWhen?->evaluate($this->getForm()->getState(), $this->container) ?? false);
    }

    public function isDehydrated(): bool
    {
        if ($this->dehydrated !== null) {
            return (bool) $this->evaluate($this->dehydrated);
        }

        if ($this->computed !== null) {
            return true;
        }

        if ($this->isReadOnly()) {
            return $this->keepsReadOnlyValue();
        }

        return ! $this->isDisabledInState();
    }

    public function getComputed(): ?Expression
    {
        return $this->computed;
    }

    public function hasAfterStateUpdatedHooks(): bool
    {
        return $this->afterStateUpdated !== [];
    }

    /**
     * @internal
     */
    public function callAfterStateHydrated(): void
    {
        foreach ($this->afterStateHydrated as $callback) {
            $this->evaluate($callback);
        }
    }

    /**
     * @internal
     */
    public function callAfterStateUpdated(mixed $old = null): void
    {
        foreach ($this->afterStateUpdated as $callback) {
            $this->evaluate($callback, ['old' => $old]);
        }
    }

    public function needsServer(): bool
    {
        return $this->getLiveConfig() !== null;
    }

    /**
     * @return array{mode: string, ms: int|null}|null
     */
    public function getLiveConfig(): ?array
    {
        if ($this->live !== null) {
            return $this->live;
        }

        return $this->afterStateUpdated !== [] ? $this->getDefaultLiveConfig() : null;
    }

    /**
     * @return array{mode: string, ms: int|null}
     */
    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'change', 'ms' => null];
    }

    /**
     * Laravel validation rules for this field's own path.
     *
     * @return array<int, mixed>
     */
    public function getValidationRules(): array
    {
        return [
            $this->isRequiredInState() ? $this->getRequiredRule() : 'nullable',
            ...$this->getTypeRules(),
            ...$this->getCustomRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return array_map(fn (string|Closure $message): string => $this->evaluate($message), $this->validationMessages);
    }

    public function getValidationAttribute(): string
    {
        return $this->evaluate($this->validationAttribute) ?? $this->getLabel();
    }

    /**
     * The message for a rule that checks with a closure, which Laravel can't
     * match to validationMessages() by itself. Laravel fills in :attribute.
     */
    public function getFailureMessage(string $rule): string
    {
        return $this->getValidationMessages()[$rule] ?? __("validation.{$rule}");
    }

    protected function getRequiredRule(): string
    {
        return 'required';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTypeRules(): array
    {
        return [];
    }

    /**
     * Transform the validated value before it is returned or saved.
     */
    public function dehydrateState(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @internal Override dehydrateState() instead.
     */
    public function getDehydratedState(mixed $value): mixed
    {
        $value = $this->dehydrateState($value);

        return $this->dehydrateStateUsing === null ? $value : $this->evaluate($this->dehydrateStateUsing, ['state' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getNodeAttributes(): array
    {
        foreach ($this->dependsOn as $path) {
            $this->recordDependency(StatePath::resolve($path, $this->container));
        }

        $textRules = $this->getTextRules();

        return [
            'path' => $this->getStatePath(),
            'label' => $this->getLabel(),
            'hint' => $this->resolveText($this->hint),
            'placeholder' => $this->resolveText($this->placeholder),
            'required' => $this->isRequired(),
            'disabled' => $this->isDisabled(),
            'rules' => [
                'visible' => null,
                'required' => $this->requiredWhen?->toArray($this->container),
                'disabled' => $this->disabledWhen?->toArray($this->container),
                ...($textRules === [] ? [] : ['text' => $textRules]),
            ],
            'computed' => $this->computed?->toArray($this->container),
            'live' => $this->getLiveConfig(),
            ...($this instanceof ContainsRows ? ['containsRows' => true] : []),
            ...($this->canBeReadOnly() ? ['readOnly' => $this->isReadOnly()] : []),
            ...array_map(
                fn (bool|Closure $flag): bool => (bool) $this->evaluate($flag),
                array_filter([
                    'autofocus' => $this->autofocus,
                    'hiddenLabel' => $this->hiddenLabel,
                    'inlineLabel' => $this->inlineLabel,
                ], fn (bool|Closure $flag): bool => $flag !== false),
            ),
        ];
    }
}
