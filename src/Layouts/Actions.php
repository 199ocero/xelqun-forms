<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;

/**
 * A row of action buttons.
 *
 * @api
 */
class Actions extends Layout
{
    protected string|Closure|null $alignment = null;

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    final public function __construct(array $actions = [])
    {
        $this->schema($actions);
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    public static function make(array $actions = []): static
    {
        return (new static($actions))->configure();
    }

    public function getType(): string
    {
        return 'actions';
    }

    /**
     * start, center or end.
     */
    public function alignment(string|Closure|null $alignment): static
    {
        $this->alignment = $alignment;

        return $this;
    }

    protected function getProps(): array
    {
        return $this->evaluateConfigured(['alignment' => $this->alignment]);
    }
}
