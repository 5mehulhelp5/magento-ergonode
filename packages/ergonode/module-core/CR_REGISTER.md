# Ergonode Integration Code Review Register

## Findings

### ERG-CR-001 - Resolved - Empty category import can remove all cached and mapped categories
- Severity: P1
- Rules: MAG-OBS-001, MAG-DB-001
- Evidence:
  - `app/code/Ergonode/CategoryConsumer/Model/Import/CategoryImportProcess.php:116`
  - `app/code/Ergonode/CategoryConsumer/Model/Import/CategoryCacheWriter.php:107`
- Resolution: Empty completed imports skip snapshot removal. Mapping is stored
  separately and automatic Magento deletion no longer exists. Destructive
  cleanup is a profile-scoped CLI command with dry-run default, explicit
  `--execute`, candidate listing and root protection.

### ERG-CR-002 - Resolved - Category sync bypasses Magento category save/index/cache handling
- Severity: P2
- Rules: MAG-DB-001, MAG-SOLID-005
- Evidence:
  - `app/code/Ergonode/CategoryConsumer/Model/Sync/CategorySynchronizer.php:142`
  - `app/code/Ergonode/CategoryConsumer/Model/Sync/CategoryPositionUpdater.php:16`
  - `app/code/Ergonode/CategoryConsumer/Model/Sync/CategoryAttributeWriter.php:72`
- Resolution: Synchronization invalidates affected category cache identities,
  writes localized attributes through the tested category attribute writer and
  uses Magento category APIs for creation, movement and explicit deletion.

### ERG-CR-003 - Resolved - Production DoD is not proven for critical Ergonode sync paths
- Severity: P2
- Rules: MAG-MOD-004
- Evidence:
  - `make -f .agents/backend/Makefile changed-modules-check`
  - `app/code/Ergonode/TemplateConsumer/Test/Unit/Model/Sync/AttributeSetResourceTest.php`
- Resolution: Category profiles, root validation, pagination retry, separated
  snapshot/mapping storage, migration, synchronization, cron isolation, CLI
  selection, cleanup safety, Admin controller ACL and JavaScript profile
  switching now have focused unit/integration/static tests.

## Sessions

### 2026-07-12 - Production readiness review
- Scope: `Ergonode_Core`, `Ergonode_AttributeConsumer`, `Ergonode_ProductAttributeConsumerAdminUi`, `Ergonode_CategoryConsumer`, `Ergonode_CategoryConsumerAdminUi`, `Ergonode_TemplateConsumer`, `Ergonode_TemplateAttributeConsumerAdminUi`.
- Commands:
  - `make -f .agents/backend/Makefile agent-start modules=Ergonode_Core,Ergonode_AttributeConsumer,Ergonode_ProductAttributeConsumerAdminUi,Ergonode_CategoryConsumer,Ergonode_CategoryConsumerAdminUi,Ergonode_TemplateConsumer,Ergonode_TemplateAttributeConsumerAdminUi`
  - `make -f .agents/backend/Makefile module-composer-check`
  - `make -f .agents/backend/Makefile changed-modules-check`
  - `ddev exec bin/magento setup:db:status`
- Result: Not production-ready. Static/module gates pass for the Ergonode modules, but category deletion safety, direct category persistence, and missing tests remain open production risks. `setup:db:status` is also not clean because of an unrelated `ergonode_blog_post_publication_schedule` schema diff.
