# Ergonode_Publisher

This module owns protocol-neutral publishing contracts and shared GraphQL
mutation execution for the `Ergonode_*Publisher` module family.

The module is intentionally domain-agnostic. It must not know product,
attribute, category, tree, template or multimedia mutation names. Domain
modules create typed in-memory operations and pass them to this module for
composition and execution.

## Runtime model

Publication plans are stateless:

1. A domain module reads desired Magento state and current Ergonode state.
2. The domain module calculates an in-memory synchronization plan.
3. This module chunks the plan and composes variables-based GraphQL batches.
4. The existing `Ergonode_Core` GraphQL client executes the document.
5. Results and errors are correlated by aliases and returned to the caller.

The module does not persist an outbox, cursors, remote identifiers, generated
documents or synchronization status. A caller recovers from interruption by
running reconciliation again.

## Owned contracts

The module exposes narrow contracts for:

- an immutable mutation operation with a field, typed variables, response
  field paths, an optional alias and scalar correlation metadata;
- a batch document with its variables and alias-to-operation correlation;
- deterministic input-order batch planning;
- per-operation success, validation failure, permanent transport failure,
  transient failure and unresolved results;
- an optional remote-state verifier for ambiguous responses;
- transient retry with in-memory attempt counters and `Retry-After` support.

An empty operation list builds an explicit empty batch. Executing that batch
is a successful no-op and does not call Ergonode.

Transient failures that are known to happen before execution, such as local
request limiting or HTTP 429, may be retried directly. A timeout, transient
5xx response, transient GraphQL error or missing result key is ambiguous and
is never repeated until a domain verifier confirms that the operation was not
applied. If verification finds the intended remote state, the operation is
reported as successful without another mutation.

Variable values are limited to JSON-compatible scalars, null and nested arrays.
Correlation metadata is limited to immutable scalar values. This keeps an
operation stable between its initial request, verification and any retry.

Each batch contains at most 50 operations and at most 524,288 bytes of
serialized GraphQL variables. `MutationBatchPlannerInterface` applies a greedy
input-order split before the first operation that would cross either limit. A
single operation larger than the variables limit is rejected as a permanent
planning error instead of being sent. The limits are explicit DI arguments and
may be lowered for a target environment without changing domain planners.

## Execution barriers

One mutation document may contain only operations whose inputs are complete
before execution and whose success can be verified independently. Independent
updates to already verified remote entities may share a batch.

A create operation must be executed and re-read before any later operation
that depends on the created entity, generated remote identity or resulting
state. Attribute options, allowed-category attributes, values and relationships
therefore use a subsequent batch when their prerequisite is created or changed
in the same synchronization plan. Domain planners own these barriers; the
shared batch planner only chunks an already ordered, dependency-safe group.

## Boundaries

- GraphQL mutation execution and authenticated REST transport are shared infrastructure.
- Values are always sent through GraphQL variables, never interpolated into
  raw input literals.
- Domain validation and diff calculation belong to domain publisher modules.
- Domain publishers own their application workflows and depend only on shared
  mutation contracts from this module; this module does not orchestrate domains.
- Admin configuration and UI actions belong to `Ergonode_PublisherAdminUi`.

See [PLAN.md](PLAN.md) for the implementation decisions and completion
criteria.

Rate-limit reporting preserves the originating message, including internal
Magento quota failures, together with the retry delay. Request limiting remains
owned by `Ergonode_Core`; this module does not maintain its own request quota.

## Connection mode and credentials

Owns the `write` (read-and-write) implementation of Core's `ConnectionModeInterface`,
registered through global DI. Credentials are encrypted at
`ergonode_connection/test/publisher/api_key` and
`ergonode_connection/production/publisher/api_key`; both are marked sensitive and
environment-specific.
Core selects one mode and environment for all requests. This module never selects
another profile after an error and does not own URLs, quotas or the form layout.
Uninstall removes only these two credential paths. No legacy paths or migration
fallbacks are supported. UI validation and test controls belong to PublisherAdminUi.

Global DI also classifies the retired `ergonode_connection/write_operations/api_key`
as sensitive and environment-specific while old rows remain in `core_config_data`.
This export classification does not read, migrate or reactivate the retired key.
It can be removed after those rows have been removed through a separately approved
data cleanup on all installations that retain them.

## Shared REST connection

Publisher also owns the authenticated REST transport for publication, generic grid
pagination and `ConnectionManagementInterface` (login, status, disconnect). The
remembered connection is shared by the integration, independent of Magento admin sessions.
One row per Test/Production profile in `ergonode_publisher_rest_connection` stores
origin, email, encrypted access/refresh tokens, expiry and login generation. Passwords
are used only for login. Token refresh and login/disconnect share a per-profile lock;
both tokens are replaced atomically after validating the response. A 401 triggers at
most one refresh/retry; a 403 on a resource is not retried. Rejected refresh requires
login; transient transport errors preserve credentials. Origin changes prevent reuse.
REST authentication failures log only the active profile, a reason code and, when
available, the HTTP status. Reason codes distinguish a missing connection, origin
mismatch, missing access or refresh token, rejected refresh and rejection after
renewal. Tokens, email, origin, request payloads and response bodies are not logged.

REST uses Core's active environment, URL and rate limiter.
The retired template/attribute metadata cache and its grid reader are no longer
part of this module. Product writes continue through GraphQL; category tree REST
is a separate caller. Consumers do not depend on this REST feature.

REST transport failures and non-success HTTP responses are recorded through Magento's
standard logger (`var/log/system.log`), with profile, method, query-free path and HTTP
status (null for transport failure). Credentials, payloads, response bodies and exception
traces are excluded. HTTP 405 guidance identifies an unsupported request method that
requires an integration correction, not another login. HTTP 400 guidance identifies
rejected request parameters and directs investigation to the integration format/log.
Domain callers retain their
validation barrier and SKU context; the shared transport does not orchestrate publication.

Uninstall drops the owned token table and ACL rows and removes owned write keys.
Backlog removal with removeData invokes this lifecycle; moving
without removeData intentionally preserves owned data. Removing PublisherAdminUi alone
preserves the connection. Disconnect is local deletion, not remote revocation.
The new declarative table needs setup upgrade and one integration login; no session
migration, data patch or stored password is introduced.

Admin login offers explicit persistence. PublisherAdminUi decorates the storage
contract in adminhtml with encrypted session credentials when Remember me is off.
The same login and refresh services use the selected storage; only remembered
credentials reach this module's table. CLI and cron continue using the persisted
account. Status refreshes expired tokens and returns unauthenticated after a rejected
refresh, allowing an operation-time login before publication starts.

## Verification round lifecycle

`MutationVerificationRoundInterface` is an optional extension of the ambiguous mutation
verifier. The executor begins a new round before every attempted mutation document,
including retries. A domain may share fresh observations across operations in that
round; it must discard them when the next round begins. Existing verifiers without
shared observations continue to implement AmbiguousMutationVerifierInterface.
