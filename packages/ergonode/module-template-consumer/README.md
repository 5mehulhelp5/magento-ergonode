# Ergonode_TemplateConsumer

This module owns Ergonode template import and synchronization with Magento
product attribute sets. Snapshot refresh updates only the local Ergonode
template cache and optional snapshot contributors. Synchronization additionally
auto-matches mappings and updates Magento attribute sets.

`TemplateSynchronizerInterface` owns both complete synchronization and a
lease-protected cursor-only reset so CLI, cron and Admin UI use the same process
boundary.

The module contributes these lock-aware synchronization and reset-only cursor
operations to the shared executable-operation registry in `Ergonode_Core`.

It registers `template_stream` in the shared `Ergonode_Core`
synchronization-status provider. Because the registration is packaged with this
module, disabling the module also removes its Admin status row.

The module imports snapshots and synchronizes assignments. Neutral Admin mapping persistence and snapshot reads belong to Ergonode_Template.
Execution results are returned to callers; no generic operation history is recorded.

## Snapshot and synchronization boundaries

ImportedTemplateProcessor and TemplateImportContributorInterface write source snapshots only.
TemplateListImporter validates and reads the whole list and all contributed structures before
invoking TemplateSynchronizationProcessor. Its optional TemplateSynchronizationContributorInterface
implementations may then change Magento. Refresh never invokes this phase. The core module has
no dependency on a structure or Publisher implementation.

A completed list marks missing templates as deleted. Explicit synchronization supplies all deleted
codes, including earlier deletions, to contributors so a prior refresh or interrupted cleanup does
not lose work. The Magento set and template mapping remain. An incomplete list/detail/structure
response stops the process before deletion reconciliation or Magento synchronization.

Schema verified against the configured Ergonode instance on 2026-09-11: templateList, template,
sectionList and section are exposed; no template or section streams are exposed. The existing
process identifier template_stream remains the shared operation identity; execution performs a
complete list scan every time. Resetting its saved cursor does not change the scan range.

## Environment-specific automation settings

Global `etc/di.xml` registers the following owned settings as `environment`
with `Magento_Config` through `Magento\Config\Model\Config\TypePool`:

- `ergonode_templates/cron/status`
- `ergonode_templates/cron/cron_expr`

Configuration export treats these switches and schedules as installation-specific.
Their defaults and runtime interpretation are unchanged; they are not sensitive.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.

## Unavailable connection in scheduled work

Automatic synchronization and recovery use Core's fresh connection probe before
starting domain work. Missing or invalid configuration and unsuccessful probes
skip the run without changing cursors, enqueueing work or logging connection
errors. A rejected connection discovered during execution is also skipped;
unexpected failures remain visible. Existing work is retained for the next run.
The local media scan and history retention remain independent of this policy.
Unit tests cover repeated rejection and recovery; the project integration cron
contract exercises Magento scheduling with existing work and stored checkpoints.
