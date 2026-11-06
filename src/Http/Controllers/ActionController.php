<?php

namespace Xelqun\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Actions\Action;
use Xelqun\Forms\Live\UpdateProcessor;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Validation\FormValidator;

/**
 * Open an action's modal, or run the action. Both go the same way: rebuild the
 * form, trust nothing the browser sent for locked fields, find an action the
 * form declares, and check it. The order matters, because each step depends on
 * the one before it.
 */
class ActionController
{
    public function __invoke(Request $request, FormTokenEncrypter $tokens, FormValidator $validator, UpdateProcessor $processor): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'action' => ['required', 'string', 'max:500'],
            'phase' => ['required', 'in:mount,run'],
            'state' => ['present', 'array'],
            'data' => ['nullable', 'array'],
            'deps' => ['present', 'array', 'max:2000'],
            'deps.*' => ['array', 'max:100'],
        ]);

        $form = $tokens->resolveForm($validated['token']);
        $state = $validated['state']['data'] ?? [];
        $state = is_array($state) ? $state : [];

        $validator->prepare($form, $state);

        $action = $form->findComponent($validated['action']);

        abort_unless($action instanceof Action, 404);
        abort_unless($action->canRun(), 403);

        if ($validated['phase'] === 'mount') {
            return response()->json(['form' => $action->hasModalForm() ? $action->makeForm($state)->toSchema() : null]);
        }

        $result = $form->getConnection()->transaction(fn () => $action->run($state, $validated['data'] ?? []));

        return response()->json([
            ...$processor->buildPatch(
                $form,
                $state,
                [],
                array_map(fn (array $paths): array => array_values(array_filter($paths, 'is_string')), $validated['deps']),
                0,
            ),
            'result' => $action->hasResult() ? $result : null,
            'redirect' => $action->getSuccessRedirectUrl(),
        ]);
    }
}
