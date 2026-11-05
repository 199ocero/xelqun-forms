<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

class ClosureReadsForm extends Form
{
    /** @var array<int, mixed> */
    public static array $reads = [];

    public function schema(): array
    {
        return [
            TextInput::make('note')->disabled(function (Get $get): bool {
                self::$reads[] = $get('team_id');

                return false;
            }),
            TextInput::make('team_id')->default('7')->disabled(),
        ];
    }
}
