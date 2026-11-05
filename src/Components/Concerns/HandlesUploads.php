<?php

namespace Xelqun\Forms\Components\Concerns;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Throwable;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

/**
 * Everything a field needs to take files: the AcceptsUploads contract, the rules
 * for the state, and the props for the browser.
 *
 * To add your own, spread getUploadStateRules() and getUploadProps() rather than
 * replacing them. Without the state rules, a posted value could name any path.
 *
 * @api
 */
trait HandlesUploads
{
    /**
     * Types a web server may run, or a browser may treat as a page from this
     * site. Refused unless the field lists its own acceptedFileTypes().
     */
    private const array ACTIVE_CONTENT_EXTENSIONS = [
        'asp', 'aspx', 'cgi', 'htaccess', 'htm', 'html', 'js', 'jsp', 'mjs', 'phar',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'pl', 'shtml',
        'svg', 'svgz', 'xht', 'xhtml', 'xml', 'xsl',
    ];

    protected ?string $disk = null;

    protected string $directory = '';

    protected string $visibility = 'public';

    protected ?int $maxSize = null;

    /** @var array<int, string> */
    protected array $acceptedFileTypes = [];

    protected bool $isImage = false;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Maximum file size in kilobytes.
     */
    public function maxSize(int $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     */
    public function acceptedFileTypes(array $mimeTypes): static
    {
        $this->acceptedFileTypes = $mimeTypes;

        return $this;
    }

    public function image(): static
    {
        $this->isImage = true;

        return $this;
    }

    /**
     * Identifies this field across requests, for every row of a repeater.
     */
    public function getUploadScope(): string
    {
        return $this->getForm()::class.'|'.$this->getStatePathPattern();
    }

    public function getDiskName(): string
    {
        return $this->disk ?? (string) config('xelqun-forms.uploads.disk', 'public');
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    /**
     * Rules for the uploaded file itself, checked by the upload endpoint.
     *
     * @return array<int, mixed>
     */
    public function getUploadRules(): array
    {
        $rules = ['required', 'file'];

        if ($this->acceptedFileTypes === []) {
            $rules[] = function (string $attribute, mixed $file, Closure $fail): void {
                $extensions = [Str::lower($file->getClientOriginalExtension()), $file->guessExtension()];

                if (array_intersect($extensions, self::ACTIVE_CONTENT_EXTENSIONS) !== []) {
                    $fail(__('xelqun-forms::messages.uploads.type_not_allowed'));
                }
            };
        }

        if ($this->isImage) {
            $rules[] = 'image';
        }

        if ($this->acceptedFileTypes !== []) {
            $rules[] = 'mimetypes:'.implode(',', $this->acceptedFileTypes);
        }

        $rules[] = 'max:'.($this->maxSize ?? (int) config('xelqun-forms.uploads.max_size', 10240));

        return $rules;
    }

    /**
     * The state must be an upload made for this field, or a path the record
     * already had.
     *
     * @return array<int, mixed>
     */
    protected function getUploadStateRules(): array
    {
        return [
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                $store = app(TemporaryUploadStore::class);

                if ($store->isToken($value)) {
                    if (! $store->isUsable($store->idFromToken($value), $this->getUploadScope())) {
                        $fail(__('xelqun-forms::messages.uploads.expired', ['attribute' => $this->getLabel()]));
                    }

                    return;
                }

                if (! in_array($value, $this->getForm()->getInitialValues($this->getStatePathPattern()), true)) {
                    $fail(__('validation.in', ['attribute' => $this->getLabel()]));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUploadProps(): array
    {
        $accept = $this->isImage ? ['image/*'] : $this->acceptedFileTypes;
        $files = [];
        $state = $this->getState();
        $store = app(TemporaryUploadStore::class);

        if (is_string($state) && $state !== '') {
            $files[$state] = $store->isToken($state)
                ? $store->describe($store->idFromToken($state))
                : ['name' => basename($state), 'url' => $this->getFileUrl($state), 'size' => null];
        }

        return [
            'accept' => $accept === [] ? null : implode(',', $accept),
            'maxSize' => $this->maxSize ?? (int) config('xelqun-forms.uploads.max_size', 10240),
            'image' => $this->isImage,
            'files' => $files === [] ? new stdClass : $files,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTypeRules(): array
    {
        return $this->getUploadStateRules();
    }

    protected function getProps(): array
    {
        return $this->getUploadProps();
    }

    private function getFileUrl(string $path): ?string
    {
        try {
            return Storage::disk($this->getDiskName())->url($path);
        } catch (Throwable) {
            return null;
        }
    }
}
