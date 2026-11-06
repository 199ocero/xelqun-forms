/**
 * TypeScript mirror of the JSON schema contract produced by the PHP package.
 * Bump SUPPORTED_SCHEMA_VERSIONS together with Xelqun\Forms\Schema\SchemaVersion.
 */
export const SUPPORTED_SCHEMA_VERSIONS = [1] as const;

export type ConditionNode =
    | { op: 'and' | 'or' | 'not'; args: ConditionNode[] }
    | {
          op: 'eq' | 'neq' | 'in' | 'notIn' | 'gt' | 'gte' | 'lt' | 'lte';
          path: string;
          value: unknown;
      }
    | { op: 'filled' | 'blank'; path: string };

export interface ConditionalText {
    when: ConditionNode;
    text: string;
    otherwise: string | null;
}

export type TextKey = 'label' | 'hint' | 'placeholder';

export type ExpressionNode =
    | { lit: unknown }
    | { ref: string }
    | { op: string; args: ExpressionNode[] }
    | { fn: string; args: ExpressionNode[] };

export type LiveMode = 'change' | 'blur' | 'debounce';

export interface LiveConfig {
    mode: LiveMode;
    ms: number | null;
}

export type Breakpoint = 'default' | 'sm' | 'md' | 'lg' | 'xl';

export type ColumnSpan = number | 'full' | Partial<Record<Breakpoint, number | 'full'>> | null;

export interface SchemaNode {
    key: string;
    type: string;
    hidden: boolean;
    path?: string | null;
    label?: string | null;
    hint?: string | null;
    placeholder?: string | null;
    required?: boolean;
    disabled?: boolean;
    /** The server ignores this field's value. Text controls stay readable, others render disabled. */
    readOnly?: boolean;
    autofocus?: boolean;
    hiddenLabel?: boolean;
    inlineLabel?: boolean;
    extraAttributes?: Record<string, unknown>;
    rules?: {
        visible?: ConditionNode | null;
        required?: ConditionNode | null;
        disabled?: ConditionNode | null;
        text?: Partial<Record<TextKey, ConditionalText>>;
    };
    computed?: { src: string; ast: ExpressionNode } | null;
    live?: LiveConfig | null;
    /** Set by fields with rows. They need props.template, rowPlaceholder, rows and newRow. */
    containsRows?: boolean;
    span?: ColumnSpan;
    props?: Record<string, any>;
    children?: SchemaNode[];
    deps?: string[];
}

export interface FormEndpoints {
    update: string;
    upload: string;
    search: string;
}

export interface FormSchema {
    $xq: number;
    pkg: string;
    id: string;
    token: string | null;
    endpoints: FormEndpoints;
    state: { data: Record<string, any> };
    components: SchemaNode[];
}

export interface UpdateRequest {
    token: string;
    seq: number;
    state: { data: Record<string, any> };
    changed: string[];
    deps: Record<string, string[]>;
}

export interface UpdatePatch {
    $xq: number;
    seq: number;
    set: Record<string, unknown>;
    schema: Record<string, Partial<SchemaNode>>;
    errors: Record<string, string | string[]>;
}

export interface SelectOption {
    value: string | number;
    label: string;
    description?: string | null;
    disabled?: boolean;
    /** The group this option is listed under. */
    group?: string;
}

/** Text a select shows in place of its defaults. */
export interface SelectMessages {
    loading?: string;
    noOptions?: string;
    noResults?: string;
    prompt?: string;
    searching?: string;
}

export interface UploadResult {
    token: string;
    name: string;
    size: number;
    mime: string | null;
}
