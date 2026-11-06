<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * A number on a track. An array default gives one handle per value, and the state
 * is then a list. A slider always has a value, so it never ends up empty.
 *
 * @api
 */
class Slider extends Field
{
    protected int|float $minValue = 0;

    protected int|float $maxValue = 100;

    protected int|float $step = 1;

    protected bool $hasTooltips = false;

    public function getType(): string
    {
        return 'slider';
    }

    public function range(int|float $minValue, int|float $maxValue): static
    {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;

        return $this;
    }

    /**
     * The value moves in these increments, counted from the minimum.
     */
    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    /**
     * Allow this many decimal places. Replaces step().
     */
    public function decimalPlaces(int $places): static
    {
        return $this->step(10 ** -$places);
    }

    /**
     * Show the value above the track.
     */
    public function tooltips(bool $condition = true): static
    {
        $this->hasTooltips = $condition;

        return $this;
    }

    public function getDefaultState(): mixed
    {
        return parent::getDefaultState() ?? $this->minValue;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return parent::formatStateFromRecord($value) ?? $this->getDefaultState();
    }

    public function normalizeState(mixed $state): mixed
    {
        return $state ?? $this->getDefaultState();
    }

    private function isOnTrack(mixed $value): bool
    {
        if (! is_numeric($value) || $value < $this->minValue || $value > $this->maxValue) {
            return false;
        }

        $steps = ($value - $this->minValue) / $this->step;

        return abs($steps - round($steps)) < 1e-9;
    }

    protected function getTypeRules(): array
    {
        return [function (string $attribute, mixed $value, Closure $fail): void {
            $default = $this->getDefaultState();
            $handles = is_array($default) ? count($default) : null;

            if ($handles !== null ? (! is_array($value) || count($value) !== $handles) : is_array($value)) {
                $fail($this->getFailureMessage('in'));

                return;
            }

            foreach ((array) $value as $item) {
                if (! $this->isOnTrack($item)) {
                    $fail($this->getFailureMessage('in'));

                    return;
                }
            }
        }];
    }

    public function dehydrateState(mixed $value): mixed
    {
        return is_array($value)
            ? array_map(fn (mixed $item): int|float => $item + 0, array_values($value))
            : (is_numeric($value) ? $value + 0 : $value);
    }

    protected function getProps(): array
    {
        return [
            'min' => $this->minValue,
            'max' => $this->maxValue,
            'step' => $this->step,
            'tooltips' => $this->hasTooltips,
        ];
    }
}
