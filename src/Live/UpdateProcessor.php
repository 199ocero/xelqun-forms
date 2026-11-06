<?php

namespace Xelqun\Forms\Live;

use Illuminate\Support\Arr;
use stdClass;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\ContainsRows;
use Xelqun\Forms\Form;
use Xelqun\Forms\Schema\SchemaVersion;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Forms\Validation\FormValidator;

/**
 * Handles one live round-trip: run hooks for the changed paths, re-run only
 * the closures that depend on what changed, and build a patch.
 */
final class UpdateProcessor
{
    public function __construct(private readonly FormValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data  The browser's current form data.
     * @param  array<int, string>  $changed  Paths the user changed.
     * @param  array<string, array<int, string>>  $dependencies  Node key => paths its closures read.
     * @return array<string, mixed>
     */
    public function process(Form $form, array $data, array $changed, array $dependencies, int $sequence): array
    {
        $this->validator->prepare($form, $data);

        foreach ($changed as $path) {
            $component = $form->findComponent($path);

            if ($component instanceof Field && $component->hasAfterStateUpdatedHooks() && $component->acceptsInput()) {
                $component->callAfterStateUpdated();
            }
        }

        $setPaths = $form->getSetPaths();
        $triggers = array_values(array_unique([...$changed, ...$setPaths]));
        $schema = [];
        $revealed = [];

        foreach ($this->affectedKeys($form, $triggers, $setPaths, $dependencies) as $key) {
            $component = $form->findComponent($key);

            // The browser names these keys, and its parent's patch brings a child back.
            if ($component === null || $component->getParent()?->isHiddenOnServer()) {
                continue;
            }

            $node = $component->toNode();

            if ($node !== null) {
                $schema[$key] = $node;
                array_push($revealed, ...$this->findRevealedPaths($component, $data));
            }
        }

        $set = [];

        foreach (array_unique([...$setPaths, ...$revealed]) as $path) {
            $component = $form->findComponent($path);

            if ($component instanceof Field && $component->isHiddenOnServer()) {
                continue;
            }

            $value = $form->getStateValue($path);
            $set[$path] = $value === [] && $component instanceof ContainsRows ? new stdClass : $value;
        }

        return [
            '$xq' => SchemaVersion::CURRENT,
            'seq' => $sequence,
            'set' => $set === [] ? new stdClass : $set,
            'schema' => $schema === [] ? new stdClass : $schema,
            'errors' => new stdClass,
        ];
    }

    /**
     * A field hidden on the server has no value in the browser, so the moment
     * it shows, the patch has to bring its value along.
     *
     * @param  array<string, mixed>  $data  The browser's current form data.
     * @return array<int, string>
     */
    private function findRevealedPaths(Component $component, array $data): array
    {
        $paths = [];
        $posted = [StatePath::ROOT => $data];

        Form::eachField([$component], function (Field $field) use ($posted, &$paths): void {
            $path = $field->getStatePath();

            if (! Arr::has($posted, $path)) {
                $paths[] = $path;
            }
        });

        return $paths;
    }

    /**
     * @param  array<int, string>  $triggers
     * @param  array<int, string>  $setPaths
     * @param  array<string, array<int, string>>  $dependencies
     * @return array<int, string>
     */
    private function affectedKeys(Form $form, array $triggers, array $setPaths, array $dependencies): array
    {
        $keys = [];

        foreach ($dependencies as $key => $paths) {
            foreach ($paths as $path) {
                foreach ($triggers as $trigger) {
                    if (StatePath::overlaps($path, $trigger)) {
                        $keys[] = (string) $key;

                        continue 3;
                    }
                }
            }
        }

        if ($setPaths !== []) {
            $form->walk(function (Component $component) use ($setPaths, &$keys): void {
                if (! $component instanceof ContainsRows) {
                    return;
                }

                foreach ($setPaths as $path) {
                    $replacesRows = substr_count($path, '.') <= substr_count($component->getStatePath(), '.');

                    if ($replacesRows && StatePath::overlaps($component->getStatePath(), $path)) {
                        $keys[] = $component->getKey();

                        return;
                    }
                }
            });
        }

        return array_values(array_unique($keys));
    }
}
