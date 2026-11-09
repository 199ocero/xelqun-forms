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
    /** Set by fields that hold a file, which a copied row can't share. */
    acceptsUploads?: boolean;
    span?: ColumnSpan;
    /** False keeps a child of a Flex at its own width. */
    grow?: boolean;
    /** Rules the browser can check, on forms with frontendValidation(). */
    validation?: FieldValidation | null;
    /** The place around its field, or in its repeater row's header, this child renders in. */
    slot?: FieldSlot | 'itemAction' | 'modalFooter';
    props?: Record<string, any>;
    children?: SchemaNode[];
    deps?: string[];
}

/** One kind of row in a builder. */
export interface BlockOption {
    name: string;
    label: string;
    icon: string | null;
    group: string | null;
    maxItems: number | null;
    template: SchemaNode[];
    newRow: Record<string, unknown>;
}

export interface ClientRule {
    rule: 'required' | 'accepted' | 'email' | 'numeric' | 'integer' | 'min' | 'max' | 'size';
    type?: 'string' | 'numeric';
    value?: number;
    message: string;
}

export interface FieldValidation {
    attribute: string;
    rules: ClientRule[];
}

export type FieldSlot =
    | 'aboveLabel'
    | 'beforeLabel'
    | 'afterLabel'
    | 'belowLabel'
    | 'aboveContent'
    | 'beforeContent'
    | 'afterContent'
    | 'belowContent'
    | 'aboveErrorMessage'
    | 'belowErrorMessage'
    | 'prefixAction'
    | 'suffixAction'
    | 'optionAction'
    | 'markdownPreview';

export interface FormEndpoints {
    update: string;
    upload: string;
    search: string;
    validate: string;
    action: string;
}

export interface FormSchema {
    $xq: number;
    pkg: string;
    id: string;
    token: string | null;
    endpoints: FormEndpoints;
    locale?: string;
    /** Text in this locale, by its English original. Anything missing stays English. */
    messages?: Record<string, string>;
    unsavedChangesAlert?: boolean;
    disabled?: boolean;
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

export interface ValidateRequest {
    token: string;
    step: string;
    state: { data: Record<string, any> };
    deps: Record<string, string[]>;
}

export interface ActionRequest {
    token: string;
    action: string;
    phase: 'mount' | 'run';
    state: { data: Record<string, any> };
    data?: Record<string, any>;
    deps: Record<string, string[]>;
}

export interface UpdatePatch {
    $xq: number;
    seq: number;
    set: Record<string, unknown>;
    schema: Record<string, Partial<SchemaNode>>;
    errors: Record<string, string | string[]>;
    /** Left out when there are none. */
    notifications?: XqNotification[];
}

export interface XqNotification {
    title?: string | null;
    body?: string | null;
    status?: 'success' | 'danger' | 'warning' | 'info' | null;
    icon?: string | null;
    /** In milliseconds. */
    duration?: number | null;
    persistent?: boolean;
    actions?: XqNotificationAction[];
    closeLabel?: string;
}

export interface XqNotificationAction {
    label: string;
    icon?: string | null;
    color?: string | null;
    /** Close the notification once the button has done its work. */
    close: boolean;
    url?: string;
    openInNewTab?: boolean;
    run?: { endpoint: string; token: string; action: string };
    failureMessage?: string;
}

export interface ActionResult extends UpdatePatch {
    result?: unknown;
    /** Where the action sends the user, in place of staying on the page. */
    redirect?: string | null;
    /** The action stopped itself and wants its modal to stay open. */
    halted?: boolean;
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
