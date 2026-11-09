<?php

namespace Xelqun\Forms\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\Concerns\HasFileAttachments;
use Xelqun\Forms\Components\RichEditor\CustomBlock;
use Xelqun\Forms\Components\RichEditor\MentionProvider;
use Xelqun\Forms\Contracts\EmbedsUploads;
use Xelqun\Forms\Contracts\HasSearchResults;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\RichContentRenderer;
use Xelqun\Forms\Support\RichJsonSanitizer;
use Xelqun\Forms\Support\RichTextSanitizer;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

/**
 * Rich text, kept as HTML, or as Tiptap's JSON with json(). The server cleans it
 * when it arrives and when it loads from the record, so saved HTML is safe to
 * print with {!! !!}, as long as it was saved through this field. Show merge
 * tags and custom blocks through RichContentRenderer.
 *
 * @api
 */
class RichEditor extends Field implements EmbedsUploads, HasSearchResults
{
    use HasFileAttachments;

    /** @var array<int, array<int, string>> */
    protected array $toolbarButtons = [
        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
        ['h2', 'h3'],
        ['alignStart', 'alignCenter', 'alignEnd'],
        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
        ['table', 'attachFiles'],
        ['tableAddRowAfter', 'tableAddColumnAfter', 'tableDeleteRow', 'tableDeleteColumn', 'tableMergeCells', 'tableSplitCell', 'tableToggleHeaderRow', 'tableDelete'],
        ['textColor', 'mergeTags', 'customBlocks'],
        ['undo', 'redo'],
    ];

    /** @var array<int, string> */
    protected array $disabledToolbarButtons = [];

    protected ?string $minHeight = null;

    protected ?Closure $sanitizeUsing = null;

    protected bool|Closure $isJson = false;

    /** @var array<string, string> */
    protected array $textColors = [];

    /** @var array<string, string> */
    protected array $mergeTags = [];

    /** @var array<int, MentionProvider> */
    protected array $mentionProviders = [];

    /** @var array<int, class-string<CustomBlock>> */
    protected array $customBlocks = [];

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

    /**
     * Keep Tiptap's JSON document instead of HTML. Cast the column to array.
     * The server keeps only the nodes and marks the editor can make. File
     * attachments are off in this mode. This replaces the json validation rule,
     * which means nothing for rich text.
     */
    public function json(bool|Closure $condition = true): static
    {
        $this->isJson = $condition;

        return $this;
    }

    public function isJson(): bool
    {
        return (bool) $this->evaluate($this->isJson);
    }

    /**
     * The colors the textColor button offers, as hex => label.
     *
     * @param  array<string, string>  $colors
     */
    public function textColors(array $colors): static
    {
        $this->textColors = $colors;

        return $this;
    }

    /**
     * Tags the mergeTags button inserts, as name => label or a list of names.
     * They're saved as {{ name }}, and RichContentRenderer fills them in.
     *
     * @param  array<int|string, string>  $tags
     */
    public function mergeTags(array $tags): static
    {
        $this->mergeTags = array_is_list($tags) ? array_combine($tags, $tags) : $tags;

        return $this;
    }

    /**
     * What typing @, or another character, offers.
     *
     * @param  array<int, MentionProvider>  $providers
     */
    public function mentions(array $providers): static
    {
        $this->mentionProviders = $providers;

        return $this;
    }

    /**
     * Blocks the customBlocks button inserts. Each opens a modal when its
     * editor action has a schema.
     *
     * @param  array<int, class-string<CustomBlock>>  $blocks
     */
    public function customBlocks(array $blocks): static
    {
        $this->customBlocks = $blocks;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->isAsync();
    }

    public function isAsync(): bool
    {
        foreach ($this->mentionProviders as $provider) {
            if ($provider->searchesOnServer()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The search starts with the character that was typed, so it reaches the
     * provider for that character.
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    public function getSearchResults(string $search): array
    {
        foreach ($this->mentionProviders as $provider) {
            if ($provider->searchesOnServer() && str_starts_with($search, $provider->getChar())) {
                $results = $provider->search(substr($search, strlen($provider->getChar())));

                return array_map(fn (int|string $id): array => ['value' => $id, 'label' => (string) $results[$id]], array_keys($results));
            }
        }

        return [];
    }

    /**
     * Each block inserts through an action of its own, so its modal checks its
     * fields on the server like any other.
     */
    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        $this->slotComponents = array_filter($this->slotComponents, fn (Component $component): bool => $component->getSlot() !== 'richBlock');

        if (! $form->isAnonymous()) {
            foreach ($this->customBlocks as $block) {
                $this->slotComponents[] = $block::configureEditorAction(
                    Action::make('block_'.Str::snake($block::getId()))->label($block::getLabel())->authorize(true),
                )->sendsResult()->action(fn (array $data): array => [
                    'id' => $block::getId(),
                    'config' => (string) json_encode((object) $data),
                    'preview' => $block::toPreviewHtml($data),
                ])->inSlot('richBlock');
            }
        }

        return parent::mount($form, $parent, $container, $position, $containerPattern);
    }

    private function sanitize(string $html): string
    {
        return $this->sanitizeUsing === null
            ? app(RichTextSanitizer::class)->sanitize($html)
            : (string) $this->evaluate($this->sanitizeUsing, ['html' => $html]);
    }

    public function formatStateFromRecord(mixed $value): mixed
    {
        if ($this->isJson()) {
            return is_array($value) ? app(RichJsonSanitizer::class)->sanitize($value, $this->getUnofferedTypes()) : null;
        }

        return is_string($value) ? $this->sanitize($value) : $value;
    }

    /**
     * An empty editor posts "<p></p>", which a required rule would accept.
     */
    public function normalizeState(mixed $state): mixed
    {
        if ($this->isJson()) {
            $document = is_array($state) ? app(RichJsonSanitizer::class)->sanitize($state, $this->getUnofferedTypes()) : null;

            return $document === null ? null : $this->checkJsonBlocks($document);
        }

        if (! is_string($state)) {
            return $state;
        }

        $html = RichContentRenderer::replaceBlocks($this->sanitize($state), function (string $id, string $config): string {
            $checked = $this->checkBlock($id, $config);

            return $checked === null ? '' : '<div data-type="customBlock" data-id="'.e($id).'" data-config="'.e($checked).'"></div>';
        });

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

    /**
     * Nodes and marks this field doesn't offer, which the editor couldn't load.
     *
     * @return array<int, string>
     */
    private function getUnofferedTypes(): array
    {
        return [
            ...($this->customBlocks === [] ? ['customBlock'] : []),
            ...($this->mentionProviders === [] ? ['mention'] : []),
            ...($this->textColors === [] ? ['textStyle'] : []),
        ];
    }

    /**
     * A browser can post any block, so its config goes through the block's
     * modal rules here too. A block that fails, or isn't offered, is dropped.
     *
     * @return string|null The config as its validated JSON.
     */
    private function checkBlock(string $id, string $config): ?string
    {
        $action = null;

        foreach ($this->slotComponents as $component) {
            if ($component instanceof Action && $component->getSlot() === 'richBlock' && $component->getName() === 'block_'.Str::snake($id)) {
                $action = $component;
            }
        }

        $values = json_decode($config, true);

        if ($action === null || ! is_array($values)) {
            return null;
        }

        if (! $action->hasModalForm()) {
            return '{}';
        }

        try {
            return (string) json_encode((object) $action->makeForm([])->validate([StatePath::ROOT => $values]));
        } catch (ValidationException|HttpExceptionInterface) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function checkJsonBlocks(array $node): ?array
    {
        if ($node['type'] === 'customBlock') {
            $config = $this->checkBlock((string) ($node['attrs']['id'] ?? ''), (string) ($node['attrs']['config'] ?? ''));

            return $config === null ? null : [...$node, 'attrs' => [...$node['attrs'], 'config' => $config]];
        }

        if (isset($node['content'])) {
            $node['content'] = array_values(array_filter(array_map($this->checkJsonBlocks(...), $node['content'])));
        }

        return $node;
    }

    protected function getDefaultLiveConfig(): array
    {
        return ['mode' => 'debounce', 'ms' => 400];
    }

    protected function getTypeRules(): array
    {
        return [$this->isJson() ? 'array' : 'string'];
    }

    /**
     * What each custom block in the content looks like, by its id and config,
     * so the editor can draw the blocks it loads.
     *
     * @return array<string, string>
     */
    private function getBlockPreviews(): array
    {
        $state = $this->getState();
        $found = is_string($state) ? RichContentRenderer::findBlocks($state) : (is_array($state) ? $this->findJsonBlocks($state) : []);
        $blocks = [];

        foreach ($this->customBlocks as $block) {
            $blocks[$block::getId()] = $block;
        }

        $previews = [];

        foreach ($found as ['id' => $id, 'config' => $config]) {
            if (isset($blocks[$id])) {
                $values = json_decode($config, true);
                $previews[$id."\n".$config] = $blocks[$id]::toPreviewHtml(is_array($values) ? $values : []);
            }
        }

        return $previews;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<int, array{id: string, config: string}>
     */
    private function findJsonBlocks(array $node): array
    {
        $found = ($node['type'] ?? null) === 'customBlock'
            ? [['id' => (string) ($node['attrs']['id'] ?? ''), 'config' => (string) ($node['attrs']['config'] ?? '')]]
            : [];

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            array_push($found, ...(is_array($child) ? $this->findJsonBlocks($child) : []));
        }

        return $found;
    }

    protected function getProps(): array
    {
        $disabled = [
            ...$this->disabledToolbarButtons,
            // shortcut: no attachments in JSON mode, as uploads are swapped in the HTML; walk the document for image nodes when someone needs them.
            ...($this->hasFileAttachments && ! $this->isJson() ? [] : ['attachFiles']),
            ...($this->textColors === [] ? ['textColor'] : []),
            ...($this->mergeTags === [] ? ['mergeTags'] : []),
            ...($this->customBlocks === [] ? ['customBlocks'] : []),
        ];
        $blocks = array_map(fn (string $block): array => ['id' => $block::getId(), 'label' => $block::getLabel()], $this->customBlocks);

        return [
            'toolbar' => array_values(array_filter(array_map(
                fn (array $group): array => array_values(array_diff($group, $disabled)),
                $this->toolbarButtons,
            ))),
            'minHeight' => $this->minHeight,
            'accept' => implode(',', $this->fileAttachmentsAcceptedFileTypes),
            ...($this->isJson() ? ['json' => true] : []),
            ...($this->textColors === [] ? [] : ['textColors' => $this->textColors]),
            ...($this->mergeTags === [] ? [] : ['mergeTags' => $this->mergeTags]),
            ...($this->mentionProviders === [] ? [] : ['mentions' => array_map(fn (MentionProvider $provider): array => [
                'char' => $provider->getChar(),
                'remote' => $provider->searchesOnServer(),
                'items' => array_map(fn (int|string $id, string $label): array => ['value' => $id, 'label' => $label], array_keys($provider->getItems()), $provider->getItems()),
            ], $this->mentionProviders)]),
            ...($blocks === [] ? [] : ['blocks' => $blocks, 'blockPreviews' => (object) $this->getBlockPreviews()]),
        ];
    }
}
