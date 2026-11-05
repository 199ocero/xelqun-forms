<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\AcceptsUploads;

/**
 * A plugin that wrote the contract by hand and validates nothing.
 */
class CarelessUpload extends Field implements AcceptsUploads
{
    public function getType(): string
    {
        return 'acme/careless-upload';
    }

    public function getUploadScope(): string
    {
        return 'careless|'.$this->getStatePathPattern();
    }

    public function getUploadRules(): array
    {
        return ['required', 'file'];
    }

    public function getDiskName(): string
    {
        return 'public';
    }

    public function getDirectory(): string
    {
        return '';
    }

    public function getVisibility(): string
    {
        return 'public';
    }
}
