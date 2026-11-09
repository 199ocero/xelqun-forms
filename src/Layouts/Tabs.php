<?php

namespace Xelqun\Forms\Layouts;

use Closure;

/**
 * @api
 */
class Tabs extends Layout
{
    protected string|Closure|null $queryKey = null;

    protected int|Closure|null $activeTab = null;

    protected bool|Closure $persistsTab = false;

    protected bool|Closure $isVertical = false;

    protected bool|Closure $isContained = false;

    final public function __construct(protected string|Closure|null $label = null) {}

    public static function make(string|Closure|null $label = null): static
    {
        return (new static($label))->configure();
    }

    /**
     * @param  array<int, Tab>  $tabs
     */
    public function tabs(array $tabs): static
    {
        return $this->schema($tabs);
    }

    /**
     * Keep the open tab in the URL, as ?tab=pricing, so a link can open it. Give
     * each set of tabs on a page its own key.
     */
    public function persistTabInQueryString(string|Closure|null $key = 'tab'): static
    {
        $this->queryKey = $key;

        return $this;
    }

    /**
     * The tab that starts open, counting from 1. A tab in the URL still wins.
     */
    public function activeTab(int|Closure|null $tab): static
    {
        $this->activeTab = $tab;

        return $this;
    }

    /**
     * Remember the open tab in the browser, for the next visit.
     */
    public function persistTab(bool|Closure $condition = true): static
    {
        $this->persistsTab = $condition;

        return $this;
    }

    /**
     * The tabs in a column beside their content, on wide screens.
     */
    public function vertical(bool|Closure $condition = true): static
    {
        $this->isVertical = $condition;

        return $this;
    }

    /**
     * Put the tabs and their content in a card.
     */
    public function contained(bool|Closure $condition = true): static
    {
        $this->isContained = $condition;

        return $this;
    }

    public function getType(): string
    {
        return 'tabs';
    }

    protected function getNodeAttributes(): array
    {
        return [...parent::getNodeAttributes(), 'label' => $this->evaluate($this->label)];
    }

    private function getStartKey(?string $queryKey): ?string
    {
        $slug = $queryKey === null ? null : request()->query($queryKey);

        foreach ($this->getChildComponents() as $tab) {
            if ($tab instanceof Tab && $tab->getSlug() === $slug) {
                return $tab->getKey();
            }
        }

        $index = $this->evaluate($this->activeTab);
        $tabs = array_values(array_filter($this->getChildComponents(), fn ($tab): bool => $tab instanceof Tab));

        return $index === null ? null : ($tabs[$index - 1] ?? null)?->getKey();
    }

    protected function getProps(): array
    {
        $queryKey = $this->evaluate($this->queryKey);

        return [
            ...parent::getProps(),
            'startKey' => $this->getStartKey($queryKey),
            'queryKey' => $queryKey,
            ...$this->evaluateConfigured([
                'persistTab' => $this->persistsTab,
                'vertical' => $this->isVertical,
                'contained' => $this->isContained,
            ]),
        ];
    }
}
