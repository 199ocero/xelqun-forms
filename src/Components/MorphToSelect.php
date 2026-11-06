<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LogicException;
use Xelqun\Forms\Components\MorphToSelect\Type;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Layout;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\State\Set;

/**
 * Two selects for a MorphTo relationship: the kind of record, then the record.
 * Their values are the relationship's two columns, saved like any other field.
 *
 * @api
 */
class MorphToSelect extends Layout
{
    /** @var array<int, Type> */
    protected array $types = [];

    protected string|Closure|null $label = null;

    protected bool|Closure $required = false;

    protected bool $isSearchable = false;

    final public function __construct(protected string $relationship) {}

    public static function make(string $relationship): static
    {
        return (new static($relationship))->configure();
    }

    public function getType(): string
    {
        return 'fieldset';
    }

    /**
     * The only records that can be chosen. A posted type outside this list is refused.
     *
     * @param  array<int, Type>  $types
     */
    public function types(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        $this->required = $condition;

        return $this;
    }

    /**
     * Search records on the server instead of loading them all.
     */
    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    /**
     * The column names come from the relationship, so the selects can only be
     * built once the model is known.
     *
     * @internal
     */
    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        $this->form = $form;
        $this->parent = $parent;

        // A blank model, because the record's own relationship loads its stored type, which may be gone.
        $relationship = $form->getModelInstanceFor($this)->newInstance()->{$this->relationship}();

        if (! $relationship instanceof MorphTo) {
            throw new LogicException("MorphToSelect relationship [{$this->relationship}] must be a MorphTo relationship.");
        }

        $this->childComponents = $this->makeSelects($relationship->getMorphType(), $relationship->getForeignKeyName());

        return parent::mount($form, $parent, $container, $position, $containerPattern);
    }

    /**
     * @return array<int, Select>
     */
    private function makeSelects(string $typeColumn, string $keyColumn): array
    {
        $types = collect($this->types)->keyBy(fn (Type $type): string => $type->getMorphClass());
        $selected = function (Get $get) use ($types, $typeColumn): ?Type {
            $type = $get($typeColumn);

            return is_string($type) ? $types->get($type) : null;
        };

        $key = Select::make($keyColumn)
            ->label(fn (Get $get): string => $selected($get)?->getLabel() ?? __('xelqun-forms::messages.morph.record'))
            ->required($this->required);

        return [
            Select::make($typeColumn)
                ->label(__('xelqun-forms::messages.morph.type'))
                ->options($types->map(fn (Type $type): string => $type->getLabel())->all())
                ->required($this->required)
                ->afterStateUpdated(fn (Set $set) => $set($keyColumn, null)),
            $this->isSearchable
                ? $key
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $selected($get)?->getOptions($search, 50) ?? [])
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => $selected($get)?->getOptionLabel($value))
                : $key->options(fn (Get $get): array => $selected($get)?->getOptions() ?? []),
        ];
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label) ?? Str::headline($this->relationship)];
    }
}
