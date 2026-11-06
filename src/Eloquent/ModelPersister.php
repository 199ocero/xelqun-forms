<?php

namespace Xelqun\Forms\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\ContainsRows;
use Xelqun\Forms\Contracts\PersistsBeforeSave;
use Xelqun\Forms\Contracts\PersistsItself;
use Xelqun\Forms\Form;
use Xelqun\Forms\Uploads\UploadBatch;

/**
 * Writes validated form data to a model and its relationships.
 */
final class ModelPersister
{
    /**
     * @template TModel of Model
     *
     * @param  Form<TModel>  $form
     * @param  array<string, mixed>  $data  Validated, row-keyed data.
     * @return TModel
     */
    public function save(Form $form, array $data, UploadBatch $uploads): Model
    {
        $connection = $form->getConnection();

        return $connection->transaction(function () use ($form, $data, $uploads, $connection): Model {
            $uploads->settleWithTransaction($connection);

            $recordBeforeSave = $form->getRecord() === null ? null : clone $form->getRecord();
            $connection->afterRollBack(fn () => $form->setRecord($recordBeforeSave));

            $record = $form->getRecord() ?? $form->getModelInstance();

            $form->beforeSave($data);
            $this->persistScope($record, $data, $form->getComponents(), $form->mutateBeforeSave(...));
            $form->afterSave($record, $data);

            // The save stands, so a failure here must not look like a failed save and invite a second one.
            $connection->afterCommit(fn () => rescue(fn () => $form->afterCommit($record, $data)));

            return $record;
        });
    }

    /**
     * Save one record and everything below it, depth-first. Nothing here treats
     * a missing key as "clear this": a hidden component has no key in $data.
     *
     * @param  array<string, mixed>  $data  The validated data of this record.
     * @param  array<int, Component>  $components
     * @param  (Closure(array<string, mixed>): (array<string, mixed>|null))|null  $mutate  Adjusts the attributes. Returning null leaves the record unsaved.
     * @return bool Whether the record was saved.
     */
    public function persistScope(Model $record, array $data, array $components, ?Closure $mutate = null): bool
    {
        $selfPersisting = [];
        $attributes = [];

        foreach ($components as $component) {
            $this->collect($component, $data, $attributes, $selfPersisting);
        }

        $attributes = $mutate === null ? $attributes : $mutate($attributes);

        if ($attributes === null) {
            return false;
        }

        foreach ($selfPersisting as $component) {
            if ($component instanceof PersistsBeforeSave) {
                $component->persistBeforeSave($record, $data[$component->getName()]);
            }
        }

        $record->forceFill($attributes)->save();

        foreach ($selfPersisting as $component) {
            $component->persist($record, $data[$component->getName()]);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  array<int, PersistsItself>  $selfPersisting
     */
    private function collect(Component $component, array $data, array &$attributes, array &$selfPersisting): void
    {
        if ($component instanceof PersistsItself && $component->shouldPersistItself()) {
            if (array_key_exists($component->getName(), $data)) {
                $selfPersisting[] = $component;
            }

            return;
        }

        if ($component instanceof Field) {
            if (array_key_exists($component->getName(), $data)) {
                $value = $data[$component->getName()];
                $attributes[$component->getName()] = $component instanceof ContainsRows ? $component->toPlainRows((array) $value) : $value;
            }

            return;
        }

        foreach ($component->getChildComponents() as $child) {
            $this->collect($child, $data, $attributes, $selfPersisting);
        }
    }
}
