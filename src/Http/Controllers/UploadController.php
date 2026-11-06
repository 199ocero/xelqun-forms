<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Contracts\AcceptsUploads;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Uploads\TemporaryUploadStore;
use Xelqun\Forms\Validation\FormValidator;

class UploadController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens, TemporaryUploadStore $store, FormValidator $validator): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        $form = $tokens->resolveForm($request->string('token')->value());
        $state = json_decode($request->string('state')->value(), true);

        // A row of a builder needs its posted type to know which block's field this is.
        if (is_array($state)) {
            $validator->prepare($form, $state);
        }

        $field = $form->findComponent($request->string('path')->value());

        if (! $field instanceof AcceptsUploads || ! $field->acceptsInput()) {
            throw ValidationException::withMessages(['file' => __('xelqun-forms::messages.uploads.not_accepted')]);
        }

        $request->validate(['file' => $field->getUploadRules()], [], ['file' => $field->getValidationAttribute()]);

        return response()->json($store->store($request->file('file'), $field->getUploadScope()));
    }
}
