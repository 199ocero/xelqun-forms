import type { FormSchema, SchemaNode } from '../../resources/js/schema/types';

export function field(
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

export function schema(
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

export function orderSchema(): FormSchema {
    const template = [
        field('data.items.{row}.qty', 'text-input', { props: { numeric: true } }),
        field('data.items.{row}.price', 'text-input', { props: { numeric: true } }),
        field('data.items.{row}.subtotal', 'text-input', {
            disabled: true,
            computed: {
                src: 'qty * price',
                ast: {
                    op: '*',
                    args: [{ ref: 'data.items.{row}.qty' }, { ref: 'data.items.{row}.price' }],
                },
            },
        }),
    ];
    const rowNodes = JSON.parse(JSON.stringify(template).split('{row}').join('r1'));

    return schema(
        [
            field('data.type', 'radio'),
            field('data.company', 'text-input', {
                rules: {
                    visible: { op: 'eq', path: 'data.type', value: 'business' },
                    required: null,
                    disabled: null,
                },
            }),
            field('data.country', 'select', { live: { mode: 'change', ms: null } }),
            field('data.state', 'select', { deps: ['data.country'], props: { options: [] } }),
            field('data.items', 'repeater', {
                props: {
                    template,
                    rows: { r1: rowNodes },
                    newRow: { qty: 1, price: null, subtotal: 0 },
                    itemLabel: 'Item',
                },
            }),
            field('data.total', 'text-input', {
                computed: {
                    src: 'sum(items.*.subtotal)',
                    ast: { fn: 'sum', args: [{ ref: 'data.items.*.subtotal' }] },
                },
            }),
        ],
        {
            type: 'individual',
            company: null,
            country: null,
            state: null,
            items: { r1: { qty: 2, price: 5, subtotal: 0 } },
            total: 0,
        },
    );
}
