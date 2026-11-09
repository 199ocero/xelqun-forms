<?php

namespace Xelqun\Forms\Components\RichEditor;

use Xelqun\Actions\Action;

/**
 * A block people insert into a rich editor, such as a hero or a call to action.
 * The editor keeps only its id and the values from its modal; your app turns
 * them into HTML when it shows the content, through RichContentRenderer.
 *
 * @api
 */
abstract class CustomBlock
{
    abstract public static function getId(): string;

    abstract public static function getLabel(): string;

    /**
     * Give the action a schema() to ask for values when the block is inserted.
     */
    public static function configureEditorAction(Action $action): Action
    {
        return $action;
    }

    /**
     * What the block looks like inside the editor. It isn't cleaned, so escape
     * every value from $config. The server checks $config against the modal's
     * rules on every save, but content saved some other way wasn't checked.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        return e(static::getLabel());
    }

    /**
     * What the block becomes on the page. It isn't cleaned, so escape every
     * value from $config. $data is what you passed to the renderer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    abstract public static function toHtml(array $config, array $data): string;
}
