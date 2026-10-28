import { reactive, ref } from 'vue';
import type { LiveConfig, UpdatePatch, UpdateRequest } from '../schema/types';

export interface RequestLogEntry {
    seq: number;
    changed: string[];
    status: 'pending' | 'ok' | 'stale' | 'aborted' | 'error';
    startedAt: number;
    ms: number | null;
    patch: UpdatePatch | null;
    error: string | null;
}

export interface RequestQueueOptions {
    /** Build the request body for the given sequence number and changed paths. */
    buildRequest: (seq: number, changed: string[]) => UpdateRequest;
    /** Snapshot of the per-path edit counters at send time. */
    snapshotVersions: () => Map<string, number>;
    send: (request: UpdateRequest, signal: AbortSignal) => Promise<UpdatePatch>;
    apply: (patch: UpdatePatch, versions: Map<string, number>) => void;
    onError?: (error: unknown) => void;
}

/**
 * Batches live changes into as few update requests as possible.
 *
 * - "change" flushes on the next tick, "debounce" after the quiet period,
 *   "blur" only when the field loses focus.
 * - Sending a new request aborts the one in flight and carries its paths along.
 * - Responses for anything but the latest sequence number are ignored.
 */
export class RequestQueue {
    readonly log = reactive<RequestLogEntry[]>([]);

    readonly busy = ref(false);

    private pending = new Set<string>();

    private timer: ReturnType<typeof setTimeout> | null = null;

    private inflight: { controller: AbortController; paths: string[]; seq: number } | null = null;

    private seq = 0;

    private waiters: Array<() => void> = [];

    constructor(private readonly options: RequestQueueOptions) {}

    notify(path: string, live: LiveConfig): void {
        this.pending.add(path);

        if (live.mode === 'blur') {
            return;
        }

        this.schedule(live.mode === 'debounce' ? (live.ms ?? 400) : 0);
    }

    blur(path: string): void {
        if (this.pending.has(path)) {
            this.schedule(0);
        }
    }

    /**
     * Send everything that is pending now and resolve once nothing is in flight.
     */
    idle(): Promise<void> {
        if (this.pending.size > 0) {
            this.clearTimer();
            void this.flush();
        }

        if (this.isIdle()) {
            return Promise.resolve();
        }

        return new Promise((resolve) => this.waiters.push(resolve));
    }

    hasPending(): boolean {
        return !this.isIdle();
    }

    async flush(): Promise<void> {
        this.timer = null;

        if (this.pending.size === 0) {
            this.settle();

            return;
        }

        if (this.inflight) {
            this.inflight.controller.abort();
            this.inflight.paths.forEach((path) => this.pending.add(path));
        }

        const paths = [...this.pending];
        const seq = ++this.seq;
        const controller = new AbortController();
        const versions = this.options.snapshotVersions();
        const request = this.options.buildRequest(seq, paths);
        const entry: RequestLogEntry = reactive({
            seq,
            changed: paths,
            status: 'pending',
            startedAt: Date.now(),
            ms: null,
            patch: null,
            error: null,
        });

        this.pending.clear();
        this.inflight = { controller, paths, seq };
        this.busy.value = true;
        this.log.unshift(entry);
        this.log.splice(25);

        try {
            const patch = await this.options.send(request, controller.signal);
            entry.ms = Date.now() - entry.startedAt;
            entry.patch = patch;

            if (seq !== this.seq || patch.seq !== seq) {
                entry.status = 'stale';

                return;
            }

            this.options.apply(patch, versions);
            entry.status = 'ok';
        } catch (error) {
            entry.ms = Date.now() - entry.startedAt;

            if (controller.signal.aborted) {
                entry.status = 'aborted';
            } else {
                entry.status = 'error';
                entry.error = error instanceof Error ? error.message : String(error);
                this.options.onError?.(error);
            }
        } finally {
            if (this.inflight?.seq === seq) {
                this.inflight = null;
            }

            this.settle();
        }
    }

    private schedule(ms: number): void {
        this.clearTimer();
        this.timer = setTimeout(() => void this.flush(), ms);
    }

    private clearTimer(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private isIdle(): boolean {
        return this.inflight === null && this.timer === null && this.pending.size === 0;
    }

    private settle(): void {
        this.busy.value = this.inflight !== null;

        if (this.isIdle()) {
            this.waiters.splice(0).forEach((resolve) => resolve());
        }
    }
}
