'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {resolveModuleRoot} = require('./module-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const systemXml = fs.readFileSync(
    path.join(moduleRoot, 'etc/adminhtml/system.xml'),
    'utf8'
);
const consumerSystemXml = fs.readFileSync(path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'etc/adminhtml/system.xml'), 'utf8');
const defaultConfigXml = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumer'), 'etc/config.xml'),
    'utf8'
);
const crontabXml = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumer'), 'etc/crontab.xml'),
    'utf8'
);
const policy = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/Config/ProductAttributePolicy.php'),
    'utf8'
);
const placementPolicy = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/ProductAttributePlacementPolicy.php'),
    'utf8'
);
const attributeMappingProvider = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/Mapping/MappingStateBuilder.php'),
    'utf8'
);
const attributeMappingSaver = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/Mapping/AttributeMappingSaver.php'),
    'utf8'
);
const attributeMappingNormalizer = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/Mapping/AttributeMappingNormalizer.php'),
    'utf8'
);
const productAttributeMode = fs.readFileSync(
    path.join(moduleRoot, 'Model/Config/Source/ProductAttributeMode.php'),
    'utf8'
);
const productUrlKeyMode = fs.readFileSync(
    path.join(moduleRoot, 'Model/Config/Source/ProductUrlKeyMode.php'),
    'utf8'
);
const polishTranslations = fs.readFileSync(
    path.join(moduleRoot, 'i18n/pl_PL.csv'),
    'utf8'
);
const defaultValueTooltip = 'The default Magento value is assigned only when synchronization creates a product. It is never used to update an existing product.';
const managedPriceComment = 'Map Price from Ergonode or keep it managed by Magento. Magento-managed Price is not mapped or published to Ergonode; the default is used only when synchronization creates a Magento product.';

function getFieldXml(fieldId) {
    const match = (systemXml + consumerSystemXml).match(new RegExp(`<field id="${fieldId}"[^>]*>[\\s\\S]*?</field>`));

    assert.ok(match, `Expected system configuration field "${fieldId}"`);

    return match[0];
}

test('product attributes configuration is grouped under the Ergonode Products section', () => {
    assert.match(
        systemXml,
        /<section id="ergonode_products"[\s\S]*?<group id="attributes"[\s\S]*?<label>Attributes<\/label>/
    );
    assert.doesNotMatch(systemXml, /<group id="creation"/);
    assert.doesNotMatch(systemXml, /<group id="product_mapping"/);
    assert.doesNotMatch(systemXml, /<field id="sku_mode"/);
    assert.match(systemXml, /<field id="url_key"/);
    assert.match(getFieldXml('url_key'), /<label>URL Key<\/label>/);
    assert.match(getFieldXml('url_key'), /ProductUrlKeyMode/);
    assert.match(systemXml, /<field id="price_mode"/);
    assert.match(getFieldXml('price_mode'), new RegExp(`<comment\\s*>${managedPriceComment}</comment>`));
    assert.match(systemXml, /<field id="price_default"[\s\S]*?<validate>required-entry validate-number validate-zero-or-greater<\/validate>[\s\S]*?<field id="price_mode">manual<\/field>/);
    assert.match(systemXml, /<field id="status"/);
    assert.match(getFieldXml('status'), /<label>Status<\/label>/);
    assert.match(systemXml, /<field id="status_default"[\s\S]*?<field id="status">manual<\/field>/);
    assert.match(systemXml, /<field id="visibility"/);
    assert.match(getFieldXml('visibility'), /<label>Visibility<\/label>/);
    assert.match(systemXml, /<field id="visibility_default"[\s\S]*?<field id="visibility">manual<\/field>/);
    assert.doesNotMatch(systemXml, /url_key_mapping|status_mode|visibility_mode/);
    for (const fieldId of ['price_default', 'status_default', 'visibility_default']) {
        assert.match(getFieldXml(fieldId), new RegExp(`<tooltip\\s*>${defaultValueTooltip}</tooltip>`));
    }
    for (const fieldId of ['price_mode', 'status', 'visibility']) {
        assert.doesNotMatch(getFieldXml(fieldId), /<tooltip>/);
    }
    assert.match(productAttributeMode, /Map from an Ergonode attribute/);
    assert.match(productAttributeMode, /Managed by Magento — use a default when creating/);
    assert.match(productUrlKeyMode, /Map from an Ergonode attribute/);
    assert.match(productUrlKeyMode, /Let Magento handle it/);
    assert.match(polishTranslations, /Mapuj z atrybutu Ergonode/);
    assert.match(polishTranslations, /Pozwól Magento obsłużyć to automatycznie/);
    assert.match(polishTranslations, /Zarządzane przez Magento — użyj wartości domyślnej podczas tworzenia/);
    assert.match(polishTranslations, /Cena zarządzana przez Magento nie jest mapowana ani publikowana do Ergonode/);
    assert.match(polishTranslations, /Status zarządzany przez Magento nie jest mapowany ani publikowany do Ergonode/);
    assert.match(polishTranslations, /Widoczność zarządzana przez Magento nie jest mapowana ani publikowana do Ergonode/);
    assert.doesNotMatch(systemXml, /<config_path>/);
    assert.doesNotMatch(defaultConfigXml, /<product_mapping>/);
    assert.match(defaultConfigXml, /<url_key>magento<\/url_key>/);
    assert.match(defaultConfigXml, /<price_mode>mapping<\/price_mode>/);
    assert.match(defaultConfigXml, /<price_default>0<\/price_default>/);
    assert.match(defaultConfigXml, /<status>mapping<\/status>/);
    assert.match(defaultConfigXml, /<status_default>2<\/status_default>/);
    assert.doesNotMatch(defaultConfigXml, /<creation>/);
    assert.match(defaultConfigXml, /<visibility>mapping<\/visibility>/);
    assert.match(defaultConfigXml, /<visibility_default>4<\/visibility_default>/);
    assert.doesNotMatch(defaultConfigXml, /url_key_mapping|status_mode|visibility_mode/);
});

test('product attribute policy and mapping normalization enforce the same ownership boundary', () => {
    for (const code of [
        'gallery',
        'category_ids',
        'quantity_and_stock_status',
        'msrp_display_actual_price_type',
        'image',
        'small_image',
        'thumbnail',
        'swatch_image',
        'tax_class_id',
        'cost'
    ]) {
        assert.match(placementPolicy, new RegExp(`['"]${code}['"]`));
    }
    assert.match(placementPolicy, /['"]media_gallery['"]/);
    assert.match(policy, /isErgonodeMappable/);
    assert.match(policy, /if \(\$attributeCode === 'sku'\)/);
    assert.match(policy, /if \(\$attributeCode === 'url_key'\)/);
    assert.match(attributeMappingProvider, /attributePolicy->isErgonodeMappable\(\$leftCode\)/);
    assert.match(attributeMappingProvider, /attributePolicy->isMappable\(\$rightCode\)/);
    assert.match(attributeMappingNormalizer, /attributePolicy->isMappable\(\$rightCode\)/);
    assert.match(attributeMappingSaver, /normalizer->normalize\(\$mappings, \$existing\)/);
});

test('attribute cron is configurable and disabled by default', () => {
    const attributesSection = (systemXml + consumerSystemXml).match(
        /<section id="ergonode_attributes"[\s\S]*?<\/section>/
    )?.[0] || '';

    assert.doesNotMatch(attributesSection, /<group id="synchronization"/);
    assert.doesNotMatch(attributesSection, /SynchronizationButton|<button_url>/);
    assert.match(attributesSection, /<group id="cron"/);
    assert.match(attributesSection, /<field id="status"/);
    assert.match(attributesSection, /<field id="schedule"/);
    assert.match(attributesSection, /Ergonode\\CoreAdminUi\\Model\\Config\\Backend\\CronSchedule/);
    assert.match(defaultConfigXml, /<cron>[\s\S]*?<status>0<\/status>[\s\S]*?<schedule>\*\/15 \* \* \* \*<\/schedule>/);
    assert.match(crontabXml, /<config_path>ergonode_attributes\/cron\/schedule<\/config_path>/);
});

test('required product attribute fields use the shared required marker renderer', () => {
    const requiredRenderer = 'Ergonode\\CoreAdminUi\\Block\\Adminhtml\\System\\Config\\RequiredField';
    const fields = (systemXml + consumerSystemXml).match(
        /^ {16}<field\b[\s\S]*?^ {16}<\/field>/gm
    ) || [];
    const requiredFields = fields.filter((field) => /<validate>[^<]*\brequired-entry\b/.test(field));

    assert.notEqual(requiredFields.length, 0);
    requiredFields.forEach((field) => {
        assert.match(
            field,
            new RegExp(`<frontend_model>${requiredRenderer.replace(/\\/g, '\\\\')}<\\/frontend_model>`)
        );
    });
});

test('option synchronization behavior is explicit and safe by default', () => {
    const attributesSection = (systemXml + consumerSystemXml).match(
        /<section id="ergonode_attributes"[\s\S]*?<\/section>/
    )?.[0] || '';

    assert.match(attributesSection, /<group id="options"/);
    assert.match(getFieldXml('synchronize_sort_order'), /<label>Synchronize Sort Order<\/label>/);
    assert.match(getFieldXml('delete_missing_magento_options'), /<label>Delete Missing Options<\/label>/);
    assert.match(getFieldXml('delete_missing_magento_options'), /Product values using deleted options can become empty/);
    assert.match(defaultConfigXml, /<options>[\s\S]*?<synchronize_sort_order>1<\/synchronize_sort_order>/);
    assert.match(defaultConfigXml, /<delete_missing_magento_options>0<\/delete_missing_magento_options>/);
});
