<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;
use stdClass;
use Xelqun\Forms\Components\Concerns\HasColumns;
use Xelqun\Forms\Components\Repeater\TableColumn;
use Xelqun\Forms\Contracts\ContainsRows;
use Xelqun\Forms\Contracts\PersistsItself;
use Xelqun\Forms\Eloquent\ModelPersister;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\StatePath;

/**
 * A list of rows that share one schema. Row state is keyed by stable string
 * keys, never by array index: "record-{id}" for related models, "stored-{hash}"
 * for rows kept on the record, "row-{n}" for starter rows, and a UUID for rows
 * added in the browser.
 *
 * @api Extend this to build a field with rows.
 */
class Repeater extends Field implements ContainsRows, PersistsItself
{
    use HasColumns;

    public const string RECORD_KEY_PREFIX = 'record-';

    private const string STORED_ROW_KEY_PREFIX = 'stored-';

    private const string STARTER_ROW_KEY_PREFIX = 'row-';

    protected ?string $relationship = null;

    protected ?string $orderColumn = null;

    protected int $minItems = 0;

    protected ?int $maxItems = null;

    protected int $defaultItems = 0;

    protected string|Closure|null $addActionLabel = null;

    protected bool $isOrderable = true;

    protected string|Closure|null $itemLabel = null;

    protected ?Closure $mutateBeforeFill = null;

    protected ?Closure $mutateBeforeCreate = null;

    protected ?Closure $mutateBeforeSave = null;

    protected ?Closure $afterCreate = null;

    protected ?Closure $afterUpdate = null;

    protected ?Closure $afterDelete = null;

    protected bool|Closure $isAddable = true;

    protected bool|Closure $isDeletable = true;

    protected bool|Closure $isCloneable = false;

    protected bool|Closure $isCollapsible = false;

    protected bool|Closure $isCollapsed = false;

    protected bool|Closure $hasItemNumbers = true;

    protected bool $isDraggable = true;

    protected bool|Closure $requiresDeleteConfirmation = false;

    protected string|Closure|null $deleteConfirmationMessage = null;

    /** @var array<string, int>|null */
    protected ?array $grid = null;

    /** @var array<int, TableColumn>|null */
    protected ?array $tableColumns = null;

    protected ?Field $simpleField = null;

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

    /**
     * Adjust a related row's attributes before they fill the form. Gets $data
     * and returns it. The record itself is not saved.
     */
    public function mutateRelationshipDataBeforeFillUsing(Closure $callback): static
    {
        $this->mutateBeforeFill = $callback;

        return $this;
    }

    /**
     * Adjust a new related row's attributes. Gets $data and returns it, or null
     * to skip creating the row.
     */
    public function mutateRelationshipDataBeforeCreateUsing(Closure $callback): static
    {
        $this->mutateBeforeCreate = $callback;

        return $this;
    }

    /**
     * Adjust an existing related row's attributes. Gets $data and returns it, or
     * null to leave the row as it is.
     */
    public function mutateRelationshipDataBeforeSaveUsing(Closure $callback): static
    {
        $this->mutateBeforeSave = $callback;

        return $this;
    }

    /**
     * Run after a related row is created. Gets $data and $record.
     */
    public function afterCreate(Closure $callback): static
    {
        $this->afterCreate = $callback;

        return $this;
    }

    /**
     * Run after a related row is updated. Gets $data and $record.
     */
    public function afterUpdate(Closure $callback): static
    {
        $this->afterUpdate = $callback;

        return $this;
    }

    /**
     * Run after a related row is deleted. Gets $record, which is gone from the
     * database but keeps its attributes.
     */
    public function afterDelete(Closure $callback): static
    {
        $this->afterDelete = $callback;

        return $this;
    }

    /**
     * Rows of one field, with a list of values as the state. The field keeps its
     * rules, and needs no label.
     */
    public function simple(Field $field): static
    {
        $this->simpleField = $field->hiddenLabel();

        return $this->schema([$field]);
    }

    /**
     * Let users add rows. A row they post anyway is dropped on the server.
     */
    public function addable(bool|Closure $condition = true): static
    {
        $this->isAddable = $condition;

        return $this;
    }

    /**
     * Let users delete rows. A row they remove anyway comes back on the server.
     */
    public function deletable(bool|Closure $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    /**
     * Add a button that copies a row. File fields start empty in the copy, so two
     * rows never own one stored file.
     */
    public function cloneable(bool|Closure $condition = true): static
    {
        $this->isCloneable = $condition;

        return $this;
    }

    public function collapsible(bool|Closure $condition = true): static
    {
        $this->isCollapsible = $condition;

        return $this;
    }

    /**
     * Start with every row collapsed. A row with an error always opens.
     */
    public function collapsed(bool|Closure $condition = true): static
    {
        $this->isCollapsed = $condition;

        return $this->collapsible();
    }

    /**
     * Show each row's number next to its label.
     */
    public function itemNumbers(bool|Closure $condition = true): static
    {
        $this->hasItemNumbers = $condition;

        return $this;
    }

    /**
     * Ask before deleting a row.
     */
    public function requiresDeleteConfirmation(bool|Closure $condition = true, string|Closure|null $message = null): static
    {
        $this->requiresDeleteConfirmation = $condition;
        $this->deleteConfirmationMessage = $message;

        return $this;
    }

    /**
     * Lay the rows out in columns, like columns() does inside a row.
     *
     * @param  int|array<string, int>  $columns
     */
    public function grid(int|array $columns): static
    {
        $this->grid = $this->normalizeColumns($columns);

        return $this;
    }

    /**
     * Show the rows as a table, with one column per field, in order.
     *
     * @param  array<int, TableColumn>  $columns
     */
    public function table(array $columns): static
    {
        $this->tableColumns = $columns;

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

    /**
     * The same as orderable().
     */
    public function reorderable(bool $condition = true): static
    {
        return $this->orderable($condition);
    }

    /**
     * Turn dragging off. The move buttons stay, because keyboard and touch users
     * need them.
     */
    public function reorderableWithDragAndDrop(bool $condition = true): static
    {
        $this->isDraggable = $condition;

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
        return (string) ($this->evaluate($this->itemLabel) ?? __('xelqun-forms::messages.repeater.item'));
    }

    /**
     * Stands for this repeater's row key in its template. Nested repeaters
     * each use their own, or filling in one level would overwrite the others.
     */
    public function getRowPlaceholder(): string
    {
        $depth = 0;

        for ($parent = $this->getParent(); $parent !== null; $parent = $parent->getParent()) {
            if ($parent instanceof ContainsRows) {
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

    public function normalizePostedRows(): void
    {
        $this->discardUnsafeRows();
        $this->restoreRowOrder();
    }

    /**
     * Remove rows the browser could not have created, before anything reads them.
     */
    private function discardUnsafeRows(): void
    {
        $state = $this->getState();

        if (! is_array($state)) {
            return;
        }

        $rows = array_filter($state, fn (int|string $rowKey): bool => StatePath::isSafeRowKey((string) $rowKey), ARRAY_FILTER_USE_KEY);

        if (count($rows) !== count($state)) {
            $this->getForm()->setStateValue($this->getStatePath(), $rows);
        }
    }

    /**
     * Put rows back in the order the server filled them, with added rows after
     * them. Reordering in the browser is only a button, so a repeater that may
     * not be reordered has to enforce that here.
     */
    private function restoreRowOrder(): void
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

            Form::fillFields($this->getRowComponents($rowKey), $rowSource);
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
                ->mapWithKeys(fn (Model $model): array => [self::RECORD_KEY_PREFIX.$model->getKey() => $this->mutateRowBeforeFill($model)])
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
                $row = $this->simpleField === null ? $item : [$this->simpleField->getName() => $item];
                $rows[$this->filledRowKey($item, $rows)] = is_array($row) ? $row : [];
            }

            return $rows;
        }

        $rows = [];

        for ($index = 0; $index < $this->defaultItems; $index++) {
            $rows[$this->filledRowKey(null, $rows)] = null;
        }

        return $rows;
    }

    private function mutateRowBeforeFill(Model $model): Model
    {
        return $this->mutateBeforeFill === null
            ? $model
            : $model->forceFill($this->evaluate($this->mutateBeforeFill, ['data' => $model->attributesToArray(), 'record' => $model]));
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

    public function getRowModel(Model $parent): ?Model
    {
        return $this->relationship === null ? null : $this->getRelationshipInstance($parent)->getRelated();
    }

    public function shouldPersistItself(): bool
    {
        return $this->relationship !== null;
    }

    /**
     * Create, update and delete related rows. Rows keyed "record-{id}" update
     * an existing related model, but only one that belongs to this record.
     */
    public function persist(Model $record, mixed $state): void
    {
        $relationship = $this->getRelationshipInstance($record);
        $existing = $relationship->get()->keyBy(fn (Model $model): string => (string) $model->getKey());
        $kept = [];
        $order = 0;

        foreach ((array) $state as $rowKey => $row) {
            $row = (array) $row;
            $position = $order++;
            $recordKey = str_starts_with((string) $rowKey, self::RECORD_KEY_PREFIX)
                ? substr((string) $rowKey, strlen(self::RECORD_KEY_PREFIX))
                : null;
            $isExisting = $recordKey !== null && $existing->has($recordKey);

            if ($isExisting) {
                $kept[] = $recordKey;
            }

            $model = $isExisting ? $existing->get($recordKey) : $relationship->make();
            $mutate = $isExisting ? $this->mutateBeforeSave : $this->mutateBeforeCreate;

            $isSaved = app(ModelPersister::class)->persistScope(
                $model,
                $row,
                $this->getRowComponents((string) $rowKey),
                function (array $attributes) use ($mutate, $position): ?array {
                    if ($this->orderColumn !== null) {
                        $attributes[$this->orderColumn] = $position;
                    }

                    return $mutate === null ? $attributes : $this->evaluate($mutate, ['data' => $attributes]);
                },
            );

            if ($isSaved) {
                $this->evaluate($isExisting ? $this->afterUpdate : $this->afterCreate, ['data' => $row, 'record' => $model]);
            }
        }

        $existing->except($kept)->each(function (Model $model): void {
            $model->delete();
            $this->evaluate($this->afterDelete, ['record' => $model]);
        });
    }

    public function toPlainRows(array $rows): array
    {
        $rowContainer = $this->getChildContainer().'.';
        $nested = [];

        Form::eachField($this->childComponents, function (Field $field) use (&$nested, $rowContainer): void {
            if ($field instanceof ContainsRows) {
                $nested[Str::after($field->getStatePath(), $rowContainer)] = $field;
            }
        });

        $plain = [];

        foreach ($rows as $row) {
            foreach ($nested as $path => $field) {
                $inner = data_get($row, $path);

                if (is_array($inner)) {
                    data_set($row, $path, $field->toPlainRows($inner));
                }
            }

            $plain[] = $this->simpleField === null ? $row : data_get($row, $this->simpleField->getName());
        }

        return $plain;
    }

    public function enforceRowLimits(): void
    {
        $rows = (array) $this->getState();
        $filled = (array) $this->getForm()->getInitialValue($this->getStatePath());
        $limited = $rows;

        if (! $this->evaluate($this->isAddable)) {
            $limited = array_intersect_key($limited, $filled);
        }

        if (! $this->evaluate($this->isDeletable)) {
            $limited += array_diff_key($filled, $limited);
        }

        if ($limited !== $rows) {
            $this->getForm()->setStateValue($this->getStatePath(), $limited);
        }
    }

    /**
     * Default state for a row the user adds in the browser.
     *
     * @return array<string, mixed>
     */
    public function getNewRowState(): array
    {
        $state = [];
        $rowContainer = $this->getChildContainer().'.';

        Form::eachField($this->childComponents, function (Field $field) use (&$state, $rowContainer): void {
            if (! $field->isHiddenOnServer()) {
                data_set($state, Str::after($field->getStatePath(), $rowContainer), $field instanceof ContainsRows ? new stdClass : $field->getDefaultState());
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
                $fail(__('xelqun-forms::messages.repeater.stale', ['attribute' => $this->getValidationAttribute()]));
            }
        };

        return [...$rules, ...$this->getCustomRules()];
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
            'addLabel' => $this->evaluate($this->addActionLabel) ?? __('xelqun-forms::messages.repeater.add', ['item' => Str::lower($this->getItemLabel())]),
            'itemLabel' => $this->getItemLabel(),
            'orderable' => $this->isOrderable,
            'columns' => $this->columns,
            'addable' => (bool) $this->evaluate($this->isAddable),
            'deletable' => (bool) $this->evaluate($this->isDeletable),
            'itemNumbers' => (bool) $this->evaluate($this->hasItemNumbers),
            'draggable' => $this->isOrderable && $this->isDraggable,
            ...$this->evaluateConfigured([
                'cloneable' => $this->isCloneable,
                'collapsible' => $this->isCollapsible,
                'collapsed' => $this->isCollapsed,
                'deleteConfirmation' => $this->requiresDeleteConfirmation === false ? null : fn (): ?string => $this->evaluate($this->requiresDeleteConfirmation)
                    ? ($this->evaluate($this->deleteConfirmationMessage) ?? __('xelqun-forms::messages.repeater.delete_confirmation'))
                    : null,
                'grid' => $this->grid,
                'simple' => $this->simpleField !== null,
                'table' => $this->tableColumns === null ? null : array_map(fn (TableColumn $column): array => $column->toArray(), $this->tableColumns),
            ]),
        ];
    }
}
