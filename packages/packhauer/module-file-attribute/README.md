# PackHauer File Attribute

`PackHauer_FileAttribute` owns the reusable Magento product-attribute input type
`file`. The EAV value is a path relative to Magento's media directory and is
stored in a varchar backend. The backend accepts an existing relative path or
one file-uploader value and promotes new uploads from temporary to permanent
media storage. Permanent files use the source-independent layout
`catalog/product/files/<attribute_code>/<dispersion>/<filename>`; the two-level
dispersion is preserved from Magento's uploader.

`PackHauer_FileAttributeAdminUi` adds the attribute type, product-form uploader,
and protected Admin upload endpoint. Uploads are limited to one non-empty file,
25 MB, and the document/archive extensions declared by
`FileUploadPolicyInterface`. Executable and active web formats are not allowed.

Integrations can obtain the same attribute-owned directory through
`FileStorageInterface`; the module does not depend on a particular PIM. Existing
safe media-relative paths remain valid. The module deliberately does not delete
replaced files automatically because paths can be shared across products or
store views.

Standard Magento uninstall removes every catalog product attribute owned by
this input type, including legacy attributes that still reference the former
`Vendivo` backend class. Database foreign-key cascades remove their varchar
values, option records, assignments, labels, catalog metadata, and the
backend-model FQCN stored in `eav_attribute`. The uninstall also removes current
and legacy ACL assignments, temporary uploads, and each removed attribute's
permanent media directory. A permanent file referenced by a remaining varchar
attribute is preserved.
