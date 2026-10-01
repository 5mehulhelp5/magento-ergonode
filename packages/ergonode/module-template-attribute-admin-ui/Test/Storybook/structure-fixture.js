import ergonodeIcon from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import magentoIcon from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
const fixture = {
    icons: {ergonode: ergonodeIcon, magento: magentoIcon},
    template: 'Obuwie · shoes', attributeSet: 'Obuwie',
    sections: [
        {section_code: 'general', label: 'Informacje podstawowe'},
        {section_code: 'details', label: 'Szczegóły produktu'},
        {section_code: 'media', label: 'Zdjęcia i multimedia'}
    ],
    sourceAttributes: [
        {section_code: 'general', attribute_code: 'product_name', frontend_label: 'Nazwa produktu'},
        {section_code: 'general', attribute_code: 'product_sku', frontend_label: 'SKU'},
        {section_code: 'general', attribute_code: 'description', frontend_label: 'Opis'},
        {section_code: 'details', attribute_code: 'colour', frontend_label: 'Kolor'},
        {section_code: 'details', attribute_code: 'size', frontend_label: 'Rozmiar'},
        {section_code: 'details', attribute_code: 'material', frontend_label: 'Materiał'},
        {section_code: 'media', attribute_code: 'image', frontend_label: 'Zdjęcie główne'}
    ],
    groups: [{attribute_group_id: 1, attribute_group_name: 'General'},
        {attribute_group_id: 2, attribute_group_name: 'Ergonode - Szczegóły produktu', managed_by_ergonode: true, ergonode_section_codes: ['details']},
        {attribute_group_id: 3, attribute_group_name: 'Gallery'},
        {attribute_group_id: 4, attribute_group_name: 'Advanced Pricing'},
        {attribute_group_id: 5, attribute_group_name: 'Advanced Inventory'}],
    attributes: [
        {attribute_group_id: 1, attribute_code: 'name', frontend_label: 'Nazwa produktu', frontend_input: 'text', is_required: 1},
        {attribute_group_id: 1, attribute_code: 'sku', frontend_label: 'SKU', frontend_input: 'text', is_required: 1},
        {attribute_group_id: 1, attribute_code: 'description', frontend_label: 'Opis', frontend_input: 'textarea'},
        {attribute_group_id: 4, attribute_code: 'price', frontend_label: 'Cena', frontend_input: 'price', is_required: 1},
        {attribute_group_id: 2, attribute_code: 'color', frontend_label: 'Kolor', frontend_input: 'select'},
        {attribute_group_id: 2, attribute_code: 'size', frontend_label: 'Rozmiar', frontend_input: 'select'},
        {attribute_group_id: 3, attribute_code: 'image', frontend_label: 'Zdjęcie główne', frontend_input: 'media_image'},
        {attribute_group_id: 5, attribute_code: 'quantity_and_stock_status', frontend_label: 'Stan magazynowy', frontend_input: 'stock'},
        {attribute_group_id: 1, attribute_code: 'legacy_material', frontend_label: 'Materiał archiwalny', frontend_input: 'select', manual_placement: true, missing_from_template: true},
        {attribute_group_id: null, attribute_code: 'material', frontend_label: 'Materiał', frontend_input: 'select'},
        {attribute_group_id: null, attribute_code: 'manufacturer', frontend_label: 'Producent', frontend_input: 'select'},
        {attribute_group_id: null, attribute_code: 'country_of_manufacture', frontend_label: 'Kraj produkcji', frontend_input: 'select'},
        {attribute_group_id: null, attribute_code: 'weight', frontend_label: 'Waga', frontend_input: 'text'}
    ]
};
fixture.attributes = fixture.attributes.map((attribute, index) => ({
    ...attribute,
    attribute_id: index + 1,
    ergonode_attribute_codes: ({name: ['product_name'], sku: ['product_sku'], description: ['description'],
        price: ['base_price'], color: ['colour'], size: ['size'], legacy_material: ['fabric']})[attribute.attribute_code] || [],
    placement_protected: ['name', 'sku', 'description', 'price'].includes(attribute.attribute_code)
}));
export default fixture;
