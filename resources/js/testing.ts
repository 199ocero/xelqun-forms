import { SUPPORTED_SCHEMA_VERSIONS } from './schema/types';
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
        $xq: SUPPORTED_SCHEMA_VERSIONS[SUPPORTED_SCHEMA_VERSIONS.length - 1],
        // Nothing in the browser reads pkg; it only names the PHP package in the payload.
        pkg: 'testing',
        id: 'test-form',
        token,
        endpoints: {
            update: '/_xelqun/forms/update',
            upload: '/_xelqun/forms/upload',
            search: '/_xelqun/forms/search',
            validate: '/_xelqun/forms/validate',
            action: '/_xelqun/forms/action',
        },
        state: { data },
        components,
    };
}
