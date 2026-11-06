import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/**
 * Keep keyboard users in place: focus the first of these that can take it.
 */
export function focusFirstEnabled(container: HTMLElement | null, selectors: string[]): void {
    for (const selector of selectors) {
        const control = container?.querySelector<HTMLButtonElement>(selector);

        if (control && !control.disabled) {
            control.focus();

            return;
        }
    }
}

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) {
        return '';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** exponent).toFixed(exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}
