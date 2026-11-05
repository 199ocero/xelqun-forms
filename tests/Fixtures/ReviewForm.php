<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Acme\Reviews\Rating;
use Acme\Reviews\Receipt;
use Acme\Reviews\Reviewer;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * @extends Form<Order>
 */
class ReviewForm extends Form
{
    public static bool $hideRating = false;

    public function model(): string
    {
        return Order::class;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer')->required(),
            Rating::make('stars')->relationship('review')->max(5)->hidden(fn (): bool => self::$hideRating),
            Reviewer::make('type')->options(['ada' => 'Ada Lovelace', 'alan' => 'Alan Turing']),
            Receipt::make('company'),
            Repeater::make('items')->relationship('items')->schema([
                TextInput::make('product'),
                TextInput::make('qty')->numeric()->default(1),
                TextInput::make('price')->numeric()->default(1),
                TextInput::make('subtotal')->numeric()->computed('qty * price'),
                Rating::make('stars')->relationship('review'),
                Receipt::make('attachment'),
            ]),
        ];
    }
}
