<script setup lang="ts">
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { Compartment, EditorState } from '@codemirror/state';
import type { Extension } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { tags } from '@lezer/highlight';
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
const editable = new Compartment();

/**
 * Colors from the theme tokens, which change in dark mode, so the editor follows
 * the page. CodeMirror's own theme is light only.
 */
const theme = [
    EditorView.theme({
        '&': { backgroundColor: 'transparent', color: 'var(--xq-foreground)' },
        '&.cm-focused': { outline: 'none' },
        '.cm-content': { caretColor: 'var(--xq-foreground)' },
        '.cm-cursor, .cm-dropCursor': { borderLeftColor: 'var(--xq-foreground)' },
        '.cm-gutters': {
            backgroundColor: 'var(--xq-muted)',
            color: 'var(--xq-muted-foreground)',
            borderRight: '1px solid var(--xq-border)',
        },
        '.cm-activeLine': {
            backgroundColor: 'color-mix(in oklab, var(--xq-accent) 60%, transparent)',
        },
        '.cm-activeLineGutter': {
            backgroundColor: 'var(--xq-accent)',
            color: 'var(--xq-foreground)',
        },
        '&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground, .cm-selectionBackground':
            { backgroundColor: 'color-mix(in oklab, var(--xq-primary) 30%, transparent)' },
        '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': {
            backgroundColor: 'color-mix(in oklab, var(--xq-primary) 20%, transparent)',
        },
    }),
    syntaxHighlighting(
        HighlightStyle.define([
            {
                tag: [tags.keyword, tags.operatorKeyword, tags.modifier],
                color: 'var(--xq-primary)',
            },
            {
                tag: [tags.string, tags.special(tags.string), tags.regexp],
                color: 'var(--xq-success)',
            },
            { tag: [tags.number, tags.bool, tags.null, tags.atom], color: 'var(--xq-warning)' },
            { tag: tags.comment, color: 'var(--xq-muted-foreground)', fontStyle: 'italic' },
            {
                tag: [
                    tags.function(tags.variableName),
                    tags.function(tags.propertyName),
                    tags.typeName,
                    tags.className,
                ],
                color: 'var(--xq-info)',
            },
            {
                tag: [tags.tagName, tags.attributeName, tags.propertyName],
                color: 'var(--xq-destructive)',
            },
        ]),
    ),
];

function editability(isDisabled: boolean): Extension {
    return [EditorState.readOnly.of(isDisabled), EditorView.editable.of(!isDisabled)];
}

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
                theme,
                language ?? [],
                options.value.wrap ? EditorView.lineWrapping : [],
                editable.of(editability(disabled.value)),
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

// disabledWhen() and live updates can switch it after the editor exists.
watch(disabled, (isDisabled) =>
    view?.dispatch({ effects: editable.reconfigure(editability(isDisabled)) }),
);

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
