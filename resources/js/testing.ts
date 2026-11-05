import type { FormSchema, SchemaNode } from './schema/types';

/**
 * Hand-built schema for testing a renderer without a server, from
 * '@xelqun/forms/testing'. Pass it to XqForm in a mounted test.
 */
export function fieldNode(
    path: string,
    type = 'text-input',
    extra: Partial<SchemaNode> = {},
): SchemaNode {
    return {
        key: path,
        type,
        hidden: false,
        path,
        label: path.split('.').pop(),
        required: false,
        disabled: false,
        rules: { visible: null, required: null, disabled: null },
        computed: null,
        live: null,
        props: {},
        children: [],
        deps: [],
        ...extra,
    };
}

export function formSchema(
    components: SchemaNode[],
    data: Record<string, any> = {},
    token: string | null = 'token',
): FormSchema {
    return {
        $xq: 1,
        pkg: '0.1.0',
        id: 'test-form',
        token,
        endpoints: {
            update: '/_xelqun/forms/update',
            upload: '/_xelqun/forms/upload',
            search: '/_xelqun/forms/search',
        },
        state: { data },
        components,
    };
}
