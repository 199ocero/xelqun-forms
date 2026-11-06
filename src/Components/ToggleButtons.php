<?php

namespace Xelqun\Forms\Components;

use Closure;
use Xelqun\Forms\Components\Concerns\HasColumns;
use Xelqun\Forms\Components\Concerns\HasOptions;

/**
 * Options shown as buttons. Choosing the active button again clears it.
 *
 * @api
 */
class ToggleButtons extends Field
{
    use HasColumns;
    use HasOptions {
        boolean as setBooleanOptions;
        formatStateFromRecord as formatOptionState;
        getDefaultState as getOptionDefaultState;
    }

    protected bool $isMultiple = false;

    protected bool $isInline = false;

    protected bool $isGrouped = false;

    /** @var array<array-key, string>|Closure */
    protected array|Closure $colors = [];

    /** @var array<array-key, string>|Closure */
    protected array|Closure $icons = [];

    public function getType(): string
    {
        return 'toggle-buttons';
    }

    /**
     * Pick several options. The state is then a list of values.
     */
    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function inline(bool $condition = true): static
    {
        $this->isInline = $condition;

        return $this;
    }

    /**
     * Join the buttons into one compact control.
     */
    public function grouped(bool $condition = true): static
    {
        $this->isGrouped = $condition;

        return $this;
    }

    /**
     * @param  array<array-key, string>|Closure  $colors  Option value => primary, success, warning, danger or info.
     */
    public function colors(array|Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * @param  array<array-key, string>|Closure  $icons  Option value => icon name.
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    public function boolean(?string $trueLabel = null, ?string $falseLabel = null, ?string $placeholder = null): static
    {
        $this->colors = $this->colors === [] ? [1 => 'success', 0 => 'danger'] : $this->colors;
        $this->icons = $this->icons === [] ? [1 => 'check', 0 => 'x'] : $this->icons;

        return $this->setBooleanOptions($trueLabel, $falseLabel, $placeholder);
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        $value = $this->formatOptionState($value);

        return $this->isMultiple ? array_values((array) ($value ?? [])) : $value;
    }

    public function getDefaultState(): mixed
    {
        $default = $this->getOptionDefaultState();

        return $this->isMultiple ? array_values((array) ($default ?? [])) : $default;
    }

    protected function getTypeRules(): array
    {
        return $this->getOptionRules($this->isMultiple);
    }

    protected function getProps(): array
    {
        $colors = $this->evaluate($this->colors);
        $icons = $this->evaluate($this->icons);
        $options = $this->getOptionList();

        foreach ($options as $index => $option) {
            $options[$index]['color'] = $colors[$option['value']] ?? null;
            $options[$index]['icon'] = $icons[$option['value']] ?? null;
        }

        return [
            'options' => $options,
            'multiple' => $this->isMultiple,
            'inline' => $this->isInline,
            'grouped' => $this->isGrouped,
            'columns' => $this->columns,
        ];
    }
}
