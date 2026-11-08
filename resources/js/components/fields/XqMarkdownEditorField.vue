<script setup lang="ts">
import {
    Bold,
    Code,
    Heading,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    LoaderCircle,
    Quote,
    Strikethrough,
    Table,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, nextTick, ref } from 'vue';
import { useField } from '../../composables/useField';
import { insertBlock, prefixLines, wrapSelection } from '../../core/markdown';
import type { TextEdit } from '../../core/markdown';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, spinnerClass, toolbarButtonClass } from '../../ui/classes';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { store, path, value, error, describedBy, required, disabled, readOnly, id, onBlur } =
    useField<string | null>(props.node);
const { t } = store;
const options = computed(() => props.node.props ?? {});
const textarea = ref<HTMLTextAreaElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const previewing = ref(false);
const preview = ref<string | null>(null);
const loadingPreview = ref(false);
const previewAction = computed(() =>
    props.node.children?.find((child) => child.slot === 'markdownPreview'),
);

type Edit = (text: string, start: number, end: number) => TextEdit;

const table = '| Column | Column |\n| ------ | ------ |\n| Cell   | Cell   |';

const buttons: Record<string, { label: () => string; icon: Component; edit?: Edit }> = {
    bold: {
        label: () => t('Bold'),
        icon: Bold,
        edit: (text, start, end) => wrapSelection(text, start, end, '**', '**', t('Text')),
    },
    italic: {
        label: () => t('Italic'),
        icon: Italic,
        edit: (text, start, end) => wrapSelection(text, start, end, '*', '*', t('Text')),
    },
    strike: {
        label: () => t('Strikethrough'),
        icon: Strikethrough,
        edit: (text, start, end) => wrapSelection(text, start, end, '~~', '~~', t('Text')),
    },
    link: {
        label: () => t('Link'),
        icon: LinkIcon,
        edit: (text, start, end) => wrapSelection(text, start, end, '[', '](https://)', t('Text')),
    },
    heading: {
        label: () => t('Heading'),
        icon: Heading,
        edit: (text, start, end) => prefixLines(text, start, end, '## '),
    },
    blockquote: {
        label: () => t('Quote'),
        icon: Quote,
        edit: (text, start, end) => prefixLines(text, start, end, '> '),
    },
    codeBlock: {
        label: () => t('Code block'),
        icon: Code,
        edit: (text, start, end) => wrapSelection(text, start, end, '```\n', '\n```'),
    },
    bulletList: {
        label: () => t('Bulleted list'),
        icon: List,
        edit: (text, start, end) => prefixLines(text, start, end, '- '),
    },
    orderedList: {
        label: () => t('Numbered list'),
        icon: ListOrdered,
        edit: (text, start, end) => prefixLines(text, start, end, '#'),
    },
    table: {
        label: () => t('Table'),
        icon: Table,
        edit: (text, _, end) => insertBlock(text, end, table),
    },
    attachFiles: { label: () => t('Attach a file'), icon: ImagePlus },
};

async function apply(edit: Edit): Promise<void> {
    const element = textarea.value;

    if (!element) {
        return;
    }

    const result = edit(value.value ?? '', element.selectionStart, element.selectionEnd);

    value.value = result.text;
    await nextTick();
    element.focus();
    element.setSelectionRange(result.selectionStart, result.selectionEnd);
}

function press(name: string): void {
    const edit = buttons[name]?.edit;

    if (edit) {
        void apply(edit);
    } else if (name === 'attachFiles') {
        fileInput.value?.click();
    }
}

async function attach(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (file) {
        const { token } = await store.upload(path, file, () => {});

        await apply((text, _, end) => insertBlock(text, end, `![${file.name}](${token})`));
    }
}

async function showPreview(): Promise<void> {
    previewing.value = true;

    if (previewAction.value) {
        loadingPreview.value = true;

        const response = await store.runAction(previewAction.value);

        preview.value = (response?.result as string | undefined) ?? null;
        loadingPreview.value = false;
    }
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <div
            :class="
                cn(
                    controlClass,
                    'focus-within:border-xq-ring focus-within:ring-[3px] focus-within:ring-xq-ring/25',
                )
            "
        >
            <div class="gap-1 p-1 flex flex-wrap items-center border-b border-xq-border">
                <template v-if="!disabled">
                    <div
                        v-for="(group, index) in options.toolbar ?? []"
                        :key="index"
                        class="gap-0.5 flex"
                    >
                        <template v-for="name in group" :key="name">
                            <button
                                v-if="buttons[name]"
                                type="button"
                                :class="toolbarButtonClass"
                                :aria-label="buttons[name].label()"
                                :title="buttons[name].label()"
                                :disabled="previewing"
                                :data-xq-tool="name"
                                @click="press(name)"
                            >
                                <component
                                    :is="buttons[name].icon"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </template>
                    </div>
                </template>
                <div v-if="previewAction && !disabled" class="gap-1 ms-auto flex">
                    <button
                        type="button"
                        class="px-2 py-1 text-xs cursor-pointer rounded-xq-sm hover:bg-xq-accent aria-pressed:bg-xq-accent"
                        :aria-pressed="!previewing"
                        @click="previewing = false"
                    >
                        {{ t('Write') }}
                    </button>
                    <button
                        type="button"
                        class="px-2 py-1 text-xs cursor-pointer rounded-xq-sm hover:bg-xq-accent aria-pressed:bg-xq-accent"
                        :aria-pressed="previewing"
                        data-xq-markdown-preview
                        @click="showPreview"
                    >
                        {{ t('Preview') }}
                    </button>
                </div>
            </div>
            <textarea
                v-show="!previewing"
                :id="id"
                ref="textarea"
                :value="value ?? ''"
                :readonly="readOnly"
                :disabled="disabled && !readOnly"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :placeholder="store.text(node, 'placeholder') ?? undefined"
                :style="{ minHeight: options.minHeight ?? '10rem', maxHeight: options.maxHeight }"
                class="px-3 py-2 text-sm font-mono block w-full resize-y bg-transparent outline-none"
                @input="value = ($event.target as HTMLTextAreaElement).value"
                @blur="onBlur"
            />
            <div
                v-if="previewing"
                class="px-3 py-2 text-sm"
                :style="{ minHeight: options.minHeight ?? '10rem' }"
            >
                <LoaderCircle v-if="loadingPreview" :class="spinnerClass" aria-hidden="true" />
                <!-- MarkdownEditor::render() strips raw HTML and cleans the result on the server. -->
                <div v-else-if="preview !== null" class="xq-rich-content" v-html="preview" />
                <p v-else class="text-xq-muted-foreground">
                    {{ t('Could not load the preview.') }}
                </p>
            </div>
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
