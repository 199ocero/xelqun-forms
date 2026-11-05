<?php

namespace Xelqun\Forms\Contracts;

use Xelqun\Forms\Components\Field;

/**
 * A field the search endpoint may call. The endpoint rebuilds the form, restores
 * trusted state and checks acceptsInput() before getSearchResults() runs.
 *
 * Results only help the user find a value. The search can't stop the browser
 * posting anything else, so the field must also validate what is submitted.
 *
 * @api
 *
 * @phpstan-require-extends Field
 */
interface HasSearchResults
{
    /**
     * Whether the search endpoint answers for this field right now.
     */
    public function isSearchable(): bool;

    /**
     * Whether options come from the server as the user types. A form that can't
     * be rebuilt there refuses such a field.
     */
    public function isAsync(): bool;

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function getSearchResults(string $search): array;
}
