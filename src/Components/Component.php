<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Xelqun\Forms\Conditions\Condition;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Support\StatePath;

/**
 * Base class for every node of a form: fields and layouts.
 *
 * @api Anything public or protected here, unless it says @internal.
 */
abstract class Component
{
    use Macroable;

    /** @var array<class-string<Component>, array<int, Closure>> */
    private static array $configurations = [];

    protected ?Form $form = null;

    protected ?Component $parent = null;

    protected string $container = StatePath::ROOT;

    protected string $containerPattern = StatePath::ROOT;

    protected string $position = '0';

    /** @var array<int, Component> */
    protected array $childComponents = [];

    /** @var array<int, Component> Extra content around a field. See HasSlots. */
    protected array $slotComponents = [];

    protected ?string $slot = null;

    protected bool|Closure|null $grow = null;

    protected bool|Closure $hidden = false;

    /** @var array<string, Closure(string): bool> Property name => whether it is on for an operation. */
    protected array $operationRules = [];

    /** @var array<string, mixed>|Closure */
    protected array|Closure $extraAttributes = [];

    protected ?Condition $visibleWhen = null;

    protected ?Condition $hiddenWhen = null;

    /** @var int|string|array<string, int|string>|null */
    protected int|string|array|null $columnSpan = null;

    /** @var array<int, string> */
    protected array $recordedDependencies = [];

    /**
     * Run a callback on every new instance of this class and its subclasses,
     * after setUp() and before the caller's own settings. Call it from a service
     * provider: it lasts as long as the process, and each call adds another.
     *
     * @param  Closure(static): mixed  $callback
     */
    public static function configureUsing(Closure $callback): void
    {
        self::$configurations[static::class][] = $callback;
    }

    /**
     * Forget every configureUsing() callback, so tests don't leak into each other.
     *
     * @internal
     */
    public static function flushConfigurations(): void
    {
        self::$configurations = [];
    }

    /**
     * Apply the configureUsing() callbacks. Every make() has to end with this.
     */
    protected function configure(): static
    {
        foreach (self::$configurations as $class => $callbacks) {
            if (! $this instanceof $class) {
                continue;
            }

            foreach ($callbacks as $callback) {
                $callback($this);
            }
        }

        return $this;
    }

    /**
     * The renderer key of this node, e.g. "text-input" or "section".
     */
    abstract public function getType(): string;

    /**
     * Evaluate the optional props that were set, leaving out the rest. A prop
     * that was set is sent even when it comes out false or null, because the
     * browser merges patches and would otherwise keep the old value.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    protected function evaluateConfigured(array $props): array
    {
        return array_map(
            fn (mixed $value): mixed => $this->evaluate($value),
            array_filter($props, fn (mixed $value): bool => $value !== null && $value !== false),
        );
    }

    /**
     * Type-specific data for the renderer.
     *
     * @return array<string, mixed>
     */
    protected function getProps(): array
    {
        return [];
    }

    /**
     * @param  array<int, Component>  $components
     */
    public function schema(array $components): static
    {
        $this->childComponents = array_values($components);

        return $this;
    }

    /**
     * Hide the component on the server. A closure is re-evaluated whenever
     * the state it reads changes.
     */
    public function hidden(bool|Closure $condition = true): static
    {
        $this->hidden = $condition;
        unset($this->operationRules['hidden']);

        return $this;
    }

    /**
     * Hide the component on these operations, such as "edit". Replaces hidden()
     * and visible(), and locks like hidden(true): no closure has to run to know.
     *
     * @param  string|array<int, string>  $operations
     */
    public function hiddenOn(string|array $operations): static
    {
        $this->operationRules['hidden'] = static fn (string $operation): bool => in_array($operation, (array) $operations, true);

        return $this;
    }

    /**
     * Show the component only on these operations. See hiddenOn().
     *
     * @param  string|array<int, string>  $operations
     */
    public function visibleOn(string|array $operations): static
    {
        $this->operationRules['hidden'] = static fn (string $operation): bool => ! in_array($operation, (array) $operations, true);

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        unset($this->operationRules['hidden']);

        $this->hidden = $condition instanceof Closure
            ? static fn (Component $component): bool => ! $component->evaluate($condition)
            : ! $condition;

        return $this;
    }

    /**
     * Show the component only while the condition is true. Evaluated in the
     * browser without a server call, and re-checked on submit. Combines with
     * hiddenWhen(): the component shows only when both allow it.
     */
    public function visibleWhen(Condition $condition): static
    {
        $this->visibleWhen = $condition;

        return $this;
    }

    /**
     * Hide the component while the condition is true. See visibleWhen().
     */
    public function hiddenWhen(Condition $condition): static
    {
        $this->hiddenWhen = $condition;

        return $this;
    }

    private function getVisibleCondition(): ?Condition
    {
        $hidden = $this->hiddenWhen === null ? null : When::not($this->hiddenWhen);

        return match (true) {
            $this->visibleWhen === null => $hidden,
            $hidden === null => $this->visibleWhen,
            default => When::and($this->visibleWhen, $hidden),
        };
    }

    /**
     * @param  int|string|array<string, int|string>  $span
     */
    public function columnSpan(int|string|array $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function columnSpanFull(): static
    {
        return $this->columnSpan('full');
    }

    /**
     * Whether this component takes the free space in a Flex. It does by default.
     */
    public function grow(bool|Closure $condition = true): static
    {
        $this->grow = $condition;

        return $this;
    }

    /**
     * HTML attributes for the component's outer element. They reach the page as
     * given, so never put user input in an attribute name.
     *
     * @param  array<string, mixed>|Closure  $attributes
     */
    public function extraAttributes(array|Closure $attributes): static
    {
        $this->extraAttributes = $attributes;

        return $this;
    }

    /**
     * Attach the component to a form and place it in the tree.
     *
     * @internal
     */
    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        $this->form = $form;
        $this->parent = $parent;
        $this->container = $container;
        $this->containerPattern = $containerPattern ?? $container;
        $this->position = $position;

        foreach ($this->operationRules as $property => $appliesTo) {
            $this->{$property} = $appliesTo($form->getOperation());
        }

        foreach ($this->childComponents as $index => $child) {
            $child->mount($form, $this, $this->getChildContainer(), $this->getChildPosition($index), $this->getChildContainerPattern());
        }

        foreach ($this->slotComponents as $index => $child) {
            $child->mount($form, $this, $this->container, "{$this->position}.s{$index}", $this->containerPattern);
        }

        return $this;
    }

    protected function getChildContainer(): string
    {
        return $this->container;
    }

    protected function getChildContainerPattern(): string
    {
        return $this->containerPattern;
    }

    protected function getChildPosition(int $index): string
    {
        return $this->position.'.'.$index;
    }

    public function __clone()
    {
        foreach ($this->childComponents as $index => $child) {
            $this->childComponents[$index] = clone $child;
        }

        foreach ($this->slotComponents as $index => $child) {
            $this->slotComponents[$index] = clone $child;
        }
    }

    /**
     * Name the slot this component fills.
     *
     * @internal
     */
    public function inSlot(string $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getSlot(): ?string
    {
        return $this->slot;
    }

    public function getKey(): string
    {
        return $this->container.'#'.$this->position;
    }

    /**
     * What this component adds to the scope of the uploads inside it, for one
     * that can't tell them apart by state path. See AcceptsUploads.
     */
    public function getUploadScopePart(): string
    {
        return '';
    }

    /**
     * The "type" of the rows this component is for, when a row's shape depends
     * on it, as in a Builder.
     */
    public function getRowType(): ?string
    {
        return null;
    }

    /**
     * @internal
     */
    public function getContainerPattern(): string
    {
        return $this->containerPattern;
    }

    /**
     * The key with every repeater row key replaced by "*".
     *
     * @internal
     */
    public function getKeyPattern(): string
    {
        return $this->containerPattern.'#'.$this->position;
    }

    public function getContainer(): string
    {
        return $this->container;
    }

    public function getForm(): Form
    {
        return $this->form ?? throw new LogicException('The component ['.static::class.'] is not mounted to a form.');
    }

    public function getParent(): ?Component
    {
        return $this->parent;
    }

    /**
     * The model this component's children read relationships from, or null
     * when they use the same model as this component. Gets the model above.
     */
    public function getChildModel(Model $parent): ?Model
    {
        return null;
    }

    /**
     * What this component's children fill their state from.
     *
     * @param  Model|array<string, mixed>|null  $source
     * @return Model|array<string, mixed>|null
     */
    public function getChildSource(Model|array|null $source): Model|array|null
    {
        return $source;
    }

    /**
     * Children rendered by this component. Repeaters leave out their template
     * because their rows are expanded from state.
     *
     * @return array<int, Component>
     */
    public function getChildComponents(): array
    {
        return [...$this->childComponents, ...$this->slotComponents];
    }

    /**
     * @internal
     */
    public function recordDependency(string $path): void
    {
        if (! in_array($path, $this->recordedDependencies, true)) {
            $this->recordedDependencies[] = $path;
        }
    }

    /**
     * @internal
     */
    public function isHiddenStatically(): bool
    {
        return $this->hidden === true;
    }

    /**
     * @internal
     */
    public function isHiddenDynamically(): bool
    {
        return $this->hidden instanceof Closure && (bool) $this->evaluate($this->hidden);
    }

    /**
     * Whether a server rule hides this component or one of its ancestors. These
     * rules are the security boundary, unlike visibleWhen(), which the browser
     * evaluates against state it controls.
     *
     * @internal
     */
    public function isHiddenOnServer(): bool
    {
        for ($component = $this; $component !== null; $component = $component->getParent()) {
            if ($component->isHiddenStatically() || $component->isHiddenDynamically()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a server rule could hide this component or one of its ancestors.
     * Such a field's value only reaches the browser while the field is showing.
     *
     * @internal
     */
    public function canBeHiddenOnServer(): bool
    {
        for ($component = $this; $component !== null; $component = $component->getParent()) {
            if ($component->hidden !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this component and all its ancestors show for the current state.
     */
    public function isShown(): bool
    {
        for ($component = $this; $component !== null; $component = $component->getParent()) {
            if (! $component->isVisibleInState()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether this component is visible for the current state, ignoring ancestors.
     */
    public function isVisibleInState(): bool
    {
        if ($this->isHiddenStatically() || $this->isHiddenDynamically()) {
            return false;
        }

        return $this->getVisibleCondition()?->evaluate($this->getForm()->getState(), $this->container) ?? true;
    }

    /**
     * Evaluate a value, calling closures with injected helpers such as Get, Set, $state and $record.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function evaluate(mixed $value, array $parameters = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $get = new Get($this);
        $set = new Set($this);

        return app()->call($value, [
            'get' => $get,
            'set' => $set,
            Get::class => $get,
            Set::class => $set,
            'state' => $this instanceof Field ? $this->getState() : null,
            'record' => $this->getForm()->getRecord(),
            'form' => $this->getForm(),
            'operation' => $this->getForm()->getOperation(),
            'component' => $this,
            ...$parameters,
        ]);
    }

    /**
     * Serialize to a schema node, or null when the server hides it entirely.
     *
     * @internal Add to a node through getProps() instead. Xelqun's own packages call this, so it changes only in a major version.
     *
     * @return array<string, mixed>|null
     */
    public function toNode(): ?array
    {
        if ($this->isHiddenStatically()) {
            return null;
        }

        $this->recordedDependencies = [];

        if ($this->isHiddenDynamically()) {
            return [
                'key' => $this->getKey(),
                'type' => $this->getType(),
                'hidden' => true,
                'deps' => $this->recordedDependencies,
            ];
        }

        $node = [
            'key' => $this->getKey(),
            'type' => $this->getType(),
            'hidden' => false,
            ...$this->getNodeAttributes(),
            'span' => $this->columnSpan,
            'props' => (object) $this->getProps(),
            'children' => $this->getChildNodes(),
        ];

        if ($this->extraAttributes !== []) {
            $node['extraAttributes'] = $this->evaluate($this->extraAttributes);
        }

        if ($this->slot !== null) {
            $node['slot'] = $this->slot;
        }

        if ($this->grow !== null) {
            $node['grow'] = (bool) $this->evaluate($this->grow);
        }

        $node['rules']['visible'] = $this->getVisibleCondition()?->toArray($this->container);
        $node['deps'] = $this->recordedDependencies;

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getNodeAttributes(): array
    {
        return [
            'path' => null,
            'label' => null,
            'rules' => ['visible' => null],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getChildNodes(): array
    {
        $nodes = [];

        foreach ($this->getChildComponents() as $child) {
            $node = $child->toNode();

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }
}
