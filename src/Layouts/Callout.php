<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Components\Component;

/**
 * A highlighted message. A callout that appears after the page opens is only
 * announced with extraAttributes(['role' => 'status']), or "alert" if urgent.
 *
 * @api
 */
class Callout extends Layout
{
    protected string|Closure|null $description = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $status = null;

    final public function __construct(protected string|Closure|null $heading = null) {}

    public static function make(string|Closure|null $heading = null): static
    {
        return (new static($heading))->configure();
    }

    public function getType(): string
    {
        return 'callout';
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * An icon name from the browser's icon set. A status brings its own.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * danger, info, success or warning.
     */
    public function status(string|Closure|null $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function danger(): static
    {
        return $this->status('danger');
    }

    public function info(): static
    {
        return $this->status('info');
    }

    public function success(): static
    {
        return $this->status('success');
    }

    public function warning(): static
    {
        return $this->status('warning');
    }

    /**
     * Components shown below the text.
     *
     * @param  array<int, Component>  $components
     */
    public function footer(array $components): static
    {
        return $this->schema($components);
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->heading)];
    }

    protected function getProps(): array
    {
        $status = $this->evaluate($this->status);

        return [
            ...parent::getProps(),
            'description' => $this->evaluate($this->description),
            'status' => $status,
            'icon' => $this->evaluate($this->icon) ?? match ($status) {
                'danger' => 'circle-alert',
                'info' => 'info',
                'success' => 'circle-check',
                'warning' => 'triangle-alert',
                default => null,
            },
        ];
    }
}
