<?php

namespace Xelqun\Forms\Components;

/**
 * A color, stored as hex unless rgb(), rgba() or hsl() says otherwise.
 *
 * @api
 */
class ColorPicker extends Field
{
    private const array PATTERNS = [
        'hex' => '/^#([a-f0-9]{6}|[a-f0-9]{3})$/i',
        'rgb' => '/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/',
        'rgba' => '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/',
        'hsl' => '/^hsl\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*\)$/',
    ];

    protected string $colorFormat = 'hex';

    public function getType(): string
    {
        return 'color-picker';
    }

    public function hex(): static
    {
        $this->colorFormat = 'hex';

        return $this;
    }

    public function rgb(): static
    {
        $this->colorFormat = 'rgb';

        return $this;
    }

    public function rgba(): static
    {
        $this->colorFormat = 'rgba';

        return $this;
    }

    public function hsl(): static
    {
        $this->colorFormat = 'hsl';

        return $this;
    }

    protected function getTypeRules(): array
    {
        return ['string', ['regex', self::PATTERNS[$this->colorFormat]]];
    }

    protected function getProps(): array
    {
        return ['format' => $this->colorFormat];
    }
}
