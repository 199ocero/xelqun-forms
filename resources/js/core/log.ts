import { reactive } from 'vue';
import type { UpdatePatch } from '../schema/types';

export type RequestKind = 'update' | 'search' | 'upload' | 'validate';

export interface RequestLogEntry {
    id: number;
    kind: RequestKind;
    /** Sequence number of a live update; null for searches and uploads. */
    seq: number | null;
    changed: string[];
    /** Short extra context, e.g. the search term or the uploaded file name. */
    detail: string | null;
    status: 'pending' | 'ok' | 'stale' | 'aborted' | 'error';
    startedAt: number;
    ms: number | null;
    patch: UpdatePatch | null;
    response: unknown;
    error: string | null;
}

/**
 * A short, newest-first history of every request a form makes to the
 * server: live updates, searches and uploads. Useful for debugging tools.
 */
export class RequestLog {
    readonly entries = reactive<RequestLogEntry[]>([]);

    private nextId = 0;

    constructor(private readonly limit = 25) {}

    start(
        kind: RequestKind,
        changed: string[],
        detail: string | null = null,
        seq: number | null = null,
    ): RequestLogEntry {
        this.entries.unshift({
            id: ++this.nextId,
            kind,
            seq,
            changed,
            detail,
            status: 'pending',
            startedAt: Date.now(),
            ms: null,
            patch: null,
            response: null,
            error: null,
        });
        this.entries.splice(this.limit);

        return this.entries[0];
    }

    /**
     * Log a request from start to finish and pass its result through.
     */
    async track<T>(
        kind: RequestKind,
        changed: string[],
        detail: string | null,
        request: Promise<T>,
        signal?: AbortSignal,
    ): Promise<T> {
        const entry = this.start(kind, changed, detail);

        try {
            const response = await request;

            entry.ms = Date.now() - entry.startedAt;
            entry.response = response;
            entry.status = 'ok';

            return response;
        } catch (error) {
            entry.ms = Date.now() - entry.startedAt;
            entry.status = signal?.aborted || isAbortError(error) ? 'aborted' : 'error';
            entry.error = entry.status === 'error' ? errorMessage(error) : null;

            throw error;
        }
    }
}

function isAbortError(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

function errorMessage(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}
