<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Form;

class UploadRulesForm extends Form
{
    public function schema(): array
    {
        return [
            FileUpload::make('anything')->disk('public'),
            FileUpload::make('pdf')->disk('public')->acceptedFileTypes(['application/pdf']),
            FileUpload::make('avatar')->disk('public')->image(),
            FileUpload::make('vector')->disk('public')->acceptedFileTypes(['image/svg+xml']),
        ];
    }
}
