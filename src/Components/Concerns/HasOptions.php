<?php

namespace Xelqun\Forms\Components\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use UnitEnum;

trait HasOptions
{
    /** @var array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|class-string|null */
    protected array|Arrayable|Closure|string|null $options = null;

    /**
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|class-string  $options  An array of value => label, a closure, or an enum class.
     */
    public function options(array|Arrayable|Closure|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        $options = $this->evaluate($this->options);

        if (is_string($options) && enum_exists($options)) {
            return collect($options::cases())
                ->mapWithKeys(fn (UnitEnum $case): array => [
                    ($case instanceof BackedEnum ? $case->value : $case->name) => method_exists($case, 'getLabel') ? $case->getLabel() : $case->name,
                ])
                ->all();
        }

        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        return is_array($options) ? array_map(fn (mixed $label): string => (string) $label, $options) : [];
    }

    /**
     * @param  array<array-key, string>  $options
     * @return array<int, array{value: int|string, label: string}>
     */
    protected function toOptionList(array $options): array
    {
        $list = [];

        foreach ($options as $value => $label) {
            $list[] = ['value' => $value, 'label' => $label];
        }

        return $list;
    }
}
