<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

/**
 * Each field is hidden while the one before it is blank, declared last to
 * first so every pass of prepare() can only discover one more lock.
 */
class LockChainForm extends Form
{
    public const int DEPTH = 7;

    public function schema(): array
    {
        $fields = [TextInput::make('total')->computed('step'.self::DEPTH)->disabled()];

        for ($step = self::DEPTH; $step >= 2; $step--) {
            $previous = 'step'.($step - 1);
            $fields[] = TextInput::make('step'.$step)->hidden(fn (Get $get): bool => blank($get($previous)));
        }

        return [...$fields, TextInput::make('step1')->hidden()];
    }
}
