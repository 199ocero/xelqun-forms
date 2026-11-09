import { Node } from '@tiptap/core';
import { Mention } from '@tiptap/extension-mention';
import type { SuggestionKeyDownProps, SuggestionProps } from '@tiptap/suggestion';
import type { Ref } from 'vue';
import type { SelectOption } from '../../schema/types';

export interface MentionProviderOptions {
    char: string;
    remote: boolean;
    items: SelectOption[];
}

/** The open mention list, for the editor to draw. Null while none is open. */
export interface MentionMenu {
    items: SelectOption[];
    active: number;
    rect: DOMRect | null;
    choose: (item: SelectOption) => void;
}

/**
 * A custom block's placeholder: its id and its config as written, which also
 * finds its preview. The server drew the preview, so it's shown as HTML.
 */
export function customBlockNode(
    previews: Ref<Record<string, string>>,
    labels: () => Record<string, string>,
) {
    return Node.create({
        name: 'customBlock',
        group: 'block',
        atom: true,
        draggable: true,
        addAttributes() {
            return {
                id: { default: '', parseHTML: (element) => element.getAttribute('data-id') ?? '' },
                config: {
                    default: '{}',
                    parseHTML: (element) => element.getAttribute('data-config') ?? '{}',
                },
            };
        },
        parseHTML() {
            return [{ tag: 'div[data-type="customBlock"]' }];
        },
        renderHTML({ node }) {
            return [
                'div',
                {
                    'data-type': 'customBlock',
                    'data-id': node.attrs.id,
                    'data-config': node.attrs.config,
                },
            ];
        },
        addNodeView() {
            return ({ node }) => {
                const dom = document.createElement('div');
                const preview = previews.value[`${node.attrs.id}\n${node.attrs.config}`];

                dom.className =
                    'my-2 p-3 rounded-xq-md border border-dashed border-xq-border bg-xq-muted/40';
                dom.dataset.xqRichBlock = node.attrs.id;
                dom.contentEditable = 'false';

                if (preview !== undefined) {
                    // The block's own toPreviewHtml(), which the docs say to escape.
                    dom.innerHTML = preview;
                } else {
                    dom.textContent = labels()[node.attrs.id] ?? node.attrs.id;
                }

                return { dom };
            };
        },
    });
}

/**
 * Mentions for each provider. The list itself is drawn by the editor from
 * the menu ref, so it looks like the rest of the form.
 */
export function mentionExtension(
    providers: MentionProviderOptions[],
    search: (char: string, query: string) => Promise<SelectOption[]>,
    menu: Ref<MentionMenu | null>,
) {
    return Mention.configure({
        renderText: ({ node }) =>
            `${node.attrs.mentionSuggestionChar}${node.attrs.label ?? node.attrs.id}`,
        suggestions: providers.map((provider) => ({
            char: provider.char,
            items: async ({ query }: { query: string }) => {
                if (provider.remote) {
                    return search(provider.char, query);
                }

                const term = query.toLocaleLowerCase();

                return provider.items
                    .filter((item) => item.label.toLocaleLowerCase().includes(term))
                    .slice(0, 8);
            },
            render: () => {
                let current: SuggestionProps<SelectOption> | null = null;

                const show = (props: SuggestionProps<SelectOption>) => {
                    current = props;
                    menu.value = {
                        items: props.items,
                        active: 0,
                        rect: props.clientRect?.() ?? null,
                        choose: (item) =>
                            props.command({ id: String(item.value), label: item.label } as any),
                    };
                };

                return {
                    onStart: show,
                    onUpdate: show,
                    onKeyDown: ({ event }: SuggestionKeyDownProps) => {
                        const open = menu.value;

                        if (!open || !current) {
                            return false;
                        }

                        if (event.key === 'Escape') {
                            menu.value = null;

                            return true;
                        }

                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            const step = event.key === 'ArrowDown' ? 1 : -1;

                            open.active =
                                (open.active + step + open.items.length) %
                                Math.max(open.items.length, 1);

                            return true;
                        }

                        if (event.key === 'Enter' && open.items[open.active]) {
                            open.choose(open.items[open.active]);

                            return true;
                        }

                        return false;
                    },
                    onExit: () => {
                        current = null;
                        menu.value = null;
                    },
                };
            },
        })),
    });
}
