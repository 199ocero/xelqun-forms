<?php

namespace Xelqun\Forms\Components;

use Xelqun\Forms\Components\Concerns\HasOptions;

/**
 * @api
 */
class Radio extends Field
{
    use HasOptions;

    protected bool $inline = false;

    /** @var array<array-key, string> */
    protected array $descriptions = [];

    public function getType(): string
    {
        return 'radio';
    }

    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    /**
     * @param  array<array-key, string>  $descriptions
     */
    public function descriptions(array $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    protected function getTypeRules(): array
    {
        return $this->getOptionRules();
    }

    protected function getProps(): array
    {
        $options = $this->toOptionList($this->getOptions());

        foreach ($options as $index => $option) {
            $options[$index]['description'] = $this->descriptions[$option['value']] ?? null;
        }

        return ['options' => $options, 'inline' => $this->inline];
    }
}
