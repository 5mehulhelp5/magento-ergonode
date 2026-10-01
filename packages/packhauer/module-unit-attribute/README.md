# PackHauer Unit Attribute

`PackHauer_UnitAttribute` owns the reusable Magento product-attribute input type
`unit`. Its product value is stored as a decimal and contains no presentation
suffix. The unit definition is stored in the attribute's `additional_data`
JSON under the reserved `vendivo_unit` key:

```json
{
  "vendivo_unit": {
    "name": "CENTIMETER",
    "symbol": "cm"
  }
}
```

The `name` is a canonical integration identifier and the `symbol` is display
metadata. `PackHauer_UnitAttributeAdminUi` adds the attribute type and definition
fields to Magento Admin and displays the symbol next to the product value.

Consumers use `UnitAttributeMetadataInterface`; they must not parse or replace
`additional_data` directly. Writes preserve Magento Swatches data and unknown
top-level keys.

Ergonode imports and publishes the same contract, but neither module depends on
Ergonode. For example, a Magento `height` attribute can use decimal value
`12.5`, canonical unit name `CENTIMETER`, and symbol `cm`.

Standard Magento uninstall removes every catalog product attribute owned by
this input type, including legacy attributes that still reference the former
`Vendivo` backend class. Database foreign-key cascades remove their decimal
values, option records, assignments, labels, catalog metadata (including
`vendivo_unit`), and the backend-model FQCN stored in `eav_attribute`.
