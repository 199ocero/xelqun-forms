<?php

namespace Xelqun\Forms\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidFormToken extends HttpException
{
    public static function make(string $reason, int $status = 403): self
    {
        return new self($status, "Invalid form token: {$reason}.");
    }
}
