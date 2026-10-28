<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Forms\Live\UpdateProcessor;
use Xelqun\Forms\Security\FormTokenEncrypter;

class UpdateController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens, UpdateProcessor $processor): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'seq' => ['required', 'integer', 'min:0'],
            'state' => ['present', 'array'],
            'changed' => ['present', 'array', 'max:100'],
            'changed.*' => ['string', 'max:500'],
            'deps' => ['present', 'array', 'max:2000'],
            'deps.*' => ['array'],
        ]);

        $form = $tokens->resolveForm($validated['token']);
        $data = $validated['state']['data'] ?? [];

        return response()->json($processor->process(
            $form,
            is_array($data) ? $data : [],
            array_values($validated['changed']),
            array_map(fn (array $paths): array => array_values(array_filter($paths, 'is_string')), $validated['deps']),
            (int) $validated['seq'],
        ));
    }
}
