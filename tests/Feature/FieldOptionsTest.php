<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Hidden;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Form;

class FileNamesTestForm extends Form
{
    public function schema(): array
    {
        return [
            FileUpload::make('contract')->disk('public')->directory('docs')->preserveFilenames()->storeFileNamesIn('contract_name'),
            Hidden::make('contract_name'),
            FileUpload::make('scans')->disk('public')->directory('scans')->multiple()->storeFileNamesIn('scan_names'),
            Hidden::make('scan_names'),
        ];
    }
}

function fieldNode(Form $form, string $name): array
{
    return json_decode(json_encode(collect($form->toSchema()['components'])->firstWhere('path', "data.{$name}")), true);
}

function uploadNamed(string $path, string $name): string
{
    return test()->post('/_xelqun/forms/upload', [
        'token' => FileNamesTestForm::make()->makeToken(),
        'path' => $path,
        'file' => UploadedFile::fake()->create($name, 4, 'application/pdf'),
    ])->assertOk()->json('token');
}

it('sends the hint, its icon and color, the helper text and the asterisk', function () {
    $node = fieldNode(Form::make([
        TextInput::make('name')->hint('Public')->hintIcon('info', 'Shown on your profile')->hintColor('info')
            ->helperText('Your full name.')->required()->markAsRequired(false)
            ->extraInputAttributes(['data-1p-ignore' => 'true'])
            ->hintAction(Action::make('suggest')->authorize(true)->url('/suggest')),
    ]), 'name');

    expect($node)->toMatchArray([
        'hint' => 'Public', 'hintIcon' => 'info', 'hintIconTooltip' => 'Shown on your profile', 'hintColor' => 'info',
        'helperText' => 'Your full name.', 'required' => true, 'markedAsRequired' => false,
        'extraInputAttributes' => ['data-1p-ignore' => 'true'],
    ])->and($node['children'][0])->toMatchArray(['type' => 'action', 'slot' => 'afterLabel']);
});

it('leaves helper text and the asterisk override out until they are set', function () {
    expect(fieldNode(Form::make([TextInput::make('name')]), 'name'))->not->toHaveKeys(['helperText', 'markedAsRequired', 'extraInputAttributes']);
});

it('sends the icons and colors of a toggle', function () {
    expect(fieldNode(Form::make([Toggle::make('on')->onIcon('check')->offIcon('x')->onColor('success')->offColor('danger')]), 'on')['props'])
        ->toMatchArray(['onIcon' => 'check', 'offIcon' => 'x', 'onColor' => 'success', 'offColor' => 'danger']);
});

it('cleans HTML labels and keeps their text for searching', function () {
    $select = Select::make('tag')->allowHtml()->options(['a' => '<b>Bold</b> &amp; <script>alert(1)</script>tag']);
    $options = fieldNode(Form::make([$select]), 'tag')['props']['options'];

    expect($options[0]['label'])->toBe('Bold & alert(1)tag')
        ->and($options[0]['html'])->toContain('<b>Bold</b>')->not->toContain('<script');
});

it('leaves labels as text unless HTML is allowed', function () {
    expect(fieldNode(Form::make([Select::make('tag')->options(['a' => '<b>A</b>'])]), 'tag')['props']['options'][0])
        ->not->toHaveKey('html')->toMatchArray(['label' => '<b>A</b>']);
});

it('limits how many options show and how many a search finds', function () {
    $select = Select::make('tag')->optionsLimit(2)->searchable()->options(['a' => 'Ant', 'b' => 'Bat', 'c' => 'Cat']);

    expect(fieldNode(Form::make([$select]), 'tag')['props']['optionsLimit'])->toBe(2);
});

it('checks how many values a multiple select takes', function (array $value, bool $passes) {
    $form = Form::make([Select::make('tags')->multiple()->minItems(2)->maxItems(3)->options(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'])]);

    $validate = fn () => $form->validate(['data' => ['tags' => $value]]);

    $passes ? expect($validate())->toHaveKey('tags') : expect($validate)->toThrow(ValidationException::class);
})->with([
    'too few' => [['a'], false],
    'enough' => [['a', 'b'], true],
    'too many' => [['a', 'b', 'c', 'd'], false],
]);

describe('file names', function () {
    beforeEach(function () {
        Storage::fake('local');
        Storage::fake('public');
    });

    // One upload per test: each request in a test starts its own session, and an upload belongs to one.
    it('keeps the original name, adds a suffix when it is taken, and stores the name', function () {
        Storage::disk('public')->put('docs/Lease 2026.pdf', 'old');

        $data = FileNamesTestForm::make()->validateKeyed(['data' => ['contract' => uploadNamed('data.contract', 'Lease 2026.pdf')]]);

        expect($data['contract'])->toStartWith('docs/Lease 2026-')->toEndWith('.pdf')
            ->and($data['contract_name'])->toBe('Lease 2026.pdf');
        Storage::disk('public')->assertExists($data['contract']);
    });

    it('stores the names of many files by their stored path', function () {
        $data = FileNamesTestForm::make()->validateKeyed(['data' => ['scans' => [uploadNamed('data.scans', 'front.pdf')]]]);

        expect($data['scans'][0])->toStartWith('scans/front-')
            ->and($data['scan_names'])->toBe([$data['scans'][0] => 'front.pdf']);
    });

    it('never stores a file under a name that leaves its directory', function () {
        $data = FileNamesTestForm::make()->validateKeyed(['data' => ['contract' => uploadNamed('data.contract', '...pdf')]]);

        expect($data['contract'])->toBe('docs/file.pdf');
    });
});
