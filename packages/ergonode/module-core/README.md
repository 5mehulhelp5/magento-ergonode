# Ergonode_Core

This module owns the Ergonode GraphQL connection, authentication, mapping
visibility storage and shared paginated-import mechanics. Domain queries,
normalization and configuration defaults belong to the corresponding consumer
or publisher modules. GraphQL reads always use the remote API; consumer data
used by Magento is persisted by the owning domain module.

Enabled consumer modules register their persistent synchronization processes in
the shared synchronization-status provider. The provider joins only those
registrations with `ergonode_import_cursor`; an orphaned cursor row is therefore
not exposed as an available capability. A registered process remains visible
before its first run with an empty cursor state.

Executable synchronization operations use a separate registry. Domain runtime
modules contribute lock-aware synchronization and cursor-reset implementations;
Admin consumers dispatch only process codes present in that registry.

## Connection selection

Core owns `ergonode_connection/general/environment` (`test` or `production`)
and `ergonode_connection/general/mode`, shared transport and environment limits.
Optional modules register `ConnectionModeInterface` implementations in
`ConnectionModePool`: Consumer supplies `read`, Publisher supplies `write`.
Only one mode is selected. All queries use that mode's key; mutations and
publisher-scoped queries additionally require its write capability. Missing modes
fail closed without falling back to another key. Credential paths and defaults
belong to the module supplying the mode, not Core.

Each environment owns `url` and `requests_per_minute` directly under
`ergonode_connection/test` or `ergonode_connection/production`. No historical
configuration paths or migration fallback are supported. Existing settings must
be entered under the new paths; old database rows are not migrated or removed.
Core uninstall removes only the seven current shared paths and its existing storage.

The seven current shared connection settings (selection, global enablement,
URLs and quotas) are environment-specific. Global DI registers them with
`Magento_Config`'s `TypePool`; credentials remain owned by Consumer and Publisher.

## Synchronization monitoring

The monitor presents registered synchronization processes and their cursor state. Cursor storage
records an explicit reset time and preserves the previous checkpoint timestamp
on reset. A checkpoint timestamp is not proof of a completed successful run.
Monitoring does not own domain synchronization or write audit history.

## Internal GraphQL request limit

Each environment's `requests_per_minute` is a non-negative
integer, defaulting to `0` (disabled). Core owns its runtime configuration,
`GraphQlRequestLimiterInterface` and the shared cache counter guarded by a lock.
CoreAdminUi exposes both limits. Connection tests explicitly reserve from the
requested environment, independently of the saved active environment. Cache keys
and locks include the environment; switching modes does not reset its quota.

One outbound HTTP attempt consumes one slot, including retries and requests
that receive errors. A batched GraphQL document consumes one slot regardless
of its operation count. Reads, writes, CLI, cron and Admin share the same quota within that environment;
REST and file downloads are outside it. Separate workers must share both the
Magento cache and lock backend. Separate Magento installations do not share a
quota automatically. Flushing or evicting cache can reset this diagnostic limit.

The quota resets at each wall-clock minute boundary, not after a rolling 60
seconds. The window is selected after acquiring the shared lock. A rejected
attempt is not sent and does not increment the counter or extend the window.
`RateLimitExceededException` implements the same `GraphQlRequestException`
contract as an upstream HTTP 429: `rate_limit`, status `429` and seconds until
the next minute. Its message explicitly identifies the internal Magento limit.
No HTTP response from Ergonode is fabricated. Callers retain their existing
retry policy and must preserve the original message when reporting a failure.

Changing the setting uses normal Magento configuration cache refresh; restart
long-running consumers after changing it. A non-zero limit change does not
reset an already used minute quota. Setting `0` bypasses cache and locking.
The saved environment limit also applies to tests with submitted credentials.

Core owns one connection status at `ergonode_connection/general/enabled`,
defaulting to disabled. It gates runtime queries and mutations for both environments.
The General status remains visible; active environment, operating mode, REST controls
and both environment groups are hidden and excluded from submission while disabled.
When enabled, only the selected environment and mode expose their fields. Its URL
and API key are required and marked before submission. Hidden saved values are retained.
The former per-environment status paths are no longer read or migrated; existing
installations must explicitly enable the new global status. URLs and keys keep their paths.

## Obserwacje wykonania synchronizacji

`SynchronizationObservationProviderInterface` pozwala domenom dołączyć ostatni zarejestrowany przebieg do `SynchronizationMonitor`. Domeny rejestrują dostawców przez DI, a monitor odczytuje tylko dostawców procesów z aktywnego rejestru. Opcjonalne `monitor_only` oznacza informacyjną pozycję bez kontrolek wykonania. Core nie przejmuje zapisu ani orkiestracji domeny; dane obserwacji są niezależne od czasu przesunięcia kursora.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.

## Scheduled connection readiness

`AutomaticSynchronizationInterface::isAllowed()` checks the enabled read mode
and then probes the active endpoint with `query ConnectionTest { __typename }`
through the shared GraphQL client. Local missing/invalid URL, key or environment
prevents HTTP. A rejected key, unavailable endpoint, rate limit or invalid probe
response skips scheduled work without logging an error. Each outbound probe
uses the environment quota; results are not cached, so the next invocation can
resume after configuration or connectivity recovers. Unexpected programming,
configuration-storage and quota-storage exceptions are not swallowed.

The probe precedes domain execution, cursor leases and recovery queue dispatch.
Domain cron switches are checked before it where applicable. Core does not own
work items, domain writes, history retention or the local media scan.

`ConnectionConfigurationException` preserves the `GraphQlRequestException`
failure/status contract and identifies invalid local settings, HTTP 401/403/404
and a non-JSON endpoint response. Domain cron entrypoints also skip this exception
if credentials are rejected after a successful probe. Ordinary transport/server
failures during domain work retain their existing diagnostics and retry semantics.
Manual operations still receive connection exceptions. A normal Magento cron
start/finish record remains; skipping is a successful invocation, not an error.

## Download sources

Core authorizes file downloads only from the configured Ergonode origin: scheme,
host and effective port must match the active connection URL. No additional domain
configuration is needed or supported. External sources are rejected.

DownloadSourcePolicyInterface authorizes each request and redirect destination before
an HTTP client is created or the API key is attached. Credentials and downloaded-file
ownership remain outside this policy. Existing files and stored data are not migrated.

## Change report memory boundary

ChangeReport owns temporary report storage and filtering; domain modules still supply
events and decide when a run starts. Entries spill to temporary storage beyond 2 MiB.
`iterateEntries` reads a stable prefix without materializing the complete report; the
console writer renders batches of 200 entries. Unchanged entries and diagnostic details
remain available, including with the console's include-unchanged option. `getEntries`
is an explicitly materializing read used by integration assertions. Reset discards the
current temporary report. This storage is process-local and is not audit persistence.
