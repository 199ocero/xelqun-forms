<?php

namespace Xelqun\Forms\Contracts;

use Closure;

/**
 * A field that keeps uploads inside its text, instead of holding them as its
 * value. On save, each is stored and the field puts its URL in the text.
 *
 * @api
 */
interface EmbedsUploads extends AcceptsUploads
{
    /**
     * @param  Closure(string): string  $store  Stores the upload with this token, and gives its path.
     */
    public function replaceUploads(string $state, Closure $store): string;
}
