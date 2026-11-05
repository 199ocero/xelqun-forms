<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class AfterCommitFailureForm extends OrderForm
{
    public function mutateBeforeSave(array $attributes): array
    {
        DB::afterCommit(function (): void {
            throw new RuntimeException('The queue is down.');
        });

        return $attributes;
    }
}
