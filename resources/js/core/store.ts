import { router } from '@inertiajs/vue3';
import { effectScope, nextTick, reactive, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type {
    ConditionNode,
    FormSchema,
    LiveConfig,
    SchemaNode,
    SelectOption,
    TextKey,
    UpdatePatch,
    UpdateRequest,
    UploadResult,
} from '../schema/types';
import { SUPPORTED_SCHEMA_VERSIONS } from '../schema/types';
import { evaluateCondition } from './conditions';
import { evaluateExpression } from './expressions';
import { HttpError, postJson, uploadFile } from './http';
import { eachNode, mergeNode } from './patch';
import { clone, deletePath, getPath, overlaps, replaceRow, setPath, startsWithPath } from './paths';
import { RequestLog } from './log';
import { RequestQueue } from './queue';
import { newRowKey } from './rows';
import { isPlainObject } from './values';

export type SetSource = 'user' | 'server' | 'computed';

/**
 * How long a request runs before its spinner appears, so fast ones never flash.
 * The same 200ms as Livewire's wire:loading.delay.
 */
export const LOADING_INDICATOR_DELAY = 200;

export interface SubmitOptions {
    method?: 'post' | 'put' | 'patch';
    data?: Record<string, unknown>;
    preserveState?: boolean | 'errors';
    onSuccess?: (page: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
}

export interface FormStoreOptions {
    send?: (url: string, request: UpdateRequest, signal: AbortSignal) => Promise<UpdatePatch>;
}

const TEXT_TYPES = ['text-input', 'textarea'];

export function defaultLiveConfig(type: string | undefined): LiveConfig {
    return TEXT_TYPES.includes(type ?? '')
        ? { mode: 'debounce', ms: 400 }
        : { mode: 'change', ms: null };
}

function prefersReducedMotion(): boolean {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

export type FormStore = ReturnType<typeof createFormStore>;

/**
 * The client-side form engine: reactive state, node index, conditions,
 * computed values, repeater rows, live updates and submit.
 */
export function createFormStore(schema: FormSchema, options: FormStoreOptions = {}) {
    const scope = effectScope(true);
    const versionError = SUPPORTED_SCHEMA_VERSIONS.includes(schema.$xq as 1)
        ? null
        : `This page uses Xelqun Forms schema v${schema.$xq}, but the installed @xelqun/forms supports v${SUPPORTED_SCHEMA_VERSIONS.join(', v')}. Update the npm package to match the PHP package.`;

    if (versionError) {
        console.error(`[xelqun] ${versionError}`);
    }

    const components = reactive(clone(schema.components)) as SchemaNode[];
    const state = reactive({
        data: isPlainObject(schema.state?.data) ? clone(schema.state.data) : {},
    });
    const errors = reactive<Record<string, string>>({});
    const nodes = new Map<string, SchemaNode>();
    const effects = new Map<string, () => void>();
    const editVersions = new Map<string, number>();
    const processing = ref(false);
    const fatalError: Ref<string | null> = ref(versionError);
    const liveError: Ref<string | null> = ref(null);
    const focusRequest = ref<{ path: string; at: number } | null>(null);
    const root: Ref<HTMLElement | null> = ref(null);
    const uploads = new Set<Promise<unknown>>();
    const uploading = ref(0);

    function get(path: string): any {
        return getPath(state, path);
    }

    function set(path: string, value: unknown, source: SetSource = 'user'): void {
        if (source !== 'user' && Object.is(get(path), value)) {
            return;
        }

        setPath(state, path, value);

        if (source === 'user') {
            editVersions.set(path, (editVersions.get(path) ?? 0) + 1);
            delete errors[path];
        }

        if (source !== 'server') {
            notify(path, source);
        }
    }

    function liveConfigFor(path: string, source: SetSource): LiveConfig | null {
        if (!schema.token) {
            return null;
        }

        const node = nodes.get(path);
        const explicit = node?.live ?? null;
        let isDependency = false;

        for (const candidate of nodes.values()) {
            if (candidate.deps?.some((dependency) => overlaps(dependency, path))) {
                isDependency = true;
                break;
            }
        }

        if (!explicit && !isDependency) {
            return null;
        }

        if (source === 'computed') {
            return { mode: 'debounce', ms: 300 };
        }

        return explicit ?? defaultLiveConfig(node?.type);
    }

    function notify(path: string, source: SetSource): void {
        const live = liveConfigFor(path, source);

        if (live) {
            queue.notify(path, live);
        }
    }

    function blur(path: string): void {
        queue.blur(path);
    }

    function condition(node: ConditionNode | null | undefined, fallback: boolean): boolean {
        return node ? evaluateCondition(node, get) : fallback;
    }

    function isVisible(node: SchemaNode): boolean {
        return !node.hidden && condition(node.rules?.visible, true);
    }

    function isRequired(node: SchemaNode): boolean {
        return !!node.required || condition(node.rules?.required, false);
    }

    function isDisabled(node: SchemaNode): boolean {
        return !!node.disabled || condition(node.rules?.disabled, false);
    }

    /**
     * The label, hint or placeholder to show, following any browser-side condition.
     */
    function text(node: SchemaNode, key: TextKey): string | null {
        const rule = node.rules?.text?.[key];

        if (!rule) {
            return node[key] ?? null;
        }

        return evaluateCondition(rule.when, get) ? rule.text : rule.otherwise;
    }

    function index(list: SchemaNode[]): void {
        eachNode(list, (node) => {
            nodes.set(node.key, node);

            if (node.computed && node.path && !effects.has(node.key)) {
                const path = node.path;

                scope.run(() => {
                    effects.set(
                        node.key,
                        watch(
                            () =>
                                node.computed
                                    ? evaluateExpression(node.computed.ast, get)
                                    : get(path),
                            (value) => set(path, value, 'computed'),
                            { immediate: true },
                        ),
                    );
                });
            }

            if (node.containsRows && node.path && !effects.has(`${node.key}::rows`)) {
                const path = node.path;

                scope.run(() => {
                    effects.set(
                        `${node.key}::rows`,
                        watch(
                            () => Object.keys(get(path) ?? {}),
                            (keys) => syncRows(node, keys),
                            { immediate: true },
                        ),
                    );
                });
            }
        });
    }

    function unindex(list: SchemaNode[]): void {
        eachNode(list, (node) => {
            nodes.delete(node.key);

            for (const key of [node.key, `${node.key}::rows`]) {
                effects.get(key)?.();
                effects.delete(key);
            }
        });
    }

    function rowsOf(node: SchemaNode): Record<string, SchemaNode[]> {
        node.props ??= {};

        if (!isPlainObject(node.props.rows)) {
            node.props.rows = {};
        }

        return node.props.rows;
    }

    /**
     * Make the repeater's row nodes match the row keys in state.
     */
    function syncRows(node: SchemaNode, keys: string[]): void {
        const rows = rowsOf(node);

        for (const key of keys) {
            if (!rows[key]) {
                rows[key] = replaceRow(node.props?.template ?? [], key, node.props?.rowPlaceholder);
                index(rows[key]);
            }
        }

        for (const key of Object.keys(rows)) {
            if (!keys.includes(key)) {
                unindex(rows[key]);
                delete rows[key];
            }
        }
    }

    function rowKeys(node: SchemaNode): string[] {
        return Object.keys(get(node.path!) ?? {});
    }

    function touchRows(node: SchemaNode): void {
        const path = node.path!;

        syncRows(node, rowKeys(node));
        editVersions.set(path, (editVersions.get(path) ?? 0) + 1);
        delete errors[path];
        notify(path, 'user');
    }

    function addRow(node: SchemaNode): string {
        const key = newRowKey();

        if (!isPlainObject(get(node.path!))) {
            setPath(state, node.path!, {});
        }

        setPath(state, `${node.path}.${key}`, clone(node.props?.newRow ?? {}));
        touchRows(node);

        return key;
    }

    function removeRow(node: SchemaNode, key: string): void {
        const prefix = `${node.path}.${key}`;

        deletePath(state, prefix);
        Object.keys(errors)
            .filter((path) => startsWithPath(path, prefix))
            .forEach((path) => delete errors[path]);
        touchRows(node);
    }

    function moveRow(node: SchemaNode, key: string, offset: number): void {
        const rows = get(node.path!) ?? {};
        const keys = Object.keys(rows);
        const from = keys.indexOf(key);
        const to = from + offset;

        if (from < 0 || to < 0 || to >= keys.length) {
            return;
        }

        keys.splice(to, 0, ...keys.splice(from, 1));

        const reordered: Record<string, unknown> = {};
        keys.forEach((rowKey) => (reordered[rowKey] = rows[rowKey]));
        setPath(state, node.path!, reordered);

        const rowNodes = rowsOf(node);

        node.props!.rows = Object.fromEntries(keys.map((rowKey) => [rowKey, rowNodes[rowKey]]));
        touchRows(node);
    }

    function applyPatch(patch: UpdatePatch, versions: Map<string, number>): void {
        for (const [key, partial] of Object.entries(patch.schema ?? {})) {
            const node = nodes.get(key);

            if (node) {
                mergeNode(node, partial);
                index([node]);
            }
        }

        for (const [path, value] of Object.entries(patch.set ?? {})) {
            if ((editVersions.get(path) ?? 0) !== (versions.get(path) ?? 0)) {
                continue;
            }

            set(path, clone(value), 'server');
        }

        for (const [path, messages] of Object.entries(patch.errors ?? {})) {
            errors[path] = Array.isArray(messages) ? messages[0] : messages;
        }
    }

    function dependencyMap(): Record<string, string[]> {
        const map: Record<string, string[]> = {};

        for (const node of nodes.values()) {
            if (node.deps?.length) {
                map[node.key] = [...node.deps];
            }
        }

        return map;
    }

    const send =
        options.send ??
        ((url: string, request: UpdateRequest, signal: AbortSignal) =>
            postJson<UpdatePatch>(url, request, signal));

    const requests = new RequestLog();
    const queue = new RequestQueue({
        log: requests,
        buildRequest: (seq, changed) => ({
            token: schema.token ?? '',
            seq,
            state: { data: clone(state.data) },
            changed,
            deps: dependencyMap(),
        }),
        snapshotVersions: () => new Map(editVersions),
        send: (request, signal) => send(schema.endpoints.update, request, signal),
        apply: (patch, versions) => {
            liveError.value = null;
            applyPatch(patch, versions);
        },
        onError: (error) => {
            if (error instanceof HttpError && error.status === 419) {
                fatalError.value = 'This form has expired. Reload the page to continue.';
            } else {
                liveError.value =
                    'Some fields could not be updated. Check your connection and try again.';
            }

            console.error('[xelqun] Live update failed.', error);
        },
    });

    const indicatorPaths = ref<string[]>([]);
    let indicatorTimer: ReturnType<typeof setTimeout> | null = null;

    function clearIndicatorTimer(): void {
        if (indicatorTimer !== null) {
            clearTimeout(indicatorTimer);
            indicatorTimer = null;
        }
    }

    scope.run(() => {
        watch(
            queue.loadingPaths,
            (paths) => {
                if (paths.length === 0) {
                    clearIndicatorTimer();
                    indicatorPaths.value = [];
                } else if (indicatorPaths.value.length > 0) {
                    indicatorPaths.value = paths;
                } else if (indicatorTimer === null) {
                    indicatorTimer = setTimeout(() => {
                        indicatorTimer = null;
                        indicatorPaths.value = queue.loadingPaths.value;
                    }, LOADING_INDICATOR_DELAY);
                }
            },
            { flush: 'sync' },
        );
    });

    function dependsOnAny(node: SchemaNode, paths: string[]): boolean {
        return !!node.deps?.some((dependency) => paths.some((path) => overlaps(dependency, path)));
    }

    /**
     * True while a request that can change this node is in flight.
     */
    function isLoading(node: SchemaNode): boolean {
        return dependsOnAny(node, queue.loadingPaths.value);
    }

    /**
     * True once that request has run long enough to deserve a spinner.
     */
    function showsLoadingIndicator(node: SchemaNode): boolean {
        return dependsOnAny(node, indicatorPaths.value);
    }

    function setErrors(incoming: Record<string, string | string[]>): void {
        clearErrors();

        for (const [path, messages] of Object.entries(incoming ?? {})) {
            errors[path] = Array.isArray(messages) ? messages[0] : messages;
        }
    }

    function clearErrors(): void {
        Object.keys(errors).forEach((path) => delete errors[path]);
    }

    /**
     * Ask the UI to reveal and focus the first field with an error, in schema order.
     */
    function focusFirstError(): void {
        let first: string | null = null;

        eachNode(components, (node) => {
            if (first === null && node.path && errors[node.path]) {
                first = node.path;
            }
        });

        if (first === null) {
            first = Object.keys(errors)[0] ?? null;
        }

        if (first !== null) {
            focusRequest.value = { path: first, at: Date.now() };
            void focusPath(first);
        }
    }

    async function focusPath(path: string): Promise<void> {
        for (let attempt = 0; attempt < 3; attempt++) {
            await nextTick();

            const element = root.value?.querySelector<HTMLElement>(
                `[data-xq-path="${CSS.escape(path)}"]`,
            );
            const target = element?.querySelector<HTMLElement>(
                'input:not([type=hidden]), textarea, button, [tabindex]:not([tabindex="-1"])',
            );

            // A closed tab stays mounted, and its panel opens a tick after the tab switches.
            if (target && !target.closest('[hidden]')) {
                element!.scrollIntoView?.({
                    block: 'center',
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                });
                target.focus({ preventScroll: true });

                return;
            }
        }
    }

    async function submit(url: string, submitOptions: SubmitOptions = {}): Promise<void> {
        if (processing.value) {
            return;
        }

        let failedValidation = false;

        processing.value = true;
        await Promise.allSettled(uploads);
        await queue.idle();

        router.visit(url, {
            method: submitOptions.method ?? 'post',
            data: { data: clone(state.data), ...submitOptions.data },
            errorBag: schema.id,
            preserveScroll: true,
            preserveState: submitOptions.preserveState ?? 'errors',
            onError: (incoming) => {
                setErrors(incoming as Record<string, string>);
                failedValidation = true;
                submitOptions.onError?.({ ...errors });
            },
            onSuccess: (page) => {
                clearErrors();
                submitOptions.onSuccess?.(page);
            },
            onFinish: () => {
                processing.value = false;

                // Fields stay disabled while processing, and a disabled field can't take focus.
                if (failedValidation) {
                    focusFirstError();
                }

                submitOptions.onFinish?.();
            },
        });
    }

    async function search(
        path: string,
        query: string,
        signal?: AbortSignal,
    ): Promise<SelectOption[]> {
        const response = await requests.track(
            'search',
            [path],
            query === '' ? '(empty search)' : `“${query}”`,
            postJson<{ options: SelectOption[] }>(
                schema.endpoints.search,
                { token: schema.token, path, search: query, state: { data: clone(state.data) } },
                signal,
            ),
            signal,
        );

        return response.options;
    }

    function upload(
        path: string,
        file: File,
        onProgress: (percent: number) => void,
        signal?: AbortSignal,
    ): Promise<UploadResult> {
        const request = requests.track(
            'upload',
            [path],
            file.name,
            uploadFile(
                schema.endpoints.upload,
                { token: schema.token ?? '', path, file },
                onProgress,
                signal,
            ),
            signal,
        );
        const tracked = request.finally(() => {
            uploads.delete(tracked);
            uploading.value = uploads.size;
        });

        uploads.add(tracked);
        uploading.value = uploads.size;

        return request;
    }

    function dispose(): void {
        clearIndicatorTimer();
        scope.stop();
        effects.clear();
    }

    index(components);

    return {
        id: schema.id,
        schema,
        components,
        state,
        errors,
        nodes,
        processing,
        uploading,
        fatalError,
        liveError,
        focusRequest,
        root,
        queue,
        log: requests.entries,
        get,
        set,
        blur,
        isVisible,
        isRequired,
        isDisabled,
        text,
        isLoading,
        showsLoadingIndicator,
        evaluateCondition: (node: ConditionNode) => evaluateCondition(node, get),
        rowKeys,
        addRow,
        removeRow,
        moveRow,
        applyPatch,
        setErrors,
        clearErrors,
        focusFirstError,
        submit,
        search,
        upload,
        dispose,
    };
}
