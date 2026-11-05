<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class OtherConnectionForm extends Form
{
    /** @var class-string<Order> */
    public static string $modelClass = OtherConnectionOrder::class;

    public function model(): string
    {
        return self::$modelClass;
    }

    public function schema(): array
    {
        return [
            TextInput::make('customer'),
            FileUpload::make('company')->disk('public'),
        ];
    }
}
