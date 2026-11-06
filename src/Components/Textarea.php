<?php

namespace Xelqun\Forms\Components;

/**
 * @api
 */
class Textarea extends Field
{
    protected int $rows = 3;

    protected ?int $maxLength = null;

    public function getType(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'debounce', 'ms' => 400];
    }

    protected function getTypeRules(): array
    {
        return $this->maxLength !== null ? ['string', 'max:'.$this->maxLength] : ['string'];
    }

    protected function getClientRules(): array
    {
        return $this->maxLength === null ? [] : [['rule' => 'max', 'type' => 'string', 'value' => $this->maxLength]];
    }

    protected function getProps(): array
    {
        return ['rows' => $this->rows, 'maxLength' => $this->maxLength];
    }
}
