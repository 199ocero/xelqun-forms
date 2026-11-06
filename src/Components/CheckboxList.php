<?php

namespace Xelqun\Forms\Components;

use Xelqun\Forms\Components\Concerns\HasColumns;
use Xelqun\Forms\Components\Concerns\HasOptions;

/**
 * Pick several options, shown as checkboxes. The state is a list of values, so a
 * model needs an array cast on the column.
 *
 * @api
 */
class CheckboxList extends Field
{
    use HasColumns;
    use HasOptions {
        formatStateFromRecord as formatOptionState;
    }

    /** @var array<array-key, string> */
    protected array $descriptions = [];

    protected bool $isSearchable = false;

    protected bool $isBulkToggleable = false;

    public function getType(): string
    {
        return 'checkbox-list';
    }

    /**
     * @param  array<array-key, string>  $descriptions  Option value => text under it.
     */
    public function descriptions(array $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    /**
     * Add buttons that select and deselect every option at once.
     */
    public function bulkToggleable(bool $condition = true): static
    {
        $this->isBulkToggleable = $condition;

        return $this;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return array_values((array) ($this->formatOptionState($value) ?? []));
    }

    public function getDefaultState(): mixed
    {
        return array_values((array) (parent::getDefaultState() ?? []));
    }

    protected function getTypeRules(): array
    {
        return $this->getOptionRules(multiple: true);
    }

    protected function getProps(): array
    {
        $options = $this->getOptionList();

        foreach ($options as $index => $option) {
            $options[$index]['description'] = $this->descriptions[$option['value']] ?? null;
        }

        return [
            'options' => $options,
            'columns' => $this->columns,
            'searchable' => $this->isSearchable,
            'bulkToggleable' => $this->isBulkToggleable,
        ];
    }
}
