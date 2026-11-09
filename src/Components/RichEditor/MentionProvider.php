<?php

namespace Xelqun\Forms\Components\RichEditor;

use Closure;

/**
 * What a rich editor offers after a character such as @. Give it items, which
 * the browser filters, or a search that runs on the server as the user types.
 *
 * @api
 */
class MentionProvider
{
    /** @var array<array-key, string>|Closure */
    protected array|Closure $items = [];

    protected ?Closure $searchUsing = null;

    final public function __construct(protected string $char) {}

    public static function make(string $char = '@'): static
    {
        return new static($char);
    }

    public function getChar(): string
    {
        return $this->char;
    }

    /**
     * id => label.
     *
     * @param  array<array-key, string>|Closure  $items
     */
    public function items(array|Closure $items): static
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Gets $search and returns id => label, at most a few. It runs on every
     * keystroke after the character, so keep it to one indexed query.
     */
    public function getSearchResultsUsing(?Closure $callback): static
    {
        $this->searchUsing = $callback;

        return $this;
    }

    public function searchesOnServer(): bool
    {
        return $this->searchUsing !== null;
    }

    /**
     * @return array<array-key, string>
     */
    public function getItems(): array
    {
        return $this->items instanceof Closure ? ($this->items)() : $this->items;
    }

    /**
     * @return array<array-key, string>
     */
    public function search(string $search): array
    {
        return $this->searchUsing === null ? [] : (array) app()->call($this->searchUsing, ['search' => $search]);
    }
}
