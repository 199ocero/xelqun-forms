import { ref } from 'vue';
import type { LiveConfig, UpdatePatch, UpdateRequest } from '../schema/types';
import { RequestLog } from './log';
import type { RequestLogEntry } from './log';

export interface RequestQueueOptions {
    /** Build the request body for the given sequence number and changed paths. */
    buildRequest: (seq: number, changed: string[]) => UpdateRequest;
    /** Snapshot of the per-path edit counters at send time. */
    snapshotVersions: () => Map<string, number>;
    send: (request: UpdateRequest, signal: AbortSignal) => Promise<UpdatePatch>;
    apply: (patch: UpdatePatch, versions: Map<string, number>) => void;
    onError?: (error: unknown) => void;
    /** Shared request history; a private one is created when omitted. */
    log?: RequestLog;
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
    readonly requests: RequestLog;

    readonly busy = ref(false);

    private pending = new Set<string>();

    private timer: ReturnType<typeof setTimeout> | null = null;

    private inflight: { controller: AbortController; paths: string[]; seq: number } | null = null;

    private seq = 0;

    private waiters: Array<() => void> = [];

    constructor(private readonly options: RequestQueueOptions) {
        this.requests = options.log ?? new RequestLog();
    }

    get log(): RequestLogEntry[] {
        return this.requests.entries;
    }

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
        const entry = this.requests.start('update', paths, null, seq);

        this.pending.clear();
        this.inflight = { controller, paths, seq };
        this.busy.value = true;

        try {
            const patch = await this.options.send(request, controller.signal);
            entry.ms = Date.now() - entry.startedAt;
            entry.patch = patch;
            entry.response = patch;

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
