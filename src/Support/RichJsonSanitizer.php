<?php

namespace Xelqun\Forms\Support;

/**
 * The JSON twin of RichTextSanitizer: keeps the nodes, marks and attributes the
 * rich editor can make, with safe values, and drops the rest. A browser can
 * post any JSON it likes, so this runs on the server.
 */
final class RichJsonSanitizer
{
    /** @var array<string, array<int, string>> Node type => attributes it keeps. */
    private const array NODES = [
        'doc' => [], 'paragraph' => ['textAlign'], 'text' => [], 'heading' => ['level', 'textAlign'],
        'blockquote' => [], 'codeBlock' => ['language'], 'bulletList' => [], 'orderedList' => ['start'],
        'listItem' => [], 'hardBreak' => [], 'horizontalRule' => [],
        'table' => [], 'tableRow' => [], 'tableHeader' => ['colspan', 'rowspan', 'colwidth'], 'tableCell' => ['colspan', 'rowspan', 'colwidth'],
        'mention' => ['id', 'label', 'mentionSuggestionChar'], 'customBlock' => ['id', 'config'],
    ];

    /** @var array<string, array<int, string>> Mark type => attributes it keeps. */
    private const array MARKS = [
        'bold' => [], 'italic' => [], 'underline' => [], 'strike' => [], 'code' => [], 'subscript' => [], 'superscript' => [],
        'link' => ['href', 'target'], 'textStyle' => ['color'],
    ];

    /** @var array<int, string> */
    private array $without = [];

    /**
     * Leave out node and mark types the field doesn't offer, such as mention or
     * customBlock: the editor can't load a document that has them.
     *
     * @param  array<array-key, mixed>  $document
     * @param  array<int, string>  $without
     * @return array<string, mixed>|null
     */
    public function sanitize(array $document, array $without = []): ?array
    {
        $this->without = $without;

        $clean = $this->node($document);

        return $clean !== null && $clean['type'] === 'doc' ? $clean : null;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function node(array $node): ?array
    {
        $type = $node['type'] ?? null;

        if (! is_string($type) || ! array_key_exists($type, self::NODES) || in_array($type, $this->without, true)) {
            return null;
        }

        $clean = ['type' => $type];

        if ($type === 'text') {
            if (! is_string($node['text'] ?? null) || $node['text'] === '') {
                return null;
            }

            $clean['text'] = $node['text'];
        }

        $attrs = $this->attributes($type, self::NODES[$type], $node['attrs'] ?? []);

        if ($attrs !== []) {
            $clean['attrs'] = $attrs;
        }

        if (is_array($node['content'] ?? null)) {
            $clean['content'] = array_values(array_filter(array_map(fn (mixed $child): ?array => is_array($child) ? $this->node($child) : null, $node['content'])));
        }

        if (is_array($node['marks'] ?? null)) {
            $marks = array_values(array_filter(array_map(fn (mixed $mark): ?array => is_array($mark) ? $this->mark($mark) : null, $node['marks'])));

            if ($marks !== []) {
                $clean['marks'] = $marks;
            }
        }

        return $clean;
    }

    /**
     * @param  array<array-key, mixed>  $mark
     * @return array<string, mixed>|null
     */
    private function mark(array $mark): ?array
    {
        $type = $mark['type'] ?? null;

        if (! is_string($type) || ! array_key_exists($type, self::MARKS) || in_array($type, $this->without, true)) {
            return null;
        }

        $attrs = $this->attributes($type, self::MARKS[$type], $mark['attrs'] ?? []);

        // A link that lost its address would be a link to nowhere.
        if ($type === 'link' && ! isset($attrs['href'])) {
            return null;
        }

        return $attrs === [] ? ['type' => $type] : ['type' => $type, 'attrs' => $attrs];
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    private function attributes(string $type, array $allowed, mixed $attrs): array
    {
        $clean = [];

        foreach (is_array($attrs) ? $attrs : [] as $name => $value) {
            if (! in_array($name, $allowed, true) || $value === null) {
                continue;
            }

            $value = $this->value((string) $name, $value);

            if ($value !== null) {
                $clean[$name] = $value;
            }
        }

        return $clean;
    }

    private function value(string $name, mixed $value): mixed
    {
        return match ($name) {
            'textAlign' => in_array($value, ['left', 'center', 'right', 'justify'], true) ? $value : null,
            'level' => is_int($value) && $value >= 1 && $value <= 6 ? $value : null,
            'start', 'colspan', 'rowspan' => is_int($value) && $value >= 1 ? $value : null,
            'colwidth' => is_array($value) && array_is_list($value) && array_filter($value, fn (mixed $width): bool => ! is_int($width) && $width !== null) === [] ? $value : null,
            'href' => is_string($value) ? SafeUrl::filter($value) : null,
            'target' => $value === '_blank' ? '_blank' : null,
            'color' => is_string($value) && preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1 ? $value : null,
            default => is_string($value) || is_int($value) ? $value : null,
        };
    }
}
