<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Forms\Tests\Fixtures\AsyncSelectForm;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\DeniedForm;
use Xelqun\Forms\Tests\Fixtures\HiddenFieldsForm;
use Xelqun\Forms\Tests\Fixtures\LockedValuesForm;
use Xelqun\Forms\Tests\Fixtures\MultipleScopedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\RowTotalsForm;
use Xelqun\Forms\Tests\Fixtures\ScopedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\UploadRulesForm;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * A real file on disk: UploadedFile::fake() takes its type from the name, and
 * these tests are about the name and the content disagreeing.
 */
function uploadedFile(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'xq');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function pdfContent(): string
{
    return "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n<script>alert(1)</script>\n%%EOF\n";
}

function upload(string $path, UploadedFile $file): mixed
{
    return test()->post('/_xelqun/forms/upload', [
        'token' => UploadRulesForm::make()->makeToken(),
        'path' => $path,
        'file' => $file,
    ]);
}

describe('uploads', function () {
    it('refuses types a server could run or a browser could open as a page', function (string $name, string $content) {
        upload('data.anything', uploadedFile($name, $content))->assertSessionHasErrors('file');
    })->with([
        'php' => ['shell.php', '<?php echo 1;'],
        'php with no opening tag' => ['shell.phtml', 'plain text'],
        'html' => ['page.html', '<html><script>alert(1)</script></html>'],
        'svg' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
        'htaccess' => ['.htaccess', 'AddType application/x-httpd-php .txt'],
    ]);

    it('stores a file under the extension of its real type', function () {
        $token = upload('data.pdf', uploadedFile('x.html', pdfContent()))->assertOk()->json('token');

        $path = UploadRulesForm::make()->validate(['pdf' => $token])['pdf'];

        expect($path)->toEndWith('.pdf');
        Storage::disk('public')->assertExists($path);
    });

    it('keeps the extension when it matches the type', function () {
        $token = upload('data.anything', uploadedFile('notes.txt', 'plain text'))->assertOk()->json('token');

        expect(UploadRulesForm::make()->validate(['anything' => $token])['anything'])->toEndWith('.txt');
    });

    it('lets a field opt in to a type by listing it', function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        upload('data.vector', uploadedFile('logo.svg', $svg))->assertOk();
    });

    it('rejects an upload made for a different field', function () {
        $token = upload('data.anything', uploadedFile('notes.txt', 'plain text'))->assertOk()->json('token');

        expect(fn () => UploadRulesForm::make()->validate(['avatar' => $token]))
            ->toThrow(ValidationException::class);
    });
});

describe('computed values', function () {
    it('ignores a posted value for a disabled field', function () {
        $data = LockedValuesForm::make()->validate(['price' => 0, 'quantity' => 3, 'total' => 1]);

        expect($data['total'])->toBe(300);
    });

    it('ignores a posted value for a hidden field', function () {
        $data = LockedValuesForm::make()->validate(['fee' => -1000, 'quantity' => 3]);

        expect($data['total'])->toBe(300);
    });

    it('never saves a posted value for a disabled field', function () {
        expect(LockedValuesForm::make()->validate(['price' => 0]))->not->toHaveKey('price');
    });

    it('drops rows whose keys could reach other rows', function (string $rowKey) {
        $data = RowTotalsForm::make()->validate(['items' => [
            'real' => ['qty' => 2, 'price' => 50],
            $rowKey => ['qty' => 1, 'price' => 1, 'subtotal' => -100],
        ]]);

        expect($data['total'])->toBe(100)
            ->and($data['items'])->toHaveCount(1);
    })->with(['a dot' => 'x.y', 'a wildcard' => '*', 'a slash' => '../x', 'a space' => 'x y']);

    it('keeps rows keyed by a UUID or a record id', function () {
        $data = RowTotalsForm::make()->validate(['items' => [
            '4113394f-daca-4eda-9704-69fe1013637d' => ['qty' => 1, 'price' => 10],
            'record-12' => ['qty' => 1, 'price' => 5],
        ]]);

        expect($data['total'])->toBe(15);
    });
});

describe('fields the server hides', function () {
    function search(string $path): mixed
    {
        return test()->postJson('/_xelqun/forms/search', [
            'token' => HiddenFieldsForm::make()->makeToken(),
            'path' => $path,
            'search' => '',
        ]);
    }

    it('does not search them', function (string $path) {
        search($path)->assertNotFound();
    })->with(['hidden' => 'data.secret', 'inside a hidden section' => 'data.nested', 'disabled' => 'data.locked']);

    it('still searches fields the user can see', function () {
        search('data.shown')->assertOk()->assertJsonFragment(['label' => 'Shown']);
    });

    it('does not accept uploads for them', function () {
        test()->postJson('/_xelqun/forms/upload', [
            'token' => HiddenFieldsForm::make()->makeToken(),
            'path' => 'data.file',
            'file' => UploadedFile::fake()->create('notes.txt', 1),
        ])->assertJsonValidationErrors('file');
    });

    it('does not run their hooks', function () {
        HiddenFieldsForm::$ranHooks = [];

        test()->postJson('/_xelqun/forms/update', [
            'token' => HiddenFieldsForm::make()->makeToken(),
            'seq' => 1,
            'state' => ['data' => []],
            'changed' => ['data.adminOnly', 'data.open'],
            'deps' => (object) [],
        ])->assertOk();

        expect(HiddenFieldsForm::$ranHooks)->toBe(['open']);
    });
});

describe('authorization', function () {
    it('applies authorize() to validate()', function () {
        expect(fn () => DeniedForm::make()->validate(['name' => 'Ada']))
            ->toThrow(HttpException::class);
    });
});

describe('relationship selects', function () {
    it('rejects a record outside the scope of the options', function (string $form, mixed $wrap) {
        Country::query()->create(['name' => 'Allowed']);
        $other = Country::query()->create(['name' => 'Other tenant']);

        expect(fn () => $form::make()->validate(['country_id' => $wrap($other->id)]))
            ->toThrow(ValidationException::class);
    })->with([
        'single' => [ScopedRelationshipForm::class, fn (int $id): int => $id],
        'multiple' => [MultipleScopedRelationshipForm::class, fn (int $id): array => [$id]],
    ]);

    it('accepts a record inside the scope', function () {
        $allowed = Country::query()->create(['name' => 'Allowed']);

        expect(ScopedRelationshipForm::make()->validate(['country_id' => $allowed->id])['country_id'])
            ->toBe($allowed->id);
    });

    it('rejects nested values instead of crashing', function (string $form) {
        expect(fn () => $form::make()->validate(['country_id' => [['x']], 'tags' => [['x']]]))
            ->toThrow(ValidationException::class);
    })->with([MultipleScopedRelationshipForm::class, AsyncSelectForm::class]);
});
