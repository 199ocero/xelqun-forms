<?php

namespace Xelqun\Forms\Components;

use Closure;
use Xelqun\Forms\Components\CodeEditor\Language;

/**
 * @api
 */
class CodeEditor extends Field
{
    protected Language|Closure|null $language = null;

    protected bool|Closure $wraps = false;

    protected ?string $minHeight = null;

    public function getType(): string
    {
        return 'code-editor';
    }

    /**
     * Each language loads when a field needs it.
     */
    public function language(Language|Closure|null $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function wrap(bool|Closure $condition = true): static
    {
        $this->wraps = $condition;

        return $this;
    }

    /**
     * Any CSS size.
     */
    public function minHeight(?string $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'debounce', 'ms' => 400];
    }

    protected function getTypeRules(): array
    {
        return ['string'];
    }

    protected function getProps(): array
    {
        return [
            'language' => $this->evaluate($this->language)?->value,
            'wrap' => (bool) $this->evaluate($this->wraps),
            'minHeight' => $this->minHeight,
        ];
    }
}
