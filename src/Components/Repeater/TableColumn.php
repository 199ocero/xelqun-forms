<?php

namespace Xelqun\Forms\Components\Repeater;

/**
 * One column of a repeater shown as a table, in the order of its fields.
 *
 * @api
 */
class TableColumn
{
    protected ?string $width = null;

    protected bool $isHeaderLabelHidden = false;

    protected bool $isMarkedAsRequired = false;

    final public function __construct(protected string $label) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    /**
     * Any CSS width, such as "200px".
     */
    public function width(?string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Hide the header on screen. Screen readers still read it.
     */
    public function hiddenHeaderLabel(bool $condition = true): static
    {
        $this->isHeaderLabelHidden = $condition;

        return $this;
    }

    /**
     * Show an asterisk in the header. It adds no rule.
     */
    public function markAsRequired(bool $condition = true): static
    {
        $this->isMarkedAsRequired = $condition;

        return $this;
    }

    /**
     * @return array{label: string, width: string|null, hiddenLabel: bool, required: bool}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'width' => $this->width,
            'hiddenLabel' => $this->isHeaderLabelHidden,
            'required' => $this->isMarkedAsRequired,
        ];
    }
}
