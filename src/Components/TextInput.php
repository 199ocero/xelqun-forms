<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * @api
 */
class TextInput extends Field
{
    protected string $inputType = 'text';

    protected bool $isNumeric = false;

    protected bool $isInteger = false;

    protected int|float|Closure|null $minValue = null;

    protected int|float|Closure|null $maxValue = null;

    protected int|float|null $step = null;

    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected string|Closure|null $prefix = null;

    protected string|Closure|null $suffix = null;

    protected ?string $autocomplete = null;

    public function getType(): string
    {
        return 'text-input';
    }

    public function email(): static
    {
        $this->inputType = 'email';

        return $this;
    }

    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    public function tel(): static
    {
        $this->inputType = 'tel';

        return $this;
    }

    public function url(): static
    {
        $this->inputType = 'url';

        return $this;
    }

    public function numeric(): static
    {
        $this->isNumeric = true;

        return $this;
    }

    public function integer(): static
    {
        $this->isNumeric = true;
        $this->isInteger = true;

        return $this;
    }

    public function minValue(int|float|Closure $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(int|float|Closure $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function minLength(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function prefix(string|Closure|null $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string|Closure|null $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function autocomplete(?string $autocomplete): static
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }

    public function isNumeric(): bool
    {
        return $this->isNumeric;
    }

    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'debounce', 'ms' => 400];
    }

    protected function getTypeRules(): array
    {
        $rules = match ($this->inputType) {
            'email' => ['email'],
            'url' => ['url'],
            default => [],
        };

        if ($this->isNumeric) {
            $rules[] = $this->isInteger ? 'integer' : 'numeric';

            if (($minValue = $this->evaluate($this->minValue)) !== null) {
                $rules[] = 'min:'.$minValue;
            }

            if (($maxValue = $this->evaluate($this->maxValue)) !== null) {
                $rules[] = 'max:'.$maxValue;
            }

            return $rules;
        }

        $rules[] = 'string';

        if ($this->minLength !== null) {
            $rules[] = 'min:'.$this->minLength;
        }

        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return $rules;
    }

    public function dehydrateState(mixed $value): mixed
    {
        if ($this->isNumeric && is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value;
    }

    protected function getProps(): array
    {
        return [
            'inputType' => $this->inputType,
            'numeric' => $this->isNumeric,
            'integer' => $this->isInteger,
            'min' => $this->evaluate($this->minValue),
            'max' => $this->evaluate($this->maxValue),
            'step' => $this->step,
            'maxLength' => $this->maxLength,
            'prefix' => $this->evaluate($this->prefix),
            'suffix' => $this->evaluate($this->suffix),
            'autocomplete' => $this->autocomplete,
        ];
    }
}
