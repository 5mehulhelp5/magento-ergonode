# Ergonode_ProductMedia

Owns direction-neutral product gallery layout and native persistence, image roles,
global Gallery selection and Image insertion rules. It depends on ProductAttribute
through its mapping policy and value-adapter registration points. It does not own
remote transfers, hashing, the local index, background work, cursors or federation.
Those remain in Media. There are no new tables and no existing-data migration.

## Rules and configuration

`ergonode_products/media/gallery_attribute` and `synchronization_enabled` retain
their existing paths. New settings are `additional_images` (JSON rules),
`additional_role`, `role_position` (default 2) and `unmanaged_images` (default `keep`). All settings are global.
MediaAdminUi owns their form and remote-source validation. Admin saving and runtime
configuration both use ImageRulesNormalizer for additional-image rows and image
positions. Duplicate attributes/positions, fractions, invalid shapes and positions
outside 2..65535 are rejected instead of silently cast or overwritten. Whitespace
in attribute codes and integer digit strings are normalized identically. Malformed
stored JSON reports the setting path and cause. The bridge classifies these media
configuration errors as terminal in the product import queue, logs them and continues
with other products; correction and a new import are required. Disabled gallery
synchronization does not validate unused image rules in the product import hash. The selected Gallery
is the base order. Additional Image paths are inserted at unique positions >= 2;
an already present source is moved, not copied. The first Gallery source remains
first. A position beyond the current size appends the image. Other sources retain
their relative order. Localized Image values are included in source order at
successive positions; overlapping positions are rejected rather than dropping data.
An empty Image value removes that contribution. Missing metadata or a changed
source type interrupts preparation instead of being treated as an intentional clear.

After binary materialization, identical local paths are deduplicated again. The
first synchronized gallery image receives image, small_image and thumbnail.
One additional existing Magento media_image attribute can follow a configured
position. Native primary roles and individually mapped attributes are excluded
from that selector. Mapped Image attributes must be included in the gallery,
using the selected Gallery or the additional-image rules. Their roles refer to
the same materialized file, including the selected SEO strategy.

All desired binaries must be prepared before native gallery writes. Roles are
written after the gallery succeeds. Removing the first source assigns the next
image to the primary roles; an empty completed gallery writes no_selection.
Role writes compare effective stored values and skip unchanged attributes.
Manual overrides of configured roles are replaced. After the gallery and configured
roles are written, other native image roles are checked against the visible native
gallery in each store. A role pointing outside that gallery is cleared. This also
handles a previous partial state on the next explicit pass covering that product;
there is no separate repair job or automatic resubmission.

The global `unmanaged_images` setting controls images added outside the integration:
- `keep` (default): preserve their gallery associations and valid roles;
- `hide`: mark absent images disabled for this product in all existing store overrides;
- `remove`: unlink absent images from this product for one-to-one synchronization.

Previously managed images absent from the imported gallery continue to be unlinked
in every mode. Videos are unaffected. Returning source images are made visible
again. Configuration changes take effect when that product's gallery is synchronized.
Shared images keep other products' associations, metadata and roles.

On the last association, the unused native gallery record is removed. Media checks
remaining native gallery links, image attributes and desired integration usages
before deleting the physical product image after the product transaction commits.
Rollback preserves the file. Hidden images remain linked and retain their files.
Physical cleanup failures are logged with product, path, stage and exception;
this change adds no automatic retry. The source cache is preserved.

## Contracts and lifecycle

GalleryConfigurationInterface supplies the gallery code and synchronization flag.
GalleryRulesInterface and GalleryLayoutInterface supply insertion/position rules.
GallerySynchronizerInterface accepts prepared local paths and optional mapped
roles; Media calls it after download preparation. ImageRolesInterface and
AdditionalRoleOptionsInterface provide native attributes to runtime and Admin.
ImageRulesNormalizerInterface validates the saved table shape and uniqueness.

ProductMedia registers Image mapping capability with ProductAttribute. Gallery
is never an ordinary mapping type, regardless of code. Without ProductMedia,
Image compatibility is absent in UI, save validation and executable mappings.
The product_image value adapter permits import only when the ProductMediaConsumer
bridge is enabled and gallery synchronization is enabled. Binary publication is
not implemented, so the adapter does not advertise publication support. Removing
a capability never converts an excluded mapping into a clear operation.

Uninstall preserves configuration, native product data, media files and
ProductAttribute mapping markers. This module owns no tables; Media removes only
its own transfer tables.
The module must be enabled together with its dependent Media modules. This change
does not enable modules or execute configuration/data migration automatically.
