# Testing Ergonode_CategoryConsumerAdminUi

Run:

```bash
make -f .agents/backend/Makefile module-check module=Ergonode_CategoryConsumerAdminUi
/path/to/node --test app/code/Ergonode/CategoryConsumerAdminUi/Test/Js/*.test.cjs
ddev exec npm run storybook:ergonode:build
```

Admin smoke checks:

1. the Categories configuration has no manual Synchronization section or
   action;
2. `Nowe mapowanie` opens the configuration dialog in the mapping workspace;
3. saving a duplicate root or a non-root category is rejected;
4. configuration URLs use `category_tree/*` and `category_tree_id`;
5. mapping URLs use `category_tree_mapping/*` and `category_tree_id`;
6. the mapping screen cannot expose another Category Tree snapshot or Magento
   root;
7. branches can be collapsed and expanded independently in both trees, while
   search reveals matching paths;
8. configuration mutations are POST-backed and protected by dedicated Category Tree ACL
   resources;
9. validation errors return to the mapping workspace and unexpected failures
    show a generic admin message.
10. Auto-map sends draft mappings and visibility, uses the last local source
    snapshot without an Ergonode request, and replaces both models only on success.
    A visible loader remains outside the closed menu; the workspace is inert
    until success or failure, and duplicate requests are ignored. Apply and refresh
    still fetch the complete remote tree. Empty and inactive snapshots are covered
    by unit tests. Auto-map never loads `category-auto-matcher`.
11. 429 shows its retry time; authorization/transport failures preserve the
    rendered data, dirty state and enabled button.
12. Storybook covers default, busy, mapped plus unmatched, ambiguous conflict,
    429 and transport-error states using production AMD and CSS.
13. Category configuration exposes independent Attributes and Cron
    switches; the cron schedule is required only when cron
    is enabled and rejects malformed or out-of-range expressions.
14. Cron is disabled by default. Disabling it leaves Category Tree actions and
    CLI functional, while disabling Category Attributes pauses only
    `category_stream`.
15. The Category Tree configuration dialog persists `Active` and
    `Remove Missing Categories` independently. With removal disabled, synchronization still applies
    create/mapping/move/order operations and deletes nothing. Existing names
    change only through an enabled, complete Name attribute mapping.
16. Resetting the visible structural cursor requires its dedicated ACL, resets
    only `category_tree_stream` and is rejected while another category
    synchronization holds the shared lock.
17. Saving a new category mapping fetches mapped attributes directly without
    changing `category_stream`; saving attribute or option mappings completes
    backfill under the same shared lock.


## Progress, pause and transport recovery

- Run `Test/Js/category-sync-progress.test.cjs` for real transport polling,
  late responses, explicit resume, pause acknowledgement, counts and timeout policy.
- Run the CategorySyncProgress Storybook states and PauseAndResume interaction
  with both mouse and keyboard; run the complete Storybook contract/a11y check.
- In Magento, start a tree sync and observe a named stage and count. Pause while
  it is applying categories. Wait for the confirmed paused state before resuming.
  Completed writes remain visible and already completed stages are not reset.
- After completion, repeat reconciliation with identical input: zero moves.
  Test IDs whose numeric order differs from sibling order and positions with gaps.
- A transport timeout must trigger status recovery, never another execute POST.
  Lost status/control storage must not display success or advance a cursor.
- With base name mode Keep, data synchronization must issue no Ergonode queries;
  an attribute consumer still receives its data when installed.

Synchronization preflight: with required creation mappings missing, check combined
and tree Sync / Sync (force) tooltips by mouse and keyboard. Direct POST must report
the same reason without Ergonode requests or cursor reset. Data-only actions and
snapshot refresh remain available. Storybook Downloading covers real page/node
counters; InvalidConfiguration and Conflicts cover actionable terminal feedback.
