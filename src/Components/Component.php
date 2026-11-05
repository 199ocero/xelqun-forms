<?php

namespace Xelqun\Forms\Components;

use Closure;
use LogicException;
use Xelqun\Forms\Conditions\Condition;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;
use Xelqun\Forms\Support\StatePath;

/**
 * Base class for every node of a form: fields and layouts.
 */
abstract class Component
{
    protected ?Form $form = null;

    protected ?Component $parent = null;

    protected string $container = StatePath::ROOT;

    protected string $containerPattern = StatePath::ROOT;

    protected string $position = '0';

    /** @var array<int, Component> */
    protected array $childComponents = [];

    protected bool|Closure $hidden = false;

    protected ?Condition $visibleWhen = null;

    /** @var int|string|array<string, int|string>|null */
    protected int|string|array|null $columnSpan = null;

    /** @var array<int, string> */
    protected array $recordedDependencies = [];

    /**
     * The renderer key of this node, e.g. "text-input" or "section".
     */
    abstract public function getType(): string;

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

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->hidden = $condition instanceof Closure
            ? fn (): bool => ! $this->evaluate($condition)
            : ! $condition;

        return $this;
    }

    /**
     * Show the component only while the condition is true. Evaluated in the
     * browser without a server call, and re-checked on submit.
     */
    public function visibleWhen(Condition $condition): static
    {
        $this->visibleWhen = $condition;

        return $this;
    }

    public function hiddenWhen(Condition $condition): static
    {
        return $this->visibleWhen(When::not($condition));
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
     * Attach the component to a form and place it in the tree.
     */
    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        $this->form = $form;
        $this->parent = $parent;
        $this->container = $container;
        $this->containerPattern = $containerPattern ?? $container;
        $this->position = $position;

        foreach ($this->childComponents as $index => $child) {
            $child->mount($form, $this, $this->getChildContainer(), $this->getChildPosition($index), $this->getChildContainerPattern());
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
    }

    public function getKey(): string
    {
        return $this->container.'#'.$this->position;
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
     * Children rendered by this component. Repeaters return an empty list
     * because their rows are expanded from state.
     *
     * @return array<int, Component>
     */
    public function getChildComponents(): array
    {
        return $this->childComponents;
    }

    public function recordDependency(string $path): void
    {
        if (! in_array($path, $this->recordedDependencies, true)) {
            $this->recordedDependencies[] = $path;
        }
    }

    public function isHiddenStatically(): bool
    {
        return $this->hidden === true;
    }

    public function isHiddenDynamically(): bool
    {
        return $this->hidden instanceof Closure && (bool) $this->evaluate($this->hidden);
    }

    /**
     * Whether a server rule hides this component or one of its ancestors. These
     * rules are the security boundary, unlike visibleWhen(), which the browser
     * evaluates against state it controls.
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
     * Whether this component is visible for the current state, ignoring ancestors.
     */
    public function isVisibleInState(): bool
    {
        if ($this->isHiddenStatically() || $this->isHiddenDynamically()) {
            return false;
        }

        return $this->visibleWhen === null
            || $this->visibleWhen->evaluate($this->getForm()->getState(), $this->container);
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
            'component' => $this,
            ...$parameters,
        ]);
    }

    /**
     * Serialize to a schema node, or null when the server hides it entirely.
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

        $node['rules']['visible'] = $this->visibleWhen?->toArray($this->container);
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
