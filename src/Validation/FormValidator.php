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
    /**
     * Passes in which a lock may be released again. Enough for real forms, whose
     * locks settle quickly. After this they only grow, so prepare() always ends.
     */
    private const int RELEASING_PASSES = 5;

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

        $this->prepare($form, $data);

        $this->collect($form->getComponents(), true, null);

        Validator::make($form->getState(), $this->rules, [], $this->attributes)->validate();

        return $this->dehydrate($form);
    }

    /**
     * Load browser state into the form so server closures can trust what they
     * read: fields the browser has no say over get their server value back, and
     * computed values are recalculated. A field hidden in the browser with
     * visibleWhen() only loses its posted value, because the user turned it off.
     *
     * Whether a field is locked can depend on another field that is locked, in
     * any order, so this repeats from the posted data until the locks settle. A
     * field wrongly hidden by a tampered value gets its own posted value back.
     *
     * Static hidden() and disabled() are locked before any closure runs, so no
     * closure ever reads their posted value. A field locked by a closure can
     * only be found by running closures, so those may read its posted value on
     * the first pass and must not act on what they read. Only the result of
     * the final pass decides anything.
     *
     * @param  array<string, mixed>  $data
     */
    public function prepare(Form $form, array $data): void
    {
        $form->setData($data);

        $posted = $form->getData();
        $locks = $this->findLocks($form->getComponents(), [StatePath::ROOT => $posted], onlyStatic: true);
        $this->applyLocks($form, $posted, $locks);

        for ($pass = 0; ; $pass++) {
            $found = $this->findLocks($form->getComponents(), [StatePath::ROOT => $posted]);
            $next = $pass < self::RELEASING_PASSES ? $found : $locks + $found;

            if ($this->describeLocks($next) === $this->describeLocks($locks)) {
                return;
            }

            $locks = $next;
            $this->applyLocks($form, $posted, $locks);
        }
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
     * Find the fields the browser has no say over.
     *
     * A field a closure hides has no value in the browser, so if it shows but
     * nothing was posted for it, the server value stands in for one.
     *
     * @param  array<int, Component>  $components
     * @param  array<string, mixed>  $posted  The posted state, under the "data" key.
     * @return array<string, Field|null> State path => the field that gets its server
     *                                   value back, or null to drop the posted value.
     */
    private function findLocks(array $components, array $posted, bool $parentVisible = true, bool $onlyStatic = false): array
    {
        $locks = [];

        foreach ($components as $component) {
            $visible = $parentVisible && ($onlyStatic ? ! $component->isHiddenStatically() : $component->isVisibleInState());

            if (! $component instanceof Field) {
                $locks += $this->findLocks($component->getChildComponents(), $posted, $visible, $onlyStatic);

                continue;
            }

            $path = $component->getStatePath();

            if (! $visible) {
                $locks[$path] = $onlyStatic || $component->isHiddenOnServer() ? $component : null;

                continue;
            }

            $disabled = $onlyStatic ? $component->isDisabledStatically() : $component->isDisabledInState();
            $withheld = $component->canBeHiddenOnServer() && ! Arr::has($posted, $path);

            if (($disabled && $component->getComputed() === null) || $withheld) {
                $locks[$path] = $component;

                continue;
            }

            if ($component instanceof Repeater) {
                foreach ($component->getRows() as $rowComponents) {
                    $locks += $this->findLocks($rowComponents, $posted, true, $onlyStatic);
                }
            }
        }

        return $locks;
    }

    /**
     * What makes two sets of locks the same: the paths, and how each is locked.
     *
     * @param  array<string, Field|null>  $locks
     * @return array<string, bool>
     */
    private function describeLocks(array $locks): array
    {
        return array_map(fn (?Field $field): bool => $field !== null, $locks);
    }

    /**
     * Start again from the posted data with these locks in place. Every locked
     * value goes first, so a default closure can't read a posted one.
     *
     * @param  array<string, mixed>  $posted
     * @param  array<string, Field|null>  $locks
     */
    private function applyLocks(Form $form, array $posted, array $locks): void
    {
        $form->setData($posted);

        foreach (array_keys($locks) as $path) {
            $form->forgetStateValue($path);
        }

        foreach (array_filter($locks) as $path => $field) {
            $form->setStateValue($path, $field->getServerState());
        }

        $form->recomputeComputedFields();
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
