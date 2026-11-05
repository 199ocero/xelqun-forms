<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Form;

class SharedTokenForm extends Form
{
    public function schema(): array
    {
        return [
            FileUpload::make('first')->acceptedFileTypes(['image/png']),
            CarelessUpload::make('second'),
        ];
    }
}
