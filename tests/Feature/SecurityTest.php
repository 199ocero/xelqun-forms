<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\State\Get;
use Xelqun\Forms\Tests\Fixtures\AsyncSelectForm;
use Xelqun\Forms\Tests\Fixtures\ChangingDefaultsForm;
use Xelqun\Forms\Tests\Fixtures\ChangingRowsForm;
use Xelqun\Forms\Tests\Fixtures\ClosureReadsForm;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\DefaultReadsHiddenForm;
use Xelqun\Forms\Tests\Fixtures\DeniedForm;
use Xelqun\Forms\Tests\Fixtures\FixedRowsForm;
use Xelqun\Forms\Tests\Fixtures\HiddenFieldsForm;
use Xelqun\Forms\Tests\Fixtures\HiddenValuesForm;
use Xelqun\Forms\Tests\Fixtures\LockChainForm;
use Xelqun\Forms\Tests\Fixtures\LockedRelationRowsForm;
use Xelqun\Forms\Tests\Fixtures\LockedValuesForm;
use Xelqun\Forms\Tests\Fixtures\LockKindForm;
use Xelqun\Forms\Tests\Fixtures\MorphSelectForm;
use Xelqun\Forms\Tests\Fixtures\MultipleScopedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\NullLockedForm;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\Region;
use Xelqun\Forms\Tests\Fixtures\RowTotalsForm;
use Xelqun\Forms\Tests\Fixtures\ScopedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\TrustedStateForm;
use Xelqun\Forms\Tests\Fixtures\UnsettledLocksForm;
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

/**
 * A saved record that keeps its rows in a column instead of a relationship.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function orderWithRows(array $rows): Order
{
    $order = new Order;
    $order->exists = true;
    $order->setAttribute('rows', $rows);

    return $order;
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

        expect($data['total'])->toBe(350);
    });

    it('ignores a posted value for a hidden field', function () {
        $data = LockedValuesForm::make()->validate(['fee' => -1000, 'quantity' => 3]);

        expect($data['total'])->toBe(350);
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

describe('values of hidden fields', function () {
    function orderWithHiddenValues(): Order
    {
        return Order::query()->create([
            'customer' => 'Ada', 'type' => 'individual', 'company' => 'Secret company', 'total' => 1234,
        ]);
    }

    function revealCompany(?Order $order, array $data, array $deps): mixed
    {
        return test()->postJson('/_xelqun/forms/update', [
            'token' => HiddenValuesForm::make($order)->makeToken(),
            'seq' => 1,
            'state' => ['data' => $data],
            'changed' => ['data.type'],
            'deps' => $deps,
        ])->assertOk();
    }

    it('sends no value of a hidden field to the browser', function () {
        $state = HiddenValuesForm::make(orderWithHiddenValues())->toSchema()['state']['data'];

        expect($state)->toHaveKey('customer')
            ->not->toHaveKey('country_id')
            ->not->toHaveKey('total')
            ->not->toHaveKey('company');
    });

    it('sends no value of a hidden field in a row to the browser', function () {
        $form = HiddenValuesForm::make();
        $notes = collect($form->toSchema()['components'])->firstWhere('key', 'data.notes');

        expect((array) $notes['props']->newRow)->toHaveKey('body')->not->toHaveKey('secret');
    });

    it('gives search closures the server value of a hidden field', function () {
        $country = Country::query()->create(['name' => 'Philippines']);
        Region::query()->create(['country_id' => $country->id, 'name' => 'Luzon']);
        $order = Order::query()->create(['customer' => 'Ada', 'country_id' => $country->id]);
        $form = HiddenValuesForm::make($order);
        $schema = $form->toSchema();

        test()->postJson('/_xelqun/forms/search', [
            'token' => $schema['token'],
            'path' => 'data.region_id',
            'search' => '',
            'state' => $schema['state'],
        ])->assertOk()->assertJsonPath('options.0.label', 'Luzon');
    });

    it('ignores a posted value and keeps the server value for closures', function () {
        $country = Country::query()->create(['name' => 'Philippines']);
        $other = Country::query()->create(['name' => 'Japan']);
        Region::query()->create(['country_id' => $country->id, 'name' => 'Luzon']);
        Region::query()->create(['country_id' => $other->id, 'name' => 'Osaka']);
        $order = Order::query()->create(['customer' => 'Ada', 'country_id' => $country->id]);

        test()->postJson('/_xelqun/forms/search', [
            'token' => HiddenValuesForm::make($order)->makeToken(),
            'path' => 'data.region_id',
            'search' => '',
            'state' => ['data' => ['country_id' => $other->id]],
        ])->assertOk()->assertJsonPath('options.0.label', 'Luzon');
    });

    it('sends the value when a closure reveals the field', function () {
        $response = revealCompany(orderWithHiddenValues(), ['type' => 'business'], ['data.company' => ['data.type']]);

        expect($response->json('set'))->toBe(['data.company' => 'Secret company'])
            ->and($response->json('schema')['data.company']['hidden'])->toBeFalse();
    });

    it('sends the values of fields in a section a closure reveals', function () {
        $response = revealCompany(null, ['type' => 'business'], ['data#6' => ['data.type']]);

        expect($response->json('set'))->toBe(['data.region_note' => 'From the server']);
    });

    it('keeps the value away while the closure still hides the field', function () {
        $response = revealCompany(orderWithHiddenValues(), ['type' => 'individual'], ['data.company' => ['data.type']]);

        expect($response->json('set'))->toBe([]);
    });

    it('keeps a value out of the patch when a hook writes to a hidden field', function () {
        $response = test()->postJson('/_xelqun/forms/update', [
            'token' => HiddenValuesForm::make()->makeToken(),
            'seq' => 1,
            'state' => ['data' => ['type' => 'individual']],
            'changed' => ['data.customer'],
            'deps' => (object) [],
        ])->assertOk();

        expect($response->json('set'))->toBe([]);
    });

    it('saves a field a closure reveals with its server value when none is posted', function () {
        $order = orderWithHiddenValues();

        $data = HiddenValuesForm::make($order)->validate(['type' => 'business', 'customer' => 'Ada']);

        expect($data['company'])->toBe('Secret company');
    });

    it('keeps a field shown because of a hidden server value when saving', function () {
        $order = Order::query()->create(['customer' => 'Ada', 'type' => 'business', 'company' => 'Old']);
        $form = new class($order) extends Form
        {
            public function model(): string
            {
                return Order::class;
            }

            public function schema(): array
            {
                return [
                    TextInput::make('customer'),
                    TextInput::make('type')->hidden(),
                    TextInput::make('company')->visible(fn (Get $get): bool => $get('type') === 'business'),
                ];
            }
        };

        $form->fill()->save(['customer' => 'Ada', 'company' => 'New']);

        expect($order->fresh()->company)->toBe('New');
    });
});

describe('authorization', function () {
    it('applies authorize() to validate()', function () {
        expect(fn () => DeniedForm::make()->validate(['name' => 'Ada']))
            ->toThrow(HttpException::class);
    });

    it('runs no form closures for a user who may not use the form', function () {
        $token = app(FormTokenEncrypter::class)->encrypt(new FormToken(DeniedForm::class, null, null));
        DeniedForm::$defaultRan = false;

        test()->postJson('/_xelqun/forms/update', [
            'token' => $token,
            'seq' => 1,
            'state' => ['data' => []],
            'changed' => [],
            'deps' => (object) [],
        ])->assertForbidden();

        expect(DeniedForm::$defaultRan)->toBeFalse();
    });
});

describe('state from the browser', function () {
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, string>>  $deps
     */
    function liveUpdate(array $data, array $changed, array $deps): mixed
    {
        return test()->postJson('/_xelqun/forms/update', [
            'token' => TrustedStateForm::make()->makeToken(),
            'seq' => 1,
            'state' => ['data' => $data],
            'changed' => $changed,
            'deps' => $deps,
        ])->assertOk();
    }

    it('gives hidden() closures the server value of a disabled field', function () {
        $response = liveUpdate(['role' => 'admin'], ['data.role'], ['data.admin_note' => ['data.role']]);

        expect($response->json('schema')['data.admin_note']['hidden'])->toBeTrue();
    });

    it('gives options closures no value from a hidden field', function () {
        $response = liveUpdate(['internal' => 'tampered'], ['data.internal'], ['data.channel' => ['data.internal']]);

        expect($response->json('schema')['data.channel']['props']['options'])
            ->toBe([['value' => 'safe', 'label' => 'Safe']]);
    });

    it('gives closures the server value of a computed field', function () {
        $response = liveUpdate(['quantity' => 2, 'total' => 5000], ['data.total'], ['data.large_order_note' => ['data.total']]);

        expect($response->json('schema')['data.large_order_note']['hidden'])->toBeTrue();
    });

    it('gives search closures the server value of a disabled field', function () {
        test()->postJson('/_xelqun/forms/search', [
            'token' => TrustedStateForm::make()->makeToken(),
            'path' => 'data.plan',
            'search' => '',
            'state' => ['data' => ['role' => 'admin']],
        ])->assertOk()->assertExactJson(['options' => [['value' => 'member', 'label' => 'Plans for member']]]);
    });

    it('drops a field shown only by a tampered disabled value', function () {
        $data = TrustedStateForm::make()->validate(['role' => 'admin', 'admin_note' => 'Injected']);

        expect($data)->not->toHaveKey('admin_note');
    });

    it('keeps a field hidden only by a tampered disabled value', function () {
        $data = TrustedStateForm::make()->validate(['role' => 'admin', 'member_note' => 'Mine']);

        expect($data['member_note'])->toBe('Mine');
    });

    it('restores a field that a tampered value moved from hidden to disabled', function () {
        $data = LockKindForm::make()->validate(['role' => 'admin', 'reviewer' => 'Posted']);

        expect($data['reviewer'])->toBe('Server');
    });

    it('locks every field it saw when conditions never settle', function () {
        $data = UnsettledLocksForm::make()->validate(['first' => 'Posted', 'second' => 'Posted', 'third' => 'Posted']);

        expect($data)->not->toContain('Posted');
    });

    it('refuses to save a stored row that changed after the form was opened', function () {
        [$key] = array_keys(ChangingRowsForm::make(orderWithRows([['sku' => 'A-1', 'qty' => 1]]))->getData()['rows']);

        ChangingRowsForm::make(orderWithRows([['sku' => 'A-1', 'qty' => 5]]))
            ->validate(['rows' => [$key => ['qty' => 9]]]);
    })->throws(ValidationException::class, 'changed');

    it('refuses to save a stored row that was removed after the form was opened', function () {
        $rows = [['sku' => 'A-1', 'qty' => 1], ['sku' => 'B-2', 'qty' => 2]];
        [$removed, $kept] = array_keys(ChangingRowsForm::make(orderWithRows($rows))->getData()['rows']);

        ChangingRowsForm::make(orderWithRows([$rows[1]]))
            ->validate(['rows' => [$removed => ['qty' => 10], $kept => ['qty' => 20]]]);
    })->throws(ValidationException::class, 'changed');

    it('keeps the locked value of a stored row that did not change', function () {
        $rows = [['sku' => 'A-1', 'qty' => 1], ['sku' => 'B-2', 'qty' => 2]];
        $keys = array_keys(ChangingRowsForm::make(orderWithRows($rows))->getData()['rows']);

        $data = ChangingRowsForm::make(orderWithRows([$rows[1]]))
            ->validate(['rows' => [$keys[1] => ['sku' => 'Tampered', 'qty' => 20]]]);

        expect($data['rows'][0]['sku'])->toBe('B-2');
    });

    it('accepts rows added in the browser next to stored rows', function () {
        $data = ChangingRowsForm::make(orderWithRows([['sku' => 'A-1', 'qty' => 1]]))
            ->validate(['rows' => ['4113394f-daca-4eda-9704-69fe1013637d' => ['qty' => 3]]]);

        expect($data['rows'][0]['qty'])->toBe(3);
    });

    it('gives identical stored rows their own keys', function () {
        $row = ['sku' => 'A-1', 'qty' => 1];

        expect(ChangingRowsForm::make(orderWithRows([$row, $row]))->getData()['rows'])->toHaveCount(2);
    });

    it('does not treat a starter row that differs per request as changed', function () {
        ChangingDefaultsForm::$startsAt = '09:00';
        [$key] = array_keys(ChangingDefaultsForm::make()->getData()['slots']);

        ChangingDefaultsForm::$startsAt = '09:01';
        $data = ChangingDefaultsForm::make()->validate(['slots' => [$key => []]]);

        expect($data['slots'][0]['starts_at'])->toBe('09:01');
    });

    it('locks a chain of dependent fields of any length', function () {
        $posted = [];

        for ($step = 1; $step <= LockChainForm::DEPTH; $step++) {
            $posted['step'.$step] = 'Tampered';
        }

        expect(LockChainForm::make()->validate($posted))->toBe([]);
    });

    it('never shows a closure the posted value of a statically disabled field', function () {
        $form = ClosureReadsForm::make();
        ClosureReadsForm::$reads = [];

        $form->validate(['team_id' => '999']);

        expect(ClosureReadsForm::$reads)->not->toBeEmpty()->not->toContain('999');
    });

    it('keeps a null value of a locked field on an existing record', function () {
        $order = Order::query()->create(['customer' => 'Ada']);

        expect(NullLockedForm::make($order)->save(['customer' => 'Grace'])->fresh()->company)->toBeNull();
    });

    it('refuses to save a related row that was removed after the form was opened', function () {
        $order = Order::query()->create(['customer' => 'Ada']);
        $item = $order->items()->create(['product' => 'Licensed Desk', 'qty' => 1, 'price' => 5, 'subtotal' => 5]);
        $posted = LockedRelationRowsForm::make($order)->getData();

        $item->delete();

        LockedRelationRowsForm::make($order->fresh())->save(['data' => $posted]);
    })->throws(ValidationException::class, 'changed');

    it('gives no default closure the posted value of a hidden field', function () {
        $data = DefaultReadsHiddenForm::make()->validate([
            'internal_price' => 'Tampered',
            'items' => ['added' => ['internal_price' => 'Tampered']],
        ]);

        expect($data['price'])->toBeNull()
            ->and($data['items'][0]['price'])->toBeNull();
    });

    it('keeps the server order of rows that may not be reordered', function () {
        $form = FixedRowsForm::make();
        [$first, $second] = array_keys($form->getData()['items']);

        $data = $form->validate(['items' => [
            $second => ['qty' => 20],
            $first => ['qty' => 10],
        ]]);

        expect(array_column($data['items'], 'qty'))->toBe([10, 20]);
    });

    it('keeps added rows after the rows that may not be reordered', function () {
        $form = FixedRowsForm::make();
        [$first, $second] = array_keys($form->getData()['items']);

        $data = $form->validate(['items' => [
            'added' => ['qty' => 30],
            $second => ['qty' => 20],
            $first => ['qty' => 10],
        ]]);

        expect(array_column($data['items'], 'qty'))->toBe([10, 20, 30]);
    });

    it('restores a disabled value in a row that has no relationship', function () {
        $form = FixedRowsForm::make();
        [$first] = array_keys($form->getData()['items']);

        $data = $form->validate(['items' => [$first => ['sku' => 'Tampered', 'qty' => 5]]]);

        expect($data['items'][0]['sku'])->toBe('A-1');
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

    it('refuses a morph-to relationship', function () {
        expect(fn () => MorphSelectForm::make()->toSchema())->toThrow(LogicException::class);
    });

    it('rejects nested values instead of crashing', function (string $form) {
        expect(fn () => $form::make()->validate(['country_id' => [['x']], 'tags' => [['x']]]))
            ->toThrow(ValidationException::class);
    })->with([MultipleScopedRelationshipForm::class, AsyncSelectForm::class]);
});

class ScopedRecordTestForm extends Form
{
    public function schema(): array
    {
        return [TextInput::make('name')];
    }

    public function model(): ?string
    {
        return Country::class;
    }

    public function resolveRecord(int|string $key): Model
    {
        return Country::query()->where('name', 'Allowed')->findOrFail($key);
    }
}

it('finds a token\'s record through the form\'s own query', function () {
    $allowed = Country::create(['name' => 'Allowed']);
    $outside = Country::create(['name' => 'Outside']);
    $token = fn (Country $country): string => app(FormTokenEncrypter::class)->encrypt(
        new FormToken(ScopedRecordTestForm::class, $country->getKey(), null),
    );

    expect(app(FormTokenEncrypter::class)->resolveForm($token($allowed))->getRecord()->is($allowed))->toBeTrue();

    app(FormTokenEncrypter::class)->resolveForm($token($outside));
})->throws(ModelNotFoundException::class);

it('uses a named rate limiter as it is', function () {
    RateLimiter::for('xelqun-test', fn () => Limit::perMinute(1));
    config(['xelqun-forms.routes.throttle.search' => 'xelqun-test']);
    Route::setRoutes(new RouteCollection);
    require __DIR__.'/../../routes/xelqun-forms.php';
    app('router')->getRoutes()->refreshNameLookups();

    expect(app('router')->getRoutes()->getByName('xelqun.forms.search')->middleware())->toContain('throttle:xelqun-test');
});
