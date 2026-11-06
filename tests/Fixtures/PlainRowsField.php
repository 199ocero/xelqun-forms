<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\ContainsRows;

/**
 * A row field from a plugin that implements the contract without Repeater.
 */
class PlainRowsField extends Field implements ContainsRows
{
    public function getType(): string
    {
        return 'acme/plain-rows';
    }

    public function getTemplateComponents(): array
    {
        return [];
    }

    public function getRowKeys(): array
    {
        return [];
    }

    public function getRowComponents(string $rowKey): array
    {
        return [];
    }

    public function getRows(): array
    {
        return [];
    }

    public function getRowPlaceholder(): string
    {
        return '{row}';
    }

    public function getNewRowState(): array
    {
        return [];
    }

    public function getItemLabel(): string
    {
        return 'Row';
    }

    public function normalizePostedRows(): void {}

    public function getRowModel(Model $parent): ?Model
    {
        return new Tag;
    }
}
