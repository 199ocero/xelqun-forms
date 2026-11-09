<script setup lang="ts">
import { Image } from '@tiptap/extension-image';
import { Subscript } from '@tiptap/extension-subscript';
import { Superscript } from '@tiptap/extension-superscript';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import type { Editor as CoreEditor } from '@tiptap/core';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    BetweenHorizontalEnd,
    BetweenVerticalEnd,
    Blocks,
    Bold,
    Braces,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Heading4,
    Heading5,
    Heading6,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Minus,
    Palette,
    PanelTop,
    Quote,
    Redo,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Table,
    TableCellsMerge,
    TableCellsSplit,
    Trash2,
    Underline,
    Undo,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode, SelectOption } from '../../schema/types';
import { controlClass, optionClass, popoverClass, toolbarButtonClass } from '../../ui/classes';
import XqPopover from '../../ui/XqPopover.vue';
import XqAction from '../XqAction.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { customBlockNode, mentionExtension } from './richEditorExtensions';
import type { MentionMenu, MentionProviderOptions } from './richEditorExtensions';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, label, describedBy, required, disabled, id, onBlur } = useField<
    string | Record<string, unknown> | null
>(props.node);
const { t } = store;
const options = computed(() => props.node.props ?? {});
const fileInput = ref<HTMLInputElement | null>(null);
const objectUrls: string[] = [];

/** Holds the upload's token until the server stores it and writes the real address. */
const UploadedImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            upload: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-xq-upload'),
                renderHTML: (attributes) =>
                    attributes.upload ? { 'data-xq-upload': attributes.upload } : {},
            },
        };
    },
});

const isJson = !!options.value.json;
const previews = ref<Record<string, string>>({ ...options.value.blockPreviews });
const blockLabels = () =>
    Object.fromEntries(
        (options.value.blocks ?? []).map((block: { id: string; label: string }) => [
            block.id,
            block.label,
        ]),
    );
const mentionMenu = ref<MentionMenu | null>(null);
const mentionProviders: MentionProviderOptions[] = options.value.mentions ?? [];
const blockActions = computed(
    () => props.node.children?.filter((child) => child.slot === 'richBlock') ?? [],
);
const blockActionRefs: Array<InstanceType<typeof XqAction> | null> = [];
const openMenu = ref<string | null>(null);

/** What the editor holds, in the form the field keeps. */
function contentOf(current: CoreEditor): string | Record<string, unknown> | null {
    if (current.isEmpty) {
        return null;
    }

    return isJson ? current.getJSON() : current.getHTML();
}

const sameContent = (left: unknown, right: unknown) =>
    (isJson ? JSON.stringify(left ?? null) : (left ?? '')) ===
    (isJson ? JSON.stringify(right ?? null) : (right ?? ''));

const editor = useEditor({
    content: (value.value as any) ?? '',
    editable: !disabled.value,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false } }),
        UploadedImage,
        Subscript,
        Superscript,
        TableKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        ...(options.value.textColors ? [TextStyle, Color] : []),
        ...(options.value.blocks ? [customBlockNode(previews, blockLabels)] : []),
        ...(mentionProviders.length
            ? [
                  mentionExtension(
                      mentionProviders,
                      (char, query) => store.search(path, `${char}${query}`),
                      mentionMenu,
                  ),
              ]
            : []),
    ],
    editorProps: {
        attributes: {
            class: 'xq-rich-content px-3 py-2 text-sm',
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': label.value ?? '',
            'aria-describedby': describedBy.value ?? '',
            style: `min-height: ${options.value.minHeight ?? '10rem'}`,
        },
    },
    onUpdate: ({ editor: current }) => {
        value.value = contentOf(current);
    },
    onBlur: () => onBlur(),
});

watch(value, (next) => {
    const current = editor.value;

    if (current && !sameContent(next, contentOf(current))) {
        current.commands.setContent((next as any) ?? '', { emitUpdate: false });
    }
});

watch(disabled, (isDisabled) => editor.value?.setEditable(!isDisabled));

interface Tool {
    label: () => string;
    icon: Component;
    run: (editor: Editor) => void;
    active?: (editor: Editor) => boolean;
    /** Shown only while this is true, as table tools are inside a table. */
    shown?: (editor: Editor) => boolean;
    /** Opens a list to choose from instead of running. */
    menu?: boolean;
}

function tableTool(label: () => string, icon: Component, command: string): Tool {
    return {
        label,
        icon,
        run: (current) => (chain(current) as any)[command]().run(),
        shown: (current) => current.isActive('table'),
    };
}

function menuTool(label: () => string, icon: Component): Tool {
    return { label, icon, run: () => {}, menu: true };
}

function chain(editor: Editor) {
    return editor.chain().focus();
}

function heading(level: 1 | 2 | 3 | 4 | 5 | 6, icon: Component): Tool {
    return {
        label: () => t('Heading :level', { level }),
        icon,
        run: (current) => chain(current).toggleHeading({ level }).run(),
        active: (current) => current.isActive('heading', { level }),
    };
}

function mark(name: string, label: () => string, icon: Component, toggle: string): Tool {
    return {
        label,
        icon,
        run: (current) => (chain(current) as any)[toggle]().run(),
        active: (current) => current.isActive(name),
    };
}

function align(side: string, label: () => string, icon: Component): Tool {
    return {
        label,
        icon,
        run: (current) => chain(current).setTextAlign(side).run(),
        active: (current) => current.isActive({ textAlign: side }),
    };
}

// shortcut: a browser prompt for the address, swap in a popover when someone asks for one.
function editLink(current: Editor): void {
    const url = window.prompt(t('Link address'), current.getAttributes('link').href ?? 'https://');

    if (url === null) {
        return;
    }

    if (url === '') {
        chain(current).unsetLink().run();
    } else {
        chain(current).extendMarkRange('link').setLink({ href: url }).run();
    }
}

const tools: Record<string, Tool> = {
    bold: mark('bold', () => t('Bold'), Bold, 'toggleBold'),
    italic: mark('italic', () => t('Italic'), Italic, 'toggleItalic'),
    underline: mark('underline', () => t('Underline'), Underline, 'toggleUnderline'),
    strike: mark('strike', () => t('Strikethrough'), Strikethrough, 'toggleStrike'),
    subscript: mark('subscript', () => t('Subscript'), SubscriptIcon, 'toggleSubscript'),
    superscript: mark('superscript', () => t('Superscript'), SuperscriptIcon, 'toggleSuperscript'),
    link: {
        label: () => t('Link'),
        icon: LinkIcon,
        run: editLink,
        active: (current) => current.isActive('link'),
    },
    h1: heading(1, Heading1),
    h2: heading(2, Heading2),
    h3: heading(3, Heading3),
    h4: heading(4, Heading4),
    h5: heading(5, Heading5),
    h6: heading(6, Heading6),
    alignStart: align('left', () => t('Align left'), AlignLeft),
    alignCenter: align('center', () => t('Align center'), AlignCenter),
    alignEnd: align('right', () => t('Align right'), AlignRight),
    alignJustify: align('justify', () => t('Justify'), AlignJustify),
    blockquote: mark('blockquote', () => t('Quote'), Quote, 'toggleBlockquote'),
    codeBlock: mark('codeBlock', () => t('Code block'), Code, 'toggleCodeBlock'),
    bulletList: mark('bulletList', () => t('Bulleted list'), List, 'toggleBulletList'),
    orderedList: mark('orderedList', () => t('Numbered list'), ListOrdered, 'toggleOrderedList'),
    horizontalRule: {
        label: () => t('Divider'),
        icon: Minus,
        run: (current) => chain(current).setHorizontalRule().run(),
    },
    table: {
        label: () => t('Table'),
        icon: Table,
        run: (current) =>
            chain(current).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    },
    tableAddRowBefore: tableTool(() => t('Add a row above'), BetweenHorizontalEnd, 'addRowBefore'),
    tableAddRowAfter: tableTool(() => t('Add a row below'), BetweenHorizontalEnd, 'addRowAfter'),
    tableAddColumnBefore: tableTool(
        () => t('Add a column before'),
        BetweenVerticalEnd,
        'addColumnBefore',
    ),
    tableAddColumnAfter: tableTool(
        () => t('Add a column after'),
        BetweenVerticalEnd,
        'addColumnAfter',
    ),
    tableDeleteRow: tableTool(() => t('Delete the row'), Minus, 'deleteRow'),
    tableDeleteColumn: tableTool(() => t('Delete the column'), Minus, 'deleteColumn'),
    tableMergeCells: tableTool(() => t('Merge cells'), TableCellsMerge, 'mergeCells'),
    tableSplitCell: tableTool(() => t('Split the cell'), TableCellsSplit, 'splitCell'),
    tableToggleHeaderRow: tableTool(() => t('Header row'), PanelTop, 'toggleHeaderRow'),
    tableToggleHeaderCell: tableTool(() => t('Header cell'), PanelTop, 'toggleHeaderCell'),
    tableDelete: tableTool(() => t('Delete the table'), Trash2, 'deleteTable'),
    textColor: menuTool(() => t('Text color'), Palette),
    mergeTags: menuTool(() => t('Insert a merge tag'), Braces),
    customBlocks: menuTool(() => t('Insert a block'), Blocks),
    attachFiles: {
        label: () => t('Attach a file'),
        icon: ImagePlus,
        run: () => fileInput.value?.click(),
    },
    undo: { label: () => t('Undo'), icon: Undo, run: (current) => chain(current).undo().run() },
    redo: { label: () => t('Redo'), icon: Redo, run: (current) => chain(current).redo().run() },
};

async function attach(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (file) {
        const { token } = await store.upload(path, file, () => {});
        const src = URL.createObjectURL(file);

        objectUrls.push(src);
        editor.value
            ?.chain()
            .focus()
            .setImage({ src, alt: file.name, upload: token } as any)
            .run();
    }
}

function setColor(color: string | null): void {
    openMenu.value = null;

    if (editor.value) {
        const next = chain(editor.value);

        (color === null ? next.unsetColor() : next.setColor(color)).run();
    }
}

function insertMergeTag(name: string): void {
    openMenu.value = null;
    editor.value?.chain().focus().insertContent(`{{ ${name} }}`).run();
}

function chooseBlock(index: number): void {
    openMenu.value = null;
    void blockActionRefs[index]?.open();
}

function insertBlock(result: unknown): void {
    const block = result as { id: string; config: string; preview: string } | null;

    if (!block) {
        return;
    }

    previews.value[`${block.id}\n${block.config}`] = block.preview;
    editor.value
        ?.chain()
        .focus()
        .insertContent({ type: 'customBlock', attrs: { id: block.id, config: block.config } })
        .run();
}

const menuItems = computed<Record<string, SelectOption[]>>(() => ({
    textColor: Object.entries(options.value.textColors ?? {}).map(([color, name]) => ({
        value: color,
        label: String(name),
    })),
    mergeTags: Object.entries(options.value.mergeTags ?? {}).map(([name, tagLabel]) => ({
        value: name,
        label: String(tagLabel),
    })),
    customBlocks: (options.value.blocks ?? []).map(
        (block: { id: string; label: string }, index: number) => ({
            value: index,
            label: block.label,
        }),
    ),
}));

function chooseFromMenu(name: string, item: SelectOption): void {
    if (name === 'textColor') {
        setColor(String(item.value));
    } else if (name === 'mergeTags') {
        insertMergeTag(String(item.value));
    } else {
        chooseBlock(Number(item.value));
    }
}

onBeforeUnmount(() => objectUrls.forEach((url) => URL.revokeObjectURL(url)));
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <div
            :id="id"
            :class="
                cn(
                    controlClass,
                    'focus-within:border-xq-ring focus-within:ring-[3px] focus-within:ring-xq-ring/25',
                    disabled && 'opacity-60',
                )
            "
        >
            <div
                v-if="editor && !disabled"
                class="gap-1 p-1 flex flex-wrap items-center border-b border-xq-border"
                role="toolbar"
                :aria-label="label ?? undefined"
            >
                <div
                    v-for="(group, index) in options.toolbar ?? []"
                    :key="index"
                    class="gap-0.5 flex"
                >
                    <template v-for="name in group" :key="name">
                        <XqPopover
                            v-if="tools[name]?.menu"
                            :open="openMenu === name"
                            class="p-1 min-w-40"
                            @update:open="(isOpen) => (openMenu = isOpen ? name : null)"
                        >
                            <template #trigger>
                                <button
                                    type="button"
                                    :class="toolbarButtonClass"
                                    :aria-label="tools[name].label()"
                                    :title="tools[name].label()"
                                    :data-xq-tool="name"
                                >
                                    <component
                                        :is="tools[name].icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </template>
                            <div role="menu" :aria-label="tools[name].label()">
                                <button
                                    v-for="item in menuItems[name]"
                                    :key="String(item.value)"
                                    type="button"
                                    role="menuitem"
                                    :class="cn(optionClass, 'hover:bg-xq-accent')"
                                    :data-xq-menu-item="item.value"
                                    @click="chooseFromMenu(name, item)"
                                >
                                    <span
                                        v-if="name === 'textColor'"
                                        class="size-3 rounded-full"
                                        :style="{ backgroundColor: String(item.value) }"
                                        aria-hidden="true"
                                    />
                                    {{ item.label }}
                                </button>
                                <button
                                    v-if="name === 'textColor'"
                                    type="button"
                                    role="menuitem"
                                    :class="cn(optionClass, 'hover:bg-xq-accent')"
                                    @click="setColor(null)"
                                >
                                    {{ t('Default color') }}
                                </button>
                            </div>
                        </XqPopover>
                        <button
                            v-else-if="tools[name] && (tools[name].shown?.(editor) ?? true)"
                            type="button"
                            :class="toolbarButtonClass"
                            :aria-label="tools[name].label()"
                            :aria-pressed="tools[name].active?.(editor) ?? undefined"
                            :title="tools[name].label()"
                            :data-xq-tool="name"
                            @click="tools[name].run(editor)"
                        >
                            <component :is="tools[name].icon" class="size-4" aria-hidden="true" />
                        </button>
                    </template>
                </div>
            </div>
            <EditorContent :editor="editor" />
            <XqAction
                v-for="(action, index) in blockActions"
                :key="action.key"
                :ref="(element: any) => (blockActionRefs[index] = element)"
                :node="action"
                headless
                @result="insertBlock"
            />
            <Teleport to="body">
                <div
                    v-if="mentionMenu && mentionMenu.items.length"
                    role="listbox"
                    :class="cn(popoverClass, 'p-1 min-w-40 fixed')"
                    :style="{
                        top: `${(mentionMenu.rect?.bottom ?? 0) + 4}px`,
                        left: `${mentionMenu.rect?.left ?? 0}px`,
                    }"
                    data-xq-mentions
                >
                    <button
                        v-for="(item, index) in mentionMenu.items"
                        :key="String(item.value)"
                        type="button"
                        role="option"
                        :aria-selected="index === mentionMenu.active"
                        :class="cn(optionClass, index === mentionMenu.active && 'bg-xq-accent')"
                        @mousedown.prevent="mentionMenu.choose(item)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </Teleport>
            <input
                ref="fileInput"
                type="file"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                :accept="options.accept"
                @change="attach"
            />
        </div>
    </XqFieldWrapper>
</template>
