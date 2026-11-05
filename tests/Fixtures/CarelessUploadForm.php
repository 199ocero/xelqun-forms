<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Form;

class CarelessUploadForm extends Form
{
    public function schema(): array
    {
        return [CarelessUpload::make('file')];
    }
}
