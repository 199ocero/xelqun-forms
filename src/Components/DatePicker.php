<?php

namespace Xelqun\Forms\Components;

use Closure;
use DateTimeInterface;

class DatePicker extends Field
{
    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    public function getType(): string
    {
        return 'date-picker';
    }

    public function minDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->minDate = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $this;
    }

    public function maxDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->maxDate = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $this;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    protected function getTypeRules(): array
    {
        $rules = ['date_format:Y-m-d'];

        if (($minDate = $this->getMinDate()) !== null) {
            $rules[] = 'after_or_equal:'.$minDate;
        }

        if (($maxDate = $this->getMaxDate()) !== null) {
            $rules[] = 'before_or_equal:'.$maxDate;
        }

        return $rules;
    }

    public function getMinDate(): ?string
    {
        $date = $this->evaluate($this->minDate);

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    public function getMaxDate(): ?string
    {
        $date = $this->evaluate($this->maxDate);

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    protected function getProps(): array
    {
        return ['min' => $this->getMinDate(), 'max' => $this->getMaxDate()];
    }
}
