<?php

namespace Xelqun\Forms\Live;

use stdClass;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Form;
use Xelqun\Forms\Schema\SchemaVersion;
use Xelqun\Forms\Support\StatePath;

/**
 * Handles one live round-trip: run hooks for the changed paths, re-run only
 * the closures that depend on what changed, and build a patch.
 */
final class UpdateProcessor
{
    /**
     * @param  array<string, mixed>  $data  The browser's current form data.
     * @param  array<int, string>  $changed  Paths the user changed.
     * @param  array<string, array<int, string>>  $dependencies  Node key => paths its closures read.
     * @return array<string, mixed>
     */
    public function process(Form $form, array $data, array $changed, array $dependencies, int $sequence): array
    {
        $form->setData($data);

        foreach ($changed as $path) {
            $component = $form->findComponent($path);

            if ($component instanceof Field && $component->hasAfterStateUpdatedHooks() && $component->acceptsInput()) {
                $component->callAfterStateUpdated();
            }
        }

        $setPaths = $form->getSetPaths();
        $triggers = array_values(array_unique([...$changed, ...$setPaths]));
        $schema = [];

        foreach ($this->affectedKeys($form, $triggers, $setPaths, $dependencies) as $key) {
            $component = $form->findComponent($key);

            if ($component === null) {
                continue;
            }

            $node = $component->toNode();

            if ($node !== null) {
                $schema[$key] = $node;
            }
        }

        $set = [];

        foreach ($setPaths as $path) {
            $value = $form->getStateValue($path);
            $set[$path] = $value === [] && $form->findComponent($path) instanceof Repeater ? new stdClass : $value;
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
                if (! $component instanceof Repeater) {
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
