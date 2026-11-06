<?php

namespace Xelqun\Forms\Components;

use Closure;

/**
 * Rows of keys and values. The state is one flat map, so a model needs an array
 * cast on the column. A row with no key isn't kept.
 *
 * @api
 */
class KeyValue extends Field
{
    protected string|Closure|null $keyLabel = null;

    protected string|Closure|null $valueLabel = null;

    protected string|Closure|null $keyPlaceholder = null;

    protected string|Closure|null $valuePlaceholder = null;

    protected string|Closure|null $addActionLabel = null;

    protected bool|Closure $isAddable = true;

    protected bool|Closure $isDeletable = true;

    protected bool|Closure $hasEditableKeys = true;

    protected bool|Closure $hasEditableValues = true;

    protected bool|Closure $isReorderable = false;

    public function getType(): string
    {
        return 'key-value';
    }

    public function keyLabel(string|Closure|null $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function valueLabel(string|Closure|null $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function keyPlaceholder(string|Closure|null $placeholder): static
    {
        $this->keyPlaceholder = $placeholder;

        return $this;
    }

    public function valuePlaceholder(string|Closure|null $placeholder): static
    {
        $this->valuePlaceholder = $placeholder;

        return $this;
    }

    public function addActionLabel(string|Closure|null $label): static
    {
        $this->addActionLabel = $label;

        return $this;
    }

    public function addable(bool|Closure $condition = true): static
    {
        $this->isAddable = $condition;

        return $this;
    }

    public function deletable(bool|Closure $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    public function editableKeys(bool|Closure $condition = true): static
    {
        $this->hasEditableKeys = $condition;

        return $this;
    }

    public function editableValues(bool|Closure $condition = true): static
    {
        $this->hasEditableValues = $condition;

        return $this;
    }

    public function reorderable(bool|Closure $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return (array) ($value ?? []);
    }

    public function getDefaultState(): mixed
    {
        return (array) (parent::getDefaultState() ?? []);
    }

    public function normalizeState(mixed $state): mixed
    {
        return is_array($state) ? array_filter($state, fn (int|string $key): bool => trim((string) $key) !== '', ARRAY_FILTER_USE_KEY) : $state;
    }

    /**
     * The browser only hides the add and delete buttons, so a value that breaks
     * these limits has to be refused here.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function breaksLimits(array $value): bool
    {
        $stored = (array) $this->getServerState();
        $isAddable = $this->evaluate($this->isAddable) && $this->evaluate($this->hasEditableKeys);
        $isDeletable = $this->evaluate($this->isDeletable) && $this->evaluate($this->hasEditableKeys);

        if (! $isAddable && array_diff_key($value, $stored) !== []) {
            return true;
        }

        if (! $isDeletable && array_diff_key($stored, $value) !== []) {
            return true;
        }

        if ($this->evaluate($this->hasEditableValues)) {
            return false;
        }

        foreach (array_intersect_key($value, $stored) as $key => $item) {
            if ((string) $item !== (string) $stored[$key]) {
                return true;
            }
        }

        return false;
    }

    protected function getTypeRules(): array
    {
        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) || array_filter($value, fn (mixed $item): bool => $item !== null && ! is_scalar($item)) !== [] || $this->breaksLimits($value)) {
                    $fail($this->getFailureMessage('in'));
                }
            },
        ];
    }

    protected function getProps(): array
    {
        return [
            'keyLabel' => $this->evaluate($this->keyLabel) ?? __('xelqun-forms::messages.key_value.key'),
            'valueLabel' => $this->evaluate($this->valueLabel) ?? __('xelqun-forms::messages.key_value.value'),
            'keyPlaceholder' => $this->evaluate($this->keyPlaceholder),
            'valuePlaceholder' => $this->evaluate($this->valuePlaceholder),
            'addLabel' => $this->evaluate($this->addActionLabel) ?? __('xelqun-forms::messages.key_value.add'),
            'addable' => (bool) $this->evaluate($this->isAddable),
            'deletable' => (bool) $this->evaluate($this->isDeletable),
            'editableKeys' => (bool) $this->evaluate($this->hasEditableKeys),
            'editableValues' => (bool) $this->evaluate($this->hasEditableValues),
            'reorderable' => (bool) $this->evaluate($this->isReorderable),
        ];
    }
}
