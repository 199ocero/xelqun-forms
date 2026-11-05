<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Contracts\PersistsItself;
use Xelqun\Forms\Form;

/**
 * @extends Form<Order>
 */
class ThrowingPersistForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        $field = new class('note') extends Field implements PersistsItself
        {
            public function getType(): string
            {
                return 'acme/throwing';
            }

            public function shouldPersistItself(): bool
            {
                return true;
            }

            public function persist(Model $record, mixed $state): void
            {
                throw new RuntimeException('The plugin failed.');
            }
        };

        return [TextInput::make('customer'), $field];
    }
}
