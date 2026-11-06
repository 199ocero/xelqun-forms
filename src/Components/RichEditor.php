<?php

namespace Xelqun\Forms\Components;

use Closure;
use Xelqun\Forms\Components\Concerns\HasFileAttachments;
use Xelqun\Forms\Contracts\EmbedsUploads;
use Xelqun\Forms\Support\RichTextSanitizer;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

/**
 * Rich text, kept as HTML. The server cleans it when it arrives and when it
 * loads from the record, so what is saved is safe to print with {!! !!}, as long
 * as it was saved through this field.
 *
 * @api
 */
class RichEditor extends Field implements EmbedsUploads
{
    use HasFileAttachments;

    /** @var array<int, array<int, string>> */
    protected array $toolbarButtons = [
        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
        ['h2', 'h3'],
        ['alignStart', 'alignCenter', 'alignEnd'],
        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['table', 'attachFiles'],
        ['undo', 'redo'],
    ];

    /** @var array<int, string> */
    protected array $disabledToolbarButtons = [];

    protected ?string $minHeight = null;

    protected ?Closure $sanitizeUsing = null;

    public function getType(): string
    {
        return 'rich-editor';
    }

    /**
     * The buttons, in groups. Besides the defaults there are h1, h4, h5, h6,
     * alignJustify and horizontalRule.
     *
     * @param  array<int, array<int, string>>  $buttons
     */
    public function toolbarButtons(array $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

    /**
     * @param  array<int, string>  $buttons
     */
    public function disableToolbarButtons(array $buttons): static
    {
        $this->disabledToolbarButtons = $buttons;

        return $this;
    }

    /**
     * Any CSS size.
     */
    public function minHeight(?string $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    /**
     * Clean the HTML your own way. It gets $html and returns the result, and
     * replaces the default, so it has to keep the page just as safe.
     */
    public function sanitizeUsing(?Closure $callback): static
    {
        $this->sanitizeUsing = $callback;

        return $this;
    }

    private function sanitize(string $html): string
    {
        return $this->sanitizeUsing === null
            ? app(RichTextSanitizer::class)->sanitize($html)
            : (string) $this->evaluate($this->sanitizeUsing, ['html' => $html]);
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        return is_string($value) ? $this->sanitize($value) : $value;
    }

    /**
     * An empty editor posts "<p></p>", which a required rule would accept.
     */
    public function normalizeState(mixed $state): mixed
    {
        if (! is_string($state)) {
            return $state;
        }

        $html = $this->sanitize($state);

        return trim(strip_tags($html, '<img><hr>')) === '' ? null : $html;
    }

    public function replaceUploads(string $state, Closure $store): string
    {
        $token = '/data-xq-upload="('.preg_quote(TemporaryUploadStore::TOKEN_PREFIX, '/').'[^"]+)"/';

        return preg_replace_callback('/<img\b[^>]*>/', function (array $image) use ($token, $store): string {
            if (preg_match($token, $image[0], $matches) !== 1) {
                return $image[0];
            }

            $url = e($this->storeAttachment(html_entity_decode($matches[1]), $store));

            return str_replace($matches[0], "src=\"{$url}\"", (string) preg_replace('/\ssrc="[^"]*"/', '', $image[0]));
        }, $state) ?? $state;
    }

    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'debounce', 'ms' => 400];
    }

    protected function getTypeRules(): array
    {
        return ['string'];
    }

    protected function getProps(): array
    {
        $disabled = [...$this->disabledToolbarButtons, ...($this->hasFileAttachments ? [] : ['attachFiles'])];

        return [
            'toolbar' => array_values(array_filter(array_map(
                fn (array $group): array => array_values(array_diff($group, $disabled)),
                $this->toolbarButtons,
            ))),
            'minHeight' => $this->minHeight,
            'accept' => implode(',', $this->fileAttachmentsAcceptedFileTypes),
        ];
    }
}
