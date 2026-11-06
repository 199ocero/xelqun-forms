<?php

namespace Xelqun\Forms\Uploads;

use Illuminate\Contracts\Filesystem\Filesystem;
use JsonSerializable;

/**
 * A file from a field with storeFiles(false). It lives on the temporary disk
 * until the prune command clears it, so read it while handling the form.
 *
 * @api
 */
final class TemporaryUpload implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly ?string $mime,
        private readonly Filesystem $disk,
        private readonly string $path,
    ) {}

    public function get(): ?string
    {
        return $this->disk->get($this->path);
    }

    /**
     * @return resource|null
     */
    public function readStream()
    {
        return $this->disk->readStream($this->path);
    }

    /**
     * @return array{name: string, size: int, mime: ?string}
     */
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'size' => $this->size, 'mime' => $this->mime];
    }
}
