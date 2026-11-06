<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Forms\Layouts\Step;
use Xelqun\Forms\Live\UpdateProcessor;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Validation\FormValidator;

/**
 * Check one wizard step against the whole form's state. Nothing is saved or
 * remembered, so this only spares the user a failed submit.
 */
class ValidateController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens, FormValidator $validator, UpdateProcessor $processor): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'step' => ['required', 'string', 'max:500'],
            'state' => ['present', 'array'],
            'deps' => ['present', 'array', 'max:2000'],
            'deps.*' => ['array', 'max:100'],
        ]);

        $form = $tokens->resolveForm($validated['token']);
        $data = $validated['state']['data'] ?? [];
        $data = is_array($data) ? $data : [];

        $validator->prepare($form, $data);

        $step = $form->findComponent($validated['step']);

        abort_unless($step instanceof Step && $step->isShown(), 404);

        $step->callBeforeValidation();

        $errors = $validator->errorsIn($form, $step);

        if ($errors === []) {
            $step->callAfterValidation();
        }

        return response()->json($processor->buildPatch(
            $form,
            $data,
            [],
            array_map(fn (array $paths): array => array_values(array_filter($paths, 'is_string')), $validated['deps']),
            0,
            $errors,
        ));
    }
}
