<?php

namespace Xelqun\Forms\Expressions;

use InvalidArgumentException;

class ExpressionException extends InvalidArgumentException
{
    public static function at(string $source, int $position, string $message): self
    {
        return new self("{$message} at position {$position} in expression [{$source}].");
    }
}
