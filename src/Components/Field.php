<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
    protected string|Closure|ConditionalText|null $label = null;

    protected string|Closure|ConditionalText|null $hint = null;

    protected string|Closure|ConditionalText|null $placeholder = null;

    protected mixed $default = null;

    protected bool|Closure $required = false;

    protected ?Condition $requiredWhen = null;

    protected bool|Closure $disabled = false;

    protected ?Condition $disabledWhen = null;

    /** @var array<int, mixed> */
    protected array $rules = [];

    /** @var array{mode: string, ms: int|null}|null */
    protected ?array $live = null;

    /** @var array<int, Closure> */
    protected array $afterStateUpdated = [];

    /** @var array<int, string> */
    protected array $dependsOn = [];

    protected ?bool $dehydrated = null;

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

        return $this;
    }

    public function disabledWhen(Condition $condition): static
    {
        $this->disabledWhen = $condition;

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
    public function dehydrated(bool $condition = true): static
    {
        $this->dehydrated = $condition;

        return $this;
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
        return ! $this->isHiddenOnServer() && ! $this->isDisabled();
    }

    public function isDisabledInState(): bool
    {
        return $this->isDisabled()
            || ($this->disabledWhen?->evaluate($this->getForm()->getState(), $this->container) ?? false);
    }

    public function isDehydrated(): bool
    {
        return $this->dehydrated ?? ($this->computed !== null || ! $this->isDisabledInState());
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
            ...$this->rules,
        ];
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
        ];
    }
}
