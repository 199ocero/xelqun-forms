<?php

namespace Xelqun\Forms\Components;

use Carbon\CarbonInterface;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A date and a time. The browser always holds one fixed format in the shown
 * timezone, and this class converts to and from the stored format on the server.
 *
 * @api
 */
class DateTimePicker extends Field
{
    protected bool $hasDate = true;

    protected bool $hasTime = true;

    protected bool $hasSeconds = true;

    protected string|Closure|null $format = null;

    protected string|Closure|null $displayFormat = null;

    protected string|Closure|null $timezone = null;

    protected string|DateTimeInterface|Closure|null $minDate = null;

    protected string|DateTimeInterface|Closure|null $maxDate = null;

    /** @var array<int, string|DateTimeInterface>|Closure */
    protected array|Closure $disabledDates = [];

    protected int $hoursStep = 1;

    protected int $minutesStep = 1;

    protected int $secondsStep = 1;

    public function getType(): string
    {
        return 'date-time-picker';
    }

    /**
     * How the value is stored, in PHP date tokens.
     */
    public function format(string|Closure|null $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * How the value is shown, in PHP date tokens. The form shows the browser's
     * own format without it.
     */
    public function displayFormat(string|Closure|null $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    /**
     * Show the value in this timezone. It is always stored in the app's. A field
     * with only a date or only a time has no timezone, so this changes nothing there.
     */
    public function timezone(string|Closure|null $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function seconds(bool $condition = true): static
    {
        $this->hasSeconds = $condition;

        return $this;
    }

    public function hoursStep(int $step): static
    {
        $this->hoursStep = $step;

        return $this;
    }

    public function minutesStep(int $step): static
    {
        $this->minutesStep = $step;

        return $this;
    }

    public function secondsStep(int $step): static
    {
        $this->secondsStep = $step;

        return $this;
    }

    public function minDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function maxDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /**
     * Days that can't be chosen. The server refuses them too.
     *
     * @param  array<int, string|DateTimeInterface>|Closure  $dates
     */
    public function disabledDates(array|Closure $dates): static
    {
        $this->disabledDates = $dates;

        return $this;
    }

    /**
     * The format the browser holds. It never changes with format().
     */
    public function getStateFormat(): string
    {
        return trim(($this->hasDate ? 'Y-m-d' : '').' '.($this->hasTime ? ($this->hasSeconds ? 'H:i:s' : 'H:i') : ''));
    }

    public function getStorageFormat(): string
    {
        return $this->evaluate($this->format) ?? trim(($this->hasDate ? 'Y-m-d' : '').' '.($this->hasTime ? 'H:i:s' : ''));
    }

    private function getAppTimezone(): string
    {
        return (string) config('app.timezone');
    }

    private function getShownTimezone(): string
    {
        return $this->hasDate && $this->hasTime ? ($this->evaluate($this->timezone) ?? $this->getAppTimezone()) : $this->getAppTimezone();
    }

    /**
     * @return array<int, string>
     */
    private function getDisabledDates(): array
    {
        return array_values(array_filter(array_map(
            fn (string|DateTimeInterface $date): ?string => $this->parseStored($date)?->format('Y-m-d'),
            $this->evaluate($this->disabledDates),
        )));
    }

    /**
     * Read a stored value, in the stored format if it is in one.
     */
    private function parseStored(mixed $value): ?CarbonInterface
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            $format = $this->getStorageFormat();

            return Carbon::hasFormat((string) $value, $format)
                ? Carbon::createFromFormat('!'.$format, (string) $value, $this->getAppTimezone())
                : Carbon::parse((string) $value, $this->getAppTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        $date = $this->parseStored($value);

        return $date === null ? $value : $date->setTimezone($this->getShownTimezone())->format($this->getStateFormat());
    }

    public function getDefaultState(): mixed
    {
        return $this->formatStateFromRecord(parent::getDefaultState());
    }

    public function dehydrateState(mixed $value): mixed
    {
        if (! is_string($value) || ! Carbon::hasFormat($value, $this->getStateFormat())) {
            return $value;
        }

        return Carbon::createFromFormat('!'.$this->getStateFormat(), $value, $this->getShownTimezone())
            ?->setTimezone($this->getAppTimezone())
            ->format($this->getStorageFormat());
    }

    public function getMinDate(): ?string
    {
        return $this->formatStateFromRecord($this->evaluate($this->minDate));
    }

    public function getMaxDate(): ?string
    {
        return $this->formatStateFromRecord($this->evaluate($this->maxDate));
    }

    protected function getTypeRules(): array
    {
        $rules = ['date_format:'.$this->getStateFormat()];

        if (($minDate = $this->getMinDate()) !== null) {
            $rules[] = 'after_or_equal:'.$minDate;
        }

        if (($maxDate = $this->getMaxDate()) !== null) {
            $rules[] = 'before_or_equal:'.$maxDate;
        }

        if ($this->disabledDates !== []) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && in_array(substr($value, 0, 10), $this->getDisabledDates(), true)) {
                    $fail($this->getFailureMessage('in'));
                }
            };
        }

        return $rules;
    }

    protected function getProps(): array
    {
        return [
            'min' => $this->getMinDate(),
            'max' => $this->getMaxDate(),
            ...$this->evaluateConfigured([
                'displayFormat' => $this->displayFormat,
                'disabledDates' => $this->disabledDates === [] ? null : fn (): array => $this->getDisabledDates(),
                'time' => $this->hasTime,
                'timeOnly' => ! $this->hasDate,
                'seconds' => $this->hasTime && $this->hasSeconds,
                'steps' => $this->hasTime ? ['hour' => $this->hoursStep, 'minute' => $this->minutesStep, 'second' => $this->secondsStep] : null,
            ]),
        ];
    }
}
