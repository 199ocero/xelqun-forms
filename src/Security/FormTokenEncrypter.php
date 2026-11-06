<?php

namespace Xelqun\Forms\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Xelqun\Actions\Action;
use Xelqun\Forms\Exceptions\InvalidFormToken;
use Xelqun\Forms\Form;
use Xelqun\Forms\Validation\FormValidator;

final class FormTokenEncrypter
{
    public function __construct(private readonly StringEncrypter $encrypter) {}

    public function encrypt(FormToken $token): string
    {
        return $this->encrypter->encryptString((string) json_encode($token->toArray()));
    }

    public function decrypt(string $value): FormToken
    {
        try {
            $payload = json_decode($this->encrypter->decryptString($value), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw InvalidFormToken::make('the token could not be decrypted');
        }

        return FormToken::fromArray(is_array($payload) ? $payload : []);
    }

    /**
     * Reject a token that has expired or was issued to someone else.
     *
     * @param  int  $ttl  Minutes a token lasts. Zero or less means it never expires.
     */
    public function assertUsable(FormToken $token, int $ttl): void
    {
        if ($ttl > 0 && $token->issuedAt !== null && $token->issuedAt < time() - $ttl * 60) {
            throw InvalidFormToken::make('the token has expired, reload the page', 419);
        }

        if ((string) $token->userId !== (string) auth()->id()) {
            throw InvalidFormToken::make('the token belongs to another user');
        }
    }

    /**
     * Decrypt the token, check it and rebuild the form it describes.
     */
    public function resolveForm(string $value): Form
    {
        $token = $this->decrypt($value);

        if (! is_subclass_of($token->formClass, Form::class)) {
            throw InvalidFormToken::make('the token does not describe a form');
        }

        $this->assertUsable($token, (int) config('xelqun-forms.token_ttl', 720));

        /** @var class-string<Form> $formClass */
        $formClass = $token->formClass;
        $record = null;

        if ($token->recordKey !== null) {
            $record = (new $formClass(null, $token->context))->resolveRecord($token->recordKey);
        }

        $form = new $formClass($record, $token->context);

        if (! $form->authorize(auth()->user(), $record)) {
            throw InvalidFormToken::make('this action is unauthorized');
        }

        $form->fill();

        foreach ($token->actions as ['k' => $key, 's' => $state]) {
            $form = $this->enterAction($form, $key, $state);
        }

        return $form;
    }

    /**
     * Rebuild an action's modal the way opening it did, checks included, so
     * losing a permission closes every modal that depended on it.
     *
     * @param  array<string, mixed>  $state
     */
    private function enterAction(Form $form, string $key, array $state): Form
    {
        app(FormValidator::class)->prepare($form, $state);

        $action = $form->findComponent($key);

        if (! $action instanceof Action || ! $action->canRun()) {
            throw InvalidFormToken::make('this action is unavailable');
        }

        return $action->makeForm($state);
    }
}
