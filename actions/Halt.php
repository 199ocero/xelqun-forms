<?php

namespace Xelqun\Actions;

use RuntimeException;

/**
 * Thrown by Action::halt() and cancel() to stop an action quietly. What it
 * wrote so far is rolled back.
 *
 * @internal
 */
final class Halt extends RuntimeException
{
    public function __construct(public readonly bool $closesModal)
    {
        parent::__construct('The action was stopped.');
    }
}
