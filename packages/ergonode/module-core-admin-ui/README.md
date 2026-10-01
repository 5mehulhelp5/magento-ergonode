# Ergonode_CoreAdminUi

Owns the shared Connection form: one active environment, one operating mode,
and Test/Production groups with URL and request limit. Only the selected
environment is visible; its group stays expanded. Empty nested credential groups
have no accordion headers or separator lines.
`OperatingMode` presents modes registered by runtime modules. This module does
not declare read/write credential fields or their configuration paths.
ConsumerAdminUi and PublisherAdminUi contribute one nested group per environment;
only the selected mode's credential group is visible. Hidden values are preserved.

Shared encrypted-field validation, button presentation and the POST connection-test
adapter are neutral extension points. The supplying module selects its mode.
Tests receive the explicit environment and mode, resolve masked keys from that
exact profile and share its saved request quota. The harmless `__typename` query
checks connectivity; it does not certify write permissions. The endpoint keeps
`Ergonode_Core::config` ACL and Magento form-key protection.

The active environment requires a URL and selected-mode key; the inactive profile
may remain empty. Any supplied URL must be valid. Limits are non-negative integers;
`0` disables limiting. No `config_path` aliases or old-path fallbacks are supported.
Removing this UI leaves runtime configuration intact. Configuration cleanup belongs
to Core and the modules owning credentials. Language-to-store-view mapping remains
owned by Language and LanguageAdminUi.

## Admin workspace design system

`view/adminhtml/web/css/ergonode-workspace.css` owns the shared visual contract
for Ergonode mapping screens. Feature modules load it before their local styles
and use the `veui-*` classes for the full-height workspace, toolbar, buttons,
three-column layout, panels, headers, brand marks, counters, messages, tool rows
and search fields. Feature-specific classes remain available for JavaScript and
domain-specific states, but must not redefine these shared primitives.

`view/adminhtml/web/js/bulk-publish-progress.js` owns the shared publication
dialog lifecycle. Its idempotent `destroy()` closes/removes the modal, cancels
timers and rejects pending `wait()` promises. Consumers must handle rejection
and call it when their workspace is disposed; late updates cannot reopen it.
The same component owns the publication
progress dialog. `open(total, {pause, resume})` optionally enables its pause
control; consumers without these callbacks keep the existing presentation.
An optional `stop` callback exposes Stop. It requests stopping at a domain-owned safe
boundary; `stopped()` preserves completed counters and opens the terminal close action.
Existing consumers without this callback retain their presentation.
The dialog requests a pause and displays `pausing` until the domain controller
calls `setPauseState('paused')` at a safe boundary. Resume invokes the domain
callback; queue position, transport and retry rules remain domain-owned.
Pause time is excluded from the estimate. Finalization, completion and failure
hide the pause control. The native close button and Escape share the terminal close
handler. When a consumer supplies Stop, closing requests stopping first and waits
for the current batch; a transport failure keeps its diagnostic visible. Consumers
without Stop disable the close button until completion. Controls live in a fixed
modal footer outside the scrolling results.

`veui-panel-head-with-tools` combines a panel title with a flexible
`veui-panel-head-tools` group containing search, counters and actions in one
header row. At narrow panel widths, `veui-panel-title-text` stays available to
assistive technology while the brand mark remains visible to leave room for
the controls. Category Tree Mapping uses this variant for its Ergonode and
Magento panels.

`veui-search-expandable` is an opt-in search variant aligned to the right of
its tools row. Its collapsed 34px control shares the options button's border,
background and corner radius. It expands from the search icon on hover,
keyboard focus or a non-empty value. Its input requires a placeholder and an
accessible label; reduced-motion preferences disable the width transition.
Category Tree Mapping enables it for Ergonode and Magento search.

The canonical brand assets are `images/m2_configuration.svg` for Ergonode and
`images/magento-mark.svg` for Magento. The Ergonode asset is shared with the
System Configuration tab so mapping headers and configuration use one mark.
Reusable action icons live in `ergonode-actions.css`; the
`veui-sync-ergonode-icon` and `veui-reset-cursor-icon` primitives expose shared
cloud synchronization and cursor-reset marks for domain Admin UI modules.
`veui-split-button*` is the neutral shared contract for a primary link or button
with an adjacent options menu. Section navigation and `SynchronizationActions`
reuse it with their own links, labels and actions instead of domain-specific
styling classes. Cursor-backed domains enable `Reset cursor` and
`Reset cursor & Sync`; domains without a persisted cursor render only the
primary Sync button.

`veui-split-button-primary` applies the primary action palette to both the main
button and options trigger, including hover and focus states. A disabled main
button keeps its orange background and dims only its icon and label. It also supports
panel headers and an `entity-options` menu; disabling the main button leaves
the menu available. Category Tree Mapping uses it for Save and Magento options.

`view/adminhtml/web/js/unsaved-navigation.js` owns the shared dirty-navigation
guard for Ergonode workspaces. Domain Admin UI modules provide only their dirty
state and save operation. The guard handles links and native browser navigation,
allows a domain screen to retain selected links for its own navigation flow, and
uses the shared `veui-unsaved-navigation-modal` presentation defined in
`ergonode-workspace.css`.

`view/adminhtml/web/js/option-mapping-modal.js` owns the optional embedded option
mapping workspace lifecycle. A domain Admin UI module supplies a modal endpoint
and may keep its direct full-page option route for fallback navigation. The modal
loads one mapping workspace at a time, reuses the option mapping controller and
guards closing against unsaved changes.
After a successful refresh in the embedded workspace, it reloads the workspace
content in the open modal. The direct option page still reloads the page.
After initializing each embedded workspace, the attribute page root emits
`vea:option-mapping-ready` with `detail.workspace`. Optional Admin UI modules
can attach behavior to the workspace and register cleanup with its `veaWorkspace`.

The shared `veui-connected-icon` class exposes the existing connection icon
used between mapping pairs. Category mapping and history screens reuse it for
the `Connected` label and indicators.

`view/adminhtml/web/js/source-bulk-transfer.js` owns source-column multi-selection
for compatible mapping workspaces. It adds per-card checkboxes and a `Select all` /
`Deselect all` action in each source options menu. The action toggles active, unmapped cards in the visible list,
keeps selections hidden by search, and is disabled when no visible cards are eligible.
It updates its label after manual selection, filtering or source changes and
delegates the domain-specific move operation to the consuming mapping controller
through the shared `Add to mapping` action. Product and category attributes and
options, templates and language mapping reuse this behavior;
tree-specific category selection remains in its domain module.

Section navigation and synchronization-readiness presentation are extension
points. This module registers only its own destinations and connection checks.
Each domain Admin UI module contributes its navigation item or complete grouped
navigation, readiness label, remediation route and check definitions through
DI. Group labels, options and domain section codes remain owned by that domain
module. Disabling it therefore removes its destination and readiness
presentation without leaving a route or ACL reference in this module.

The Synchronizations screen presents the cursor state supplied by
`Ergonode_Core` using the same workspace and navigation primitives. It does not
hard-code optional domains: enabled consumer modules contribute their own
process definitions, so absent or disabled modules leave no status row.
Administrators with the dedicated run or reset ACL can select executable
processes in this screen. The shared UI sends one CSRF-protected POST per
selected process and delegates the operation to the domain runtime registry.

## Admin UI Storybook

`dev/tools/ergonode-storybook` renders the shared Admin UI contract with the
HTML Storybook renderer. It imports the production CSS from this module and
loads production RequireJS modules from raw source through an explicit AMD
adapter. Domain kinds are story data, not separate item components.
Feature-specific variants and stories live in the owning module's
`Test/Storybook` directory.

Run `ddev exec npm run storybook:ergonode` for the development server or
`ddev exec npm run storybook:ergonode:build` for the deterministic build.

## Live synchronization monitor

The Synchronizations screen refreshes cursor state every two seconds and shows the
last checkpoint update and explicit reset time. These timestamps do not certify a
successful completed run. The screen offers registered synchronization and cursor
reset actions; it does not store execution history or estimate duration.
Process identifiers use camelCase only in presentation; persisted keys are unchanged.

Attribute and option mapping for products and categories share the templates in
`view/adminhtml/templates/attribute` and `view/adminhtml/templates/option`. Their
side searches and selection/options controls occupy the panel header; central
search and the shared primary Save/options split button occupy the mapping header,
alongside the option context selector when present. These screens use expandable
search and omit header counts. `veui-panel-heading` lets a subtitle truncate
inside a compact header.

Core owns one connection status at `ergonode_connection/general/enabled`,
defaulting to disabled. It gates runtime queries and mutations for both environments.
The General status remains visible; active environment, operating mode, REST controls
and both environment groups are hidden and excluded from submission while disabled.
When enabled, only the selected environment and mode expose their fields. Its URL
and API key are required and marked before submission. Hidden saved values are retained.
The former per-environment status paths are no longer read or migrated; existing
installations must explicitly enable the new global status. URLs and keys keep their paths.

The shared attribute template exposes an optional `sidebar` child below the Magento attribute list in the existing right column. A `vea-side-content` region scrolls the list and contribution together while the column header remains visible. The host owns the container; extensions own its content.

## Shared mapping history panel

`Block/Adminhtml/History/Panel` and `history/panel.phtml` render the optional
read-only history panel within a mapping sidebar. The domain layout supplies
`operations_route`, `history_route` and its history ACL. CoreAdminUi owns the
panel, operation cards, pagination/retry behavior and `history-operations.css`.
`js/history/operations` is also used by the full product/category history viewers,
so sidebar and full-view operation cards share one implementation and styles.
The component loads summary pages only and links to domain-owned historical states;
it does not own storage, capture, authorization resources or entity mapping rules.

The existing Product navigation group combines the optional Attributes and List
contributions and checks each destination's ACL independently. Options and history
are reached through mapping modals and sidebars, not section navigation.

Attribute pair rows accept domain-supplied `validation_message`, `validation_tone` and Magento attribute code. The shared controller preserves this message during editing and saving while that target attribute remains in the row. Replacing the target removes the stale message. Core does not interpret adapter codes or domain availability.

## Informacyjne kontrole synchronizacji

Panel synchronizacji renderuje `monitor_only` z ogólnego rejestru Core: rozpoczęcie ostatniej kontroli, jej wynik, zakończenie i ostatnio wykrytą zmianę. Dane odświeża istniejący polling. Pozycja informacyjna nie udostępnia uruchomienia/resetu ani kursora. UI rozróżnia brak danych, rozpoczęcie bez zapisanego zakończenia, wykrycie zmiany podczas odświeżania, stan początkowy, brak zmian, obsłużone zmiany i błąd. Nie interpretuje czasu checkpointu jako dowodu wykonania.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.

## Testy konfiguracji połączenia

Scenariusze `ERG-CONN-001..007` należą do tego modułu i są uruchamiane przez
PackHauer Playwright. Obejmują globalny status, środowiska, tryby, walidację,
zapis i rzeczywiste połączenie odczytowe ze stagingiem. Szczegółowe polskie opisy,
przygotowanie i zasady odtwarzania konfiguracji są w
[Test/Playwright/README.md](Test/Playwright/README.md).
Dane dostępowe pochodzą ze wspólnego, ignorowanego pliku projektu
`app/etc/playwright.yaml`; nie należą do modułu i nie są wysyłane do repozytorium.
Nie dodaje to zależności runtime Magento od PackHauer.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.

CoreAdminUi owns the opt-in handle, content rendering gate and notice; domains
opt in through their existing CoreAdminUi dependency. Core's existing connection
check supplies configuration blockers without running domain mapping checks or
remote queries. Readiness, configuration and history pages do not opt in.
The Core ReadinessContext value object is reused to call ReadinessCheckInterface;
no duplicate readiness rules or domain dependencies are introduced here.

## Snapshot removal lifecycle

`snapshot-removal.bind` accepts optional `beforeRemove(button)` and `onError(error, button)`
callbacks. The synchronous preflight runs after confirmation and dirty-state validation,
just before starting HTTP; returning false cancels that attempt. Errors notify the consumer
before the shared error message. Without these callbacks the existing behavior is unchanged.
LanguageAdminUi uses them to lock its editor until navigation or release it after failure.
The consuming workspace owns its editing state; CoreAdminUi owns confirmation, request and reload.
`snapshot-removal.enhance` accepts `removable: false` for screens without a
snapshot endpoint while retaining their mapping and direction-specific card actions.
The shared product attribute template shows Refresh for both the neutral remote
catalog and the optional inbound snapshot. The option template uses the optional
`refresh_available` capability for neutral fetching and falls back to
`snapshot_available` for existing consumers. Product mapping scripts derive removal availability
from the configured endpoint. Other domains keep the default action.
