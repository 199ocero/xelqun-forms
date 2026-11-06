<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\CodeEditor;
use Xelqun\Forms\Components\CodeEditor\Language;
use Xelqun\Forms\Components\MarkdownEditor;
use Xelqun\Forms\Components\RichEditor;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Actions;
use Xelqun\Forms\Support\RichTextSanitizer;
use Xelqun\Forms\Tests\Fixtures\Order;

class EditorsForm extends Form
{
    public function schema(): array
    {
        return [
            RichEditor::make('body')->required()->fileAttachments()->fileAttachmentsDisk('public'),
            RichEditor::make('plain'),
            MarkdownEditor::make('notes')->fileAttachmentsDisk('public'),
            MarkdownEditor::make('locked')->disabled()->default('# Hi'),
            CodeEditor::make('snippet'),
        ];
    }
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function clean(?string $html): ?string
{
    return app(RichTextSanitizer::class)->sanitize((string) $html);
}

function attach(string $path, UploadedFile $file): TestResponse
{
    return test()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40))->post(route('xelqun.forms.upload'), [
        'token' => EditorsForm::make()->makeToken(),
        'path' => $path,
        'file' => $file,
    ]);
}

/**
 * Validate in the session the upload came from.
 */
function saveEditors(array $data): array
{
    request()->setLaravelSession(tap(app('session')->driver())->setId(str_repeat('a', 40)));

    return EditorsForm::make()->validate(['data' => ['body' => '<p>x</p>', ...$data]]);
}

describe('the sanitizer', function () {
    it('takes out what could run', function (string $html, string $bad) {
        expect(clean($html))->not->toContain($bad);
    })->with([
        'script' => ['<p>a</p><script>alert(1)</script>', '<script'],
        'event handler' => ['<img src="/a.png" onerror="alert(1)">', 'onerror'],
        'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
        'mixed case scheme' => ['<a href=" JaVaScRiPt:alert(1)">x</a>', 'alert'],
        'data image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', 'data:'],
        'iframe' => ['<iframe src="https://evil.test"></iframe>', 'iframe'],
        'style tag' => ['<style>body{display:none}</style>', 'display'],
        'free css' => ['<p style="position:fixed;background:url(https://evil.test)">x</p>', 'style'],
        'css after text-align' => ['<p style="text-align:center;position:fixed">x</p>', 'fixed'],
        'svg' => ['<svg onload="alert(1)"></svg>', 'svg'],
        'form' => ['<form action="/x"><input name="a"></form>', 'form'],
        'base' => ['<base href="https://evil.test/">', 'base'],
        'upload attribute elsewhere' => ['<p data-xq-upload="xq-tmp:a">x</p>', 'data-xq-upload'],
    ]);

    it('keeps what the editor writes', function (string $html) {
        expect(clean($html))->toBe($html);
    })->with([
        'marks' => ['<p><strong>a</strong> <em>b</em> <u>c</u> <s>d</s> <sub>e</sub> <sup>f</sup> <code>g</code></p>'],
        'headings' => ['<h2>a</h2><h3>b</h3>'],
        'alignment' => ['<p style="text-align: center">a</p>'],
        'lists' => ['<ul><li><p>a</p></li></ul><ol><li><p>b</p></li></ol>'],
        'blocks' => ['<blockquote><p>a</p></blockquote><pre><code>b</code></pre>'],
        'table' => ['<table><tbody><tr><th colspan="1" rowspan="1"><p>a</p></th></tr><tr><td colspan="1" rowspan="1"><p>b</p></td></tr></tbody></table>'],
    ]);

    it('makes links safe to follow', function () {
        expect(clean('<a href="https://example.com" target="_blank">x</a>'))->toContain('rel="noopener noreferrer"');
    });

    it('does not cut a long text short', function () {
        $html = '<p>'.str_repeat('a', 50000).'</p>';

        expect(clean($html))->toBe($html);
    });
});

describe('rich editor', function () {
    it('cleans what the browser posts', function () {
        $output = EditorsForm::make()->validate(['data' => ['body' => '<p>a</p><script>alert(1)</script><img src="/x.png" onerror="y">']]);

        expect($output['body'])->toContain('<p>a</p>')->not->toContain('script')->not->toContain('onerror');
    });

    it('cleans what comes out of the database', function () {
        $order = new Order;
        $order->exists = true;
        $order->setAttribute('meta', '<p>a</p><script>alert(1)</script>');

        $form = new class($order) extends Form
        {
            public function schema(): array
            {
                return [RichEditor::make('meta')];
            }
        };

        expect($form->fill()->getData()['meta'])->toBe('<p>a</p>');
    });

    it('treats an empty document as empty', function (string $html) {
        EditorsForm::make()->validate(['data' => ['body' => $html]]);
    })->with(['<p></p>', '<p>  </p>', '<h2></h2>', '<script>x</script>'])->throws(ValidationException::class);

    it('counts an image as content', function () {
        expect(EditorsForm::make()->validate(['data' => ['body' => '<p><img src="/a.png"></p>']])['body'])->toContain('img');
    });

    it('takes a sanitizer of your own', function () {
        $form = new class extends Form
        {
            public function schema(): array
            {
                return [RichEditor::make('body')->sanitizeUsing(fn (string $html): string => strtoupper($html))];
            }
        };

        expect($form::make()->validate(['data' => ['body' => '<p>a</p>']])['body'])->toBe('<P>A</P>');
    });

    it('only offers the buttons that are on', function () {
        $props = EditorsForm::make()->toSchema()['components'][1]['props'];

        expect(collect($props->toolbar)->flatten())->not->toContain('attachFiles')->toContain('bold');
    });

    it('stores an attached image and writes its address', function () {
        $token = attach('data.body', UploadedFile::fake()->image('a.png'))->assertOk()->json('token');

        $output = saveEditors(['body' => "<p><img alt=\"Logo\" src=\"blob:x\" data-xq-upload=\"{$token}\"></p>"]);

        expect($output['body'])->toContain('alt="Logo"')->toContain('src="')->not->toContain('blob:')->not->toContain('data-xq-upload')->not->toContain('xq-tmp');

        preg_match('/src="([^"]+)"/', $output['body'], $matches);

        Storage::disk('public')->assertExists(basename(parse_url($matches[1], PHP_URL_PATH)));
    });

    it('refuses a token from another field', function () {
        $token = attach('data.notes', UploadedFile::fake()->image('a.png'))->assertOk()->json('token');

        saveEditors(['body' => "<p><img data-xq-upload=\"{$token}\"></p>"]);
    })->throws(ValidationException::class);

    it('refuses an upload when attachments are off', function () {
        attach('data.plain', UploadedFile::fake()->image('a.png'))->assertSessionHasErrors('file');
    });

    it('takes images only, and never SVG', function (UploadedFile $file) {
        attach('data.body', $file)->assertSessionHasErrors('file');
    })->with([
        'svg' => fn () => UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        'html' => fn () => UploadedFile::fake()->createWithContent('a.html', '<html></html>'),
    ]);

    it('needs a form class only with attachments', function () {
        expect(Form::make([RichEditor::make('a')])->toSchema()['token'])->toBeNull()
            ->and(fn () => Form::make([RichEditor::make('a')->fileAttachments()])->toSchema())->toThrow(FormDefinitionException::class);
    });

    it('stays in a copied row, unlike a file field', function () {
        expect(EditorsForm::make()->toSchema()['components'][0])->not->toHaveKey('acceptsUploads');
    });
});

describe('markdown editor', function () {
    it('keeps what the user typed', function () {
        expect(EditorsForm::make()->validate(['data' => ['body' => '<p>a</p>', 'notes' => "# Hi\n\n<b>x</b>"]])['notes'])->toBe("# Hi\n\n<b>x</b>");
    });

    it('renders safely', function (string $markdown, string $bad) {
        expect(MarkdownEditor::render($markdown))->not->toContain($bad);
    })->with([
        'raw html' => ['<script>alert(1)</script>', '<script'],
        'javascript link' => ['[x](javascript:alert(1))', 'javascript:'],
        'data link' => ['[x](data:text/html;base64,AAAA)', 'data:'],
        'event handler' => ['<img src=x onerror=alert(1)>', 'onerror'],
    ]);

    it('renders what Markdown means', function () {
        expect(MarkdownEditor::render('**a**'))->toContain('<strong>a</strong>');
    });

    it('previews through an action, on the server, from the posted text', function () {
        $node = EditorsForm::make()->toSchema()['components'][2];
        $action = collect($node['children'])->firstWhere('slot', 'markdownPreview');

        test()->postJson(route('xelqun.forms.action'), [
            'token' => EditorsForm::make()->makeToken(),
            'action' => $action['key'],
            'phase' => 'run',
            'state' => ['data' => ['notes' => '**a** <script>x</script>']],
            'deps' => (object) [],
        ])->assertOk()->assertJsonPath('result', fn (string $html): bool => str_contains($html, '<strong>a</strong>') && ! str_contains($html, '<script'));
    });

    it('refuses a preview of a locked field', function () {
        $node = EditorsForm::make()->toSchema()['components'][3];
        $action = collect($node['children'])->firstWhere('slot', 'markdownPreview');

        test()->postJson(route('xelqun.forms.action'), [
            'token' => EditorsForm::make()->makeToken(),
            'action' => $action['key'],
            'phase' => 'run',
            'state' => ['data' => []],
            'deps' => (object) [],
        ])->assertForbidden();
    });

    it('stores an attached image and writes its address', function () {
        $token = attach('data.notes', UploadedFile::fake()->image('a.png'))->assertOk()->json('token');

        $output = saveEditors(['notes' => "![Logo]({$token})"]);

        expect($output['notes'])->toStartWith('![Logo](')->not->toContain('xq-tmp');
    });

    it('works with no server, without a preview', function () {
        $node = Form::make([MarkdownEditor::make('a')])->toSchema()['components'][0];

        expect($node['children'])->toBe([]);
    });
});

describe('code editor', function () {
    it('keeps code as it is', function () {
        expect(EditorsForm::make()->validate(['data' => ['body' => '<p>a</p>', 'snippet' => '<?php echo "<b>";']])['snippet'])->toBe('<?php echo "<b>";');
    });

    it('sends the language', function () {
        $form = new class extends Form
        {
            public function schema(): array
            {
                return [CodeEditor::make('a')->language(Language::JavaScript)->wrap(), TextInput::make('b')];
            }
        };

        expect($form::make()->toSchema()['components'][0]['props'])->language->toBe('javascript')->wrap->toBeTrue();
    });

    it('takes a language from a closure, and none at all', function () {
        $form = Form::make([CodeEditor::make('a')->language(fn (): Language => Language::Php), CodeEditor::make('b')]);

        expect($form->toSchema()['components'][0]['props']->language)->toBe('php')
            ->and($form->toSchema()['components'][1]['props']->language)->toBeNull();
    });
});

describe('what a save leaves alone', function () {
    class CarefulEditorsForm extends Form
    {
        public function schema(): array
        {
            return [
                TextInput::make('title'),
                RichEditor::make('hidden_body')->hidden()->fileAttachments(),
                MarkdownEditor::make('notes')->fileAttachments(),
                Actions::make([
                    Action::make('secret')->authorize(true)->action(fn (): array => ['password' => 'hunter2']),
                ]),
            ];
        }
    }

    it('adds nothing to the output for a field that was left out', function () {
        expect(CarefulEditorsForm::make()->validate(['data' => ['title' => 'a']]))->not->toHaveKey('hidden_body')->not->toHaveKey('notes');
    });

    it('leaves text alone that only looks like a token', function () {
        $text = 'The prefix is xq-tmp:abc, see the docs.';

        expect(CarefulEditorsForm::make()->validate(['data' => ['notes' => $text]])['notes'])->toBe($text);
    });

    it('sends nothing an action returns, unless it was built to', function () {
        $this->postJson(route('xelqun.forms.action'), [
            'token' => CarefulEditorsForm::make()->makeToken(),
            'action' => 'data#3.0',
            'phase' => 'run',
            'state' => ['data' => []],
            'deps' => (object) [],
        ])->assertOk()->assertJsonPath('result', null);
    });
});
