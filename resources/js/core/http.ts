import type { UploadResult } from '../schema/types';

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: any,
        /** Seconds the server asked to wait, from a 429's Retry-After header. */
        public readonly retryAfter: number | null = null,
    ) {
        super(body?.message ?? `Request failed with status ${status}.`);
    }

    get validationErrors(): Record<string, string[]> {
        return this.body?.errors ?? {};
    }
}

function xsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

function headers(json: boolean): Record<string, string> {
    const result: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = xsrfToken();

    if (token) {
        result['X-XSRF-TOKEN'] = token;
    }

    if (json) {
        result['Content-Type'] = 'application/json';
    }

    return result;
}

export async function postJson<T>(url: string, body: unknown, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: headers(true),
        body: JSON.stringify(body),
        credentials: 'same-origin',
        signal,
    });
    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new HttpError(response.status, payload);
    }

    return payload as T;
}

/**
 * Upload with progress events (fetch has no upload progress).
 */
export function uploadFile(
    url: string,
    fields: Record<string, string | Blob>,
    onProgress: (percent: number) => void,
    signal?: AbortSignal,
): Promise<UploadResult> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const body = new FormData();

        Object.entries(fields).forEach(([key, value]) => body.append(key, value));

        request.open('POST', url);
        request.withCredentials = true;
        Object.entries(headers(false)).forEach(([key, value]) =>
            request.setRequestHeader(key, value),
        );
        request.responseType = 'json';
        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        });
        request.addEventListener('load', () => {
            if (request.status >= 200 && request.status < 300) {
                resolve(request.response as UploadResult);
            } else {
                const retryAfter = Number(request.getResponseHeader('Retry-After'));

                reject(
                    new HttpError(
                        request.status,
                        request.response,
                        Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : null,
                    ),
                );
            }
        });
        request.addEventListener('error', () =>
            reject(new HttpError(0, { message: 'Network error.' })),
        );
        request.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
        signal?.addEventListener('abort', () => request.abort());
        request.send(body);
    });
}
