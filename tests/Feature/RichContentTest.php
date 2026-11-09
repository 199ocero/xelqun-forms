<?php

use Illuminate\Support\HtmlString;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\RichEditor;
use Xelqun\Forms\Components\RichEditor\CustomBlock;
use Xelqun\Forms\Components\RichEditor\MentionProvider;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\RichContentRenderer;
use Xelqun\Forms\Support\RichJsonSanitizer;
use Xelqun\Forms\Support\RichTextSanitizer;

class HeroTestBlock extends CustomBlock
{
    public static function getId(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([TextInput::make('heading')->required()]);
    }

    public static function toPreviewHtml(array $config): string
    {
        return '<b>'.e($config['heading'] ?? '').'</b>';
    }

    public static function toHtml(array $config, array $data): string
    {
        return '<section>'.e($config['heading'] ?? '').' '.e($data['suffix'] ?? '').'</section>';
    }
}

class RichContentTestForm extends Form
{
    public function schema(): array
    {
        return [
            RichEditor::make('body')
                ->textColors(['#ef4444' => 'Red'])
                ->mergeTags(['name' => 'Full name'])
                ->mentions([
                    MentionProvider::make('@')->items([1 => 'Jane']),
                    MentionProvider::make('#')->getSearchResultsUsing(fn (string $search): array => $search === 'bu' ? [7 => 'Bug 7'] : []),
                ])
                ->customBlocks([HeroTestBlock::class]),
            RichEditor::make('doc')->json(),
        ];
    }
}

function richNode(string $name): array
{
    return json_decode(json_encode(collect(RichContentTestForm::make()->toSchema()['components'])->firstWhere('path', "data.{$name}")), true);
}

describe('the sanitizer', function () {
    it('keeps text colors, mentions and block placeholders', function (string $html) {
        // It may encode a character, such as @, which reads the same.
        expect(html_entity_decode(app(RichTextSanitizer::class)->sanitize($html)))->toBe(html_entity_decode($html));
    })->with([
        'color' => ['<p><span style="color: #ef4444">red</span></p>'],
        'mention' => ['<p><span data-type="mention" data-id="1" data-label="Jane" data-mention-suggestion-char="@">@Jane</span></p>'],
        'block' => ['<div data-type="customBlock" data-id="hero" data-config="{&#34;heading&#34;:&#34;Hi&#34;}"></div>'],
    ]);

    it('takes out a color that is more than a color', function (string $style) {
        expect(app(RichTextSanitizer::class)->sanitize("<span style=\"{$style}\">x</span>"))->not->toContain('style');
    })->with([
        'a url' => ['color: red; background: url(https://evil.test)'],
        'a name' => ['color: red'],
        'an expression' => ['color: expression(alert(1))'],
    ]);
});

describe('json', function () {
    it('keeps what the editor makes and drops the rest', function () {
        $clean = app(RichJsonSanitizer::class)->sanitize(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center', 'onclick' => 'x'], 'content' => [
                ['type' => 'text', 'text' => 'Hi', 'marks' => [
                    ['type' => 'bold'],
                    ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                    ['type' => 'textStyle', 'attrs' => ['color' => 'url(x)']],
                    ['type' => 'script'],
                ]],
            ]],
            ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.test']],
            ['type' => 'heading', 'attrs' => ['level' => 9]],
        ]]);

        expect($clean)->toBe(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [
                ['type' => 'text', 'text' => 'Hi', 'marks' => [['type' => 'bold'], ['type' => 'textStyle']]],
            ]],
            ['type' => 'heading'],
        ]]);
    });

    it('refuses something that is not a document', function () {
        expect(app(RichJsonSanitizer::class)->sanitize(['type' => 'paragraph']))->toBeNull();
    });

    it('validates and cleans a JSON editor', function () {
        $data = RichContentTestForm::make()->validate(['data' => ['doc' => ['type' => 'doc', 'content' => [['type' => 'video']]]]]);

        expect($data['doc'])->toBe(['type' => 'doc', 'content' => []])
            ->and(richNode('doc')['props']['json'])->toBeTrue()
            ->and(richNode('doc')['props']['toolbar'])->not->toContain(['attachFiles']);

        expect(RichContentTestForm::make()->validate(['data' => ['doc' => '<p>html</p>']])['doc'])->toBeNull();
    });
});

describe('the editor', function () {
    it('sends its colors, tags, mentions and blocks', function () {
        $props = richNode('body')['props'];

        expect($props['textColors'])->toBe(['#ef4444' => 'Red'])
            ->and($props['mergeTags'])->toBe(['name' => 'Full name'])
            ->and($props['mentions'])->toBe([
                ['char' => '@', 'remote' => false, 'items' => [['value' => 1, 'label' => 'Jane']]],
                ['char' => '#', 'remote' => true, 'items' => []],
            ])
            ->and($props['blocks'])->toBe([['id' => 'hero', 'label' => 'Hero']])
            ->and(collect($props['toolbar'])->flatten())->toContain('textColor', 'mergeTags', 'customBlocks');
    });

    it('searches the provider of the character that was typed', function () {
        $this->postJson(route('xelqun.forms.search'), [
            'token' => RichContentTestForm::make()->makeToken(),
            'path' => 'data.body',
            'search' => '#bu',
            'state' => ['data' => []],
        ])->assertOk()->assertJsonPath('options', [['value' => 7, 'label' => 'Bug 7']]);
    });

    it('checks a block in its modal and sends back its config and preview', function () {
        $node = richNode('body');
        $block = collect($node['children'])->firstWhere('slot', 'richBlock');
        $call = fn (?array $data) => $this->postJson(route('xelqun.forms.action'), [
            'token' => RichContentTestForm::make()->makeToken(),
            'action' => $block['key'],
            'phase' => 'run',
            'state' => ['data' => []],
            'data' => $data,
            'deps' => (object) [],
        ]);

        $call(['heading' => null])->assertStatus(422);
        $call(['heading' => '<i>Hi</i>'])->assertOk()->assertJsonPath('result', [
            'id' => 'hero',
            'config' => '{"heading":"<i>Hi<\/i>"}',
            'preview' => '<b>&lt;i&gt;Hi&lt;/i&gt;</b>',
        ]);
    });

    it('draws the blocks it loads', function () {
        $form = Form::make([RichEditor::make('body')->customBlocks([HeroTestBlock::class])->default('<div data-type="customBlock" data-id="hero" data-config="{&quot;heading&quot;:&quot;Hi&quot;}"></div>')]);
        $props = json_decode(json_encode(collect($form->toSchema()['components'])->firstWhere('path', 'data.body')), true)['props'];

        expect($props['blockPreviews'])->toBe(["hero\n{\"heading\":\"Hi\"}" => '<b>Hi</b>']);
    });
});

describe('the renderer', function () {
    it('fills merge tags, escaping all but HTML', function () {
        $calls = 0;

        $html = RichContentRenderer::make('<p>{{ name }} {{name}} {{ bold }} {{ unknown }}</p>')->mergeTags([
            'name' => function () use (&$calls): string {
                $calls++;

                return '<Ada>';
            },
            'bold' => new HtmlString('<strong>B</strong>'),
        ])->toHtml();

        expect($html)->toBe('<p>&lt;Ada&gt; &lt;Ada&gt; <strong>B</strong> {{ unknown }}</p>')
            ->and($calls)->toBe(1);
    });

    it('draws the blocks it knows and leaves out the rest', function () {
        $html = '<div data-type="customBlock" data-id="hero" data-config="{&quot;heading&quot;:&quot;Hi&quot;}"></div><div data-type="customBlock" data-id="gone" data-config="{}"></div><script>x</script>';

        expect(RichContentRenderer::make($html)->customBlocks([HeroTestBlock::class => ['suffix' => '!']])->toHtml())
            ->toBe('<section>Hi !</section>');
    });
});

describe('what a browser can post', function () {
    it('fills no merge tag inside a tag, so a value never becomes an address', function () {
        $html = RichContentRenderer::make('<p><a href="{{name}}">click</a> {{name}}</p>')->mergeTags(['name' => 'javascript:alert(1)'])->toHtml();

        expect($html)->not->toContain('href="javascript')->toContain('</a> javascript:alert(1)</p>');
    });

    it('checks a posted block against its modal rules, and drops one that fails', function () {
        $block = fn (string $id, string $config): string => '<div data-type="customBlock" data-id="'.$id.'" data-config="'.e($config).'"></div>';
        $body = fn (string $html): ?string => RichContentTestForm::make()->validate(['data' => ['body' => '<p>a</p>'.$html]])['body'];

        expect($body($block('hero', '{"heading":"Hi","extra":"x"}')))->toBe('<p>a</p>'.$block('hero', '{"heading":"Hi"}'))
            ->and($body($block('hero', '{"heading":["x"]}')))->toBe('<p>a</p>')
            ->and($body($block('hero', '{}')))->toBe('<p>a</p>')
            ->and($body($block('nope', '{}')))->toBe('<p>a</p>');
    });

    it('leaves out of a JSON document what the field does not offer', function () {
        $data = RichContentTestForm::make()->validate(['data' => ['doc' => ['type' => 'doc', 'content' => [
            ['type' => 'customBlock', 'attrs' => ['id' => 'hero', 'config' => '{}']],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'mention', 'attrs' => ['id' => '1']],
                ['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'textStyle', 'attrs' => ['color' => '#fff']]]],
            ]],
        ]]]]);

        expect($data['doc'])->toBe(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]]]]);
    });
});
