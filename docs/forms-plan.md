# Xelqun Forms: planning document

> Status: approved plan (2026-10-28), implemented through Milestone 9. Parity work continues in [`parity-plan.md`](parity-plan.md), through Milestone 17. See "Implementation status" at the end.

## Context

Xelqun is an open-source admin framework in the style of Filament, built for the VILT stack (Vue 3, Inertia, Laravel, Tailwind). The first package is **forms**. Developers define forms in PHP. The package turns them into a versioned JSON schema, and generic Vue components render that schema. Create/edit pages, filters, action modals and settings pages will all be built on it later, so the core has to be right: reactivity, server rules, security and design.

### What exploration found (it differs from the brief)

| Area | Finding | Effect |
|---|---|---|
| Playground app | Built from the **blank** Vue starter kit: Laravel 13.39, Inertia 3.5 (laravel) / 3.9 (vue3), Vue 3.5, Tailwind 4.3, PHP 8.4, Pest 5.3, Wayfinder. Only `Welcome.vue` and `routes/web.php` exist. **No auth, no sidebar, no shadcn-vue, no `components/ui`.** | You chose a simple public page with in-page navigation and no login. |
| Toolchain | Uses **Vite+** (`vp dev/build/test/check/pack`). `vp test` = Vitest 5 and `vp pack` builds libraries. npm 10 / Node 22 (`package-lock.json`; `pnpm-workspace.yaml` is just a leftover from the kit). `.npmrc` has `ignore-scripts=true`. | Vitest is already available and no new test runner is needed. |
| Git | The playground is **not a git repo**, and you chose to keep it that way. | No `.gitignore` or submodule work. Only `packages/forms` is versioned. |
| `199ocero/xelqun-forms` | Exists, **private and empty** (no commits). | Plan the first scaffold (below). |
| Names | `xelqun/forms` (Packagist) and `@xelqun/forms` (npm) are both **free**. `199ocero/xelqun` (SES app) will be removed or made private, so the name is OK. | Keep the proposed names. |
| `.ai/rules` | Does not exist. | Nothing to read. |

---

## 1. Questions and decisions

### Answered
1. **Playground shape:** a simple public page with no starter kit, login or app sidebar. Examples use in-page navigation.
2. **Name:** keep Xelqun (`xelqun/forms`, `@xelqun/forms`, `Xelqun\Forms`, `Xq*`, `--xq-*`, `/_xelqun/...`).
3. **Git:** the playground stays local only. `packages/forms` is its own repo. Nothing has to be ignored. *If you ever `git init` the playground, add `/packages/` to its `.gitignore`. That is better than a submodule for a solo developer, because a submodule needs two commits for every package change.*
4. **E2E tests:** Pest browser plugin (Playwright underneath, tests written in PHP).

### Decisions I'd change or sharpen (already built into the plan below)
1. **Live forms must be classes.** The update, upload and search endpoints have to *rebuild* the form from a token, so the form must be a class they can resolve (`OrderForm extends Form`). `Form::make([...])` stays for simple static forms. If an anonymous form uses closures, `live()` or uploads, serializing it throws a clear exception.
2. **Auto-live through dependency tracking.** If a server closure reads `country`, then `country` *is* a trigger. Without this, forgetting `->live()` would silently break forms. `->live()` then only picks the mode (`onBlur`, `debounce`), and fields with `afterStateUpdated` are live automatically. An explicit `->dependsOn([...])` covers branches the recorder never saw.
3. **Expressions are parsed once, in PHP.** `computed('qty * price')` is parsed by a PHP parser into a small AST, and the AST goes into the schema. Vue only *evaluates* the AST and ships no parser, so it stays small and safe. **On submit, the server recomputes every computed value with the same AST.** The client's totals are never trusted.
4. **Evaluator parity is enforced by shared test files.** One set of JSON test cases (`tests/fixtures/conformance/*.json`) runs in both Pest and Vitest. Conditions and expressions must give identical results in both languages.
5. **Patch `schema` is keyed partial nodes**, `{ "data.state": { "props": { "options": [...] } } }`, never a whole new tree. That is how components avoid re-mounting.
6. **Tokens are encrypted, not just signed** (Laravel `Crypt`). This hides class names and record keys, and the MAC stops tampering.
7. **Validate on UUID-keyed state, then convert rows to arrays.** Error keys (`data.items.<uuid>.price`) then match field paths directly. Validation attribute names are generated from labels ("Price (item 2)") so messages never show UUIDs.
8. **Component naming:** UI primitives are `XqInput`, `XqSelect…`, `XqButton` (copied from shadcn-vue). Field renderers are `XqTextInputField`, `XqSelectField`, and so on. This avoids a "Select field vs Select primitive" clash.
9. **Live requests use the package's own small `fetch` client** (with AbortController and the XSRF cookie), not Inertia's `useHttp`. This gives full control over aborting and ordering, and keeps the core free of Inertia. Inertia is only used to **submit**.
10. **One error bag per form** (Inertia `errorBag`), because the playground puts several forms on one page. This will be checked with `search-docs` in Milestone 2.

### Small defaults (tell me if you disagree)
MIT license · PHP `^8.4` · Laravel `^13` only, for now · icons from `lucide-vue-next` · dates through Reka UI + `@internationalized/date` · code highlighting in the playground through lazy-loaded Shiki (playground only).

---

## 2. Setup steps

All commands run from `/Users/jaocero/Projects/Personal/xelqun-playground`.

```bash
# 1. Clone the empty repo (git warns "cloned an empty repository", which is expected)
mkdir -p packages
git clone git@github.com:199ocero/xelqun-forms.git packages/forms
cd packages/forms && git checkout -b main

# 2. Scaffold the package (files listed in section 3), then install its own dev tools
composer install            # testbench, pest, pint, larastan for package tests
cd ../..                    # npm deps for the package are installed by the workspace (step 4)

# 3. PHP link: Composer path repository (symlinked by default)
composer config repositories.xelqun-forms '{"type":"path","url":"packages/forms","options":{"symlink":true}}'
composer require xelqun/forms:@dev     # @dev needed: playground has minimum-stability "stable"
ls -la vendor/xelqun                   # forms -> ../../packages/forms

# 4. JS link: npm workspace (recommended, see below)
npm pkg set 'workspaces[0]=packages/*'
npm pkg set 'dependencies.@xelqun/forms=*'
npm install
npm ls vue                             # must show ONE vue (deduped)
ls -la node_modules/@xelqun            # forms -> ../../packages/forms

# 5. First commit of the package
cd packages/forms && git add . && git commit -m "Initial package scaffold" && git push -u origin main
```

**Why an npm workspace (instead of a Vite alias or a `file:` dependency):**
- One `node_modules` at the playground root. The package's dependencies (reka-ui…) are installed and hoisted automatically, and there is **one copy of `vue`**. Two copies of Vue is the classic bug with linked Vue packages: reactivity and `provide/inject` silently break.
- Imports look exactly as they will for real users (`import { XqForm } from '@xelqun/forms'`). Moving to the published npm version later is a one-line change.
- A Vite alias would need the package's dependencies installed by hand in the host, and `tsconfig` paths too. A `file:` dependency tends to install a second `vue` inside `packages/forms/node_modules`.
- Safety net in `vite.config.ts`: `resolve: { dedupe: ['vue', '@inertiajs/vue3', 'reka-ui'] }`.

**Playground wiring after linking:**
- `resources/js/app.ts`: `withApp: (app) => app.use(createXelqunForms())`.
- `resources/css/app.css`: add `@import '@xelqun/forms/style.css';`. That file contains `@source "../js";` (relative to itself), so the host never has to know where the package lives. *Milestone 0 checks that this works through the symlink. Fallback: `@source '../../packages/forms/resources/js';` in `app.css`.*
- `vite.config.ts`: add `packages/**` to `lint.ignorePatterns` and `fmt.ignorePatterns`, because the package has its own lint and format config.
- Pest browser plugin (Milestone 2): `composer require pestphp/pest-plugin-browser --dev` (the version that matches Pest 5), `npm install -D playwright`, `npx playwright install chromium`.

> These are new dependencies for the playground: the `xelqun/forms` path repo, the npm workspace, the Pest browser plugin, Playwright and Shiki. Approving this plan approves them.

---

## 3. Package structure

**Recommendation: one repo, two manifests.** `xelqun-forms` holds both `composer.json` (`xelqun/forms`) and `package.json` (`@xelqun/forms`), and both are released from the same tag. Ziggy does the same. Users run `composer require xelqun/forms && php artisan xelqun-forms:install`, and the installer adds the matching npm package and wires up the app.

| Option | Pros | Cons |
|---|---|---|
| **npm package from the same repo (chosen)** | Normal JS tooling: dependencies resolve, TypeScript types work, works with any package manager, one Vue copy | Two installs, so PHP and JS versions can drift. The installer pins the npm version to the PHP package, and the schema version handshake (section 4) catches any later drift. |
| Vite path into `vendor/xelqun/forms` | One install, versions always match | Host installs the package's JS dependencies by hand, awkward TypeScript paths, unusual for JS developers, a later Vite plugin would be needed anyway |

**MVP ships TypeScript and Vue source** (no build step). The host's Vite compiles it, Tailwind scans it, and an "eject" is a file copy. Before 1.0, decide whether to precompile with `vp pack`. A prototype showed it can drop the npm package, but `reka-ui` can't simply be bundled: a second copy breaks popovers inside a host's own Reka dialog (Escape closes the dialog, and options can't be clicked because they inherit `pointer-events: none`). It would need a second build that uses the host's `reka-ui`, and about 53 kB of calendar code moved into the main bundle.

```
packages/forms/
├── composer.json  package.json  tsconfig.json  vite.config.ts (vitest)  phpunit.xml  pint.json  phpstan.neon
├── .github/workflows/ci.yml       # pest + pint + larastan + vp test + vue-tsc
├── config/xelqun-forms.php        # route prefix, middleware, temp disk, upload ttl, token ttl
├── routes/xelqun-forms.php        # /_xelqun/forms/{update,upload,search}
├── src/                           # namespace Xelqun\Forms\
│   ├── FormsServiceProvider.php
│   ├── Form.php                   # base class + static make([...]) for anonymous forms
│   ├── Components/                # Component (base), Field (base), TextInput, Textarea, Select,
│   │                              # Checkbox, Toggle, Radio, DatePicker, FileUpload, Repeater,
│   │                              # Hidden, CustomField
│   ├── Layouts/                   # Section, Grid, Fieldset, Tabs, Tab
│   ├── Concerns/                  # HasLabel, HasRules, HasConditions, CanBeLive, HasOptions, HasColumnSpan…
│   ├── Conditions/                # When (builder), Condition (AST node), ConditionEvaluator
│   ├── Expressions/               # Lexer, Parser, Node types, ExpressionEvaluator
│   ├── State/                     # Get, Set, RecordingGet, StatePath, StateBag, DependencyMap, RowKeys
│   ├── Schema/                    # SchemaSerializer, SchemaVersion, NodeKey
│   ├── Live/                      # UpdateRequest, UpdateProcessor, Patch
│   ├── Security/                  # FormToken (value object), FormTokenEncrypter
│   ├── Validation/                # RuleCollector, AttributeNames, SubmittedData (dehydrate)
│   ├── Uploads/                   # TemporaryUpload, TemporaryUploadStore, PruneTemporaryUploadsCommand
│   ├── Eloquent/                  # ModelHydrator (fill), ModelPersister (save), BelongsToHandler, HasManyHandler
│   ├── Http/Controllers/          # UpdateController, UploadController, SearchController
│   └── Exceptions/
├── resources/
│   ├── css/xelqun.css             # @source "../js"; --xq-* tokens; .dark overrides; @theme inline mappings
│   └── js/
│       ├── index.ts               # public API
│       ├── plugin.ts              # createXelqunForms({ components })
│       ├── schema/types.ts        # TS mirror of the JSON contract
│       ├── core/                  # store.ts, paths.ts, conditions.ts, expressions.ts, patch.ts,
│       │                          # queue.ts, http.ts, submit.ts, rows.ts
│       ├── composables/           # useXqForm.ts, useField.ts, useNode.ts
│       ├── registry.ts
│       ├── components/
│       │   ├── XqForm.vue  XqNode.vue  XqFieldWrapper.vue (label/hint/error)
│       │   ├── fields/            # XqTextInputField.vue … XqRepeaterField.vue, XqFileUploadField.vue
│       │   └── layouts/           # XqSection.vue XqGrid.vue XqFieldset.vue XqTabs.vue
│       └── ui/                    # copied shadcn-vue primitives built on Reka UI, renamed Xq*, tokens only
└── tests/
    ├── Pest.php  TestCase.php (Orchestra Testbench)
    ├── Unit/  Feature/  .pest/snapshots/
    ├── fixtures/conformance/      # conditions.json, expressions.json, shared with Vitest
    └── js/                        # *.test.ts (Vitest + @vue/test-utils + happy-dom)
```

- **composer.json:** `require` php ^8.4, `illuminate/{support,http,validation,database,filesystem}` ^13, `inertiajs/inertia-laravel` ^3. `require-dev` Testbench (Laravel 13 line), Pest 5, pest-plugin-laravel, Pint, Larastan. PSR-4 `Xelqun\\Forms\\` → `src/`. Auto-discovered provider.
- **package.json:** `"exports": { ".": "./resources/js/index.ts", "./style.css": "./resources/css/xelqun.css" }`. Peer dependencies `vue ^3.5`, `@inertiajs/vue3 ^3`. Dependencies `reka-ui`, `clsx`, `tailwind-merge`, `class-variance-authority`, `@internationalized/date`, `lucide-vue-next`. Dev dependencies `vitest`, `@vue/test-utils`, `happy-dom`, `vue-tsc`, `typescript`.
- **Theming:** components use only token utilities (`bg-xq-surface`, `text-xq-muted`, `ring-xq-ring`…), which `@theme inline { --color-xq-surface: var(--xq-surface) … }` maps to `--xq-*` variables. Dark mode swaps variables under `.dark, [data-xq-theme=dark]`. There are no `dark:` variants in components, so the host's dark mode setup does not matter. Because these names never collide with shadcn's `--primary` and similar, they can live inside any shadcn-vue app.

---

## 4. The JSON schema contract (v1 draft)

```jsonc
// Inertia prop: what OrderForm::make($order)->toSchema() returns
{
  "$xq": 1,                              // schema MAJOR version. JS supports [1]. Mismatch → dev error box
  "pkg": "0.1.0",                        // PHP package version, for diagnostics
  "id": "order-form",                    // also the Inertia errorBag name
  "token": "eyJpdiI6…",                  // encrypted FormToken (null for static forms)
  "endpoints": { "update": "/_xelqun/forms/update", "upload": "…/upload", "search": "…/search" },
  "state": { "data": { "type": "individual", "country": null, "items": { "9f1c…": { "qty": 1, "price": 0, "subtotal": 0 } } } },
  "dependencies": { "data.country": ["data.state"] },   // trigger path → nodes the server must re-resolve
  "components": [ /* nodes */ ]
}
```

**Node (field or layout):**
```jsonc
{
  "key": "data.state",                   // unique and stable. Vue :key and patch target
  "type": "select",                      // registry key
  "path": "data.state",                  // state path (null for layouts)
  "label": "State", "hint": null, "placeholder": null,
  "required": false, "disabled": false,  // static, resolved on the server
  "rules": {                             // client conditions (optional)
    "visible":  { "op": "filled", "path": "data.country" },
    "required": null, "disabled": null
  },
  "computed": null,                      // expression AST (see below)
  "live": { "mode": "change" | "blur" | "debounce", "ms": 400 } | null,
  "span": { "default": 1, "md": 2 },
  "props": { "options": [{ "value": 12, "label": "Cebu" }], "searchable": false },  // type-specific
  "children": []                         // layouts only
}
```
- Fields hidden or forbidden by the server are **not serialized at all**.
- **Repeater** node: `props.template` (child nodes with row-relative paths like `.price`), `props.orderable`, `props.minItems`/`maxItems`, `props.addLabel`. Row state lives in an object keyed by UUID. Per-row overrides arrive as patches keyed by the full key (`data.items.9f1c….product`).
- **Empty repeater must serialize as `{}`**, not `[]`. In PHP, `json_encode([])` gives `[]`, a classic bug. The serializer casts row bags to objects.

**Condition AST:** `{ op, path?, value?, args? }`
`eq, neq, in, notIn, gt, gte, lt, lte, filled, blank, and, or, not`.
**Path resolution (same for `When`, `Get` and expressions):** a bare name resolves against the field's container, which is the form root or the current repeater row. `$.name` forces the form root. `data` has no special meaning here, so a field can be named `data`. The serializer turns both into absolute paths (rows use the `{row}` placeholder in templates).

**Expression AST:** `{ "src": "qty * price", "ast": { "op": "*", "args": [{ "ref": "qty" }, { "ref": "price" }] } }`.
Supported: numbers, strings, refs, `+ - * / %`, comparisons, `&& || !`, parentheses, functions `sum`, `count`, `min`, `max`, `avg`, `round(x, d)`, `abs`, and wildcards `items.*.subtotal`. Null or empty counts as 0 in arithmetic, and dividing by 0 gives 0. Both evaluators follow these rules exactly, and the conformance files prove it.

**Update request / patch:**
```jsonc
// POST /_xelqun/forms/update
{ "token": "…", "seq": 7, "state": { "data": { … } }, "changed": ["data.country"] }
// response
{
  "$xq": 1, "seq": 7,
  "set":    { "data.state": null },                                  // values the server changed
  "schema": { "data.state": { "props": { "options": [ … ] } } },     // partial nodes, deep-merged by key
  "remove": [], "add": [],                                           // rare: server-side visibility changes
  "errors": { "data.state": ["…"] },                                 // optional live validation
  "dependencies": { "data.country": ["data.state"] }                 // refreshed map
}
```

**Versioning rules:** adding an optional key = no bump. Renaming or removing a key, or changing what it means = `$xq` + 1. The JS package keeps a `SUPPORTED_SCHEMA = [1]` list. On a mismatch it shows a readable error in dev and a console error in prod.

---

## 5. The PHP API

```php
final class OrderForm extends Form
{
    public function schema(): array
    {
        return [
            Radio::make('type')->options(['individual' => 'Individual', 'business' => 'Business'])->default('individual'),
            Section::make('Business details')->visibleWhen(When::eq('type', 'business'))->schema([
                TextInput::make('company')->requiredWhen(When::eq('type', 'business')),
            ]),
            Select::make('country_id')->relationship('country', 'name')->live(),
            Select::make('state_id')->options(fn (Get $get) => State::where('country_id', $get('country_id'))->pluck('name', 'id'))
                ->afterStateUpdated(fn (Set $set) => $set('city', null)),
            Repeater::make('items')->relationship('items')->schema([
                TextInput::make('qty')->numeric()->minValue(1),
                TextInput::make('price')->numeric(),
                TextInput::make('subtotal')->computed('qty * price')->disabled(),
                FileUpload::make('attachment')->disk('public')->directory('order-items')->maxSize(2048),
            ]),
            TextInput::make('total')->computed('sum(items.*.subtotal)')->disabled(),
        ];
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool { return true; }
    public function model(): string { return Order::class; }
}

// controller
Inertia::render('playground/Forms', ['form' => OrderForm::make($order)->toSchema()]);
$data = OrderForm::make($order)->validate($request);   // clean, dehydrated array
OrderForm::make($order)->save($request);                // validate + fill + relationships + finalize uploads
```

| Class | Job |
|---|---|
| `Form` | Base class. `make(?Model $record, array $context = [])`, `schema()`, `authorize()`, `model()`, `toSchema()`, `validate()`, `save()`, `fill()`. Static `Form::make([...])` creates an anonymous, non-live form. |
| `Components\Component` | Base for all nodes: key, `visible()`/`hidden()` (bool or closure, server-side), `visibleWhen()`, `columnSpan()`, `toNode()`. |
| `Components\Field` | Adds name/path, label, hint, default, `required()`, `rules()`, `requiredWhen()`, `disabled()`, `disabledWhen()`, `live()`, `afterStateUpdated()`, `dependsOn()`, `dehydrated()`, `computed()`. M12 adds `readOnly()`, `disabledOn()`, `saved()`, `formatStateUsing()`, `dehydrateStateUsing()`, `afterStateHydrated()`, `state()`, `autofocus()`, `hiddenLabel()` and `inlineLabel()`. |
| Fields | `TextInput` (`email()`, `password()`, `numeric()`, `prefix/suffix`), `Textarea`, `Select` (`options()`, `searchable()`, `getSearchResultsUsing()`, `relationship()`, `multiple()`), `Checkbox`, `Toggle`, `Radio`, `DatePicker`, `FileUpload`, `Repeater`, `Hidden`, `CustomField` (`component('rating')`). |
| Layouts | `Section`, `Grid` (`columns()`), `Fieldset`, `Tabs` + `Tab`. |
| `Conditions\When` | `eq`, `neq`, `in`, `notIn`, `gt`, `gte`, `lt`, `lte`, `filled`, `blank`, `and`, `or`, `not` → `Condition` (`toArray()`, `evaluate(StateBag)`). |
| `Expressions\Parser` / `ExpressionEvaluator` | Parse the string to an AST once at schema build time. Evaluate on submit. |
| `State\Get` / `Set` / `RecordingGet` | Closure helpers with container-relative paths. `RecordingGet` logs every path it reads, which builds the `DependencyMap`. |
| `Security\FormToken` / `FormTokenEncrypter` | `{form class, record key, user id, context, issued_at}` → `Crypt::encryptString`. On decrypt it checks that the class extends `Form`, the user matches, and the token has not expired. |
| `Live\UpdateProcessor` | Rebuilds the form with the posted state, runs hooks for the changed paths, re-runs dependent closures, and builds a `Patch`. |
| `Validation\SubmittedData` | Evaluates conditions, drops hidden/disabled/non-dehydrated values, recomputes computed values, validates on UUID state, then converts rows to lists and resolves upload tokens. |
| `Uploads\*` | The temp store puts files on a configurable disk under a random id and stores metadata (session, field key, ttl). The prune command deletes expired files. |
| `Eloquent\*` | `fill()` reads the record (BelongsTo → foreign key, HasMany → rows keyed by new UUIDs and holding the record id). `save()` runs in a transaction and syncs HasMany (create, update, delete). |
| Controllers | `UpdateController`, `UploadController`, `SearchController`. All decrypt the token, call `authorize()`, rebuild the form and validate their own input. Routes use `web` middleware plus `throttle`. |

---

## 6. The Vue/TS API

```ts
// app.ts
app.use(createXelqunForms({ components: { rating: () => import('./fields/RatingField.vue') } }))

// page
const form = useXqForm(props.form)            // or <XqForm :form="props.form" :action="store.url()" />
form.state.data.items                          // reactive
await form.submit(store.url(), { method: 'post' })
```

| Piece | File | Behavior |
|---|---|---|
| **Form store** | `core/store.ts` | `createFormStore(schema)` creates reactive `state`, `errors`, a `nodes` map (key → reactive node), `editVersion` per path, and `get`/`set(path, value, source)` where source is `user`, `server` or `computed`. Converts between paths and nested objects. |
| **`useField(path)`** | `composables/useField.ts` | Returns `{ value (writable computed), error, node, visible, required, disabled, onBlur }`. A user write bumps `editVersion` and notifies the queue. Used by every field component, including custom ones. |
| **Condition evaluator** | `core/conditions.ts` | `evaluate(ast, get)` is pure. Each node's `visible`/`required`/`disabled` is a Vue `computed`, so Vue tracks dependencies itself and there is no server call. |
| **Expression evaluator** | `core/expressions.ts` | `evaluate(ast, get)` is pure and has no parser. Each computed node gets a `watchEffect` that writes the result into state with source `computed`, so the JSON viewer and submit see it. |
| **Patch applier** | `core/patch.ts` | Drops a patch if `seq < lastAppliedSeq`. `set` skips any path whose `editVersion` changed after the request snapshot. `schema` deep-merges into the existing reactive node objects (never replaced), so there is no re-mount and focus and cursor stay. Handles `add`/`remove`, replaces the dependency map, and merges errors. |
| **Component registry** | `registry.ts` | `type → component` (sync or async, so heavy fields are code-split). Built-in defaults plus user overrides. An unknown type renders a dev warning box. |
| **Request queue** | `core/queue.ts` | Collects changed paths that are live or in the dependency map. Flush triggers are `change` (next tick), `blur`, or `debounce` (one timer, batched). On flush it aborts any in-flight request and merges the aborted request's paths into the new one, then sends `seq = ++seq` with a state snapshot. `form.submit()` awaits `queue.idle()`. Exposes a request log for the playground's Network panel. |
| **HTTP client** | `core/http.ts` | `fetch` with the `X-XSRF-TOKEN` cookie header, JSON, an AbortSignal, and errors mapped (419 → token expired, so the page reloads). |
| **Submit** | `core/submit.ts` | `router.visit(url, { method, data: { data: state.data }, errorBag: form.id, preserveScroll: true })`. Watches `page.props.errors[form.id]` and maps them into the store, switches to the tab with the first error, and focuses it. |

---

## 7. The playground page plan

- **Route** (`routes/web.php`): `Route::get('/playground/forms/{example?}', [PlaygroundFormsController::class, 'show'])->name('playground.forms')` and `Route::post('/playground/forms/{example}', [..., 'submit'])->name('playground.forms.submit')`. Public, with no login. `{example}` is a slug, so each example has its own URL and only the active form is built. Add a link from `Welcome.vue`.
- **Controller** `app/Http/Controllers/PlaygroundFormsController.php`: `show` renders `playground/Forms` with `examples` (slug, title, description), `active`, `form` (`->toSchema()`), `source` (the PHP file contents of the example class, read through `ReflectionClass::getFileName()`), and flashed `submitted` data. `submit` validates (or saves, for the order form), then redirects back with the cleaned payload flashed.
- **Example classes** (`app/Playground/Forms/`): an `Example` enum or registry mapping slugs to `BasicFieldsForm`, `ConditionalFieldsForm`, `DependentFieldsForm`, `RepeaterTotalsForm`, `FileUploadsForm`, `ValidationErrorsForm` and `TrickyOrderForm`.
- **Demo data:** migrations, models, factories and seeders for `countries`, `states`, `orders` (type, customer_name, company, vat_number, country_id, state_id, total) and `order_items` (order_id, sort, product, qty, price, subtotal, attachment). The order example can create and edit (`?order=ID`).
- **Inertia page** `resources/js/pages/playground/Forms.vue` with a persistent `layouts/PlaygroundLayout.vue` (header with the Xelqun wordmark, a light/dark toggle and a GitHub link). Two columns on wide screens:
  - left: the example nav (vertical list on desktop, scrolling pills on mobile) and the rendered form card
  - right, an **Inspector** with tabs: **State** (live JSON, with changed paths flashing), **Errors**, **Network** (the queue's request log: seq, changed paths, ms, patch body), **PHP** (Shiki-highlighted source), and **Submitted** (the last cleaned server payload)
- The playground builds its UI from the package's `Xq*` primitives (dogfooding). Supporting components go in `resources/js/components/playground/` (`ExampleNav`, `InspectorPanel`, `JsonView`, `CodeView`, `ThemeToggle`).
- Wayfinder: the page uses `@/routes/playground` helpers for the submit URLs.

---

## 8. Sequence diagrams

**Live update (country → state)**
```mermaid
sequenceDiagram
    actor U as User
    participant F as Field (Vue)
    participant S as Form store
    participant Q as Request queue
    participant C as UpdateController
    participant P as OrderForm (PHP)
    U->>F: picks Country = PH
    F->>S: set("data.country_id", "PH", user)  (editVersion++)
    S->>S: re-evaluate conditions + computed locally
    S->>Q: changed "data.country_id" (in dependency map)
    Q->>Q: wait for mode (change/blur/debounce), batch paths
    Q->>Q: abort in-flight request, merge its paths
    Q->>C: POST /_xelqun/forms/update {token, seq: 7, state, changed}
    C->>C: decrypt token, check user + expiry, authorize()
    C->>P: rebuild form with posted state
    P->>P: afterStateUpdated hooks for changed paths
    P->>P: re-run closures depending on them (RecordingGet)
    P-->>C: Patch
    C-->>Q: {seq: 7, set, schema, errors, dependencies}
    Q->>Q: seq older than latest? drop
    Q->>S: apply (skip paths edited since request)
    S-->>F: options update in place, no re-mount, focus kept
```

**Submit**
```mermaid
sequenceDiagram
    actor U as User
    participant V as XqForm (Vue)
    participant I as Inertia router
    participant Ctl as PlaygroundFormsController
    participant P as OrderForm (PHP)
    U->>V: Save
    V->>V: await queue.idle()
    V->>I: post(url, {data}, {errorBag: "order-form"})
    I->>Ctl: POST /playground/forms/order
    Ctl->>P: save($request)
    P->>P: evaluate conditions, drop hidden/disabled values
    P->>P: recompute computed values (server AST)
    P->>P: validate UUID-keyed state (labels as attribute names)
    alt invalid
        P-->>I: 302 back + errors {"data.items.9f1c.price": [...]}
        I-->>V: page.props.errors["order-form"]
        V->>V: map to fields, open tab, focus first error
    else valid
        P->>P: rows → lists, finalize temp uploads
        P->>P: DB transaction: Order + sync items
        Ctl-->>I: redirect with flash (submitted payload)
    end
```

---

## 9. Testing strategy

| Layer | Where | What |
|---|---|---|
| **Pest unit tests** (package) | `packages/forms/tests/Unit` | `When` → AST, the condition evaluator, the expression lexer, parser and evaluator, `StatePath` resolution (relative, `$.`, wildcards), `RecordingGet`, token encrypt/decrypt/tamper/expiry, repeater row keys, and the empty repeater serializing as `{}`. |
| **Schema snapshots** (package) | `tests/Feature/Schema` | One fixture form per field and layout → `expect($form->toSchema())->toMatchSnapshot()`. A snapshot diff is a contract change, so decide whether `$xq` needs a bump. |
| **Shared conformance files** | `tests/fixtures/conformance/*.json` | `{ ast, state, expected }` cases. Pest uses a dataset and Vitest uses `describe.each` over the same files. |
| **Pest feature tests** (package, Testbench) | `tests/Feature/Http` | Update endpoint: a dependent closure re-runs and an unrelated one doesn't, wrong user → 403, tampered token → 403, expired token → 419-style error, `authorize()` false → 403. Upload: mime and size rules, temp file stored and pruned. `validate()`: hidden values dropped, computed values recomputed, nested errors keyed by UUID with friendly attribute names. Eloquent fill and save with BelongsTo and HasMany. |
| **Vitest** (package) | `tests/js` | Store and `useField`. The patch applier keeps a focused `<input>` (same element and `document.activeElement` after a patch). The queue with fake timers and a fake fetch: batching, abort and merge, stale seq dropped, edited paths not overwritten. The registry. Rendering each snapshot fixture without errors. |
| **Playground feature tests** | `tests/Feature/PlaygroundFormsTest.php` | Each example slug renders `playground/Forms` with the expected props (`assertInertia`), and submit redirects with errors or flash. |
| **Pest browser tests** | `tests/Browser/TrickyOrderFormTest.php` (+ one per example) | Visit `/playground/forms/order`. Pick a country and states appear. Choose type business and the section appears. Add 2 rows, type qty and price, and the subtotals and grand total update. Upload a file in row 2. Submit with a bad price and the error appears under row 2's price. `assertNoJavascriptErrors()`. |

Commands: package `vendor/bin/pest`, `vp test`, `vendor/bin/pint`. Playground `php artisan test --compact tests/Browser`. Feature and browser tests rebuild `public/build` first if a front-end source file is newer than it (see `rebuildAssetsWhenStale()` in the playground's `tests/Pest.php`).

---

## 10. Milestones

Each one ends with something visible at `/playground/forms`.

| # | Milestone | Done when you can see… |
|---|---|---|
| **0** | **Package setup and linking.** Clone, scaffold both manifests, provider, `xelqun.css` tokens, one `XqButton` primitive, Composer path repo, npm workspace, Vite `dedupe`, CI skeleton, first push. | A temporary route renders `XqButton` from the package with `--xq` colors, a PHP value comes from the package provider, and editing the package's `.vue` file hot-reloads. `npm ls vue` shows one copy. |
| **1** | **Playground page shell.** Route, controller, `Example` registry with 7 empty example classes, `PlaygroundLayout`, example nav, Inspector (State/PHP tabs), theme toggle, demo migrations and seeders. | The page with all 7 tabs, highlighted PHP source for each, and working light/dark. |
| **2** | **Schema and TextInput renderer.** `Form`, `Component`, `Field`, `TextInput`, `Textarea`, `Section`, `SchemaSerializer` (`$xq: 1`), `XqForm`, `XqNode`, registry, store, `useField`, submit through Inertia with an error bag, server validation (flat paths). First snapshot tests and the first browser smoke test. | "Basic fields" renders text inputs, the State panel updates as you type, and submitting shows server errors under fields and the cleaned payload. |
| **3** | **Remaining fields and layouts** (can be interleaved): Select (static), Checkbox, Toggle, Radio, DatePicker, Hidden, Grid, Fieldset, Tabs. | "Basic fields" shows every MVP field type in a nice layout. |
| **4** | **Conditions.** `When`, the PHP evaluator, the TS evaluator, the conformance files, server-side dropping of hidden values, `requiredWhen` in validation. | "Conditional fields": sections and fields appear and disappear instantly, and submitting while hidden shows the value dropped in the Submitted panel. |
| **5** | **Live protocol.** Token, `/update`, `Get`/`Set`, `RecordingGet` + dependency map, `afterStateUpdated`, queue, patch applier, Network panel, plus the searchable/async Select search endpoint. | "Live dependent fields": country → states with requests visible in Network, fast typing produces one request, and focus is never lost. |
| **6** | **Repeater and computed values.** UUID rows (add, remove, reorder), template nodes, expression parser and evaluators, `sum()`, server recompute, nested errors with friendly names. | "Repeater with totals" with live subtotal and grand total, and "Validation errors" with errors on `items.<uuid>.price`. |
| **7** | **File upload.** Temp upload endpoint, temp store, finalize on save, prune command, FileUpload inside a repeater row. | "File uploads" with progress, preview, remove, and a file finalized after submit. |
| **8** | **Eloquent and the tricky order form.** `fill`/`save`, `relationship()` on Select (BelongsTo) and Repeater (HasMany), the full `TrickyOrderForm`, and the browser test from section 9. | All 5 proof points pass, by hand and in the browser test. Saving, then reopening `?order=ID`, refills everything. |
| **9** | **Polish for the public demo.** Design pass, a11y (labels, aria-invalid, focus rings), empty and loading states, mobile layout, lazy heavy fields. "Eject" command noted for later. | A demo page you would share. |

---

## 11. Risks and unknowns

| Risk | Why it matters | Reduced in |
|---|---|---|
| Tailwind doesn't scan package classes through the symlink, or `@source` in an imported CSS file misbehaves | Unstyled components | **M0** (fallback: explicit `@source` in host) |
| Two copies of Vue from the linked package | Reactivity and `inject` silently break | **M0** (workspace hoisting, `dedupe`, `npm ls vue`) |
| Host `vue-tsc` type-checks package source and trips over strictness differences | Red CI in user apps | **M0** (match strict settings, run `vue-tsc` in both) |
| Losing focus or cursor when a patch arrives | The biggest "feels broken" bug | **M5** (Vitest focus test, keyed in-place merge) + **M8** browser test |
| Race conditions: stale responses, overwriting the user's typing | Lost input | **M5** (seq, abort + merge, `editVersion` guard, fake-timer tests) |
| The dependency recorder misses closure branches | Fields don't refresh | **M5** (re-record on every server run, send a fresh map, `dependsOn()` escape hatch) |
| PHP and JS evaluators disagree (floats, null, empty string) | Client shows a different total than the server saves | **M4/M6** (shared conformance files, server recompute is the source of truth) |
| Data leaks: a hidden field's options or default values sent to the browser | Security | **M2/M5** (server-hidden nodes never serialized, tests assert their absence) |
| Token replay or tampering, or a forged form class | Security | **M5** (encrypted, user-bound, ttl, `instanceof Form` check, `authorize()` on every call, tests) |
| `json_encode([])` → `[]` for empty repeaters, and UUID keys in validation messages | Subtle client bugs, ugly errors | **M6** (object casting, attribute-name map, tests) |
| Large option lists bloat the schema | Slow pages | **M5** (searchable Select endpoint, `preload` limits) |
| Several forms on one page mixing errors | Wrong errors shown | **M2** (error bag per form id) |
| The public demo's upload endpoint gets abused | Disk filling up | **M7** (throttle, size limits, short TTL, prune via scheduler) |
| Inertia v3 and Vite+ details (error bags, persistent layouts, `vp` quirks) | Rework | Check with `search-docs` when each is first used (M1/M2) |
| Scope creep for one part-time developer | Never shipping | Each milestone is small and visible; M9 is optional polish |

---

## Implementation status (2026-11-05)

Milestones 0–9 are built and tested. Milestone 9 added failure and loading feedback for live updates, screen reader and keyboard support across every field type, reduced motion, and WCAG AA text contrast. It has not been checked with a real screen reader, on real phones, or in Safari and Firefox.

| Proof point | Covered by |
|---|---|
| Country → state, states loaded from the server | `tests/Browser/TrickyOrderFormTest.php`, package `LiveUpdateTest` |
| Section shown only for business customers | browser test, package `ValidationTest` (hidden values dropped) |
| Repeater with live `qty × price` subtotal and grand total | browser tests, Vitest `store.test.ts`, server recompute in `ValidationTest` |
| File upload inside a repeater row | browser test, package `UploadTest` |
| Server errors on the correct nested field | browser tests (`Price (Item 2)`), package `ValidationTest` |

**Test counts:** package Pest 985, package Vitest 511, playground feature and browser 77.

### Milestone 10: foundations

The first milestone of [`parity-plan.md`](parity-plan.md). It changes no public field API.

- **One preparation step.** `FormValidator::prepare()` now runs for update, search and validate, so the values that decide anything are server values. Static `hidden()` and `disabled()` are locked before any closure runs. The rest repeats from the posted data until the locks settle, so a closure can safely read a locked field declared after it. After five passes locks can only be added, so it always ends and a long chain can't stop partway.
- **Hidden values stay on the server.** A field hidden with `hidden()` sends no value to the browser, in the page state, in `newRow` or in a patch from a hook. Closures still read its server value. When a closure reveals the field, the patch brings the value along. `visibleWhen()` is different: the browser evaluates it, so it needs the value, and a field the user switched off simply stops counting.
- **Uploads survive a failed save.** `UploadBatch` copies files before the transaction and deletes temporary files only after commit, on the connection the record is saved on. A real rollback deletes the copies, including one from a transaction around `save()`, and the tokens stay valid. An exception after the commit leaves the files alone. Each save gets its own copy, so a failed save can't delete another save's file, and a disk that refuses a file fails the save.
- **Nesting-safe rows.** Each repeater level has its own placeholder (`{row}`, `{row:1}`), sent as `props.rowPlaceholder`. Starter rows are keyed `row-{n}`, and rows stored on a record `stored-{hash}` from their content, so a rebuilt form recognises them. If another user changed or removed a stored or related row, submitting it fails with a validation error asking the user to reload, instead of losing its locked values.
- **One field per path.** Two fields on one state path now throw a `FormDefinitionException`, because state, validation, patches and the browser all address a field by its path. To vary a field, use closures on one field. Supporting duplicates would need a change to how nodes are keyed.
- **Smaller fixes.** `visible(Closure)` reads the row it belongs to, closures no longer run before `authorize()`, `orderable(false)` is enforced on the server, `Select` refuses `MorphTo`, a locked `NULL` column stays `NULL`, retrying `save()` on the same form after a rollback writes the record, and the first error gets focus, including in a closed tab and after rows move.

Limits to keep in mind:

- A `computed()` expression runs in the browser too, and can't read a `hidden()` value there. The browser then shows a different total from the one the server saves. If a total depends on a hidden value, calculate it in a server closure.
- A field locked by a closure can only be found by running closures, so on the first pass `hidden()` and `disabled()` closures may read a posted value that later gets locked. Only the final pass decides anything, but these closures must not act on what they read. Static `hidden()` and `disabled()` have no such window.
- `validate()` makes uploads final as soon as it returns. Callers who write the data themselves should wrap it in `DB::transaction()`; `save()` does this for them.
- A value a hook writes into a disabled or hidden field lasts one request. Later requests read the server's value, because the server can't tell the browser echoing it from tampering. M12 kept this. To save a locked value that follows other fields, use `disabled()->saved()->dehydrateStateUsing()`, which derives it on the server at save time.
- Stale rows are only detected for rows the browser posts. A row another user added is not posted, so saving still replaces the list without it.
- `hidden()` and `disabled()` closures run on every update and search, usually twice when a closure locks a field.

### Milestone 11: plugin foundation

The package can now be extended from outside. The proof is `tests/Fixtures/ExamplePlugin`, a separate package with a namespaced type, search, uploads, its own saving, a validation rule and a translation. The playground installs it with `xelqun-forms:plugin`, and a browser test uses it, with no change to package source. Authors start from [`plugins.md`](plugins.md).

- **Contracts.** `HasSearchResults`, `AcceptsUploads`, `ContainsRows`, `PersistsItself` and `NeedsServer` replace every check on a concrete field. Built-in fields use them too, and two tests fail if such a check comes back, in PHP or in the client core.
- **`Repeater` is a client of its own contracts.** Its HasMany sync moved from `ModelPersister` to `Repeater::persist()`. A plugin field inside a relationship row gets `persist()` with the row's model. M16 made a repeater nested in such a row save its rows.
- **Uploads fail closed.** `HandlesUploads` holds the rules `FileUpload` used to own, and `UploadBatch` re-checks a token's scope before storing it, so a plugin that leaves the rules out still can't copy another field's upload.
- **The client reads `containsRows`** on a node instead of its type. It is a new optional key, so `$xq` stays 1.
- **`createXelqunForms({ plugins })`** registers plugins. A collision throws in development and is logged in production, where the first registration stays. `replaces: [...]` is the explicit flag from decision 18, and it only works on built-in fields. `setup({ app })` gets no access to the registry, so it can't get around these rules.
- **`xelqun-forms:plugin`** reads `extra.xelqun` and validates it, because those values reach a shell command and the app's source. It ignores comments, and refuses spread options or a comment inside the call rather than guess. It shares its patching with `xelqun-forms:install`.
- **`make:xelqun-field`** creates a PHP class and a Vue component. The Vue stubs are real files that Vitest mounts, so a broken stub fails CI.
- **Also added:** `configureUsing()`, `Macroable` on every component, translations under `xelqun-forms::messages`, `@api` tags, `InteractsWithForms` and `@xelqun/forms/testing`.

Changes that can break existing code, for the release notes:

- `Repeater::isSafeRowKey()` moved to `StatePath::isSafeRowKey()`.
- `Repeater::discardUnsafeRows()` and `restoreRowOrder()` are private. Call `normalizePostedRows()`.
- A hand-built node with rows needs `containsRows: true`.
- Package messages moved to translation keys, so a JSON translation of the old English text no longer applies.

Limits to keep in mind:

- The plugin starter is stubs and the example plugin. A public starter repo needs your go-ahead, because it's a new public GitHub repo.
- `make:xelqun-field` writes into `app/` and `resources/js/forms`, so a plugin author moves the files into the plugin.
- Plugin limits are listed at the end of [`plugins.md`](plugins.md). The main ones are in that list.

### Milestone 12: state and field basics

One form can now behave differently on create and edit. The proof is the order example in the playground: after saving, it shows a read-only order number, locks the customer type and drops the confirmation toggle. A feature test posts tampered values to that edit and they change nothing.

- **Operation.** `Form::getOperation()` returns `create` or `edit` from the record, or the context's `operation` (`Form::make($order, ['operation' => 'view'])`). It is fixed in the constructor, so saving a new record leaves it on `create`, and it comes back from the encrypted token, never from the request. Closures can ask for `$operation`.
- **`hiddenOn()`, `visibleOn()` and `disabledOn()` resolve when the form mounts.** They become plain `true` or `false`, so they get the static guarantees of `hidden()` and `disabled()`: locked before any closure runs, with no stub sent to the browser. As in Filament, each replaces `hidden()`, `visible()` or `disabled()`, whichever came last.
- **`readOnly()` is a server guarantee.** The posted value is dropped before closures run, rules are skipped, and search, upload and hooks are refused. Text inputs and textareas use the `readonly` attribute. Every other control renders as disabled, because it has no read-only mode, and this includes plugin fields that use `useField`.
- **State hooks.** `afterStateHydrated()` runs after the whole form is filled, so it can read any field, including inside rows. `formatStateUsing()` is a shorthand for it, and `state()` sets the value. `dehydrateStateUsing()` runs after the field's own `dehydrateState()`, so it receives the cast value.
- **Flags can turn off live.** The browser merges patches and never removes a key, so a field that could be read-only sends `readOnly` either way, and `extraAttributes` is replaced whole.
- **`saved()` is `dehydrated()`.** Filament 5 documents it as the same idea, so it delegates, and both now take a closure. `dehydrated()` stays. Unlike Filament, `disabled()->saved()` writes the server's value, so a browser can't change it.
- **Display options.** `extraAttributes()` goes on the outer `.xq-cell`, so it works for layouts and plugin fields too. `autofocus()` reuses `focusPath`. `hiddenLabel()` keeps the label for screen readers, and `inlineLabel()` applies at the `sm` breakpoint and up. These node keys only appear when used, so `$xq` stays 1 and existing snapshots don't change.

Changes that can break existing code, for the release notes:

- A path starting with `data.` is no longer treated as absolute by `Get`, `Set`, `When`, `computed()` or `dependsOn()`. It was never documented, and it made a field named `data` replace the whole form. Write `$.name` for the form root. This fails quietly: `$get('data.name')` now returns null instead of throwing, so search for it.
- `Field::dehydrated()` accepts a closure, so a subclass that overrides it must widen its parameter type.

Limits to keep in mind:

- `readOnly()` writes nothing over a stored value. A new record or row saves its default. A row with no model is the exception: it is stored whole, so its value stays in the row, and with `formatStateUsing()` that is the formatted value. Pair the two with `dehydrateStateUsing()` there.
- `readOnly()` on a field with rows covers every field in those rows. `disabled()` on a repeater does not do this yet.
- A `disabled()` field in a row with no model is still dropped from the stored row, because it isn't dehydrated. Add `saved()` to keep it, or use `readOnly()`.
- A dropped field is missing from `mutateBeforeSave()`. Code that reads it there must allow for that, or the field needs `saved()`. The order example hit this: without `saved()`, a locked `type` looked like a non-business order and cleared the company.
- `afterStateHydrated()` and `formatStateUsing()` run on every request, so they must be deterministic and free of side effects. They don't run for a row added in the browser, which gets its plain default.
- `dehydrateStateUsing()` is ignored on a field with rows, and it runs before uploads are moved, so on a file field it sees the token.
- `extraAttributes()` values are bound as given, so never put user input in an attribute name. Filament's `merge` option and `extraInputAttributes()` were not in scope.
- `inlineLabel()` is per field. Filament also sets it on a layout or a whole form. Checkboxes and toggles already have the label beside them, so it does nothing there.

### Milestone 13: relationships and hooks

A form now saves a record and everything around it in one transaction. The proof is the order example in the playground: tags, a morph owner and a gift recipient all save, reload and survive a tampered edit.

- **One save path.** `ModelPersister::persistScope()` saves a record and everything below it, depth-first. The form, every relationship row and every related group go through it, so `collectTopLevel`, `Repeater::isColumn()` and `persistRowFields()` are gone. A missing key still never means "clear this".
- **Form hooks.** `beforeSave()`, `afterSave()` and `afterCommit()` are methods to override on the form. The first two run inside the transaction and can roll it back. `afterCommit()` waits for the outermost transaction and never runs after a rollback, so it's the place for emails and jobs. If it throws, the exception is reported and `save()` still returns the record, because an error page would invite a second save.
- **A stage before the save.** `PersistsBeforeSave` adds `persistBeforeSave()`, which runs before the record is saved, so a component can set a foreign key on it. `PersistsItself` now applies to any component and declares `getName()`.
- **Many-to-many selects.** `Select::relationship()` accepts a BelongsToMany with `multiple()`, and the name defaults to the field's. It attaches and detaches by difference, never `sync()`, so a record outside the field's query stays attached. `pivotData()` applies to records it attaches.
- **`getOptionLabelFromRecordUsing()`** builds labels from the record for options, search results and selected values. Search still looks at the title attribute.
- **`MorphToSelect`** is two ordinary selects in a fieldset, so it needed no new Vue. Its values are the relationship's two columns. A posted type must be one of `types()`, and a record must be in that type's query, including `modifyOptionsQueryUsing()`.
- **`Group`** is a layout with no markup. With `relationship()` it edits one HasOne or BelongsTo record. A BelongsTo is saved first so its key can go on the parent. Its fields keep their state under the relationship's name, such as `data.note.body`, so errors and uploads work as they do anywhere else.
- **Repeater hooks.** `mutateRelationshipDataBeforeFillUsing()`, `mutateRelationshipDataBeforeCreateUsing()`, `mutateRelationshipDataBeforeSaveUsing()`, `afterCreate()`, `afterUpdate()` and `afterDelete()`. Returning null from a create or save mutator skips that row. A row skipped on save is kept, not deleted.

Where this differs from the plan and from Filament:

- Relationships save in schema order, not the plan's fixed BelongsToMany, HasOne, HasMany order. Nothing depends on the order between them, and sorting would add code with no effect.
- A group's missing record is only created when a field has a value, and a switched-off toggle isn't one. A field with a non-empty default counts as a value. Filament always creates the record and offers a `condition:` argument instead. Without this, leaving an optional group blank fails on any `NOT NULL` column.

Changes that can break existing code, for the release notes:

- `Select::getRelationshipInstance()` can now return a `BelongsToMany`, so a subclass that overrides it must widen its return type.
- `PersistsItself` declares `getName()`. A class extending `Field` already has it.
- `Component::getChildModel()` and `getChildSource()` are new. Any field with rows that overrides `getRowModel()` keeps working.
- A `BelongsToMany` select fills its state with the keys as the database gives them, not as strings.

Limits to keep in mind:

- ~~Any repeater inside a related row is skipped when saving.~~ M16 saves them.
- `pivotData()` applies only when attaching. A record that stays attached keeps its pivot values.
- A `readOnly()` field in a group doesn't write its default when the form edits a record whose related record doesn't exist yet.
- A field and a relationship group with the same name both use the same state path, and this isn't detected.
- Not built: `MorphOne` groups, `modifyKeySelectUsing()`, `modifyTypeSelectUsing()`, `saveRelationshipsWhenHidden()`, `saveRelationshipsUsing()`, and `condition:` on a group.

### Milestone 14: validation API

Fields can now name their rules and word their own errors. The proof is the validation example in the playground, which shows custom messages, a custom attribute and rules that compare fields, inside tabs and rows.

- **Rule helpers.** `HasValidationRules` adds Filament's named helpers to every field, such as `alphaDash()`, `in()`, `regex()`, `enum()`, `requiredIf()`, `after()` and `prohibits()`. They sit alongside `rules()`. `rule()` adds one rule, with an optional condition. Values are passed as arguments rather than joined into a string, so a comma in a value is safe.
- **Rules that name a field resolve like `$get()`.** A bare name is relative to the field's row or group, and `$.name` is the form root. State lives under `data`, so a plain `rules(['same:password'])` compares against nothing. `same()`, `gt()`, `after()`, `requiredWith()` and the others send a full path, and the error message uses the other field's label.
- **`confirmed()`** needs a `{name}_confirmation` field next to the field, also inside a row. Give it `saved(false)` when saving to a model.
- **`unique()` and `exists()`** default to the field's model and name. In a row or related group, the record to ignore is the one the form filled that row from. `Form::getRecordFor()` keeps that record from filling, so a forged row key finds nothing and can't exclude another record's row. The record is only ignored in its own table, because its id means nothing in another one. Use `ignorable` there.
- **`scopedUnique()` and `scopedExists()`** query through the model, so global scopes apply.
- **`validationMessages()` and `validationAttribute()`.** Messages are keyed by rule name and can be closures. Built-in rules that check with a closure, such as a relationship `exists` or an upload path, use them through `Field::getFailureMessage()`.
- **Values are safe to pass as they are.** `requiredIf('gift', true)`, an enum case and `null` all match, even though Laravel casts rule arguments to strings. A numeric argument to `gt()`, `gte()`, `lt()` or `lte()` is a number, not a field. A date closure that gives no date turns its rule off.

Changes that can break existing code, for the release notes:

- `Field` has about 55 new public methods, with common names such as `in()`, `after()`, `before()`, `string()`, `enum()` and `json()`. A field subclass with a method of the same name and a different signature no longer loads: PHP stops with "must be compatible". Rename the method or match the signature.
- Errors from built-in checks that use a closure, such as an invalid select value or a relationship that doesn't exist, now name the row: `Plan (Item 1)`. They used to give the label alone.
- A field with `saved(false)` or `dehydrated(false)` now runs its rules, unless it is disabled. They used to be skipped, so `->same('password')->saved(false)` compared nothing and `->required()->saved(false)` required nothing. A form that relied on that shows errors now.

Limits to keep in mind:

- `rules()` strings still go to Laravel as written, so `same:password` there doesn't work. Use the helper.
- `unique()` checks the posted value, before `dehydrateStateUsing()`. A field that lowercases on save compares the value as typed.
- Two rows of one form aren't compared with each other, so both can post the same "unique" value. Add `distinct()` to the field for that.
- `unique()` checks and then saves, so two requests at once can both pass. A unique index on the column is what guarantees it.
- A rule closure can't ask for `Get`. Filament's wrapped form, a closure that returns the rule, wasn't in scope. A condition on `rule()` can.
- `after()` and `before()` read a value PHP can parse as a date as a date, like Laravel does. Write `$.today` for a field named like a date.
- `requiredIf()` and similar rules don't show the asterisk. `requiredWhen()` does, and it runs in the browser.

### Milestone 15: everyday fields

The package now covers the fields most forms need. The proof is the "All fields" example in the playground, and a browser test types through it and submits it. Every new limit is checked on the server, because the browser can post anything.

- **Dates.** `DateTimePicker` is the base, `DatePicker` turns the time off and `TimePicker` turns the date off. The browser always holds one fixed format in the shown timezone, so `format()` and `timezone()` convert on the server, on fill and on save, and there is no timezone logic in JS. `displayFormat()` takes PHP date tokens. `disabledDates()` is refused on the server. `hoursStep()`, `minutesStep()` and `secondsStep()` set the time field.
- **New fields.** `CheckboxList`, `ToggleButtons`, `TagsInput`, `Slider`, `KeyValue` and `ColorPicker`. Each is lazy-loaded, so a form only pays for the fields it uses.
- **Options.** `HasOptions` gains groups (a nested array), `disableOptionWhen()` and `boolean()`, shared by `Select`, `Radio`, `CheckboxList` and `ToggleButtons`. A disabled option is refused on the server, unless the record already has that value, so an old record can still be saved.
- **Select.** `reorderable()`, plus `loadingMessage()`, `noOptionsMessage()`, `noSearchResultsMessage()`, `searchPrompt()` and `searchingMessage()`.
- **TextInput.** `revealable()`, `trim()`, `mask()`, `stripCharacters()`, `datalist()`, `copyable()`, `length()`, `prefixIcon()` and `suffixIcon()`.
- **A new step before validation.** `Field::normalizeState()` cleans up a posted value before the rules see it. `trim()`, `stripCharacters()`, `TagsInput` and `KeyValue` use it, and computed values run again afterwards. A locked field keeps its server value.
- **`Component::evaluateConfigured()`** sends an optional prop whenever it was set, even when it comes out false, because the browser merges patches and would otherwise keep the old value.
- **`HasColumns`** replaces the `columns()` code that `Layout` and `Repeater` each had.

Where this differs from the plan and from Filament:

- `maska` wasn't added. Installing it was blocked in this environment, and the mask grammar is only three tokens, so `core/mask.ts` does it in about 30 lines with tests. It doesn't support Filament's dynamic `RawJs` masks, which the plan already ruled out.
- Icon names come from a fixed set in `resources/js/ui/icons.ts`, and `createXelqunForms({ icons })` adds more. A dynamic import of lucide would pull all of its icons into the main bundle.
- `reorderable()` on `Select` and `TagsInput` started with buttons only. M16 added dragging, and the buttons stay.

Changes that can break existing code, for the release notes:

- `DatePicker` now extends `DateTimePicker`. `formatStateFromRecord()`, `getMinDate()` and `getMaxDate()` live there. `DatePicker::minDate()` keeps what it is given and formats it later.
- `Repeater` and `Layout` get `columns()` from `HasColumns`.
- `HasOptions` now defines `formatStateFromRecord()` and `dehydrateState()`. A class that uses the trait and overrides them keeps its own, and calling `parent::` from there skips the trait's.
- `toOptionList()` can add `disabled`, and `getOptions()` flattens groups. Use `getOptionList()` to keep them.
- `Field` has a new public `normalizeState()`, `Component` a new protected `evaluateConfigured()`.
- A key missing from an array row now gets `getDefaultState()` as it is, without going through `formatStateFromRecord()`. A field that relied on that second pass should do the work in `getDefaultState()`.
- A backed enum default becomes its value, also outside rows.

Limits to keep in mind:

- `CheckboxList` has no `relationship()`. Use `Select` with `multiple()` for a BelongsToMany.
- `hoursStep()`, `minutesStep()` and `secondsStep()` only guide the time field. The server doesn't refuse a time between steps.
- A `DateTimePicker` keeps its last full time while the time is being retyped, so a time can't be cleared on its own. Clearing the date clears both.
- `copyable()` gives no feedback when the browser refuses the copy, or has no clipboard.
- `mask()` is a typing aid. Check the format with a rule too. A numeric field with a mask holds text while typing, so a `computed()` reading it can't calculate in the browser. The server result is correct.
- `disableOptionWhen()` on a searchable field only marks options from search results and the current list.
- `KeyValue` values are strings, and a number-like key can't hold its position, because JS sorts those keys first.
- `Slider` has no pips, vertical mode, `minDifference()` or non-linear points, and `tooltips()` shows the value beside the track, not above each handle.
- `TagsInput` has no `color()`. `ColorPicker` has no `hsla()`.
- `displayFormat()` shows English month and day names unless the browser's locale says otherwise, because no `locale()` option exists yet.

### Milestone 16: repeater upgrade

Repeaters now nest, and they save and reload at every level. The proof is the order example: each line item has extras, a second level of rows in its own table, and a browser test adds one to a saved order and reloads it. The new "Repeaters" example shows the other shapes.

- **Nested repeaters save.** `Repeater::persist()` no longer stops at a row, so relationships work at any depth through `persistScope()`. Each level only sees its own records: a row key naming a record of another parent fails validation as stale. Rows in one JSON column come out as plain lists at every level, because `Field::toPlainRows()` replaces the depth-sorting code in `toPlainData()`, and `save()` and `validate()` now share it.
- **`simple()`** holds a flat list of values. Inside the form it is still rows of one field, so the field keeps its rules and its error path.
- **`addable()` and `deletable()` are enforced on the server.** A posted new row is dropped and a removed row comes back. `enforceStateLimits()` runs after locks are applied, so a closure deciding this reads the server's values.
- **`distinct()`** is a rule on a field inside a repeater. No two rows may share a value, only one row of a true or false field may be on, and each value of a multiple field may appear once. It stays within its own repeater.
- **`cloneable()`** adds a copy after its original, in the browser. File fields start empty, so two rows never own one stored file, and nested rows get new keys, because the old ones name records of the original.
- **`collapsible()` and `collapsed()`.** A row with an error always opens.
- **`table()` with `TableColumn`, `grid()` and `itemNumbers()`.**
- **`requiresDeleteConfirmation()`** uses `XqConfirmDialog`, built on Reka's alert dialog. M20 can reuse it.
- **Drag and drop** uses SortableJS, through one `useSortable` composable shared by repeaters, `KeyValue`, `TagsInput` and multiple selects. It loads only when a list can be dragged, as a separate chunk of about 13 KB. After a drop it puts the element back and lets the state move it, because Vue owns the order. Each repeater level has its own handle, so a drag never starts at two levels. `forceFallback` makes mouse and touch work the same way and keeps a drag from dropping text into an input. The move buttons stay for keyboard users. `reorderable()` is the same as `orderable()`, and `reorderableWithDragAndDrop(false)` turns dragging off.

Where this differs from the plan and from Filament:

- Filament confirms a delete through an action. Actions arrive in M20, so `requiresDeleteConfirmation()` is a plain method for now.
- Item numbers are on by default, because the repeater already showed them. Filament has them off.

Changes that can break existing code, for the release notes:

- A repeater with no relationship inside a related row now saves to a column on that row, like any other field. It used to be skipped. Add the column, or give the repeater `saved(false)`.
- A nested relationship repeater now saves its rows, and deletes rows the browser removed.
- An error in a nested row names every level: `Qty (Section 2, Line 2)`.
- Repeater nodes gain `addable`, `deletable`, `itemNumbers` and `draggable`, and fields that hold a file gain `acceptsUploads`. The client treats a missing key as the old behaviour, so `$xq` stays 1.

Limits to keep in mind:

- Touch dragging comes from SortableJS and hasn't been tried on a real phone. The move buttons work everywhere.
- A new row doesn't get `defaultItems()` for a repeater nested inside it. It starts with no inner rows.
- `cloneable()` copies what the browser holds. A hidden or disabled field in the copy takes its default, because a new row has no server value. Options loaded for the original come along, but the server hasn't checked them for the copy until the next update.
- `table()` lines columns up with the row's direct fields, so a field the server hides shifts the cells after it.
- `addable(false)` and `deletable(false)` compare with the rows the form was filled with, so a row added by someone else since then counts as new and is dropped when the form is saved.
- `disableOptionsWhenSelectedInSiblingRepeaterItems()`, `fixIndistinctState()`, `compact()` and `itemLabel()` per row weren't in scope.

### Milestone 17: files

A file field can now hold a list. The proof is the order example: each line item has a photo gallery saved to its own table, inside a repeater row.

- **`multiple()`, `minFiles()`, `maxFiles()` and `minSize()`.** The state is a list of tokens and stored paths, which can be mixed. Each entry goes through the same check as a single file: an upload made for this field, or a path the record already had. `promote()` reads the shape of the value, so `AcceptsUploads` didn't change and plugins get this through `HandlesUploads`.
- **`relationship()` on `FileUpload`** keeps each file as a row of a HasMany or MorphMany. Rows of files that stay are kept, so other columns on them survive. Only a path the record already had is accepted, so a form can't claim another record's file.
- **`reorderable()`** uses `useSortable`, with move buttons for keyboard users.
- **`pasteable()`** is on by default and works while the field has focus, not anywhere on the page, so two file fields don't fight over a paste.
- **`downloadable()`, `openable()` and `avatar()`.** A private file gets a temporary link, or no link if the disk can't sign one, and never a public URL.
- **Every on-or-off file option takes a closure.** `deletable(false)` is enforced on the server, the same way as on the repeater: a stored file that was removed or replaced comes back.
- **`maxParallelUploads()`** defaults to 1, because on SQLite two uploads at once fail with "database is locked" in the rate limiter, and SQLite is what a new Laravel app runs on. Raise it on MySQL, PostgreSQL or Redis. Files keep the order they were picked in, whichever finishes first. `store.trackUpload()` lets a submit wait for files still in the queue, which have no request yet.

Changes that can break existing code, for the release notes:

- A file name is no longer a link. Add `openable()` for that. This keeps a file from being linked unless the form asks for it.
- `HandlesUploads` now defines `formatStateFromRecord()`, `getDefaultState()` and `normalizeState()`. A field that uses the trait and overrides them keeps its own.
- `FileUpload` now implements `PersistsItself`. It only takes over saving with `relationship()`.

Limits to keep in mind:

- Removing a file doesn't delete it from the disk, with or without a relationship. That matches single uploads before this. A cleanup job can find unused files.
- `maxParallelUploads()` is per field, so two fields started at the same moment can still hit the SQLite lock. Only that file fails, and it can be picked again.
- A private file's link lasts 5 minutes, so it fails on a page left open longer.
- The upload route allows 30 requests a minute by default (`routes.throttle.upload`), so a large gallery can hit it. Raise it in the config.
- `multiple()` needs an array cast on the column, unless the files use `relationship()`.
- `minSize()` and `maxSize()` check new files, not stored ones.
- A pasted file needs the field to have focus. Clicking the dropzone opens the file picker, so tabbing to it is the reliable way.
- `appendFiles()` differs from Filament, where it works around FilePond sometimes adding files at the start. New files here go last unless the form sets `appendFiles(false)`, which puts the newest file first, so two files picked together end up in reverse order.
- `deletable(false)` protects a stored file, not the row around it: removing a repeater row still removes that row's files.
- `storeFiles(false)` and deleting removed files from the disk aren't built. They're listed under M22.

### Milestone 18: layout and display

The proof is the "Layout and display" example. Everything in it comes from PHP, `?locale=es` shows the browser text in Spanish, and leaving with changes shows the warning.

- **`Flex`** shares a row with flexbox. `from('md')` stacks it below that breakpoint, and `grow(false)` keeps a child at its own width.
- **`FusedGroup`** joins fields under one label. Each field keeps its label for screen readers, and errors list below the group, under the id each control points at.
- **`Callout`** and **`EmptyState`** take a footer of components. A callout's status brings its own icon.
- **`Text`, `Icon`, `Image` and `UnorderedList`** only show something. They live in `src/Layouts`, so no new base folder was needed. A closure reading `$get` updates live, the same way a label does.
- **Field slots.** All ten of Filament's. A slot takes a string, a closure returning one, a component or a list. Slot components travel as the field's `children` with a `slot` key, so patches, deps and row keys work with no new client code. A field in a slot throws, because nothing would check or save it.
- **Translated Vue strings.** `t('Remove :name', { name })` keys each line by its English text, so a missing line stays readable. The lines are in `resources/lang/en/client.php`, and `toSchema()` sends only those that differ from their key, so an English app sends none. A test fails if the file and the code disagree. Plugins get the same thing from `useTranslator()`.
- **`unsavedChangesAlerts()`** compares the state with a snapshot, so undoing a change clears the warning. For a form class, call it from `boot()`.

Found in review, each with a failing test first:

- `update` now refuses nodes under a hidden parent. The browser names the keys in `deps`, so a forged map could read a label or an option from a hidden section. This was already possible before slots.
- The locale goes out as `pt-BR`, because `Intl` throws on Laravel's `pt_BR`.

Where this differs from Filament:

- Colors are six names, not Filament's color arrays. `Callout::color()` isn't built, and `status()` covers it.
- `Text::make()` takes no `js()` content, which is Alpine-specific and out of scope.
- `Callout::actions()` and `controlActions()` wait for actions in M20.

Changes that can break existing code, for the release notes:

- Any `Field` now has `children` when it has slots, and a plugin field that used `childComponents` for something else has to keep it apart.
- `submitLabel` on `XqForm` no longer defaults to `'Save'`. The default comes from the translation.
- Dates follow the server's locale, not the browser's.
- `update` no longer answers for nodes under a hidden parent.

Limits to keep in mind:

- The warning can't stop the browser's back button: Inertia restores the page from history with no `before` event to cancel.
- A visit with `preserveState: true` to the same path counts as staying, even if it would replace the form.
- `FusedGroup` shows `(required)` only on the label of each field, for screen readers. The group's visible star is the only sign on screen.
- `aria-label` on an `Icon` also lands on its cell, because `extraAttributes` goes to the outer element.

### Milestone 19: wizard and browser validation

The proof is the "Wizard" example. Next stops on a missing name in the browser, on a taken username only the server can catch, and passes what a step's hook set to the next step.

- **`POST validate`** checks one step's rules against the whole form's state. The server keeps no record of the steps, so the final submit checks everything again. It runs the same `prepare()` as every other endpoint, and refuses a key that isn't a step, or a step that doesn't show.
- **`Wizard` and `Step`** with `icon()`, `completedIcon()`, `description()`, `startOnStep()`, `skippable()` and `persistStepInQueryString()`. Panels stay mounted, so uploads keep running. The header is plain buttons in a list, not Reka's stepper, because every move has to go through the step check.
- **`beforeValidation()` and `afterValidation()`** run on Next. They can set state, and the patch brings it back. A `ValidationException` keeps the user on the step.
- **Scoped errors.** Next replaces the errors of its own step and leaves the others. After a failed submit the wizard opens the step with the first error. `usePanelErrors` is shared with tabs.
- **`frontendValidation()`** checks required, accepted, email, numeric, integer and the typed lengths and limits in the browser, on Next and on submit. It is opt-in, because the user then sees those errors first, and errors only the server can find after fixing them. `conformance/validation.json` runs the same cases through Laravel's validator and `core/validation.ts`, and proves the browser never rejects what the server accepts.
- **`liveValidation()`** checks a field on the server as it changes, on blur by default. Errors come back in the patch, and one about a value the user has changed since is dropped. This closes the old limit: the protocol carried `errors`, but nothing sent them.
- **`buildPatch()`** is now its own method on `UpdateProcessor`, ready for actions in M20.

Where this differs from the plan and from Filament:

- The header doesn't use Reka's stepper, as the plan guessed it might.
- `submitAction()`, `cancelAction()`, `nextAction()` and `previousAction()` aren't built. The `actions` slot of `XqForm` gets `hasNextStep`, and the default submit button waits for the last step.
- Filament stops a step with `Halt`. Here a `ValidationException` does it, which also says why.
- A skippable wizard skips the hooks, as in Filament.

Changes that can break existing code, for the release notes:

- `endpoints` gains `validate`, and a new `validate` throttle. A config file published earlier still works, using the default.
- Patches now carry errors, and the client drops one for a value that has changed.

Limits to keep in mind:

- `frontendValidation()` skips `TextInput`s with `trim()`, `stripCharacters()` or `mask()`, because the server checks the changed value. `url`, `regex`, `in`, dates and row counts are server-only.
- A `:input` in a custom message isn't filled in the browser.
- A wizard in a closed tab still holds back the default submit button until its steps are done.
- Opening a later step from the URL skips the earlier checks. The final submit makes up for it.

### Milestone 20: action core

The proof is the "Actions" example. A row action opens a modal, the server checks its fields, and the new price lands in the row that was clicked.

- **`Action`** is a component, so it goes in `Actions`, a field slot or a callout footer with no new code, and `$get` and `$set` work relative to where it sits. `$record` is the record of its row.
- **`POST action`** has two phases: `mount` returns the modal's schema, and `run` does the work. Both rebuild the form, restore trusted state with `prepare()`, find a declared action, and check it. `run` is one transaction, and a `ValidationException` rolls it back and shows on the fields it names.
- **Modals are forms.** `ActionForm` extends `Form` and has its own client store, so live updates, search and uploads work unchanged. Its token holds the chain of actions to reach it and the state posted to each parent, so every request from the modal repeats the action's checks. Losing a permission closes the modal.
- **`authorize()` denies by default.** It takes a closure, a boolean or a policy ability. `Form::authorize()` doesn't count. An action the user can't use isn't sent to the browser, and running it is refused anyway.
- **`requiresConfirmation()`** reuses `XqConfirmDialog`. It only helps the user: nothing stops a request that skips the dialog.
- **`XqDialog`** wraps Reka's dialog, and is teleported to the body, so the modal's `<form>` never nests in the parent's.

**Security review.** Each of these has a test: a key that isn't an action, a forged row key, a row the state doesn't hold, tampered state, no `authorize()`, no policy, a disabled or hidden action, an action in a hidden section, in a disabled field or in a read-only row, a bad token, another user's token, a token for a class that isn't a form, permission lost while a modal is open, an upload from one modal used in another, hidden parent values in the modal, and rollback. The review found no way past the server checks. It did find that a second Enter in a modal ran the action twice, which is fixed.

Where this differs from the plan and from Filament:

- The endpoint is `action`, with a `phase`, not `run`, because opening a modal needs the same checks as running it.
- Filament allows an action by default. This one denies, as the plan decided.
- Notifications, `url()`, `successRedirectUrl()` and action groups aren't built, and `Callout::actions()` is `footer()` with an `Action` in it.

Changes that can break existing code, for the release notes:

- `endpoints` gains `action`, and there is a new `action` throttle. A config file published earlier still works, using the default.
- `FormToken` gains `actions`. Tokens issued before this still decrypt.
- Upload scopes now go through `Form::getUploadScope()`, which for a normal form is the same value as before.
- `Form::walk()` now visits a repeater's slots, so a callback sees them after the rows.

Limits to keep in mind:

- The parent's state travels in the modal's token, so a very large form makes every request in its modal large.
- An action's result can only change the form. To show a message or redirect, set a field or throw a `ValidationException`.
- `$set` lasts for one request, as everywhere else: a disabled field goes back to its server value on the next one.
- `Try again` in a modal retries live updates, not the action. Submit the modal again for that.

### Milestone 21: action features

The proof is the order example: the plus beside the country select opens a modal, the new country is saved and selected, and a pencil appears to edit it.

- **`createOptionForm()`, `editOptionForm()` and `manageOptionForm()`** on `Select`, with `createOptionUsing()`, `updateOptionUsing()`, `fillEditOptionActionFormUsing()`, modal headings, and `createOptionAction()`, `editOptionAction()` and `manageOptionActions()` to change the buttons. They are ordinary actions built when the field is placed, so each row gets buttons tied to its own copy of the field.
- **Neither is allowed by default.** Create needs the related model's `create` policy, and edit its `update` policy for the selected record.
- **Edit looks the record up through the relationship's query**, so a posted key outside it opens nothing. `Action::record()` makes that the action's `$record`, and a policy never falls back to the model for such an action. Filling uses the modal's fields only, so other attributes never reach the browser.
- **`Form::refreshComponent()`** sends a component again with the patch, so the new option or label shows at once.
- **`prefixAction()`, `suffixAction()` and their plural forms** are on every field, as slots beside the control.
- **`extraItemActions()`** puts buttons in each row's header, and they work on that row. They travel with the row's nodes, and the row lists leave them out, so table columns stay lined up.

Where this differs from Filament:

- Filament leaves option actions allowed unless you authorize them. Here they deny, as decision 10 says.
- `createAnother`, `getItemState()` and `$arguments` aren't built. An item action already reads its row with `$get`.
- Edit needs a relationship. Without one there is no way to find the record, so the button stays hidden.

Changes that can break existing code, for the release notes:

- An action on a field disabled by `disabledWhen()` is now refused.
- The `FieldSlot` type gains `prefixAction`, `suffixAction` and `optionAction`.
- `Action::model()` also takes a closure, so `getModel()` is evaluated each time.

Limits to keep in mind:

- The default create and update write only columns. A modal with a BelongsToMany field needs `createOptionUsing()` or `updateOptionUsing()`.
- With static options and `createOptionUsing()`, the new key has to be in `options()` too, or the select's own rule rejects it on submit. Use a closure for `options()`.
- The button of an action beside a field disabled by `disabledWhen()` stays clickable, and the server refuses it.
- An action with a label of "Create" or "Edit" reads the same to a screen reader on each select. Set `label()` in the action callback if a form has several.

### Milestone 22: heavy fields

#### Builder

The proof is the "Page editor" example. Each block has its own fields and rules, and a page lands in one JSON column as a list of `{type, data}`.

- **`Builder` extends `Repeater`**, so `cloneable()`, `collapsible()`, `minItems()`, `extraItemActions()` and dragging work with no new code. A row is built from the block its `type` names, with a hidden `type` field beside it, so the output keeps the type like any other field.
- **`Block`** has `label()`, `icon()`, `maxItems()`, `columns()` and `preview()`. A string key in `blocks()` groups blocks in the picker.
- **`type` is never trusted.** A row of a block that isn't declared, or that the server hides, fails validation, and no field or closure of that block runs for it.
- **A stored row of a hidden block is kept.** Its values never reach the browser, and it goes back out exactly as the server holds it, whatever is posted for it. Otherwise a non-admin could not save a page with an admin block on it, or would wipe it.
- **`blockPreviews()`** renders each block on the server, from a Blade view or a closure. A preview gets only the fields the block declares, and tracks its row, so it updates as the row changes.
- **Uploads post the state**, because only the row's `type` says which block's field a path means. The scope includes the block, so a file uploaded for one block can't be used in another with the same field name.

Where this differs from Filament:

- `blockPickerColumns()`, `blockPickerSearch()`, `blockIcons()`, `collapsible` per block and `reorderableWithButtons()` aren't built. The move buttons are always there.
- A preview shows an Edit button under the block, instead of opening a modal.
- `itemLabel()` per row isn't built. A row is titled by its block.

Changes that can break existing code, for the release notes:

- `POST upload` now runs `prepare()` when the browser sends state, so a field's `acceptsInput()` sees what the user has typed, not only what the form was filled with.
- `Repeater::getRowComponents()` now builds from `getRowTemplates()`, and `toPlainRows()` from `toPlainRow()`. A subclass that overrode them should override those.

Limits to keep in mind:

- A stored row of a block that was removed from the code fails validation, so nothing is lost silently. The user has to delete the row to save.
- A user can still delete or move a stored row of a block hidden from them.
- `Block::hidden()` applies to the whole builder: a closure can't depend on the row.
- Frontend errors name a row by the builder's `itemLabel()`, not by its block.

### Where the build differs from the plan
- **Patches have no `add`/`remove`.** A component hidden by a server closure is sent as a stub (`{ key, type, hidden: true, deps }`) and later patched to a full node, which keeps every key stable.
- **The update request carries the client's dependency map** (`deps: { nodeKey: [paths] }`). The server uses it only to pick which closures to re-run, then re-records fresh dependencies.
- **`validate()` builds its output from known fields only**, so posted keys that are not in the schema never reach the model.
- **`Form` is generic over its model** (`@extends Form<Order>`), so `save()` returns the right model type.
- **The package uses Vite+ too**: `vite.config.ts` holds its Vitest, lint and format config, and it renders every PHP schema snapshot in Vitest.

### Known limits (next steps)
- The Pest browser test server corrupts binary uploads (the PNG header byte `0x89` arrives as `?`), so image uploads are covered by package feature tests instead of browser tests.
- The `eject` command and a Vite plugin for distribution are still future work.
