<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

class UploadController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens, TemporaryUploadStore $store): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'path' => ['required', 'string', 'max:500'],
        ]);

        $form = $tokens->resolveForm($request->string('token')->value());
        $field = $form->findComponent($request->string('path')->value());

        if (! $field instanceof FileUpload) {
            throw ValidationException::withMessages(['file' => 'This field does not accept uploads.']);
        }

        $request->validate(['file' => $field->getUploadRules()], [], ['file' => $field->getLabel()]);

        return response()->json($store->store($request->file('file')));
    }
}
