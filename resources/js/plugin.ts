import { router } from '@inertiajs/vue3';
import { createApp } from 'vue';
import type { App, Component, Plugin } from 'vue';
import { createRegistry, defaultComponents } from './components/defaults';
import { REGISTRY, toComponent } from './composables/registry';
import type { ComponentRegistry, ComponentResolver } from './composables/registry';
import { FLASH_KEY, notify } from './core/notifications';
import type { XqNotification } from './schema/types';
import { registerIcons } from './ui/icons';
import XqNotifications from './ui/XqNotifications.vue';

export interface XelqunPluginContext {
    app: App;
}

/**
 * The browser half of a plugin. Give it a function that returns this, so the
 * host writes `plugins: [ratingPlugin()]` and options have somewhere to go.
 */
export interface XelqunPlugin {
    id: string;
    /** Renderers keyed by vendor-prefixed schema type, e.g. { 'acme/rating': () => import('./Rating.vue') }. */
    components?: Record<string, ComponentResolver>;
    /** Built-in types this plugin takes over on purpose. Another plugin's type is always a collision. */
    replaces?: string[];
    /** Icons PHP can name, as { name: component }. */
    icons?: Record<string, Component>;
    /** Runs once, after every plugin has registered its components. */
    setup?: (context: XelqunPluginContext) => void;
}

export interface XelqunFormsOptions {
    plugins?: XelqunPlugin[];
    /** The app's own renderers, keyed by schema type. These win over plugins and built-ins. */
    components?: Record<string, ComponentResolver>;
    /** Icons PHP can name, as { name: component }, on top of the built-in set. */
    icons?: Record<string, Component>;
    /** What screen readers call the notifications region. {hotkey} is the key that jumps to it. */
    notificationsLabel?: string;
}

const VENDOR_PREFIXED_TYPE = /^[a-z0-9-]+\/[a-z0-9-]+$/;

const BUILT_IN = 'built-in';

/**
 * Add each plugin's components to the registry. A collision is a bug in the app,
 * so it throws in development. In production the page should still work: the
 * error is logged and the type that was registered first stays.
 */
export function registerPlugins(
    registry: ComponentRegistry,
    plugins: XelqunPlugin[],
    strict: boolean,
): void {
    const owners = new Map(Object.keys(defaultComponents()).map((type) => [type, BUILT_IN]));
    const ids = new Set<string>();

    const refuse = (message: string): void => {
        if (strict) {
            throw new Error(`[xelqun] ${message}`);
        }

        console.error(`[xelqun] ${message}`);
    };

    for (const plugin of plugins) {
        if (ids.has(plugin.id)) {
            refuse(`The plugin "${plugin.id}" is registered twice.`);

            continue;
        }

        ids.add(plugin.id);

        for (const [type, resolver] of Object.entries(plugin.components ?? {})) {
            const owner = owners.get(type);
            const isBuiltIn = owner === BUILT_IN;
            const mayReplace = isBuiltIn && (plugin.replaces?.includes(type) ?? false);

            if (owner && !isBuiltIn) {
                refuse(
                    `The plugins "${owner}" and "${plugin.id}" both register the type "${type}".`,
                );

                continue;
            }

            if (!VENDOR_PREFIXED_TYPE.test(type) && !mayReplace) {
                refuse(
                    `The plugin "${plugin.id}" registers the type "${type}". Plugin types need a vendor prefix such as "acme/rating". Names without one are reserved for built-in fields. To replace a built-in on purpose, list it in "replaces".`,
                );

                continue;
            }

            registry.set(type, toComponent(resolver));
            owners.set(type, plugin.id);
        }
    }
}

/**
 * Put the toaster on the page and listen for flash data. Returns how to undo both.
 */
function mountNotifications(label?: string): () => void {
    const host = document.body.appendChild(document.createElement('div'));
    const toaster = createApp(XqNotifications, { label });
    const stopListening = router.on('flash', (event) => {
        const { [FLASH_KEY]: notifications } = event.detail.flash;

        (notifications as XqNotification[] | undefined)?.forEach(notify);
    });

    toaster.mount(host);

    return () => {
        stopListening();
        toaster.unmount();
        host.remove();
    };
}

export function createXelqunForms(options: XelqunFormsOptions = {}): Plugin {
    return {
        install(app) {
            const plugins = options.plugins ?? [];
            const registry = createRegistry();

            registerPlugins(registry, plugins, import.meta.env?.DEV ?? false);
            plugins.forEach((plugin) => registerIcons(plugin.icons ?? {}));
            registerIcons(options.icons ?? {});

            for (const [type, resolver] of Object.entries(options.components ?? {})) {
                registry.set(type, toComponent(resolver));
            }

            plugins.forEach((plugin) => plugin.setup?.({ app }));
            app.provide(REGISTRY, registry);

            if (typeof document !== 'undefined') {
                app.onUnmount(mountNotifications(options.notificationsLabel));
            }
        },
    };
}
