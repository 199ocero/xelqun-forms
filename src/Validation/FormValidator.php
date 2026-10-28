<?php

namespace Xelqun\Forms\Validation;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Form;
use Xelqun\Forms\Support\StatePath;

/**
 * Server-side authority for submitted data.
 *
 * Re-checks every condition, drops values of hidden and disabled fields,
 * recomputes computed values, validates the row-keyed state (so error keys
 * match field paths) and dehydrates the result.
 */
final class FormValidator
{
    /** @var array<string, array<int, mixed>> */
    private array $rules = [];

    /** @var array<string, string> */
    private array $attributes = [];

    /** @var array<int, Field> */
    private array $kept = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(Form $form, array $data): array
    {
        $this->rules = [];
        $this->attributes = [];
        $this->kept = [];

        $form->setData($data);
        $form->recomputeComputedFields();

        $this->collect($form->getComponents(), true, null);

        Validator::make($form->getState(), $this->rules, [], $this->attributes)->validate();

        return $this->dehydrate($form);
    }

    /**
     * Convert row-keyed repeater state into plain lists, deepest repeaters first.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toPlainData(Form $form, array $data): array
    {
        $state = [StatePath::ROOT => $data];
        $form->setData($data);
        $paths = [];

        $form->walk(function (Component $component) use (&$paths): void {
            if ($component instanceof Repeater) {
                $paths[] = $component->getStatePath();
            }
        });

        usort($paths, fn (string $first, string $second): int => substr_count($second, '.') <=> substr_count($first, '.'));

        foreach ($paths as $path) {
            $rows = data_get($state, $path);

            if (is_array($rows)) {
                data_set($state, $path, array_values($rows));
            }
        }

        return $state[StatePath::ROOT];
    }

    /**
     * @param  array<int, Component>  $components
     */
    private function collect(array $components, bool $parentVisible, ?string $rowLabel): void
    {
        foreach ($components as $component) {
            $visible = $parentVisible && $component->isVisibleInState();

            if (! $component instanceof Field) {
                $this->collect($component->getChildComponents(), $visible, $rowLabel);

                continue;
            }

            if (! $visible || ! $component->isDehydrated()) {
                continue;
            }

            $path = $component->getStatePath();
            $label = $component->getLabel();
            $this->rules[$path] = $component->getValidationRules();
            $this->attributes[$path] = $rowLabel === null ? $label : "{$label} ({$rowLabel})";
            $this->kept[] = $component;

            if ($component instanceof Repeater) {
                $index = 0;

                foreach ($component->getRows() as $rowComponents) {
                    $index++;
                    $this->collect($rowComponents, true, $component->getItemLabel().' '.$index);
                }
            }
        }
    }

    /**
     * Build the output from known, visible, dehydrated fields only, so keys
     * that are not in the schema can never reach the model.
     *
     * @return array<string, mixed>
     */
    private function dehydrate(Form $form): array
    {
        $state = $form->getState();
        $output = [StatePath::ROOT => []];

        foreach ($this->kept as $field) {
            $path = $field->getStatePath();

            if ($field instanceof Repeater) {
                data_set($output, $path, array_fill_keys($field->getRowKeys(), []));

                continue;
            }

            if (Arr::has($state, $path)) {
                data_set($output, $path, $field->dehydrateState(data_get($state, $path)));
            }
        }

        return $output[StatePath::ROOT];
    }
}
