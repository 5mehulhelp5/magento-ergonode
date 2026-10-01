# Ergonode_LanguageAdminUi

Provides the Magento Admin mapping UI for Ergonode languages and Magento Store
Views.

The module owns the Languages destination and language readiness presentation
contributed to `Ergonode_CoreAdminUi`. Disabling it removes those entries from
the shared navigation and readiness screen.

The mapping page renders a single current snapshot and its revision. Autosave
sends that revision and adopts each successful response's new revision before
sending the next queued edit. Conflicts return HTTP 409 with a reload instruction;
retry does not silently adopt a newer revision. Refresh flushes pending saves
and reloads server state without restoring an old browser-history snapshot.
The entire workspace is inert from the start of refresh through the pending-save
flush and remote response. A failure unlocks it; success keeps it locked until
navigation, preventing a new edit from being lost to the reload.

The block passes the existing save ACL as `canSave` to the renderer and JavaScript.
Without it, cards are not draggable, mapping/visibility controls are disabled,
editing menus and bulk selection are omitted, and mutation handlers are not
bound. Search and the excluded-items filter remain usable. Refresh is governed
by its independent ACL. The server continues to authorize every mutation.
The required Admin / Default Values scope cannot be excluded; its language can
still be assigned or changed by an editor.

Automatic matching indexes active languages by exact locale and language prefix,
retains first-match precedence, and updates panel state once per batch. The batch
uses existing card references and produces one autosave request.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.

## Browser tests

The module owns the scenarios in Test/Playwright; PackHauer_Playwright discovers
their annotations but does not own language behavior. This test-only update does
not change runtime contracts, dependencies or authorization.

| ID | Kind | Coverage |
| --- | --- | --- |
| ERG-LANG-001 | browser-mocked | Panels, pairing/unlinking, autosave presentation and visibility |
| ERG-LANG-002 | browser-mocked | Drag-and-drop and snapshot-removal requests |
| ERG-LANG-003 | e2e | Real mapping save, persistence after reload, unlink and persisted cleanup |
| ERG-LANG-004 | e2e | Two tabs, stale save and retry return real HTTP 409; first editor's data survives |
| ERG-LANG-005 | e2e | Aborted request leaves persisted data unchanged; real retry saves the pending edit |
| ERG-LANG-006 | e2e | Save commits but response is lost; retry conflicts and reload restores authoritative state |
| ERG-LANG-007 | e2e with controlled upstream | Paginated refresh persists after reload and preserves existing data |
| ERG-LANG-008 | e2e with controlled upstream | Second-page failure preserves the snapshot; retry succeeds |
| ERG-LANG-009 | e2e | Removal cancellation sends no request; confirmed removal survives reload |
| ERG-LANG-010 | e2e | Stale removal fails visibly without changing other data |
| ERG-LANG-011 | e2e | Viewer/editor roles, hidden refresh, real HTTP 403 and permitted editor save |
| ERG-LANG-012 | e2e | Missing menu entry and HTTP 403 for an authenticated direct navigation |

The first two scenarios intercept mutation responses; they do not prove database
persistence. Their conditional interactions require active languages and unmapped
Store Views; ERG-LANG-002 skips when these are absent.

All scenarios require the enabled Language and LanguageAdminUi modules, a valid
active Ergonode connection configuration (required by the workspace), Chromium
and a dedicated Magento test account. The module's support/language-test.ts
adapter uses the existing @vendivo/test-config loader and the repository's
dev/tests/playwright E2EContext. Configure magento.baseUrl, magento.adminPath,
magento.defaultAccount and magento.accounts in the ignored app/etc/playwright.yaml
(or PROJECT_TEST_CONFIG). MAGENTO_TEST_ACCOUNT may select another explicitly
configured account; there is no fallback to an administrator. The adapter passes
the selected credentials to E2EContext for that fixture only and restores the
previous environment afterward, including after failure. No account is created,
no password/permission is changed and no 2FA bypass is introduced. No package or
Magento module dependency is added; the existing project test tooling is reused.
These specs are not a standalone Composer test distribution.

Account permissions: Ergonode_Core::main and
Ergonode_Language::language_mapping. ERG-LANG-003 through ERG-LANG-006 additionally need
Ergonode_Language::language_mapping_save. PackHauer dashboard view/run permissions
belong to the person launching a test, not automatically to the browser account.

### Real-write scenario prerequisites

ERG-LANG-003 through ERG-LANG-006 use the existing local Store Views and
snapshot languages selected for this environment:

- `default` ↔ `en_GB` (the Store View locale is `en_US`; the available upstream English code is `en_GB`).
- `base_pl` ↔ `pl_PL`.

Every scenario exercises both pairs. It creates no Store View or snapshot language
and requires no manual fixture preparation. The optional preflight is read-only
(the legacy `--prepare` argument is also read-only):

    ddev exec -s playwright node packages/ergonode/module-language-admin-ui/Test/Playwright/support/prepare-fixtures.mjs --check

The tests require a local Magento host, visible source cards and no language-only
drafts for the two source languages. Before editing a Store View, they capture
its original mapping or draft, temporarily unlink it if needed, and restore it
through the real UI in finally. They verify all original mapping pairs and source
visibility afterward, ignoring mapping row order. Other Store Views are untouched.
Cleanup reloads the current revision and refuses to remove a pair changed to an
unrelated language. Hard process termination or Magento unavailability can prevent
cleanup; inspect the affected Store View before retrying.

ERG-LANG-004 opens two authenticated tabs at the same revision. The first commits
a real mapping; the second must receive HTTP 409 on stale save and retry.
ERG-LANG-005 aborts the initial request before the server receives it; its retry
uses real Magento. ERG-LANG-006 forwards the initial request to Magento and then
discards its response; a separate page confirms the write actually committed.
Neither fault scenario mocks a successful persistence response.

From the backend, using the same runner/API as the dashboard:

    make -f .agents/backend/Makefile playwright-catalog RUNTIME='ddev exec' test=ERG-LANG-
    make -f .agents/backend/Makefile playwright-run RUNTIME='ddev exec' test=ERG-LANG-003

Use IDs 004, 005 and 006 for the failure paths, or prefix ERG-LANG- for the suite.
The runner executes one test at a time. A skip/failure is reported as such by the
CLI; catalog availability alone does not establish that E2E passes.

### Initial verification checkpoint (2026-09-12; superseded below)

Approved test-only scope: descriptions/kinds and a separate persistence scenario
in this module (user approval: "akceptuję"). No PHP runtime class, public API,
dependency, ACL, schema or Storybook component changed; MAG-SOLID-001..008 are
not applicable to the changed test/documentation files. No compatibility layer,
migration or diagnostic suppression was added.

The PackHauer catalog discovers ERG-LANG-001/002/003 as available with no metadata
issues. All 22 existing JavaScript tests passed. The scoped agent-finish gate
passed 9 of 10 checks (including PHP unit tests), but PHPArkitect reports three
adminhtml-dependency violations in the unchanged Test/Unit/Block/Adminhtml/Language/MappingTest.php.
This is not an all-green module completion claim.

The first live ERG-LANG-003 attempt stopped in the old login fixture: the
container password differed from the selected account in the shared test
configuration. The module adapter now uses that existing shared configuration;
no stored credentials or permissions were changed. No mapping write occurred
during that first attempt.

The second attempt used the shared configuration and still failed before entering
the workspace. A read-only Magento User lookup confirmed that its selected
account does not exist in the current local database. No user, role or password
was created/changed. The local database also has no snapshot languages and no
reserved Store View. These environment prerequisites currently block executing
and verifying the real save/cleanup flow; the scenario is implemented, not yet
demonstrated passing end to end.

## Controller failure-path tests

Unit tests under `Test/Unit/Controller/Adminhtml/Language` cover missing and
malformed mapping payloads, revision validation, returned statistics/revision,
HTTP 409 conflicts, snapshot refresh/removal and service failures. Unexpected
exceptions are logged while the JSON response contains only the public error
message. Existing optional-visibility fallback behavior is tested explicitly.

Integration tests under `Test/Integration/Controller/Adminhtml/Language` dispatch
Save, Refresh and DeleteSnapshot through authenticated Magento admin routing:

- Each endpoint accepts its authorized POST and denies its specific ACL resource.
- Empty/incorrect form keys and GET/PUT requests never call the domain service.
- Invalid save payloads return a failure response without calling the saver.
- A stale save produces HTTP 409 against real database state; the newer mappings,
  visibility and revision remain unchanged.

The ACL tests use Magento's integration admin and in-memory ACL denial inside
the isolated test application. They do not change accounts or permissions in
the local application database. Mutation services are mocked for routing and
authorization tests, including refresh, so no remote Ergonode call occurs. The
stale-revision scenario uses the real Language saver in the integration database.
These tests complement browser E2E; they do not establish browser behavior.

Magento's controller test helper automatically adds a valid POST form key. The
negative tests set an explicit request parameter, which takes precedence, so the
helper cannot accidentally turn those requests into authorized requests. For
these legacy backend actions, invalid Ajax form keys return `error: true`, while
ACL denial returns HTTP 403 and unsupported methods return HTTP 404.

## Autosave and refresh sequencing tests

`Test/Js/language-autosave-concurrency.test.cjs` and
`Test/Js/language-refresh-flow.test.cjs` execute the production AMD modules with
controlled time and network responses. They load the real Language autosave
adapter and Core autosave/request primitives. Refresh tests also mount the full
language-mapping module and invoke its registered refresh/retry handlers with
the real button lifecycle. Presentation collaborators and a minimal mapping DOM
are fixtures; these tests do not cover browser rendering or pointer interactions.

Coverage includes queued edits using the previous response's revision, immediate
flush and multiple waiters, failures with queued changes, explicit retry using
the latest snapshot, lost responses followed by HTTP 409, invalid response
revisions and serialization failures. Refresh must wait for all saves, remain
blocked after a save failure, and reload only after its own successful response.
Failed refreshes preserve the saved revision and allow another refresh without
repeating a successful save. An unsaved mapping must not become the saved baseline.

Run the complete module JavaScript suite from the backend:

    node --test packages/ergonode/module-language-admin-ui/Test/Js/*.test.cjs

The scoped PHP completion gate does not run this JavaScript suite. Both results
are required when changing these tests. No additional Node dependency is needed.


### Historical E2E verification (2026-09-12; fixture strategy superseded)

ERG-LANG-003/004/005/006 all passed against local Magento through the PackHauer
runner. This verifies real persistence and cleanup, stale-editor HTTP 409 on both
save and retry, recovery after an aborted request, and a committed write whose
response was lost. No test/runtime correction was needed during this live run.
The catalog discovers all six scenarios without metadata issues. The two older
browser-mocked scenarios were not rerun in this final four-scenario validation.

The initial connection prerequisite was resolved with explicit user approval:
only five default-scope paths (general/enabled, general/environment, general/mode,
test/url and test/consumer/api_key under ergonode_connection) were temporarily
configured from the existing local Playwright test profile. The API key was
stored encrypted. A protected recovery snapshot and the shared connection lock
covered the run. Finally restored the exact original rows, refreshed config cache,
verified equality and removed the recovery snapshot. The original disabled
connection, empty URL/key and test/read selection are restored intentionally.
Accounts, permissions and the production profile were unchanged.

Every scenario verified original mappings and visibility after persisted cleanup.
A final fixture check found no reserved mapping/draft or visibility collision.
That historical run used a disabled reserved Store View and synthetic languages;
current tests use the existing pairs documented above.

The scoped module completion gate also passed 11/11 checks: 35 PHP unit tests
(222 assertions, one existing PHPUnit mock notice), 23 integration tests
(84 assertions) and the separately executed 38 JavaScript tests pass. They were
not repeated after the live run because no test source or runtime changed.

These four E2E cover critical mapping-save paths. They do not establish exhaustive
coverage of live remote refresh/removal or browser-specific permission matrices;
controller failure and ACL behavior have separate unit/integration tests.


### Snapshot and permission scenarios (2026-09-13)

ERG-LANG-007 through ERG-LANG-012 require the isolated CLI wrapper, which prepares
all four limited roles/accounts before clearing Magento's cached ACL role list:

    ddev exec php packages/ergonode/module-language-admin-ui/Test/Playwright/support/run-isolated.php

Pass a comma-separated list of these IDs to run a subset. The wrapper invokes
`.agents/backend/Makefile playwright-run` inside the web container. This keeps
PackHauer catalog/run results authoritative. The raw dashboard run requires the
same prepared environment; the scenario fails its prerequisite before changing
a snapshot if the isolated URL is not configured.

The GraphQL test server binds port 18089 inside the Playwright container; no host port
is published. Only the real Magento GraphQL transport calls its language-list
endpoint. The server accepts the synthetic fixture key and expected query, then
returns existing snapshot languages plus `playwright_snapshot_e2e` in two pages.
On the error path, page one contains the new code and page two returns a GraphQL
error. Tests verify that no part of the new snapshot reached the database.
This validates browser/controller/transport/database integration with a controlled
upstream, not availability or schema compatibility of a live Ergonode deployment.

For direct-navigation ACL testing, the wrapper also starts a temporary PHP router
on web-container port 18090. It accepts only a bounded request to sign the Language
index URL with the current browser form key, using Magento's own encryptor. It
exports no application encryption key and grants no permission. This is needed
because the current Magento version uses HMAC for backend URL signatures. The
router refuses normal PHP execution and non-fixture configuration; no host port
or production route is added. The wrapper stops it in finally.

The wrapper temporarily owns only the same five connection paths documented
above. It sets test/read with a local endpoint and encrypted synthetic key;
it never sends the real configured key to the fixture server. A protected
recovery file and the shared connection lock cover the whole suite. It preserves
the exact original rows and removes the recovery file only after restoring and
verifying the configuration and deleting its temporary accounts and roles.

Accounts are reserved as `pw_lang_e2e_full`, `pw_lang_e2e_viewer`,
`pw_lang_e2e_editor` and `pw_lang_e2e_denied`. Existing accounts/roles with those
names cause a failure before provisioning. New accounts reuse the selected test
account's password hash for real UI login; that account and its permissions stay
unchanged. Only dashboard/Core navigation and explicitly selected Language
resources are allowed; Magento's all-resources grant is denied. The viewer cannot
save, delete or refresh; the editor can save but cannot refresh; the denied role
cannot open Language at all. The full role means all Language actions, not all
Magento permissions. The tests assert hidden refresh controls and server-side
write denial; they do not claim that every editing control is hidden for viewers.

The fixture language must initially be absent from snapshot, mappings and visibility.
Each test deletes only this owned code in teardown and compares the complete
original snapshot, mappings and Language visibility rows. No stale full snapshot
is written back during cleanup. Failure paths also assert no automatic navigation
and unchanged persisted data before retry or reload.

Run without manual Language edits or background refresh. Hard termination can
prevent teardown: keep `var/test-state/language-e2e-connection.json`, stop the
active run, and restore exactly its captured configuration rows and recorded
temporary user/role IDs before another run. Do not delete a recovery record merely
to bypass the runner or silently remove colliding accounts without ownership proof.


Final verification for this extension: all six scenarios ERG-LANG-007 through
ERG-LANG-012 passed in one serial run. The scoped completion gate passed 11/11,
including 35 PHP unit tests (222 assertions; one existing mock notice) and
23 integration tests (84 assertions). All 38 JavaScript tests passed separately.
Post-run checks found no temporary user, role or snapshot fixture, both helper
servers stopped, and the original connection configuration restored exactly.
The module now has ten verified E2E scenarios across these two stages, plus the
two older browser-mocked scenarios; this is not exhaustive branch coverage.

## Snapshot removal and inactive mappings

Refresh and snapshot removal share one workspace lock. Confirmed removal locks before HTTP
and remains locked through the delayed reload; failure unlocks editing. Cancellation or dirty
state starts no removal. Concurrent refresh/removal cannot start another operation.

The block marks mapping availability from the current Language DTO. Missing or excluded
languages and excluded Store Views retain their saved pair and show an inactive warning.
Only active complete pairs satisfy the required Default Values mapping. The AMD controller
recomputes this presentation from current source cards after edits; remapping to an available
language clears the warning. Rendering never deletes saved pairs.

## Draft transfer and source search

Moving a Store View draft onto another draft resolves the source row before
changing the destination. The source draft is removed once; dropping onto itself
preserves it. Bulk transfer reuses the selected cards and recalculates the panel
once after the batch, preserving draft order and one autosave request. Draft
lookup still scans current rows; this is not a claim of constant-time bulk editing.
Language search includes both the displayed locale label and its language code.

Regression coverage includes both draft orders and self-drop in JavaScript tests,
and production AMD in LanguageEditing stories for draft transfer, real bulk
controls, update counts and keyboard search. Storybook uses fixture HTML/transport;
the PHTML contract test additionally checks the actual search attribute source.
