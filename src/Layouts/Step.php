<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Illuminate\Support\Str;

/**
 * @api
 */
class Step extends Layout
{
    protected string|Closure|null $description = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $completedIcon = null;

    protected ?Closure $beforeValidation = null;

    protected ?Closure $afterValidation = null;

    final public function __construct(protected string|Closure $label) {}

    public static function make(string|Closure $label): static
    {
        return (new static($label))->configure();
    }

    public function getType(): string
    {
        return 'step';
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function completedIcon(string|Closure|null $icon): static
    {
        $this->completedIcon = $icon;

        return $this;
    }

    /**
     * Runs on Next, before this step's rules. Throw a ValidationException to
     * keep the user on the step. It can read and set state, like any closure.
     */
    public function beforeValidation(?Closure $callback): static
    {
        $this->beforeValidation = $callback;

        return $this;
    }

    /**
     * Runs on Next, once this step's rules pass. Never rely on it to have run:
     * a skippable wizard doesn't call it, and the server keeps no record of steps.
     */
    public function afterValidation(?Closure $callback): static
    {
        $this->afterValidation = $callback;

        return $this;
    }

    /**
     * @internal
     */
    public function callBeforeValidation(): void
    {
        $this->evaluate($this->beforeValidation);
    }

    /**
     * @internal
     */
    public function callAfterValidation(): void
    {
        $this->evaluate($this->afterValidation);
    }

    /**
     * What this step is called in the URL.
     */
    public function getSlug(): string
    {
        return Str::slug((string) $this->evaluate($this->label));
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }

    protected function getProps(): array
    {
        return [
            ...parent::getProps(),
            'slug' => $this->getSlug(),
            'description' => $this->evaluate($this->description),
            'icon' => $this->evaluate($this->icon),
            'completedIcon' => $this->evaluate($this->completedIcon),
        ];
    }
}
