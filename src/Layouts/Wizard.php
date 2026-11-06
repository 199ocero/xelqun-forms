<?php

namespace Xelqun\Forms\Layouts;

use Closure;
use Xelqun\Forms\Contracts\NeedsServer;

/**
 * Steps shown one at a time. Next checks the current step's rules on the server
 * against the whole form's state. The server keeps no record of the steps, so
 * the final submit always checks everything.
 *
 * @api
 */
class Wizard extends Layout implements NeedsServer
{
    protected int|Closure $startStep = 1;

    protected bool|Closure $isSkippable = false;

    protected string|Closure|null $queryKey = null;

    /**
     * @param  array<int, Step>  $steps
     */
    final public function __construct(array $steps = [])
    {
        $this->schema($steps);
    }

    /**
     * @param  array<int, Step>  $steps
     */
    public static function make(array $steps = []): static
    {
        return (new static($steps))->configure();
    }

    /**
     * @param  array<int, Step>  $steps
     */
    public function steps(array $steps): static
    {
        return $this->schema($steps);
    }

    public function getType(): string
    {
        return 'wizard';
    }

    /**
     * Counts from 1.
     */
    public function startOnStep(int|Closure $step): static
    {
        $this->startStep = $step;

        return $this;
    }

    /**
     * Let the user move between steps freely, with no checks until submit.
     */
    public function skippable(bool|Closure $condition = true): static
    {
        $this->isSkippable = $condition;

        return $this;
    }

    /**
     * Keep the current step in the URL. Anyone can open any step this way, which
     * is safe because the final submit checks every step.
     */
    public function persistStepInQueryString(string|Closure|null $key = 'step'): static
    {
        $this->queryKey = $key;

        return $this;
    }

    public function needsServer(): bool
    {
        return ! $this->evaluate($this->isSkippable);
    }

    /**
     * A key, not a number: the browser only counts the steps that show.
     */
    private function getStartKey(?string $queryKey): ?string
    {
        $steps = $this->getChildComponents();
        $slug = $queryKey === null ? null : request()->query($queryKey);

        foreach ($steps as $step) {
            if ($step instanceof Step && $step->getSlug() === $slug) {
                return $step->getKey();
            }
        }

        return ($steps[$this->evaluate($this->startStep) - 1] ?? null)?->getKey();
    }

    protected function getProps(): array
    {
        $queryKey = $this->evaluate($this->queryKey);

        return [
            'startKey' => $this->getStartKey($queryKey),
            'skippable' => (bool) $this->evaluate($this->isSkippable),
            'queryKey' => $queryKey,
        ];
    }
}
