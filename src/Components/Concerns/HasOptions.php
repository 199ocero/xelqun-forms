<?php

namespace Xelqun\Forms\Components\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * @api
 */
trait HasOptions
{
    /** @var array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|class-string|null */
    protected array|Arrayable|Closure|string|null $options = null;

    protected ?Closure $disableOptionWhen = null;

    protected bool $isBoolean = false;

    /**
     * @param  array<array-key, mixed>|Arrayable<array-key, mixed>|Closure|class-string  $options  An array of value => label, a closure, or an enum class. A nested array is a group: group label => [value => label].
     */
    public function options(array|Arrayable|Closure|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Options a user can see but not choose. Gets $value and $label. A value
     * the record already has stays valid, so the form can still be saved.
     */
    public function disableOptionWhen(Closure $callback): static
    {
        $this->disableOptionWhen = $callback;

        return $this;
    }

    /**
     * Yes and No options. The browser holds 1 and 0, and the field saves true and
     * false.
     */
    public function boolean(?string $trueLabel = null, ?string $falseLabel = null, ?string $placeholder = null): static
    {
        $this->isBoolean = true;

        if ($placeholder !== null) {
            $this->placeholder($placeholder);
        }

        return $this->options([
            1 => $trueLabel ?? __('xelqun-forms::messages.boolean.true'),
            0 => $falseLabel ?? __('xelqun-forms::messages.boolean.false'),
        ]);
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return $this->toBooleanOption(parent::formatStateFromRecord($value));
    }

    public function getDefaultState(): mixed
    {
        return $this->toBooleanOption(parent::getDefaultState());
    }

    private function toBooleanOption(mixed $value): mixed
    {
        return $this->isBoolean && $value !== null ? (int) $value : $value;
    }

    public function dehydrateState(mixed $value): mixed
    {
        return $this->isBoolean && $value !== null ? (bool) $value : $value;
    }

    /**
     * Every option, with any groups flattened away.
     *
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        $options = [];

        foreach ($this->getGroupedOptions() as $value => $label) {
            $options += is_array($label) ? $label : [$value => $label];
        }

        return $options;
    }

    /**
     * @return array<array-key, string|array<array-key, string>> Value => label, or group label => [value => label].
     */
    protected function getGroupedOptions(): array
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

        if (! is_array($options)) {
            return [];
        }

        return array_map(
            fn (mixed $label): string|array => is_array($label) ? array_map(strval(...), $label) : (string) $label,
            $options,
        );
    }

    public function isOptionDisabled(int|string $value, string $label): bool
    {
        return $this->disableOptionWhen !== null
            && $this->evaluate($this->disableOptionWhen, ['value' => $value, 'label' => $label]);
    }

    /**
     * @param  array<array-key, string>  $options
     * @return array<int, array{value: int|string, label: string, disabled?: true}>
     */
    protected function toOptionList(array $options): array
    {
        $list = [];

        foreach ($options as $value => $label) {
            $list[] = [
                'value' => $value,
                'label' => $label,
                ...($this->isOptionDisabled($value, $label) ? ['disabled' => true] : []),
            ];
        }

        return $list;
    }

    /**
     * The options for the browser, each with its group if it has one.
     *
     * @return array<int, array{value: int|string, label: string, disabled?: true, group?: string}>
     */
    protected function getOptionList(): array
    {
        $list = [];

        foreach ($this->getGroupedOptions() as $key => $label) {
            if (! is_array($label)) {
                array_push($list, ...$this->toOptionList([$key => $label]));

                continue;
            }

            foreach ($this->toOptionList($label) as $option) {
                $list[] = [...$option, 'group' => (string) $key];
            }
        }

        return $list;
    }

    /**
     * The value has to be one of the options.
     *
     * @return array<int, mixed>
     */
    protected function getOptionRules(bool $multiple = false): array
    {
        $allowed = Rule::in(array_map('strval', array_keys($this->getOptions())));

        return [
            ...($multiple ? ['array', $this->eachRule($allowed)] : [$allowed]),
            ...$this->getDisabledOptionRules(),
        ];
    }

    /**
     * The browser only greys a disabled option out, so the server has to refuse it.
     *
     * @return array<int, Closure>
     */
    protected function getDisabledOptionRules(): array
    {
        if ($this->disableOptionWhen === null) {
            return [];
        }

        return [function (string $attribute, mixed $value, Closure $fail): void {
            $alreadyChosen = array_map(strval(...), Arr::wrap($this->getServerState()));
            $options = $this->getOptions();

            foreach ((array) $value as $item) {
                if ((is_string($item) || is_int($item)) && ! in_array((string) $item, $alreadyChosen, true) && $this->isOptionDisabled($item, $options[$item] ?? '')) {
                    $fail($this->getFailureMessage('in'));
                }
            }
        }];
    }

    private function eachRule(mixed $rule): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($rule): void {
            $validator = validator(['items' => (array) $value], ['items.*' => [$rule]]);

            if ($validator->fails()) {
                $fail($this->getFailureMessage('in'));
            }
        };
    }
}
