<?php

namespace Xelqun\Forms\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps what the rich editor can produce, and nothing else. A browser can post
 * any HTML it likes, so this runs on the server, on the way in and on the way
 * out of the database.
 */
final class RichTextSanitizer implements AttributeSanitizerInterface
{
    private const array BLOCKS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const array PLAIN_ELEMENTS = [
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'code', 'pre', 'blockquote', 'br', 'hr',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'colgroup', 'col',
    ];

    public function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength(-1)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowElement('a', ['href', 'title', 'target'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'data-xq-upload'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->withAttributeSanitizer($this);

        foreach (self::BLOCKS as $element) {
            $config = $config->allowElement($element, ['style']);
        }

        foreach (self::PLAIN_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedElements(): array
    {
        return self::BLOCKS;
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    /**
     * The editor only writes text-align. Free CSS could cover the page or call
     * out to another site.
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return preg_match('/^\s*text-align:\s*(left|center|right|justify)\s*;?\s*$/i', html_entity_decode($value), $matches) === 1
            ? 'text-align: '.strtolower($matches[1])
            : null;
    }
}
