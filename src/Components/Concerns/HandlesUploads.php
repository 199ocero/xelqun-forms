<?php

namespace Xelqun\Forms\Components\Concerns;

use Closure;
use Illuminate\Support\Arr;
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

    protected bool|Closure $isMultiple = false;

    protected ?int $minFiles = null;

    protected ?int $maxFiles = null;

    protected ?int $minSize = null;

    protected bool|Closure $hasImageEditor = false;

    /** @var array<int, string|null> */
    protected array $imageEditorAspectRatios = [];

    protected ?string $imageEditorFillColor = null;

    protected ?string $imageCropRatio = null;

    protected ?string $imageResizeMode = null;

    protected ?int $imageResizeWidth = null;

    protected ?int $imageResizeHeight = null;

    protected bool $imageResizeUpscales = true;

    protected bool|Closure $shouldStoreFiles = true;

    protected bool|Closure $shouldDeleteRemovedFiles = false;

    protected bool|Closure $isFileReorderable = false;

    protected bool|Closure $isPasteable = true;

    protected bool|Closure $isDownloadable = false;

    protected bool|Closure $isOpenable = false;

    protected bool|Closure $isAvatar = false;

    protected ?int $maxParallelUploads = null;

    protected bool|Closure $isDeletable = true;

    protected bool|Closure $isPreviewable = true;

    protected bool|Closure $shouldAppendFiles = true;

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
     * Take several files. The state is a list of them, so a model needs an array
     * cast on the column, unless the files live on a relationship.
     */
    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function minFiles(?int $count): static
    {
        $this->minFiles = $count;

        return $this;
    }

    public function maxFiles(?int $count): static
    {
        $this->maxFiles = $count;

        return $this;
    }

    /**
     * Minimum file size in kilobytes. Stored files aren't checked again.
     */
    public function minSize(?int $kilobytes): static
    {
        $this->minSize = $kilobytes;

        return $this;
    }

    /**
     * Let users change the order of several files.
     */
    public function reorderable(bool|Closure $condition = true): static
    {
        $this->isFileReorderable = $condition;

        return $this;
    }

    /**
     * Take files pasted while the field has focus. On by default.
     */
    public function pasteable(bool|Closure $condition = true): static
    {
        $this->isPasteable = $condition;

        return $this;
    }

    /**
     * Add a button that downloads a stored file.
     */
    public function downloadable(bool|Closure $condition = true): static
    {
        $this->isDownloadable = $condition;

        return $this;
    }

    /**
     * Add a button that opens a stored file in a new tab.
     */
    public function openable(bool|Closure $condition = true): static
    {
        $this->isOpenable = $condition;

        return $this;
    }

    /**
     * One image, shown as a compact circle.
     */
    public function avatar(bool|Closure $condition = true): static
    {
        $this->isAvatar = $condition;

        return $this;
    }

    /**
     * How many files upload at once. The default is 1, because SQLite can't take
     * two at a time: the rate limiter fails with "database is locked".
     */
    public function maxParallelUploads(?int $count): static
    {
        $this->maxParallelUploads = $count;

        return $this;
    }

    /**
     * Let users remove stored files. One they remove anyway comes back on the
     * server, and so does one they replace. New uploads can always be removed.
     */
    public function deletable(bool|Closure $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    /**
     * Show images as thumbnails. Without this, every file gets the file icon.
     */
    public function previewable(bool|Closure $condition = true): static
    {
        $this->isPreviewable = $condition;

        return $this;
    }

    /**
     * Add new files at the end of the list, which is the default. With false the
     * newest file goes first.
     */
    public function appendFiles(bool|Closure $condition = true): static
    {
        $this->shouldAppendFiles = $condition;

        return $this;
    }

    /**
     * Let the user crop, rotate and flip an image, in the browser, before saving.
     */
    public function imageEditor(bool|Closure $condition = true): static
    {
        $this->hasImageEditor = $condition;

        return $this;
    }

    /**
     * The shapes the editor offers, such as ['16:9', '1:1']. Null means free.
     *
     * @param  array<int, string|null>  $ratios
     */
    public function imageEditorAspectRatioOptions(array $ratios): static
    {
        $this->imageEditorAspectRatios = $ratios;

        return $this;
    }

    /**
     * What fills the empty corners of a rotated image. Clear by default.
     */
    public function imageEditorEmptyFillColor(?string $color): static
    {
        $this->imageEditorFillColor = $color;

        return $this;
    }

    /**
     * Crop each image to this shape before it uploads, such as '16:9'. This and
     * the resize options below run in the browser, so a request that skips them
     * isn't resized. Use rules for anything that must hold.
     */
    public function automaticallyCropImagesToAspectRatio(?string $ratio): static
    {
        $this->imageCropRatio = $ratio;

        return $this;
    }

    /**
     * force stretches to the size, cover fills it and crops the rest, and
     * contain fits inside it.
     */
    public function automaticallyResizeImagesMode(?string $mode): static
    {
        $this->imageResizeMode = $mode;

        return $this;
    }

    public function automaticallyResizeImagesToWidth(?int $pixels): static
    {
        $this->imageResizeWidth = $pixels;

        return $this;
    }

    public function automaticallyResizeImagesToHeight(?int $pixels): static
    {
        $this->imageResizeHeight = $pixels;

        return $this;
    }

    public function automaticallyUpscaleImagesWhenResizing(bool $condition = true): static
    {
        $this->imageResizeUpscales = $condition;

        return $this;
    }

    /**
     * Leave files on the temporary disk. The form's data then holds a
     * TemporaryUpload instead of a path, for an import or an email attachment.
     * Handle it while the request runs: the prune command clears it later.
     */
    public function storeFiles(bool|Closure $condition = true): static
    {
        $this->shouldStoreFiles = $condition;

        return $this;
    }

    public function storesFiles(): bool
    {
        return (bool) $this->evaluate($this->shouldStoreFiles);
    }

    /**
     * Delete a stored file from its disk once a save no longer uses it, including
     * files of a deleted row. It runs after the save commits, and only for save(),
     * so writing the data yourself keeps every file. Leave this off if two records
     * can point at one file, because nothing here can see the other one.
     */
    public function deleteRemovedFiles(bool|Closure $condition = true): static
    {
        $this->shouldDeleteRemovedFiles = $condition;

        return $this;
    }

    public function deletesRemovedFiles(): bool
    {
        return (bool) $this->evaluate($this->shouldDeleteRemovedFiles);
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    private function takesImagesOnly(): bool
    {
        return $this->isImage || $this->evaluate($this->isAvatar);
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        $value = parent::formatStateFromRecord($value);

        return $this->isMultiple() ? array_values((array) ($value ?? [])) : $value;
    }

    public function getDefaultState(): mixed
    {
        $default = parent::getDefaultState();

        return $this->isMultiple() ? array_values((array) ($default ?? [])) : $default;
    }

    /**
     * A list can come back from the browser with gaps, or with a file twice, and
     * saving it twice would give two rows or paths for one file.
     */
    public function normalizeState(mixed $state): mixed
    {
        if (! $this->isMultiple()) {
            return $state;
        }

        return array_values(array_unique(array_filter((array) $state, fn (mixed $file): bool => $file !== null && $file !== ''), SORT_REGULAR));
    }

    public function enforceStateLimits(): void
    {
        if ($this->evaluate($this->isDeletable)) {
            return;
        }

        $state = Arr::wrap($this->getState());
        $stored = Arr::wrap($this->getForm()->getInitialValue($this->getStatePath()));
        $removed = array_filter($stored, fn (mixed $file): bool => ! in_array($file, $state, true));

        if ($removed !== []) {
            $this->getForm()->setStateValue($this->getStatePath(), $this->isMultiple() ? [...$state, ...$removed] : reset($stored));
        }
    }

    /**
     * Identifies this field across requests, for every row of a repeater.
     */
    public function getUploadScope(): string
    {
        return $this->makeUploadScope();
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

        if ($this->takesImagesOnly()) {
            $rules[] = 'image';
        }

        if ($this->acceptedFileTypes !== []) {
            $rules[] = 'mimetypes:'.implode(',', $this->acceptedFileTypes);
        }

        if ($this->minSize !== null) {
            $rules[] = 'min:'.$this->minSize;
        }

        $rules[] = 'max:'.($this->maxSize ?? (int) config('xelqun-forms.uploads.max_size', 10240));

        return $rules;
    }

    /**
     * Each file must be an upload made for this field, or a path the record
     * already had.
     *
     * @return array<int, mixed>
     */
    protected function getUploadStateRules(): array
    {
        $checkFiles = function (string $attribute, mixed $value, Closure $fail): void {
            $store = app(TemporaryUploadStore::class);

            foreach (Arr::wrap($value) as $file) {
                if (! is_string($file)) {
                    $fail($this->getFailureMessage('in'));

                    return;
                }

                if ($store->isToken($file)) {
                    if (! $store->isUsable($store->idFromToken($file), $this->getUploadScope())) {
                        $fail(__('xelqun-forms::messages.uploads.expired', ['attribute' => $this->getValidationAttribute()]));
                    }

                    continue;
                }

                if (! in_array($file, Arr::flatten($this->getForm()->getInitialValues($this->getStatePathPattern())), true)) {
                    $fail($this->getFailureMessage('in'));
                }
            }
        };

        if (! $this->isMultiple()) {
            return ['string', $checkFiles];
        }

        return [
            'array',
            ...($this->minFiles === null ? [] : ['min:'.$this->minFiles]),
            ...($this->maxFiles === null ? [] : ['max:'.$this->maxFiles]),
            $checkFiles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUploadProps(): array
    {
        $accept = $this->takesImagesOnly() ? ['image/*'] : $this->acceptedFileTypes;
        $files = [];
        $store = app(TemporaryUploadStore::class);

        foreach (Arr::wrap($this->getState()) as $file) {
            if (is_string($file) && $file !== '') {
                $files[$file] = $store->isToken($file)
                    ? $store->describe($store->idFromToken($file))
                    : ['name' => basename($file), 'url' => $this->getFileUrl($file), 'size' => null];
            }
        }

        return [
            'accept' => $accept === [] ? null : implode(',', $accept),
            'maxSize' => $this->maxSize ?? (int) config('xelqun-forms.uploads.max_size', 10240),
            'image' => $this->takesImagesOnly(),
            'files' => $files === [] ? new stdClass : $files,
            ...$this->evaluateConfigured([
                'multiple' => $this->isMultiple,
                'minFiles' => $this->minFiles,
                'maxFiles' => $this->maxFiles,
                'minSize' => $this->minSize,
                'reorderable' => $this->isFileReorderable,
                'downloadable' => $this->isDownloadable,
                'openable' => $this->isOpenable,
                'avatar' => $this->isAvatar,
                'maxParallelUploads' => $this->maxParallelUploads,
                'imageEditor' => $this->hasImageEditor === false ? null : fn (): ?array => $this->evaluate($this->hasImageEditor) ? [
                    'aspectRatios' => $this->imageEditorAspectRatios,
                    'fillColor' => $this->imageEditorFillColor,
                ] : null,
                'imageResize' => $this->imageCropRatio === null && $this->imageResizeWidth === null && $this->imageResizeHeight === null ? null : fn (): array => [
                    'ratio' => $this->imageCropRatio,
                    'mode' => $this->imageResizeMode,
                    'width' => $this->imageResizeWidth,
                    'height' => $this->imageResizeHeight,
                    'upscale' => $this->imageResizeUpscales,
                ],
            ]),
            ...$this->getFlagsThatChanged(),
        ];
    }

    /**
     * Options that are on unless told otherwise. A closure is always sent, so it
     * can turn the option back on in the browser.
     *
     * @return array<string, bool>
     */
    private function getFlagsThatChanged(): array
    {
        return array_map(
            fn (bool|Closure $flag): bool => (bool) $this->evaluate($flag),
            array_filter([
                'pasteable' => $this->isPasteable,
                'deletable' => $this->isDeletable,
                'previewable' => $this->isPreviewable,
                'appendFiles' => $this->shouldAppendFiles,
            ], fn (bool|Closure $flag): bool => $flag !== true),
        );
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
            $disk = Storage::disk($this->getDiskName());

            // shortcut: a private link dies after 5 minutes on an open page, sign on click if users complain
            return $this->visibility === 'private' ? $disk->temporaryUrl($path, now()->addMinutes(5)) : $disk->url($path);
        } catch (Throwable) {
            return null;
        }
    }
}
