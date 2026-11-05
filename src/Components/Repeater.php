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
 * keys, never by array index: "record-{id}" for related models, "stored-{hash}"
 * for rows kept on the record, "row-{n}" for starter rows, and a UUID for rows
 * added in the browser.
 */
class Repeater extends Field
{
    public const string RECORD_KEY_PREFIX = 'record-';

    private const string STORED_ROW_KEY_PREFIX = 'stored-';

    private const string STARTER_ROW_KEY_PREFIX = 'row-';

    private const string SAFE_ROW_KEY_PATTERN = '/^[A-Za-z0-9_-]+$/';

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

    /**
     * Stands for this repeater's row key in its template. Nested repeaters
     * each use their own, or filling in one level would overwrite the others.
     */
    public function getRowPlaceholder(): string
    {
        $depth = 0;

        for ($parent = $this->getParent(); $parent !== null; $parent = $parent->getParent()) {
            if ($parent instanceof Repeater) {
                $depth++;
            }
        }

        return StatePath::rowPlaceholder($depth);
    }

    protected function getChildContainer(): string
    {
        return $this->getStatePath().'.'.$this->getRowPlaceholder();
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

    /**
     * Row keys become part of state paths, so "." or "*" in one would reach other rows.
     */
    public static function isSafeRowKey(string $rowKey): bool
    {
        return preg_match(self::SAFE_ROW_KEY_PATTERN, $rowKey) === 1;
    }

    /**
     * Remove rows the browser could not have created, before anything reads them.
     */
    public function discardUnsafeRows(): void
    {
        $state = $this->getState();

        if (! is_array($state)) {
            return;
        }

        $rows = array_filter($state, fn (int|string $rowKey): bool => self::isSafeRowKey((string) $rowKey), ARRAY_FILTER_USE_KEY);

        if (count($rows) !== count($state)) {
            $this->getForm()->setStateValue($this->getStatePath(), $rows);
        }
    }

    /**
     * Put rows back in the order the server filled them, with added rows after
     * them. Reordering in the browser is only a button, so a repeater that may
     * not be reordered has to enforce that here.
     */
    public function restoreRowOrder(): void
    {
        $state = $this->getState();

        if ($this->isOrderable || ! is_array($state)) {
            return;
        }

        $filledRows = (array) $this->getForm()->getInitialValue($this->getStatePath());
        $rows = array_replace(array_intersect_key($filledRows, $state), $state);

        $this->getForm()->setStateValue($this->getStatePath(), $rows);
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
                $rows[$this->filledRowKey($item, $rows)] = is_array($item) ? $item : [];
            }

            return $rows;
        }

        $rows = [];

        for ($index = 0; $index < $this->defaultItems; $index++) {
            $rows[$this->filledRowKey(null, $rows)] = null;
        }

        return $rows;
    }

    /**
     * The form is rebuilt on every request, so a key must come out the same
     * each time or posted rows could not be matched to their server values.
     *
     * A stored row is keyed by its content, because other users can change the
     * record: if another row is removed, this one keeps its key, and if this
     * one changed, hasStaleRows() refuses it instead of guessing. The hash is
     * keyed because rows can hold values the browser never sees.
     *
     * Starter rows are not shared with anyone, so their position is enough, and
     * a default that differs per request such as now() can't make them stale.
     *
     * @param  array<string, mixed>  $rows  The rows keyed so far.
     */
    private function filledRowKey(mixed $item, array $rows): string
    {
        if (! $this->getForm()->getRecord()?->exists) {
            return self::STARTER_ROW_KEY_PREFIX.count($rows);
        }

        $hash = hash_hmac('sha256', (string) json_encode($item), (string) config('app.key'));
        $base = self::STORED_ROW_KEY_PREFIX.substr($hash, 0, 16);
        $key = $base;

        for ($copy = 1; array_key_exists($key, $rows); $copy++) {
            $key = $base.'-'.$copy;
        }

        return $key;
    }

    /**
     * Whether the browser posted a row the record no longer has. Its locked
     * values can't be restored, so saving it would silently lose them.
     */
    private function hasStaleRows(): bool
    {
        $filledRows = (array) $this->getForm()->getInitialValue($this->getStatePath());

        foreach ($this->getRowKeys() as $rowKey) {
            if ($this->isRecordRowKey($rowKey) && ! array_key_exists($rowKey, $filledRows)) {
                return true;
            }
        }

        return false;
    }

    private function isRecordRowKey(string $rowKey): bool
    {
        return str_starts_with($rowKey, self::STORED_ROW_KEY_PREFIX)
            || ($this->relationship !== null
                && $this->getForm()->getRecord()?->exists
                && str_starts_with($rowKey, self::RECORD_KEY_PREFIX));
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
            if (! $field->isHiddenOnServer()) {
                $state[$field->getName()] = $field instanceof Repeater ? new stdClass : $field->getDefaultState();
            }
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

        $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->hasStaleRows()) {
                $fail(__(':attribute changed after you opened this form. Reload the page and try again.', ['attribute' => $this->getLabel()]));
            }
        };

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
            'rowPlaceholder' => $this->getRowPlaceholder(),
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
