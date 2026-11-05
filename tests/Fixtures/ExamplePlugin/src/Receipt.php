<?php

namespace Acme\Reviews;

use Xelqun\Forms\Components\Concerns\HandlesUploads;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\AcceptsUploads;

/**
 * One receipt, stored through the same temporary uploads as FileUpload.
 */
class Receipt extends Field implements AcceptsUploads
{
    use HandlesUploads;

    public function getType(): string
    {
        return 'acme/receipt';
    }

    protected function setUp(): void
    {
        $this->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg']);
    }

    protected function getProps(): array
    {
        return [...$this->getUploadProps(), 'caption' => __('acme-reviews::reviews.receipt_caption')];
    }
}
