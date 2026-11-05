<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Security\FormTokenEncrypter;

class SearchController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'path' => ['required', 'string', 'max:500'],
            'search' => ['nullable', 'string', 'max:200'],
            'state' => ['nullable', 'array'],
        ]);

        $form = $tokens->resolveForm($validated['token']);
        $data = $validated['state']['data'] ?? null;

        if (is_array($data)) {
            $form->setData($data);
        }

        $field = $form->findComponent($validated['path']);

        abort_unless($field instanceof Select && $field->isSearchable() && $field->acceptsInput(), 404);

        return response()->json(['options' => $field->getSearchResults((string) ($validated['search'] ?? ''))]);
    }
}
