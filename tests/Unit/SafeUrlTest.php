<?php

use Xelqun\Forms\Support\SafeUrl;

it('keeps a url it knows is safe', function (string $url) {
    expect(SafeUrl::filter($url))->toBe($url);
})->with([
    '/orders/1',
    '/',
    'https://example.com/a?b=c',
    'HTTP://example.com',
    'mailto:ada@example.com',
    'tel:+639171234567',
]);

it('drops anything else', function (?string $url) {
    expect(SafeUrl::filter($url))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'script' => 'javascript:alert(1)',
    'mixed case script' => 'JaVaScRiPt:alert(1)',
    'leading space' => ' javascript:alert(1)',
    'tab inside the scheme' => "java\tscript:alert(1)",
    'newline before an allowed scheme' => "\nhttps://example.com",
    'data' => 'data:text/html,<script>alert(1)</script>',
    'protocol relative' => '//evil.example',
    'backslash' => '/\\evil.example',
    'tab that collapses to protocol relative' => "/\t/evil.example",
    'relative' => 'orders/1',
]);
