<?php

namespace Xelqun\Forms\Support;

/**
 * Vue doesn't sanitize a bound href, so a URL built from a record could run
 * script. Anything not known to be safe is dropped on the server.
 */
final class SafeUrl
{
    private const string ALLOWED = '#\A(?:/(?![/\\\\])|https?://|mailto:|tel:)#i';

    public static function filter(?string $url): ?string
    {
        if ($url === null || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        return preg_match(self::ALLOWED, $url) === 1 ? $url : null;
    }
}
