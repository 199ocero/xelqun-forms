<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

/**
 * Starter rows that differ per request, like a default holding now().
 */
class ChangingDefaultsForm extends Form
{
    public static string $startsAt = '';

    public function schema(): array
    {
        return [
            Repeater::make('slots')->default(fn (): array => [['starts_at' => self::$startsAt]])->schema([
                TextInput::make('starts_at')->disabled()->dehydrated(),
            ]),
        ];
    }
}
