import type { App, Plugin } from 'vue';
import { createRegistry, defaultComponents } from './components/defaults';
import { REGISTRY, toComponent } from './composables/registry';
import type { ComponentRegistry, ComponentResolver } from './composables/registry';

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
    /** Runs once, after every plugin has registered its components. */
    setup?: (context: XelqunPluginContext) => void;
}

export interface XelqunFormsOptions {
    plugins?: XelqunPlugin[];
    /** The app's own renderers, keyed by schema type. These win over plugins and built-ins. */
    components?: Record<string, ComponentResolver>;
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

export function createXelqunForms(options: XelqunFormsOptions = {}): Plugin {
    return {
        install(app) {
            const plugins = options.plugins ?? [];
            const registry = createRegistry();

            registerPlugins(registry, plugins, import.meta.env?.DEV ?? false);

            for (const [type, resolver] of Object.entries(options.components ?? {})) {
                registry.set(type, toComponent(resolver));
            }

            plugins.forEach((plugin) => plugin.setup?.({ app }));
            app.provide(REGISTRY, registry);
        },
    };
}
