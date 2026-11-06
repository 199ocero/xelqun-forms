<script setup lang="ts">
import { Image } from '@tiptap/extension-image';
import { Subscript } from '@tiptap/extension-subscript';
import { Superscript } from '@tiptap/extension-superscript';
import { TableKit } from '@tiptap/extension-table';
import { TextAlign } from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
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
    Quote,
    Redo,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Table,
    Underline,
    Undo,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, toolbarButtonClass } from '../../ui/classes';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, label, describedBy, required, disabled, id, onBlur } = useField<
    string | null
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

const editor = useEditor({
    content: value.value ?? '',
    editable: !disabled.value,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false } }),
        UploadedImage,
        Subscript,
        Superscript,
        TableKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
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
        value.value = current.isEmpty ? null : current.getHTML();
    },
    onBlur: () => onBlur(),
});

watch(value, (next) => {
    const current = editor.value;

    if (current && (next ?? '') !== (current.isEmpty ? '' : current.getHTML())) {
        current.commands.setContent(next ?? '', { emitUpdate: false });
    }
});

watch(disabled, (isDisabled) => editor.value?.setEditable(!isDisabled));

interface Tool {
    label: () => string;
    icon: Component;
    run: (editor: Editor) => void;
    active?: (editor: Editor) => boolean;
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
                        <button
                            v-if="tools[name]"
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
