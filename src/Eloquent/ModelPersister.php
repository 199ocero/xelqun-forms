<?php

namespace Xelqun\Forms\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\ContainsRows;
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
            $selfPersisting = [];
            $attributes = [];

            foreach ($form->getComponents() as $component) {
                $this->collectTopLevel($component, $data, $attributes, $selfPersisting);
            }

            $record->forceFill($form->mutateBeforeSave($attributes))->save();

            foreach ($selfPersisting as $field) {
                $field->persist($record, $data[$field->getName()]);
            }

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  array<int, PersistsItself>  $selfPersisting
     */
    private function collectTopLevel(Component $component, array $data, array &$attributes, array &$selfPersisting): void
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
                $attributes[$component->getName()] = $component instanceof ContainsRows ? array_values((array) $value) : $value;
            }

            return;
        }

        foreach ($component->getChildComponents() as $child) {
            $this->collectTopLevel($child, $data, $attributes, $selfPersisting);
        }
    }
}
