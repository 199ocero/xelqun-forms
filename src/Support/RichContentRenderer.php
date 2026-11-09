<?php

namespace Xelqun\Forms\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Xelqun\Forms\Components\RichEditor\CustomBlock;

/**
 * Turns what a RichEditor saved as HTML into the HTML to show: it cleans it
 * again, fills merge tags and draws custom blocks.
 *
 *     RichContentRenderer::make($post->body)->mergeTags(['name' => $user->name])->toHtml()
 *
 * @api
 */
final class RichContentRenderer implements Htmlable
{
    private const string MERGE_TAG = '/\{\{\s*([\w.-]+)\s*\}\}/';

    private const string BLOCK = '/<div\b[^>]*\bdata-type="customBlock"[^>]*>\s*<\/div>/';

    /** @var array<string, mixed> */
    private array $mergeTags = [];

    /** @var array<string, array{class: class-string<CustomBlock>, data: array<string, mixed>}> */
    private array $blocks = [];

    private function __construct(private readonly ?string $html) {}

    public static function make(?string $html): self
    {
        return new self($html);
    }

    /**
     * name => value. A closure runs once, the first time its tag is used. An
     * Htmlable value is shown as HTML, and anything else is escaped. Tags are
     * filled in text only, never inside a tag such as a link's address.
     *
     * @param  array<string, mixed>  $values
     */
    public function mergeTags(array $values): self
    {
        $this->mergeTags = $values;

        return $this;
    }

    /**
     * The blocks to draw: a class, or a class => data for its toHtml(). A block
     * not named here is left out of the page.
     *
     * @param  array<int|class-string<CustomBlock>, class-string<CustomBlock>|array<string, mixed>>  $blocks
     */
    public function customBlocks(array $blocks): self
    {
        foreach ($blocks as $key => $value) {
            /** @var class-string<CustomBlock> $class */
            $class = is_string($key) ? $key : $value;
            $this->blocks[$class::getId()] = ['class' => $class, 'data' => is_array($value) ? $value : []];
        }

        return $this;
    }

    /**
     * Each custom block in the HTML, as its id and its config as written.
     *
     * @return array<int, array{id: string, config: string}>
     */
    public static function findBlocks(string $html): array
    {
        preg_match_all(self::BLOCK, $html, $matches);

        return array_map(self::readBlock(...), $matches[0]);
    }

    /**
     * Replace each custom block with what the callback returns for its id and
     * config as written.
     *
     * @param  Closure(string, string): string  $callback
     */
    public static function replaceBlocks(string $html, Closure $callback): string
    {
        return preg_replace_callback(self::BLOCK, function (array $match) use ($callback): string {
            ['id' => $id, 'config' => $config] = self::readBlock($match[0]);

            return $callback($id, $config);
        }, $html) ?? $html;
    }

    public function toHtml(): string
    {
        $html = self::replaceBlocks(app(RichTextSanitizer::class)->sanitize((string) $this->html), function (string $id, string $config): string {
            $block = $this->blocks[$id] ?? null;
            $values = json_decode($config, true);

            return $block === null ? '' : $block['class']::toHtml(is_array($values) ? $values : [], $block['data']);
        });

        // Only in text, never inside a tag, so a value can't become a link's address.
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        return implode('', array_map(fn (string $part): string => str_starts_with($part, '<') ? $part : $this->fillMergeTags($part), $parts));
    }

    private function fillMergeTags(string $text): string
    {
        return preg_replace_callback(self::MERGE_TAG, function (array $match): string {
            if (! array_key_exists($match[1], $this->mergeTags)) {
                return $match[0];
            }

            $value = $this->mergeTags[$match[1]];

            if ($value instanceof Closure) {
                $value = $this->mergeTags[$match[1]] = $value();
            }

            return $value instanceof Htmlable ? $value->toHtml() : e((string) $value);
        }, $text) ?? $text;
    }

    /**
     * @return array{id: string, config: string}
     */
    private static function readBlock(string $tag): array
    {
        $attribute = fn (string $name): string => preg_match('/\b'.$name.'="([^"]*)"/', $tag, $found) === 1 ? html_entity_decode($found[1], ENT_QUOTES | ENT_HTML5) : '';

        return ['id' => $attribute('data-id'), 'config' => $attribute('data-config')];
    }
}
