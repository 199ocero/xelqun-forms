<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;
use stdClass;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\StatePath;

/**
 * A list of rows that share one schema. Row state is keyed by stable string
 * keys (UUIDs, or "record-{id}" for related models), never by array index.
 */
class Repeater extends Field
{
    public const string RECORD_KEY_PREFIX = 'record-';

    protected ?string $relationship = null;

    protected ?string $orderColumn = null;

    protected int $minItems = 0;

    protected ?int $maxItems = null;

    protected int $defaultItems = 0;

    protected string|Closure|null $addActionLabel = null;

    protected bool $isOrderable = true;

    protected string|Closure|null $itemLabel = null;

    /** @var array<string, int>|null */
    protected ?array $columns = null;

    public function getType(): string
    {
        return 'repeater';
    }

    /**
     * Save rows to a HasMany relationship (defaults to the field name).
     */
    public function relationship(?string $name = null, ?string $orderColumn = null): static
    {
        $this->relationship = $name ?? $this->name;
        $this->orderColumn = $orderColumn;

        return $this;
    }

    public function minItems(int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    /**
     * Number of empty rows a new form starts with.
     */
    public function defaultItems(int $count): static
    {
        $this->defaultItems = $count;

        return $this;
    }

    public function addActionLabel(string|Closure|null $label): static
    {
        $this->addActionLabel = $label;

        return $this;
    }

    public function orderable(bool $condition = true): static
    {
        $this->isOrderable = $condition;

        return $this;
    }

    /**
     * Singular name of one row, used in headings and validation messages.
     */
    public function itemLabel(string|Closure|null $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    /**
     * Grid columns inside each row. An integer means one column on small
     * screens and that many from the "md" breakpoint.
     *
     * @param  int|array<string, int>  $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = is_int($columns)
            ? ($columns > 1 ? ['default' => 1, 'md' => $columns] : ['default' => 1])
            : $columns;

        return $this;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationship;
    }

    public function getOrderColumn(): ?string
    {
        return $this->orderColumn;
    }

    public function getItemLabel(): string
    {
        return (string) ($this->evaluate($this->itemLabel) ?? 'Item');
    }

    protected function getChildContainer(): string
    {
        return $this->getStatePath().'.'.StatePath::ROW_PLACEHOLDER;
    }

    protected function getChildContainerPattern(): string
    {
        return $this->getStatePathPattern().'.*';
    }

    protected function getChildPosition(int $index): string
    {
        return (string) $index;
    }

    /**
     * Rows are expanded from state, so generic tree walks stop here.
     */
    public function getChildComponents(): array
    {
        return [];
    }

    /**
     * @return array<int, Component>
     */
    public function getTemplateComponents(): array
    {
        return $this->childComponents;
    }

    /**
     * @return array<int, string>
     */
    public function getRowKeys(): array
    {
        $state = $this->getState();

        return is_array($state) ? array_map('strval', array_keys($state)) : [];
    }

    /**
     * Fresh component instances for one row.
     *
     * @return array<int, Component>
     */
    public function getRowComponents(string $rowKey): array
    {
        $components = [];

        foreach ($this->childComponents as $index => $template) {
            $component = clone $template;
            $component->mount(
                $this->getForm(),
                $this,
                $this->getStatePath().'.'.$rowKey,
                (string) $index,
                $this->getStatePathPattern().'.*',
            );
            $components[] = $component;
        }

        return $components;
    }

    /**
     * @return array<string, array<int, Component>>
     */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->getRowKeys() as $rowKey) {
            $rows[$rowKey] = $this->getRowComponents($rowKey);
        }

        return $rows;
    }

    public static function newRowKey(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  Model|array<string, mixed>|null  $source
     */
    public function fillState(Model|array|null $source): void
    {
        $form = $this->getForm();
        $form->setStateValue($this->getStatePath(), []);

        foreach ($this->getSourceRows($source) as $rowKey => $rowSource) {
            $form->setStateValue($this->getStatePath().'.'.$rowKey, []);

            Form::eachField($this->getRowComponents($rowKey), fn (Field $field) => $field->fillState($rowSource));
        }
    }

    /**
     * @param  Model|array<string, mixed>|null  $source
     * @return array<string, Model|array<string, mixed>|null>
     */
    private function getSourceRows(Model|array|null $source): array
    {
        if ($source instanceof Model && $source->exists && $this->relationship !== null) {
            $related = $this->getRelationshipInstance($source)->get();

            if ($this->orderColumn !== null) {
                $related = $related->sortBy($this->orderColumn);
            }

            return $related
                ->mapWithKeys(fn (Model $model): array => [self::RECORD_KEY_PREFIX.$model->getKey() => $model])
                ->all();
        }

        $items = match (true) {
            $source instanceof Model && $source->exists => $source->getAttribute($this->name),
            is_array($source) => $source[$this->name] ?? null,
            default => $this->getDefaultState(),
        };

        if (is_array($items) && $items !== []) {
            $rows = [];

            foreach ($items as $item) {
                $rows[self::newRowKey()] = is_array($item) ? $item : [];
            }

            return $rows;
        }

        $rows = [];

        for ($index = 0; $index < $this->defaultItems; $index++) {
            $rows[self::newRowKey()] = null;
        }

        return $rows;
    }

    /**
     * @return HasMany<Model, Model>
     */
    public function getRelationshipInstance(Model $record): HasMany
    {
        $relationship = $record->{$this->relationship}();

        if (! $relationship instanceof HasMany) {
            throw new LogicException("Repeater relationship [{$this->relationship}] must be a HasMany relationship.");
        }

        return $relationship;
    }

    /**
     * Default state for a row the user adds in the browser.
     *
     * @return array<string, mixed>
     */
    public function getNewRowState(): array
    {
        $state = [];

        Form::eachField($this->childComponents, function (Field $field) use (&$state): void {
            $state[$field->getName()] = $field instanceof Repeater ? new stdClass : $field->getDefaultState();
        });

        return $state;
    }

    public function getValidationRules(): array
    {
        $rules = [$this->isRequiredInState() ? 'required' : 'nullable', 'array'];

        if ($this->minItems > 0) {
            $rules[] = 'min:'.$this->minItems;
        }

        if ($this->maxItems !== null) {
            $rules[] = 'max:'.$this->maxItems;
        }

        return [...$rules, ...$this->rules];
    }

    protected function getProps(): array
    {
        $template = [];

        foreach ($this->childComponents as $component) {
            if (($node = $component->toNode()) !== null) {
                $template[] = $node;
            }
        }

        $rows = [];

        foreach ($this->getRows() as $rowKey => $components) {
            $rows[$rowKey] = [];

            foreach ($components as $component) {
                if (($node = $component->toNode()) !== null) {
                    $rows[$rowKey][] = $node;
                }
            }
        }

        return [
            'template' => $template,
            'rows' => $rows === [] ? new stdClass : $rows,
            'newRow' => (object) $this->getNewRowState(),
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'addLabel' => $this->evaluate($this->addActionLabel) ?? 'Add '.Str::lower($this->getItemLabel()),
            'itemLabel' => $this->getItemLabel(),
            'orderable' => $this->isOrderable,
            'columns' => $this->columns,
        ];
    }
}
