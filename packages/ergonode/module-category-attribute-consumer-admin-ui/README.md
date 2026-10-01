# Ergonode_CategoryAttributeConsumerAdminUi

## Responsibility boundary

Optional inbound adapter for the neutral CategoryAttributeAdminUi editor. Owns imported
source metadata, download and snapshot removal endpoints, inbound configuration and
category-form refresh. It contributes the editor to CategoryConsumerAdminUi navigation.
The base editor owns display, manual mapping, compatibility, save endpoints and options.

SourceMetadata implements the editor source port through CategoryAttributeConsumer API.
MappingSynchronizationPlugin wraps the editor save context through the consumer's
MappingSynchronizationInterface: prepare and save under the existing category lock,
then backfill values from local snapshots. OptionCreationPlugin resolves pending Magento
options inside that context. MappingCapabilitiesPlugin supplies inbound endpoint URLs.

Dependencies: CategoryAttributeAdminUi (extension host), CategoryAttributeConsumer
(inbound operations), CategoryConsumerAdminUi (configuration/navigation host).
No tables, remote publication or independent scheduler are owned here. Removing this
adapter leaves neutral mapping available, including the independent publisher adapter.
Existing configuration paths and endpoint/ACL identifiers are preserved; no migration.

## Validation

Cover source adaptation, save order/lock/backfill and failure propagation, source refresh,
category form and shared configuration. The mapping editor's stories and rendering tests
are owned by CategoryAttributeAdminUi; category-form stories remain here.


## Neutralne kontrakty kategorii

Współdzielony kontekst katalogu, zapis layoutu, odświeżanie źródła i blokada operacji
należą teraz do Category. Odwołania do przeniesionych klas używają aktualnych kontraktów
Category; odpowiedzialność importu i historii tego modułu pozostaje bez zmian.

## Refresh and unsaved edits

The refresh component checks Magento form fields through uiRegistry and their
hasChanged contract before sending the request and again before reloading.
Unsaved changes require saving or discarding first. A response for a different
category context does not reload or replace the current message. Errors preserve
local edits. The category form stories and JS regression tests run the production AMD.
