<script setup lang="ts">
import { EditorState } from '@codemirror/state';
import type { Extension } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { basicSetup } from 'codemirror';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass } from '../../ui/classes';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, label, error, describedBy, required, disabled, id, onBlur } = useField<
    string | null
>(props.node);
const options = computed(() => props.node.props ?? {});
const host = ref<HTMLElement | null>(null);
let view: EditorView | null = null;

/** Each language is its own chunk, so a form only downloads the one it shows. */
const languages: Record<string, () => Promise<Extension>> = {
    cpp: async () => (await import('@codemirror/lang-cpp')).cpp(),
    css: async () => (await import('@codemirror/lang-css')).css(),
    go: async () => (await import('@codemirror/lang-go')).go(),
    html: async () => (await import('@codemirror/lang-html')).html(),
    java: async () => (await import('@codemirror/lang-java')).java(),
    javascript: async () => (await import('@codemirror/lang-javascript')).javascript(),
    json: async () => (await import('@codemirror/lang-json')).json(),
    markdown: async () => (await import('@codemirror/lang-markdown')).markdown(),
    php: async () => (await import('@codemirror/lang-php')).php(),
    python: async () => (await import('@codemirror/lang-python')).python(),
    sql: async () => (await import('@codemirror/lang-sql')).sql(),
    xml: async () => (await import('@codemirror/lang-xml')).xml(),
    yaml: async () => (await import('@codemirror/lang-yaml')).yaml(),
};

onMounted(async () => {
    const language = await languages[options.value.language]?.();

    if (!host.value) {
        return;
    }

    view = new EditorView({
        parent: host.value,
        state: EditorState.create({
            doc: value.value ?? '',
            extensions: [
                basicSetup,
                language ?? [],
                options.value.wrap ? EditorView.lineWrapping : [],
                EditorState.readOnly.of(disabled.value),
                EditorView.editable.of(!disabled.value),
                EditorView.contentAttributes.of({
                    'aria-label': label.value ?? '',
                    'aria-describedby': describedBy.value ?? '',
                    id,
                }),
                EditorView.domEventHandlers({ blur: () => onBlur() }),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        const text = update.state.doc.toString();

                        value.value = text === '' ? null : text;
                    }
                }),
            ],
        }),
    });
});

watch(value, (next) => {
    const text = next ?? '';

    if (view && view.state.doc.toString() !== text) {
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: text } });
    }
});

onBeforeUnmount(() => view?.destroy());
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :label-for="null" :error="error" :required="required">
        <div
            ref="host"
            :class="cn(controlClass, 'overflow-hidden', disabled && 'opacity-60')"
            :style="{ minHeight: options.minHeight ?? undefined }"
        />
    </XqFieldWrapper>
</template>
