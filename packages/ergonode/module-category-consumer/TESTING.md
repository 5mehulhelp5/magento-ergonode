# Testing Ergonode_CategoryConsumer

Run automated checks from the backend repository root, using the active agent
runtime configuration:

```bash
make -f .agents/backend/Makefile module-check module=Ergonode_CategoryConsumer
make -f .agents/backend/Makefile test-integration args='app/code/Ergonode/CategoryConsumer/Test/Integration'
```

The related Category, CategoryAttributeConsumer and CategoryConsumerHistory modules
must also pass their checks when their contracts change. Use isolated test fixtures;
acceptance testing must not reset catalog tables in an existing environment.

## Automated regression coverage

- 101 unique category codes require three read requests; duplicate codes add none.
- Attribute aliases paginate independently and completed aliases are not fetched again.
- A fetch failure in the second batch leaves the first applied and does not save a cursor.
- Nested cache deferral coalesces invalidation and clears pending state after a failure.
- A stored mapping to an excluded Magento category or its descendant preserves
  identity but cannot cause a move or duplicate creation.
- Repeated sibling-order checks and moves preserve idempotence and tied positions.
- Download scope isolates read/write credentials and releases state after errors.
- Grouped history captures only touched trees under the synchronization lock.
- Keyset replay crosses both page and operation boundaries without dropping deltas.
- A failed value write rolls back the manual mapping and category name together.

## Pre-production acceptance

Record results for Admin and CLI against a representative, isolated catalog and
an Ergonode connection authorized for testing.

1. Configure two local roots for one remote tree. Trigger one structural event;
   both snapshots and roots must update, while the complete remote tree downloads
   once in the run. A later run must fetch fresh data.
2. Import several thousand mapped categories. Measure HTTP requests, SQL queries,
   duration and peak memory with and without the attribute extension. Account for
   stream pages, metadata preparation and per-category attribute pages separately.
3. Exclude a stored target, then its parent. Preview and apply must preserve the
   mapping, report the category as excluded and make no target changes. Include it
   again and verify normal reconciliation resumes.
4. Repeat a completed import without source changes. Verify no duplicate categories,
   unnecessary moves, value rewrites or consumer-level frontend cache cleans.
5. Inject HTTP 429 during a later batch. Earlier completed values remain saved,
   the affected cursor remains unchanged and retry safely completes the import.
   Inject an error during a value write and verify cache invalidation also covers
   the partially attempted batch.
6. Fail the second value write during manual layout save. Verify that mappings,
   visibility, names, EAV values and entity snapshots on the shared connection
   roll back. Metadata preparation before the transaction is outside this guarantee.
7. Verify ambiguous names and execution errors keep the structural cursor unchanged.
   Unrelated events advance it when at least one active configuration exists.
8. Remove a source category and synchronize with both historical `remove_missing`
   values. Neither value permits automatic Magento deletion. `delete_candidates`
   may report mapped missing categories; `deleted` remains zero.
9. Remove a complete remote tree. Preserve local configuration, categories and
   mappings, report the conflict and keep the cursor. Deleted streams are never read.
10. Run concurrent layout save and synchronization. Only one writer may proceed;
    before/after history must cover its actual mutation. Replay and cleanup must
    not overlap that mutation. Verify one history group for a multi-root run.
11. Request a retained historical state with more than 1000 subsequent changes.
    Verify correct parent, order, labels and mapping decorations across replay pages.
12. Pause/resume and fail/retry an Admin run. Pending cursors are committed only
    after all requested stages succeed; completed writes remain. Explicit reset
    clears the cursor first, so an interrupted forced run starts over.
13. Disable data synchronization and verify its cursor stays paused while structure
    can continue. With no active roots neither process reads nor resets its stream.
14. Verify category cron enablement and timezone, both CLI commands and manual
    targeted backfill. A new manual mapping must fetch current configured values
    without rewinding the global data cursor.

Automated tests do not replace a workload profile against production-sized data.
A complete tree, unique stream codes and response payloads still require memory;
these changes do not promise constant memory or eliminate all per-entity Magento SQL.
