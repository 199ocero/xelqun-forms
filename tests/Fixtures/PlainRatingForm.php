<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Acme\Reviews\Rating;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @extends Form<Order>
 */
class PlainRatingForm extends Form
{
    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [TextInput::make('customer'), Rating::make('total')];
    }
}
