import { defineAsyncComponent, inject } from 'vue';
import type { Component, InjectionKey } from 'vue';

export type ComponentLoader = () => Promise<Component | { default: Component }>;

export type ComponentResolver = Component | ComponentLoader;

export type ComponentRegistry = Map<string, Component>;

export const REGISTRY: InjectionKey<ComponentRegistry> = Symbol('xelqun-registry');

/**
 * A zero-argument function is treated as a lazy loader, e.g. () => import('./Rating.vue').
 */
export function toComponent(resolver: ComponentResolver): Component {
    if (typeof resolver === 'function' && resolver.length === 0 && !('render' in resolver)) {
        return defineAsyncComponent(resolver as ComponentLoader);
    }

    return resolver as Component;
}

export function useRegistry(): ComponentRegistry | null {
    return inject(REGISTRY, null);
}
