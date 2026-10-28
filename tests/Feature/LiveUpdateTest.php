<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Crypt;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Tests\Fixtures\Country;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\Region;

beforeEach(function () {
    $this->philippines = Country::create(['name' => 'Philippines']);
    $this->japan = Country::create(['name' => 'Japan']);
    Region::create(['country_id' => $this->philippines->id, 'name' => 'Cebu']);
    Region::create(['country_id' => $this->japan->id, 'name' => 'Osaka']);

    $this->schema = OrderForm::make()->toSchema();
});

function updatePayload(array $schema, array $data, array $changed, int $seq = 1): array
{
    return [
        'token' => $schema['token'],
        'seq' => $seq,
        'state' => ['data' => $data],
        'changed' => $changed,
        'deps' => collect($schema['components'])->mapWithKeys(fn (array $node) => [$node['key'] => $node['deps']])->all(),
    ];
}

it('re-runs dependent closures and runs hooks for changed paths', function () {
    $data = ['customer' => 'Ada', 'type' => 'individual', 'country_id' => $this->japan->id, 'region_id' => 5, 'items' => []];

    $response = $this->postJson('/_xelqun/forms/update', updatePayload($this->schema, $data, ['data.country_id'], 7));

    $response->assertOk()
        ->assertJsonPath('seq', 7)
        ->assertJsonPath('set', ['data.region_id' => null]);

    $schema = $response->json('schema');

    expect(array_keys($schema))->toBe(['data.region_id'])
        ->and($schema['data.region_id']['props']['options'])->toBe([['value' => 2, 'label' => 'Osaka']]);
});

it('does not re-run closures that did not depend on the change', function () {
    $data = ['customer' => 'Ada', 'type' => 'individual', 'country_id' => null, 'region_id' => null, 'items' => []];

    $this->postJson('/_xelqun/forms/update', updatePayload($this->schema, $data, ['data.customer']))
        ->assertOk()
        ->assertExactJson(['$xq' => 1, 'seq' => 1, 'set' => [], 'schema' => [], 'errors' => []]);
});

it('rejects tampered tokens', function () {
    $payload = updatePayload($this->schema, [], ['data.customer']);
    $payload['token'] = 'tampered'.$payload['token'];

    $this->postJson('/_xelqun/forms/update', $payload)->assertForbidden();
});

it('rejects tokens that do not describe a form', function () {
    $token = app(FormTokenEncrypter::class)->encrypt(new FormToken(User::class, null, null));
    $payload = updatePayload($this->schema, [], []);
    $payload['token'] = $token;

    $this->postJson('/_xelqun/forms/update', $payload)->assertForbidden();
});

it('rejects tokens issued to another user', function () {
    $payload = updatePayload($this->schema, [], []);
    $payload['token'] = Crypt::encryptString(json_encode(['f' => OrderForm::class, 'r' => null, 'u' => 42, 'c' => [], 't' => time()]));

    $this->postJson('/_xelqun/forms/update', $payload)->assertForbidden();
});

it('rejects expired tokens with 419', function () {
    $payload = updatePayload($this->schema, [], []);
    $payload['token'] = Crypt::encryptString(json_encode(['f' => OrderForm::class, 'r' => null, 'u' => null, 'c' => [], 't' => time() - 999999]));

    $this->postJson('/_xelqun/forms/update', $payload)->assertStatus(419);
});
