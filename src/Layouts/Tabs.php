<?php

namespace Xelqun\Forms\Layouts;

use Closure;

/**
 * @api
 */
class Tabs extends Layout
{
    protected string|Closure|null $queryKey = null;

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

        return null;
    }

    protected function getProps(): array
    {
        $queryKey = $this->evaluate($this->queryKey);

        return [...parent::getProps(), 'startKey' => $this->getStartKey($queryKey), 'queryKey' => $queryKey];
    }
}
