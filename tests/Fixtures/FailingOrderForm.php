<?php

namespace Xelqun\Forms\Tests\Fixtures;

use RuntimeException;

class FailingOrderForm extends OrderForm
{
    public static bool $shouldFail = false;

    public function mutateBeforeSave(array $attributes): array
    {
        if (self::$shouldFail) {
            throw new RuntimeException('The database is down.');
        }

        return $attributes;
    }
}
