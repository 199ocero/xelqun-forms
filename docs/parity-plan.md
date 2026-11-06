# Xelqun Forms: Filament parity plan

## Context

A comparison with Filament v5.11 showed `xelqun/forms` covers 10 of Filament's 20 field types and lacks several CRUD basics: many-to-many selects, date-times, multi-file upload and create/edit awareness. You asked for a plan to add all of it.

This continues `docs/forms-plan.md`, so milestones start at **M10**. Each one ends with something visible in the playground.

**Scope:** every gap from the comparison, nested repeaters (needed by Builder), and a plugin foundation so other developers can build fields like Filament's standalone plugins. **Out of scope:** `ModalTableSelect` (no tables package exists), panel plugins (no panel exists), a shared package for forms and tables (decided against: `xelqun/tables` requires this package, and one can still be extracted if something needs it without forms), Alpine-specific APIs such as `RawJs`, and rich editor mentions and custom blocks (follow-up after M22).

**Build order rule:** fix the shared pipeline first (M10), then build the plugin foundation (M11), then add features on top of it. Every new field uses the same contracts as a third-party plugin.

## Existing problems to fix first (M10)

Confirmed by reading the code. Nothing has been run yet, so each fix starts with a failing test.

| Problem | Where | Effect |
|---|---|---|
| Uploads are finalized during validation, before the DB transaction | `FormValidator::dehydrate`, `TemporaryUploadStore::finalize` | A failed save orphans the file, deletes the temp, and resubmitting says "expired" |
| Every nesting level uses `{row}` | `Repeater::getChildContainer`, `paths.ts replaceRow` | Nested rows would share one path |
| `visible(Closure)` stays bound to the template | `Component::visible` | Inside rows it reads the wrong paths |
| Update and search use raw browser state | `UpdateProcessor::process`, `SearchController` | A tampered disabled field can influence `hidden()` and options closures |
| The form is filled before `authorize()` | `FormTokenEncrypter::resolveForm` | Closures run for unauthorized users |
| `orderable(false)` is only enforced in the UI | `Repeater`, `XqRepeaterField.vue` | The browser can still reorder rows |
| Non-relationship rows get new UUIDs on every build | `Repeater::getSourceRows` | Disabled row values reset to defaults. Starter rows are now keyed by position (`row-{n}`) and stored rows by content (`stored-{hash}`), and a stored or related row that changed after the form opened fails validation instead of losing its locked values |
| Two fields sharing a state path overwrite each other (found in review) | `Form::getComponents` | State, patches and the browser all key by path, so duplicates now throw a `FormDefinitionException` |
| `hidden()` values reach the browser (found in review) | `Form::getJsonData` | The page state carried values of fields the server hides. They now stay on the server |
| The first error in a closed tab gets no focus (found in review) | `store.ts focusPath` | The tab opens, but focus lands on a field whose panel is still hidden |
| ~~Inactive tabs appear to unmount, which aborts uploads~~ | `XqTabsLayout.vue` | **Not a bug.** Vue casts the absent `unmountOnHide` prop to `false`, so panels stay mounted. `XqTabsLayout` now sets it explicitly, and `tests/js/tabs.test.ts` guards it |
| `Select` accepts MorphTo, which extends BelongsTo | `Select::getRelationshipInstance` | A wrong relationship type passes the check, then crashes with a `TypeError` |

**Status:** M10 is built. The problems above are fixed, apart from the one marked not a bug. Its known limits are listed under "Implementation status" in `forms-plan.md`.

## Key design decisions

1. **One shared preparation step.** `FormValidator::prepare()` sets data, restores locked values, forgets hidden fields and recomputes. Static locks apply before any closure runs. The rest repeats from the posted data until the locks settle, because a closure can read a locked field declared after it. After five passes locks can only be added, so it always ends. `hidden()` fields keep their server value and never send it to the browser. `visibleWhen()` fields only lose their posted value. Update, search, action and validate endpoints all call it. `dehydrate()` becomes free of side effects.
2. **Upload batch.** Uploads are copied to the final disk before the transaction, and temps stay until `DB::afterCommit`. Cleanup is registered inside the transaction with `DB::afterRollBack`, so only a real rollback deletes the copies, including one from a transaction around `save()`, and an exception after the commit never does. Tokens stay valid after a rollback. Promotion is idempotent by upload id within one save. Each save makes its own copy, so a failed save can't delete a file another save committed.
3. **Recursive persistence.** `persistScope(Model, $data, $components)` replaces `collectTopLevel` and `syncHasMany`. Order inside the transaction: `beforeSave`, `mutateBeforeSave`, BelongsTo, parent save, then BelongsToMany, HasOne, HasMany and file relations depth-first, then `afterSave`. An absent key means hidden and is never treated as "clear this relation".
4. **BelongsToMany uses attach/detach diffs**, not `sync()`. `sync()` would detach rows outside a scoped query.
5. **Operation is derived.** Create/edit comes from `recordKey`; custom operations such as `view` live in encrypted `context`. It is frozen at construction and never read from the request.
6. **`readOnly()` is a server guarantee.** The posted value is dropped before closures run, rules are skipped, and nothing is written on edit.
7. **Depth-aware row placeholders.** Level 0 stays `{row}`; deeper levels use `{row:1}`. Both `overlaps()` implementations change together, proven by conformance files.
8. **Builder extends Repeater.** Row state is `{key: {type, data}}`. The server rejects undeclared or hidden block types. Upload scope includes the block name, and uploads post state so the server can resolve the block.
9. **Action modals use a nested `ActionForm`** with its own client store. The token gains an action chain, so update, upload and search keep working unchanged.
10. **Action authorisation runs on every call:** `resolveForm`, `prepare`, find a declared action, `acceptsInput()`, then `Action::authorize()`. It denies when there is no policy or closure, because `Form::authorize()` does not imply permission to create a related record. Posted ids are re-resolved through the relationship query.
11. **Actions live in `actions/`**, under the `Xelqun\Actions` namespace, and tables uses them from there.
12. **Frontend validation is a strict subset.** Only typed helpers serialize, never `rules()` strings, closures, `unique`, `exists` or regex. The client must never reject what the server accepts, and `conformance/validation.json` runs against both Laravel's validator and the JS validator.
13. **The wizard validates the whole form's state but only a step's rules**, through `POST validate`. The server tracks no progress, and final submit revalidates everything.
14. **Server-enforced flags.** `addable()`, `deletable()` and `orderable()` are checked in `prepare()`, and `unique()` inside rows ignores only ids owned by the record.

### Plugin foundation decisions

15. **Contracts replace class checks.** `HasSearchResults`, `AcceptsUploads`, `ContainsRows`, `PersistsItself` and `NeedsServer` replace the 19 `instanceof` checks, and the client reads a capability flag instead of `type === 'repeater'`. Built-in fields implement the same contracts, and endpoints stay default-deny: `acceptsInput()` and `authorize()` still run first.
16. **Plugins are distributed the same way as this package:** one repo with `composer.json` and `package.json`, compiled by the host's Vite. Bundling a second `reka-ui` breaks popovers (see `docs/forms-plan.md`), so plugins declare `vue`, `reka-ui` and `@xelqun/forms` as peer dependencies.
17. **One JS entry point:** `createXelqunForms({ plugins: [ratingPlugin()] })`. A plugin is `{ id, components, setup? }`, and the name stays generic (`XelqunPlugin`) so tables can reuse it. The `components` option stays as the app-level override.
18. **Namespaced types.** Plugin types are vendor-prefixed (`acme/rating`). Unprefixed names are reserved for built-ins. A duplicate type throws in development, and replacing a built-in from a plugin needs an explicit flag.
19. **One-step install.** `php artisan xelqun-forms:plugin acme/rating` adds the npm package, the entry in `app.ts`, and the Tailwind `@source`, reusing `InstallCommand`'s patching. If a file can't be patched, it prints the manual step.
20. **PHP plugins need no plugin class.** As in Filament's standalone plugins, a plugin registers through its service provider. `configureUsing()` and macros cover changes to existing fields.
21. **Declared public API.** Classes and methods plugins may use are tagged `@api`, and anything else may change. Pre-1.0 changes to tagged items are listed in release notes.

## Milestones

M10 to M22 are built. The plugin author guide is [`plugins.md`](plugins.md).

| # | Milestone | Adds | Playground proof |
|---|---|---|---|
| **M10** | Foundations | All fixes above, `prepare()`, upload batch, deterministic row keys | Failed save keeps uploads; tampering tests pass |
| **M11** ✓ | Plugin foundation | Capability contracts, `PersistsItself` hook in `ModelPersister`, `plugins` option, namespaced types with collision errors, `configureUsing()`, `Macroable`, translation loading, `@api` tags, `make:xelqun-field`, `xelqun-forms:plugin` install command, plugin test helpers, plugin author guide, example plugin | An external example plugin with search and uploads works without changing package source |
| **M12** ✓ | State and field basics | Operation, `hiddenOn()`, `visibleOn()`, `disabledOn()`, `readOnly()`, `formatStateUsing()`, `dehydrateStateUsing()`, `afterStateHydrated()`, `saved()`, `extraAttributes()`, `autofocus()`, `hiddenLabel()`, `inlineLabel()` | Same form behaves differently on create and edit |
| **M13** ✓ | Relationships and hooks | Recursive persister, `beforeSave`/`afterSave`/`afterCommit`, repeater mutate and after hooks, BelongsToMany with `pivotData()`, `MorphToSelect`, relationship `Group` (HasOne), `getOptionLabelFromRecordUsing()` | Order form with tags and a morph owner round-trips |
| **M14** ✓ | Validation API | `unique()`, `scopedUnique()`, Filament's dedicated rule helpers, `validationMessages()`, `validationAttribute()`, field-reference paths for `same`, `confirmed` | Validation example shows custom messages |
| **M15** ✓ | Everyday fields | `DateTimePicker`, `TimePicker`, `timezone()`, `format()`, `displayFormat()`, `disabledDates()`, steps; `CheckboxList`, `TagsInput`, `ToggleButtons`, `Slider`, `KeyValue`, `ColorPicker`; Select groups, `disableOptionWhen()`, `boolean()`, `reorderable()`, messages; TextInput `revealable()`, `trim()`, `mask()`, `stripCharacters()`, `datalist()`, `copyable()`, `length()`, icons | New "All fields" example |
| **M16** ✓ | Repeater upgrade | Nested repeaters, `cloneable()`, `collapsible()`, `collapsed()`, `simple()`, `grid()`, `table()`, `addable()`, `deletable()`, `distinct()`, delete confirmation, item numbers, drag-and-drop (up/down stays for keyboard users) | Two-level repeater saves and reloads |
| **M17** ✓ | Files | `multiple()`, `minFiles()`, `maxFiles()`, `reorderable()`, `minSize()`, `pasteable()`, `downloadable()`, `openable()`, `avatar()`, parallel limit, file relations | Multi-file gallery in a row |
| **M18** ✓ | Layout and display | `Flex`, `FusedGroup`, `Callout`, empty states, `Text`, `Icon`, `Image`, `UnorderedList`, field slots, translated Vue strings, unsaved-changes warning | Layout example; a translated locale; leave warning |
| **M19** ✓ | Wizard and browser validation | `POST validate`, scoped error replacement, `Wizard`, `Step`, step query param, step hooks, frontend validation, live validation in patches | Multi-step form blocks on invalid steps |
| **M20** ✓ | Action core | `Action`, `run` endpoint, token action chain, `ActionForm`, confirmation, reka Dialog modal, parent patch through a reusable `buildPatch` | A row action opens a modal and updates the form |
| **M21** ✓ | Action features | Select `createOptionForm()`, `editOptionForm()`, `manageOptionForm()`, prefix and suffix actions, `extraItemActions()` | Create a country from its select |
| **M22** ✓ | Heavy fields | `Builder` (groups, previews, block limits), `RichEditor`, `MarkdownEditor`, `CodeEditor`, image editor, cropping, resizing, `storeFiles(false)`, deleting removed files from the disk | Block-based page editor |

**Dependencies:** M10 blocks everything. M11 blocks all feature milestones. M12 blocks M13 and M14. M13 blocks M16 and M20. M16 blocks M17 and Builder. M14 blocks M19. M20 blocks M21 and the markdown preview. M15 and M18 can run in parallel after M12.

## Critical files

- **Server core:** `src/Form.php`, `src/Validation/FormValidator.php`, `src/Eloquent/ModelPersister.php`, `src/Live/UpdateProcessor.php`, `src/Security/FormToken*.php`, `src/Uploads/TemporaryUploadStore.php`
- **Components:** `src/Components/{Component,Field,Repeater,Select,FileUpload}.php`
- **Client core:** `resources/js/core/{store,paths,patch}.ts`, `resources/js/schema/types.ts`, `resources/js/composables/useField.ts`
- **Rendering:** `resources/js/components/{XqFieldWrapper,XqForm}.vue`, `resources/js/components/defaults.ts`
- **Plugin foundation:** `src/Contracts/`, `src/FormsServiceProvider.php`, `src/Commands/`, `resources/js/plugin.ts`, `resources/js/composables/registry.ts`, `src/Http/Controllers/{SearchController,UploadController}.php`
- **New:** `src/Actions/`, `src/Http/Controllers/{ValidateController,ActionController}.php`, `resources/js/core/validation.ts`

**Reuse:** `Form::eachField` and `walk`, `Field::evaluate` injection, `HasOptions`, `Repeater::isSafeRowKey`, `StatePath`, `store.applyPatch`, `XqTabsLayout`'s error-focus logic (extract for Wizard), `useField`, the component registry (lazy-load every heavy field), and the `conditions.json` conformance pattern.

**New field pattern:** from M12 on, built-ins use the M11 contracts rather than class checks. PHP class in `src/Components`, `Xq*Field.vue` using `useField` and `XqFieldWrapper`, registered in `defaults.ts`, added to `types.ts`, plus a snapshot fixture and a playground example.

**Schema version:** changes are additive, so `$xq` stays at 1 unless a snapshot diff shows a key changed meaning.

## New dependencies (approving this plan approves them)

| Package | For | Milestone |
|---|---|---|
| `sortablejs`, `@types/sortablejs` | Drag and drop on repeaters and other reorderable fields | M16 |
| `@tiptap/*` | Rich editor | M22 |
| `symfony/html-sanitizer` | Server-side rich text sanitising | M22 |
| `@codemirror/*` | Code editor | M22 |
| `cropperjs` | Image editor, version 1 | M22 |

Reka UI should cover tags, slider, toggle group, time field, dialog, stepper and collapsible. Its color primitives covered `ColorPicker` in M15. `maska` was dropped: `core/mask.ts` handles the same mask grammar with no dependency. Markdown preview uses Laravel's `Str::markdown` on the server, so it needs no new package.

## Risks

- **Size:** 13 milestones on a one-person project. Each must ship alone, and M10 to M14 make the package usable for real CRUD and open to plugins.
- **Action security:** M20 adds a new way to run server code, so it gets a dedicated security review before M21.
- **Plugin API churn:** contracts set in M11 may need changes once later milestones use them. Pre-1.0 this is acceptable, and `@api` changes go in release notes.
- **Plugin security:** plugin code runs with full app access, so contracts only protect endpoint routing, not a malicious package. The author guide says plugins must authorize their own server methods.
- **Host file patching:** the install command edits `app.ts` and CSS, so it falls back to printing manual steps.
- **Rich text XSS:** HTML is sanitised on the server on save and on fill.
- **Bundle size:** Tiptap, CodeMirror and Cropper must stay lazy-loaded.
- **`clone` in repeaters:** cloned rows clear file fields so two rows never own one stored file.
- **Format closures** run on every rebuild, so they must be deterministic. Documented on the method.
- **Filament's API moves** with each release. Re-check its docs at the start of each milestone.

## Verification

For every milestone, from `packages/forms`:

1. `vendor/bin/pest` on the touched test files
2. `npx vp test`
3. `vendor/bin/pint --dirty --format agent`
4. `vendor/bin/phpstan analyse`
5. `npx vp check` and `npx vue-tsc --noEmit`

Also:

- **Security tests per endpoint:** wrong user, tampered token, unauthorized action, hidden field values, foreign ids, forged row keys, undeclared blocks.
- **Schema snapshots:** one fixture per new field; Vitest already renders every snapshot.
- **Conformance:** paths, conditions, expressions and validation pass in both languages.
- **Browser tests:** add a `tests/Browser` case for each new playground example, with `assertNoJavascriptErrors()`.
- **Plugin proof:** the example plugin lives in `tests/Fixtures/ExamplePlugin` so package CI covers it on every change. It uses a namespaced type, search, uploads, a validation rule and a translation, and the playground installs it through `xelqun-forms:plugin` for a browser test.
- **Regression guard:** a test fails if a new `instanceof` check on a concrete field is added to the core classes.
- **Full suites:** at each milestone's end I'll ask you to run `php artisan test --compact` in the playground.

## Working agreement

- This plan is copied to `docs/parity-plan.md` at the start of M10, and the status section of `docs/forms-plan.md` is updated after each milestone.
- I stop after each milestone for your review. Commits happen only when you ask.
- The public starter repo for plugin authors needs your go-ahead before I create it, because it's a new public GitHub repo. Until then the starter lives in the package as stubs.
