<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Support\Str;
use Xelqun\Forms\Actions\Action;
use Xelqun\Forms\Components\Concerns\HasFileAttachments;
use Xelqun\Forms\Contracts\EmbedsUploads;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\RichTextSanitizer;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

/**
 * Markdown, kept as text. The saved value is what the user typed, so render it
 * the way the preview does: raw HTML stripped, and no unsafe links. See render().
 *
 * @api
 */
class MarkdownEditor extends Field implements EmbedsUploads
{
    use HasFileAttachments;

    /** @var array<int, array<int, string>> */
    protected array $toolbarButtons = [
        ['bold', 'italic', 'strike', 'link'],
        ['heading'],
        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['table', 'attachFiles'],
    ];

    /** @var array<int, string> */
    protected array $disabledToolbarButtons = [];

    protected ?string $minHeight = null;

    protected ?string $maxHeight = null;

    public function getType(): string
    {
        return 'markdown-editor';
    }

    /**
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

    public function minHeight(?string $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    public function maxHeight(?string $height): static
    {
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Turn Markdown into HTML that is safe to print.
     */
    public static function render(?string $markdown): string
    {
        $html = (string) Str::markdown((string) $markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return app(RichTextSanitizer::class)->sanitize($html);
    }

    /**
     * The preview runs on the server, the same way as any action. A form that
     * can't be rebuilt there goes without one.
     */
    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        $this->slotComponents = array_filter($this->slotComponents, fn (Component $component): bool => $component->getSlot() !== 'markdownPreview');

        if (! $form->isAnonymous()) {
            $this->slotComponents[] = Action::make('preview')
                ->authorize(true)
                ->sendsResult()
                ->action(fn (): string => self::render(is_string($this->getState()) ? $this->getState() : null))
                ->inSlot('markdownPreview');
        }

        return parent::mount($form, $parent, $container, $position, $containerPattern);
    }

    public function replaceUploads(string $state, Closure $store): string
    {
        // The full length, so text that only mentions the prefix isn't taken for an upload.
        $token = '/'.preg_quote(TemporaryUploadStore::TOKEN_PREFIX, '/').'[A-Za-z0-9]{40}/';

        return preg_replace_callback($token, fn (array $match): string => $this->storeAttachment($match[0], $store), $state) ?? $state;
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
            'maxHeight' => $this->maxHeight,
            'accept' => implode(',', $this->fileAttachmentsAcceptedFileTypes),
        ];
    }
}
