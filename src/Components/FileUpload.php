<?php

namespace Xelqun\Forms\Components;

use Xelqun\Forms\Components\Concerns\HandlesUploads;
use Xelqun\Forms\Contracts\AcceptsUploads;

/**
 * Files are uploaded to a temporary endpoint as soon as they are picked, so
 * the state only ever holds a short token ("xq-tmp:...") or a stored path.
 * Saving the form stores the file on its final disk, see UploadBatch.
 *
 * @api
 */
class FileUpload extends Field implements AcceptsUploads
{
    use HandlesUploads;

    public function getType(): string
    {
        return 'file-upload';
    }
}
