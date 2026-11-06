<?php

namespace Xelqun\Forms\Components\Concerns;

use Closure;
use Illuminate\Support\Facades\Storage;

/**
 * Images uploaded from inside a text editor. They are off by default, and the
 * upload endpoint refuses them until fileAttachments() turns them on. The saved
 * text holds a public URL, so a disk with no public URLs won't work here.
 *
 * @api
 */
trait HasFileAttachments
{
    protected bool $hasFileAttachments = false;

    protected ?string $fileAttachmentsDisk = null;

    protected string $fileAttachmentsDirectory = '';

    protected ?int $fileAttachmentsMaxSize = null;

    /** @var array<int, string> */
    protected array $fileAttachmentsAcceptedFileTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function fileAttachments(bool $condition = true): static
    {
        $this->hasFileAttachments = $condition;

        return $this;
    }

    public function fileAttachmentsDisk(string $disk): static
    {
        $this->fileAttachmentsDisk = $disk;

        return $this->fileAttachments();
    }

    public function fileAttachmentsDirectory(string $directory): static
    {
        $this->fileAttachmentsDirectory = $directory;

        return $this->fileAttachments();
    }

    /**
     * In kilobytes.
     */
    public function fileAttachmentsMaxSize(int $kilobytes): static
    {
        $this->fileAttachmentsMaxSize = $kilobytes;

        return $this->fileAttachments();
    }

    /**
     * MIME types. SVG can carry scripts, so it isn't one of the defaults.
     *
     * @param  array<int, string>  $mimeTypes
     */
    public function fileAttachmentsAcceptedFileTypes(array $mimeTypes): static
    {
        $this->fileAttachmentsAcceptedFileTypes = $mimeTypes;

        return $this->fileAttachments();
    }

    public function needsServer(): bool
    {
        return $this->hasFileAttachments || parent::needsServer();
    }

    public function getUploadScope(): string
    {
        return $this->makeUploadScope();
    }

    /**
     * @return array<int, mixed>
     */
    public function getUploadRules(): array
    {
        if (! $this->hasFileAttachments) {
            return ['prohibited'];
        }

        return [
            'required',
            'file',
            'mimetypes:'.implode(',', $this->fileAttachmentsAcceptedFileTypes),
            'max:'.($this->fileAttachmentsMaxSize ?? (int) config('xelqun-forms.uploads.max_size', 10240)),
        ];
    }

    public function getDiskName(): string
    {
        return $this->fileAttachmentsDisk ?? (string) config('xelqun-forms.uploads.disk', 'public');
    }

    public function getDirectory(): string
    {
        return $this->fileAttachmentsDirectory;
    }

    public function getVisibility(): string
    {
        return 'public';
    }

    /**
     * @param  Closure(string): string  $store
     */
    protected function storeAttachment(string $token, Closure $store): string
    {
        return Storage::disk($this->getDiskName())->url($store($token));
    }
}
