<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Tests\Fixtures\OrderForm;

function scopeTokensTo(?string $scope, ?Closure $verify = null): void
{
    app(FormTokenEncrypter::class)->scopeUsing(
        fn (): ?string => $scope,
        $verify ?? function (?string $scope): void {},
    );
}

it('leaves tokens without a scope when nothing asked for one', function () {
    $tokens = app(FormTokenEncrypter::class);

    expect($tokens->decrypt($tokens->encrypt(new FormToken('Form', null, null)))->scope)->toBeNull();
});

it('stamps a token however it was built, including one rebuilt for a modal', function () {
    scopeTokensTo('admin');
    $tokens = app(FormTokenEncrypter::class);

    $token = $tokens->decrypt($tokens->encrypt(new FormToken('Form', null, null)));
    $modalToken = $tokens->decrypt($tokens->encrypt($token->withAction('apply', [])));

    expect($token->scope)->toBe('admin')
        ->and($modalToken->scope)->toBe('admin');
});

it('passes the scope to the check before comparing the user, so the check can change the guard', function () {
    $seen = [];

    scopeTokensTo('admin', function (?string $scope) use (&$seen): void {
        $seen[] = $scope;
        $this->actingAs((new User)->forceFill(['id' => 5]));
    });

    $tokens = app(FormTokenEncrypter::class);
    $form = $tokens->resolveForm($tokens->encrypt(new FormToken(OrderForm::class, null, 5)));

    expect($form)->toBeInstanceOf(OrderForm::class)
        ->and($seen)->toBe(['admin']);
});

it('refuses a token when the check refuses', function () {
    scopeTokensTo('admin', fn () => abort(403));
    $tokens = app(FormTokenEncrypter::class);

    $tokens->resolveForm($tokens->encrypt(new FormToken(OrderForm::class, null, null)));
})->throws(HttpException::class);

it('tells a signed out user to reload, and a different user no', function (bool $signedIn, int $status) {
    $this->actingAs((new User)->forceFill(['id' => 5]));
    $token = OrderForm::make()->makeToken();

    if ($signedIn) {
        $this->actingAs((new User)->forceFill(['id' => 6]));
    } else {
        auth()->logout();
    }

    $this->postJson(route('xelqun.forms.update'), ['token' => $token, 'seq' => 1, 'state' => ['data' => []], 'changed' => [], 'deps' => (object) []])
        ->assertStatus($status);
})->with([
    'signed out' => [false, 419],
    'another user' => [true, 403],
]);

it('keeps what a form posted out of the session when validation fails', function () {
    Route::post('save', fn () => request()->validate(['data.password' => 'integer']))->middleware('web');

    $this->post('save', ['data' => ['password' => 'hunter2'], 'other' => 'kept'])
        ->assertSessionHasErrors('data.password')
        ->assertSessionHasInput('other')
        ->assertSessionMissing('_old_input.data');
});
