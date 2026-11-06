<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

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

    protected bool|Closure $isRevealable = false;

    protected bool|Closure $shouldTrim = false;

    protected string|Closure|null $mask = null;

    /** @var string|array<int, string>|Closure */
    protected string|array|Closure $stripCharacters = [];

    /** @var array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|null */
    protected array|Arrayable|Closure|null $datalist = null;

    protected bool|Closure $isCopyable = false;

    protected string|Closure|null $copyMessage = null;

    protected int|Closure $copyMessageDuration = 2000;

    protected ?int $length = null;

    protected string|Closure|null $prefixIcon = null;

    protected string|Closure|null $suffixIcon = null;

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

    /**
     * Add a button that shows a password as plain text.
     */
    public function revealable(bool|Closure $condition = true): static
    {
        $this->isRevealable = $condition;

        return $this;
    }

    /**
     * Remove spaces around the value before it is validated and saved.
     */
    public function trim(bool|Closure $condition = true): static
    {
        $this->shouldTrim = $condition;

        return $this;
    }

    /**
     * Format what the user types: 9 is a digit, a is a letter, * is either, and
     * anything else is typed for them, as in "99/99/9999". This is a typing aid
     * only, so check the format with a rule too.
     */
    public function mask(string|Closure|null $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    /**
     * Remove characters before the value is validated and saved, usually the
     * separators a mask adds.
     *
     * @param  string|array<int, string>|Closure  $characters
     */
    public function stripCharacters(string|array|Closure $characters): static
    {
        $this->stripCharacters = $characters;

        return $this;
    }

    /**
     * Suggestions from the browser. Users can still type anything.
     *
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|null  $options
     */
    public function datalist(array|Arrayable|Closure|null $options): static
    {
        $this->datalist = $options;

        return $this;
    }

    /**
     * Add a button that copies the value. Browsers only allow this over HTTPS.
     *
     * @param  int|Closure  $copyMessageDuration  Milliseconds to show the message.
     */
    public function copyable(bool|Closure $condition = true, string|Closure|null $copyMessage = null, int|Closure $copyMessageDuration = 2000): static
    {
        $this->isCopyable = $condition;
        $this->copyMessage = $copyMessage;
        $this->copyMessageDuration = $copyMessageDuration;

        return $this;
    }

    /**
     * Require exactly this many characters.
     */
    public function length(int $length): static
    {
        $this->length = $length;

        return $this;
    }

    /**
     * An icon inside the start of the field. See resources/js/ui/icons.ts for the
     * names, and the icons option of createXelqunForms() to add more.
     */
    public function prefixIcon(string|Closure|null $icon): static
    {
        $this->prefixIcon = $icon;

        return $this;
    }

    /**
     * An icon inside the end of the field.
     */
    public function suffixIcon(string|Closure|null $icon): static
    {
        $this->suffixIcon = $icon;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    private function getDatalist(): array
    {
        $options = $this->evaluate($this->datalist);

        return array_map(strval(...), array_values($options instanceof Arrayable ? $options->toArray() : (array) $options));
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

        if ($this->length !== null) {
            $rules[] = 'size:'.$this->length;
        }

        if ($this->minLength !== null) {
            $rules[] = 'min:'.$this->minLength;
        }

        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return $rules;
    }

    protected function getClientRules(): array
    {
        // The server checks the value after changing it, which the browser doesn't.
        if ($this->stripCharacters !== [] || $this->shouldTrim !== false || $this->mask !== null) {
            return [];
        }

        $rules = $this->inputType === 'email' ? [['rule' => 'email']] : [];

        if ($this->isNumeric) {
            $rules[] = ['rule' => $this->isInteger ? 'integer' : 'numeric'];
            $minValue = $this->evaluate($this->minValue);
            $maxValue = $this->evaluate($this->maxValue);

            return [
                ...$rules,
                ...($minValue === null ? [] : [['rule' => 'min', 'type' => 'numeric', 'value' => $minValue]]),
                ...($maxValue === null ? [] : [['rule' => 'max', 'type' => 'numeric', 'value' => $maxValue]]),
            ];
        }

        return [
            ...$rules,
            ...($this->length === null ? [] : [['rule' => 'size', 'type' => 'string', 'value' => $this->length]]),
            ...($this->minLength === null ? [] : [['rule' => 'min', 'type' => 'string', 'value' => $this->minLength]]),
            ...($this->maxLength === null ? [] : [['rule' => 'max', 'type' => 'string', 'value' => $this->maxLength]]),
        ];
    }

    public function normalizeState(mixed $state): mixed
    {
        if (! is_string($state)) {
            return $state;
        }

        $state = str_replace((array) $this->evaluate($this->stripCharacters), '', $state);

        return $this->evaluate($this->shouldTrim) ? trim($state) : $state;
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
            'maxLength' => $this->length ?? $this->maxLength,
            'prefix' => $this->evaluate($this->prefix),
            'suffix' => $this->evaluate($this->suffix),
            'autocomplete' => $this->autocomplete,
            ...$this->evaluateConfigured([
                'revealable' => $this->isRevealable,
                'mask' => $this->mask,
                'prefixIcon' => $this->prefixIcon,
                'suffixIcon' => $this->suffixIcon,
                'datalist' => $this->datalist === null ? null : $this->getDatalist(...),
                'copyable' => $this->isCopyable === false ? null : fn (): ?array => $this->evaluate($this->isCopyable) ? [
                    'message' => $this->evaluate($this->copyMessage) ?? __('xelqun-forms::messages.copied'),
                    'duration' => $this->evaluate($this->copyMessageDuration),
                ] : null,
            ]),
        ];
    }
}
