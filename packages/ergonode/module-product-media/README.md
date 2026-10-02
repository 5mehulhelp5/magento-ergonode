# Ergonode_ProductMedia

Owns direction-neutral product gallery layout and native persistence, image roles,
global Gallery selection and Image insertion rules. It depends on ProductAttribute
through its mapping policy and value-adapter registration points. It does not own
remote transfers, hashing, the local index, background work, cursors or federation.
Those remain in Media. There are no new tables and no existing-data migration.

## Rules and configuration

`ergonode_products/media/gallery_attribute` and `synchronization_enabled` retain
their existing paths. New settings are `additional_images` (JSON rules),
`additional_role` and `role_position` (default 2). All settings are global.
MediaAdminUi owns their form and remote-source validation. The selected Gallery
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
Manual overrides of configured roles are replaced; other roles are left alone.
Existing unmanaged gallery associations are preserved by the native writer.

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

Uninstall removes only this module's configuration; it preserves native product
data and ProductAttribute mapping markers. Media owns transfer data cleanup.
The module must be enabled together with its dependent Media modules. This change
does not enable modules or execute configuration/data migration automatically.
