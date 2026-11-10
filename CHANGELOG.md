# Changelog

Xelqun Forms is alpha. Until 1.0, a minor version can break existing code, and each break is listed here.

## 0.10.0 - 2026-11-10

The schema version (`$xq`) stays 1. Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.10.0`.

### Upgrading

- **`Fieldset::make()` names its argument `$label`**, as in Filament. Code that passed `legend:` by name should pass `label:`.
- **More methods are `@internal`**, so they can change before 1.0 and in any minor version after it. On `Form`: `fillFields`, `recomputeComputedFields`, `validateKeyed`, `getModelInstanceFor` and `getRootPosition`. On `Component`: `getContainerPattern`, `getKeyPattern`, and the four `isHidden*`/`canBeHiddenOnServer` checks. On `Field`: `getStatePathPattern`, `isDisabledStatically`, `isReadOnlyStatically`, `getComputed`, `hasAfterStateUpdatedHooks`, `getLiveConfig` and `makeUploadScope`.
- **Browser internals moved to `@xelqun/forms/internal`:** `createFormStore`, `defaultLiveConfig`, `reportRequestFailure`, `postJson`, `HttpError`, `getPath`, `overlaps`, `evaluateCondition` and `evaluateExpression`. The main entry still exports them, deprecated, until 1.0.0-beta.1. They're meant for Xelqun's own packages, so a plugin should use `useField()` and `useFormStore()` instead.
- **The `ContainsRows` contract is `@internal`.** Extend `Repeater` for a field with rows, as the docs already said.

### Added

- A warning in the console when a plugin registers a different icon under a name that a built-in or another plugin already uses. The app's own `icons` still override without one.

### Fixed

- **`hiddenWhen()` and `visibleWhen()` no longer replace each other.** With both set, the component shows only while both allow it, as in Filament. Before, the one called last won.
- **A form whose schema version the installed `@xelqun/forms` doesn't support can no longer submit or run actions.** It still shows the error, which is now translatable.

## 0.9.0 - 2026-11-09

The schema version (`$xq`) stays 1. Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.9.0`.

### Upgrading

- The rich editor's default toolbar has a group of table tools, and `textColor`, `mergeTags` and `customBlocks`, which show only once configured.

- **`hint()` now sits beside the label**, as in Filament. For text under the field, which is what `hint()` did before, use **`helperText()`**. A plugin field that reads `hint` from `useField()` for that text should read `helperText`, and the id its control is described by now ends in `-helper`.
- `Section::collapsible()` and `collapsed()` take a closure too, and a section now renders inside a wrapper element.
- A Toggle's track color comes from `onColor()` and `offColor()`, so `data-[state=checked]:bg-xq-primary` is no longer on the switch.

### Added

- **`Action::slideOver()`** opens the modal as a full-height panel that slides in from the side. A modal's header and buttons already stay in view while its fields scroll.
- **`Action::badge()`** and **`badgeColor()`** put a count in the button's corner. `ActionGroup` has them too.
- **`Action::outlined()`** draws the button as a border in its color, and **`size('sm'|'md'|'lg')`** sets its size, on `ActionGroup` too.
- **`Action::keyBindings(['mod+s'])`** clicks the button from the keyboard. `mod` is Command on a Mac and Ctrl elsewhere.
- **`Action::rateLimit(5)`** lets one user, or one address when signed out, run the action that many times a minute. Past that, the request gets a 429 and the form says to wait.
- **`Action::authorizationTooltip()`** shows an action that `authorize()` refused as disabled, with the policy's message as its tooltip. A refusal with no message still hides it, and the server still refuses the click. `authorize()` closures can return a `Response` for the message.
- **`Action::extraModalFooterActions()`** puts more actions beside a modal's buttons. They run with the modal's state, which `$get` reads, and can open modals of their own. **`cancelParentActions()`** closes the modals an action sits in once it has run.
- **`Action::before()`** and **`after()`** run around `action()`, in its transaction. `after()` also gets `$result`.
- **`$action->halt()`** stops an action from a closure that takes `$action`, keeping the modal open. **`$action->cancel()`** stops it and closes the modal. Both roll back what the action wrote, and notifications sent so far still show.
- **`Action::steps()`** puts a wizard in the modal, with the submit button on the last step.
- **`hintIcon()`** (with a tooltip), **`hintColor()`** and **`hintAction()`** go beside the label with `hint()`.
- **`markAsRequired()`** shows or hides the asterisk, whatever the rules say.
- **`extraInputAttributes()`** puts HTML attributes on the input of a text input or textarea.
- **`Toggle::onIcon()`**, **`offIcon()`**, **`onColor()`** and **`offColor()`**.
- **`Select::allowHtml()`** draws option labels as HTML, cleaned on the server like a rich editor's content. **`optionsLimit()`** sets how many options draw at once and how many a search finds. **`minItems()`** and **`maxItems()`** check a multiple select.
- **`FileUpload::preserveFilenames()`** stores each file under its original name, with a random suffix when the name is taken. **`storeFileNamesIn()`** saves the original names in another field, such as a `Hidden` one, and the upload shows them. A plugin upload field can implement the new `NamesStoredFiles` contract to get both, since `HandlesUploads` has its methods.
- **`Section::icon()`**, **`aside()`** (heading in a column beside the card), **`compact()`**, **`persistCollapsed()`**, and **`afterHeader()`** and **`footer()`** for actions or text.
- **RichEditor table tools**: `tableAddRowBefore`, `tableAddRowAfter`, `tableAddColumnBefore`, `tableAddColumnAfter`, `tableDeleteRow`, `tableDeleteColumn`, `tableMergeCells`, `tableSplitCell`, `tableToggleHeaderRow`, `tableToggleHeaderCell` and `tableDelete`. They show while the cursor is in a table, and the default toolbar has most of them.
- **`RichEditor::json()`** keeps Tiptap's JSON instead of HTML. The server keeps only the nodes, marks and attributes the editor makes. File attachments are off in this mode.
- **`RichEditor::textColors(['#ef4444' => 'Red'])`** adds a `textColor` button. The sanitizer keeps a hex color on a `span` and nothing else.
- **`RichEditor::mergeTags()`** adds a `mergeTags` button that inserts `{{ name }}`. **`RichContentRenderer::make($html)->mergeTags([...])->toHtml()`** fills them in text only, never inside a tag, escaping all but `Htmlable` values.
- **`RichEditor::mentions([MentionProvider::make('@')->items([...])])`** offers people or anything else after a character. `getSearchResultsUsing()` searches on the server instead.
- **`RichEditor::customBlocks([HeroBlock::class])`** adds a `customBlocks` button. A block extends `CustomBlock`, can ask for values in a modal through `configureEditorAction()`, shows `toPreviewHtml()` in the editor, and `RichContentRenderer::customBlocks()` draws its `toHtml()` on the page. The server checks a posted block's config against its modal's rules on every save and drops a block that fails.
- **`Tab::icon()`**, **`badge()`** and **`badgeColor()`**. **`Tabs::activeTab()`** opens a tab counting from 1, **`persistTab()`** remembers the open one in the browser, **`vertical()`** puts the tabs in a column, and **`contained()`** puts them in a card.

## 0.8.1 - 2026-11-08

The schema version (`$xq`) stays 1. Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.8.1`.

### Changed

- A repeater's cards skip layout and paint while they're off screen. Typing in a 500-row repeater went from about 54 ms a keystroke to one frame, and resizing the page from 148 ms of layout to 18 ms.
- `MorphToSelect\Type::getOptionLabelFromRecordUsing()` takes a closure typed for your model, such as `fn (Post $post) => $post->title`, without a Larastan error.

## 0.8.0 - 2026-11-08

The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.8.0`. Icons now come from `@lucide/vue`; an app that imported icons from `lucide-vue-next` through this package should install `@lucide/vue` itself.

### Changed

- **Icons come from `@lucide/vue`**, the new name of `lucide-vue-next`, which is deprecated. Laravel's starter kits already use it, so an app now ships one copy.
- **A relationship select with more than 500 related records searches on the server** instead of sending them all, as if it were `searchable()`. Call `preload()` to send them all anyway. At 100,000 records the page was 4.8 MB and took 350 MB of PHP memory.
- **A select with more than 100 options gets a search box**, and any searchable list draws its first 100 matches, with a line saying to type for the rest. A list of thousands froze the page for seconds when it opened.
- The testing helpers take a field's name as well as its path, so `searchForm($form, 'country_id')` works, and `updateForm()` sends the dependencies from the form's schema, as the browser does.

### Fixed

- A closure in `rules()` that builds a rule, such as `fn () => function (...) {...}` or `fn (Get $get) => ...`, now runs. It was handed to Laravel as the rule itself and never checked anything.
- A computed `TextInput` without `numeric()` no longer fails with "must be a string" on submit, because the server works the value out again as a number.
- An action whose `authorize()` names an ability, with no record or model to ask a policy about, asks a plain gate, so `authorize('export')` works on a page of its own. It was always hidden.
- `createOptionForm()` asks the related model's `create` policy on an edit page too. It asked about the form's record, so the button vanished from edit pages.
- Each endpoint has its own rate limit. They shared one counter per user, so a burst of searches or uploads blocked live updates and actions.
- An upload refused for too many requests waits as long as the server asks and tries again, so a big batch no longer drops the files past the limit. A file that still fails is named in the error.
- A server error in a modal or a live update says the server went wrong, not "check your connection", and a 403 says "You are not allowed to do that."
- The code editor follows the theme's colors, so it is readable in dark mode, and `disabled` changes reach it after it loads.
- An integer field opens the number keypad on phones.
- A tag that breaks a rule is named in the message, as `The Tags "x" field...`, not `tags.1`.
- Checking a list of many choices is fast: a 1,000-option checkbox list took most of a second to validate.
- `make:xelqun-field` writes a Vue file that passes a fresh Laravel app's formatter.
- A form with no `model()` that uses `unique()` or `exists()` says so, instead of talking about relationships.

## 0.7.1 - 2026-11-07

### Added

- **`Tabs::persistTabInQueryString()`** keeps the open tab in the URL, as `?tab=pricing`, so a link can open it. Pass a key when a page has more than one set of tabs.

### Changed

- **One quiet scrollbar everywhere.** `@xelqun/forms/style.css` now styles every scrollbar on the page, thin and always visible, including those of tables and panels. It sits in a CSS layer, so any scrollbar rule of an app's own wins. Tab strips scroll without a bar.
- **The wizard draws a line between its steps**, colored up to the current step. An invalid step is marked on its circle, and its icon keeps its own color.

### Fixed

- **Icons no longer go missing in dev mode when the packages come from npm.** Vite pre-bundled their TypeScript but not their `.vue` files, so the two read separate copies of shared state. `xelqun-forms:install` now adds `optimizeDeps.exclude` to the Vite config. An app that's already installed needs this by hand:

    ```ts
    optimizeDeps: {
        exclude: ['@xelqun/forms', '@xelqun/tables', '@xelqun/panels'],
    },
    ```

## 0.7.0 - 2026-11-07

### Added

- **`XqMenuGlide` and `XqTabGlide`.** The sliding highlight and tab mark, exported for your own menus and tabs. `XqMenuGlide` takes `hover-items` for a grid of cards.
- **`preserveState` takes a function.** A form can decide from each response whether to keep the page.

### Changed

- **Motion.** One set of `--xq-*` tokens drives every animation, and reduced motion is handled in one place. Menus and popovers grow from what opened them, dialogs and toasts arrive with a soft blur, tabs slide, buttons press, switches spring, check marks draw on, fields shake on an error, and sections fold open. One highlight glides between the items of selects and menus, by pointer or arrow keys, or across a grid of cards with `hover-items`, and the tab pill leans over to a hovered tab and settles back. An action's button shows the whole run: its icon lifts on hover, a press, a spinner if it takes a while, then a check that draws on, or a shake if it fails.

### Fixed

- **Fields stand out from the page.** Inputs, selects, checkboxes, radios, toggle buttons, sliders and file rows took the page's background, so only a thin border marked them. They take the card color now.

## 0.6.0 - 2026-11-06

This release fixes two ways a password could leak, and adds what `xelqun/panels` needs to protect its endpoints. The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.6.0`. Plugins that require `^0.5.0` need a new release, because a caret range on 0.x stops at the next minor version. That includes `xelqun/tables`, which needs 0.4.

- **`TextInput::password()` no longer fills from a model, and an empty one is no longer saved.** To store an empty value on purpose, add `->saved()`. To show a stored value, drop `password()`. An array row, as in a repeater on a JSON column, still fills, because that row is written back whole.
- **A top-level input called `data` is no longer flashed to the session** when validation fails, on any form in the app. A plain Blade form that reads `old('data.x')` needs another field name.
- **A signed out user holding a form token now gets a 419, not a 403.** The form shows its "reload the page" message. A different signed-in user still gets a 403.

### Added

- **`FormTokenEncrypter::scopeUsing()`** ties every token to where it was issued, and runs your check on each request before the token's user is compared. It covers forms, tables, actions and their modals, so a section of an app can restore its guard and repeat its access checks on the `_xelqun` endpoints.

### Fixed

- **A failed submit no longer writes the password to the session.** Forms post under `data`, and Laravel's `dontFlash` list only covers top-level keys, so every field's value landed in `_old_input`.
- **An edit page no longer sends a stored password hash to the browser.** `$hidden` doesn't apply when a field reads an attribute directly. An empty password field also no longer overwrites the stored value.
- **Type checking passes in an app that types its flash data.**

## 0.5.0 - 2026-11-06

This release adds what `xelqun/panels` will build on. The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.5.0`. Plugins that require `^0.4.0` need a new release, because a caret range on 0.x stops at the next minor version. That includes `xelqun/tables`, which needs 0.3.

- **The context key `disabled` is now reserved.** `Form::make($record, ['disabled' => true])` now locks the form, so rename a key of your own that has this name.

### Added

- **Notifications.** `Notification::make()->title('Saved')->success()->send()` works from an action, a hook or a controller, and survives a redirect. `createXelqunForms` puts the toaster on the page, so there's nothing to mount. A rolled-back action shows nothing. Title and body always render as text. Use `notify()` from Vue, and the `notificationsLabel` option to translate what screen readers call the region.
- **`NotificationAction`** puts buttons on a notification. One can open a URL, close the notification, or run a named action of an `ActionSet`, which checks `authorize()` again on the click. An unsafe URL or an action the viewer can't run leaves the button out, and an action with a modal or a confirmation is refused.
- **`Action::successNotificationTitle()`** sends a success notification once the action has returned.
- **`ActionGroup`** puts actions behind one button that opens a menu. It works in an `Actions` layout and in an `ActionSet`. With no `label()`, the button is just an icon. Each action keeps its own `authorize()` and modal, and the group hides itself when the viewer can use none of them.
- **`Form::disabled()`** shows a form nobody can change, as on a View page. It is kept in the token, so requests after the page loads are refused too. Fields can't be changed, `save()` throws, and `XqForm` leaves out its `actions` slot. Actions in an `Actions` layout still work.

## 0.4.0 - 2026-11-06

This release puts actions on a page with no form or table around them, and redesigns their modals. The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.4.0`. Plugins that require `^0.3.0` need a new release, because a caret range on 0.x stops at the next minor version.

- **Actions have their own namespace.** Replace `Xelqun\Forms\Actions\` with `Xelqun\Actions\` in your imports. `Xelqun\Forms\Layouts\Actions` stays where it is.

### Added

- **`ActionSet` and `XqActions`** put action buttons on a page with no form or table around them. The page reloads after an action runs.

- **`Action::successRedirectUrl()`** sends the user somewhere else after an action runs, for a page that won't exist any more.
- **Modals can be customized.** `modalIcon()`, `modalIconColor()`, `modalAlignment()`, `modalFooterActionsAlignment()`, `modalCloseButton()` and `closeModalByClickingAway()` on `Action`, and `modalWidth()` now goes up to `5xl`.

### Changed

- **Modals have a new look.** Larger headings, a close button, a soft entrance, and buttons that stay in reach while a long form scrolls. A confirmation is centered with a warning icon and full-width buttons. `XqDialog` and `XqConfirmDialog` take the same options as props.
- **Action buttons stay still while they run.** A quick action changes nothing on screen until it's done, and a slow one shows a spinner over its label after 200ms. The button keeps its width and keyboard focus, because it no longer disables itself while running. `aria-busy` is set the whole time.

## 0.3.1 - 2026-11-06

### Fixed

- **Placeholders that start the same no longer collide.** `:to` could replace part of `:total`, and `:item` part of `:items`, leaving text like "of 10tal".
- **A value holding a placeholder name is no longer filled in twice.** A file named `:name.png` stays as it is.

## 0.3.0 - 2026-11-06

This release adds what `xelqun/tables` builds on. The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.3.0`. Plugins that require `^0.2.0` need a new release, because a caret range on 0.x stops at the next minor version.

### Added

- **`Action::url()`** turns an action into a link. It still needs `authorize()`. Its URL has to be http(s), mailto, tel or a path on the site, or the action hides itself.
- **`Form::resolveRecord()`** finds the record a token names, so a form limited to some records can stay inside them on every request.
- **`XqAction` takes a `runner` prop**, so the button works outside a form.
- **`XqPopover`** and an `indeterminate` prop on `XqCheckbox`.
- New exports: `XqAction`, `useRegistry`, `postJson`, `translate` and `reportRequestFailure`.

## 0.2.0 - 2026-11-06

This release covers Filament parity (M10 to M22): a plugin foundation, the missing field types, relationships, wizards, actions, a block builder and three editors. The schema version (`$xq`) stays 1.

### Upgrading

Update both halves together: `composer update xelqun/forms` and `npm install @xelqun/forms@0.2.0`. Plugins that require `^0.1.0` need a new release, because a caret range on 0.x stops at the next minor version.

Check these first, because they change behaviour without an error:

- **`$get('data.name')` now returns null.** A path starting with `data.` is no longer absolute in `Get`, `Set`, `When`, `computed()` or `dependsOn()`. Write `$.name` for the form root. Search your code for it.
- **`saved(false)` and `dehydrated(false)` fields now run their rules**, unless they are disabled. A form that relied on them being skipped shows errors now.
- **A repeater with no relationship inside a related row now saves to a column on that row.** Add the column, or give the repeater `saved(false)`.
- **A nested relationship repeater now saves its rows**, and deletes rows the browser removed.
- **A file name is no longer a link.** Add `openable()`.
- **Dates follow the server's locale**, not the browser's.

This one fails loudly:

- **`Field` has about 55 new public methods**, with names such as `in()`, `after()`, `string()` and `json()`. A subclass with a method of the same name and a different signature no longer loads.

### Added

- **Plugins:** capability contracts, `plugins` on `createXelqunForms()`, namespaced field types, `configureUsing()`, macros, translations, `make:xelqun-field`, `xelqun-forms:plugin`, test helpers and a [plugin guide](docs/plugins.md).
- **State and field basics:** `hiddenOn()`, `visibleOn()`, `disabledOn()`, `readOnly()`, `formatStateUsing()`, `dehydrateStateUsing()`, `afterStateHydrated()`, `saved()`, `extraAttributes()`, `autofocus()`, `hiddenLabel()` and `inlineLabel()`. Forms know their operation.
- **Relationships:** `BelongsToMany` selects with `pivotData()`, `MorphToSelect`, `Group::relationship()`, and `beforeSave()`, `afterSave()` and `afterCommit()` hooks.
- **Validation:** `unique()`, `scopedUnique()`, the named rule helpers, `validationMessages()`, `validationAttribute()`, `frontendValidation()` and `liveValidation()`.
- **Fields:** `DateTimePicker`, `TimePicker`, `CheckboxList`, `TagsInput`, `ToggleButtons`, `Slider`, `KeyValue`, `ColorPicker`, `Builder`, `RichEditor`, `MarkdownEditor` and `CodeEditor`, plus more options on `Select` and `TextInput`.
- **Repeaters:** nesting, `cloneable()`, `collapsible()`, `simple()`, `grid()`, `table()`, `addable()`, `deletable()`, `distinct()`, delete confirmation, drag and drop, and `extraItemActions()`.
- **Files:** `multiple()`, file relationships, reordering, pasting, `downloadable()`, `openable()`, `avatar()`, `imageEditor()`, automatic cropping and resizing, `storeFiles(false)` and `deleteRemovedFiles()`.
- **Layout and display:** `Flex`, `FusedGroup`, `Callout`, `EmptyState`, `Text`, `Icon`, `Image`, `UnorderedList`, ten field slots, translated browser text and `unsavedChangesAlerts()`.
- **Wizards:** `Wizard` and `Step`, with hooks, skipping and the step in the URL.
- **Actions:** `Action` with modals, confirmation and `authorize()`, and `createOptionForm()`, `editOptionForm()`, `prefixAction()` and `suffixAction()` on fields. An action does nothing until `authorize()` allows it.

### Changed

For code that extends the package:

- `Repeater::isSafeRowKey()` moved to `StatePath::isSafeRowKey()`, and `discardUnsafeRows()` and `restoreRowOrder()` are private. Call `normalizePostedRows()`.
- `Repeater::getRowComponents()` builds from `getRowTemplates()`, and `toPlainRows()` from `toPlainRow()`. A subclass that overrode them should override those.
- A hand-built node with rows needs `containsRows: true`.
- `PersistsItself` declares `getName()`, and `FileUpload` now implements it. It only takes over saving with `relationship()`.
- `DatePicker` extends `DateTimePicker`. `DatePicker::minDate()` keeps what it is given and formats it later.
- `Repeater` and `Layout` get `columns()` from `HasColumns`.
- `HasOptions` and `HandlesUploads` define `formatStateFromRecord()`, and `HandlesUploads` also `getDefaultState()` and `normalizeState()`. A class that overrides them keeps its own, but `parent::` from there skips the trait's.
- `Field::dehydrated()` accepts a closure, and `Select::getRelationshipInstance()` can return a `BelongsToMany`, so an override must widen its types.
- A field with `childComponents` of its own has to keep them apart from slots, which arrive as `children`.
- `toOptionList()` can add `disabled`, and `getOptions()` flattens groups. Use `getOptionList()` to keep them.
- A key missing from an array row gets `getDefaultState()` as it is, without going through `formatStateFromRecord()`.

For everyone:

- Package messages moved to translation keys, so a JSON translation of the old English text no longer applies. Browser text is in `xelqun-forms::client`.
- Errors in a row name it at every level, such as `Qty (Section 2, Line 2)`, and that now includes built-in checks.
- A `BelongsToMany` select fills its state with keys as the database gives them, not as strings.
- A backed enum default becomes its value, also outside rows.
- `submitLabel` on `XqForm` no longer defaults to `'Save'`. The default comes from the translation.
- `Form::walk()` also visits a repeater's slots, after its rows.
- `endpoints` gains `validate` and `action`, each with its own throttle. A config file published earlier keeps working, using the defaults.
- New dependencies: `symfony/html-sanitizer`, `sortablejs`, `@tiptap/*`, `@codemirror/*` and `cropperjs`. Heavy fields load only when a form needs them.

### Security

- Update, search and upload requests now restore trusted state first, so a tampered disabled field can't influence `hidden()` or an options closure.
- `hidden()` values no longer reach the browser.
- `update` refuses nodes under a hidden parent, so a forged dependency map can't read a hidden section.
- Closures no longer run before `authorize()`.
- `orderable(false)`, `addable(false)` and `deletable(false)` are enforced on the server.
- Rich text is cleaned on the server when it arrives and when it loads from a record.

### Fixed

- A failed save keeps its uploads, so the user can submit again.
- Nested repeater rows no longer share paths.
- `visible()` closures read the row they belong to.
- Disabled values in rows keep their value between requests.
- The first error inside a closed tab now gets focus.
- Two fields on one state path throw a `FormDefinitionException`, instead of overwriting each other.
- `Select` refuses a `MorphTo`, instead of crashing with a `TypeError`.
