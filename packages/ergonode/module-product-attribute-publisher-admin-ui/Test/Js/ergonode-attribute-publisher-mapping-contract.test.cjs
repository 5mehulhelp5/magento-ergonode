'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
let repositoryRoot = moduleRoot;
while (!fs.existsSync(path.join(repositoryRoot, 'dev/tools/ergonode-storybook/module-catalog.js'))) {
    const parent = path.dirname(repositoryRoot);
    assert.notEqual(parent, repositoryRoot, 'repository root must contain the Ergonode module catalog');
    repositoryRoot = parent;
}
const coreAdminUiRoot = path.join(repositoryRoot, 'vendor/ergonode/module-core-admin-ui');
const baseAdminUiRoot = path.join(repositoryRoot, 'vendor/ergonode/module-product-attribute-admin-ui');
const attributePublisherAdminUiRoot = path.join(repositoryRoot, 'vendor/ergonode/module-attribute-publisher-admin-ui');
const publisherAdminUiRoot = path.join(repositoryRoot, 'vendor/ergonode/module-publisher-admin-ui');
const composerJson = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');
const diXml = fs.readFileSync(path.join(moduleRoot, 'etc/di.xml'), 'utf8');
const mappingScript = fs.readFileSync(
    path.join(attributePublisherAdminUiRoot, 'view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js'),
    'utf8'
);
const publisherBlock = fs.readFileSync(
    path.join(attributePublisherAdminUiRoot, 'Block/Adminhtml/AttributePublisherMapping.php'),
    'utf8'
);
const attributeLayout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_attribute_index.xml'),
    'utf8'
);
const optionLayout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_option_index.xml'),
    'utf8'
);
const routesXml = fs.readFileSync(path.join(moduleRoot, 'etc/adminhtml/routes.xml'), 'utf8');
const attributeBatchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Attribute/Batch/Create.php'),
    'utf8'
);
const optionBatchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Option/Batch/Create.php'),
    'utf8'
);
const sharedBatchController = fs.readFileSync(
    path.join(publisherAdminUiRoot, 'Controller/Adminhtml/AbstractBatchCreate.php'),
    'utf8'
);
const sharedProgress = fs.readFileSync(
    path.join(coreAdminUiRoot, 'view/adminhtml/web/js/bulk-publish-progress.js'),
    'utf8'
);
const typePickerCss = fs.readFileSync(
    path.join(attributePublisherAdminUiRoot, 'view/adminhtml/web/css/pending-type-picker.css'),
    'utf8'
);
const attributePlugin = fs.readFileSync(
    path.join(moduleRoot, 'Plugin/AttributeMappingSaverPlugin.php'),
    'utf8'
);
const optionPlugin = fs.readFileSync(
    path.join(moduleRoot, 'Plugin/OptionMappingSaverPlugin.php'),
    'utf8'
);
const attributeCreator = fs.readFileSync(
    path.join(moduleRoot, 'Model/ErgonodeAttributeCreator.php'),
    'utf8'
);
const optionCreator = fs.readFileSync(
    path.join(moduleRoot, 'Model/ErgonodeOptionCreator.php'),
    'utf8'
);

function filesUnder(root) {
    return fs.readdirSync(root, {withFileTypes: true}).flatMap((entry) => {
        const entryPath = path.join(root, entry.name);

        return entry.isDirectory() ? filesUnder(entryPath) : [entryPath];
    });
}

test('publishing behavior is owned only by an optional module depending on the base admin UI', () => {
    assert.match(moduleXml, /<module name="Ergonode_ProductAttributeAdminUi"\/>/);

    filesUnder(baseAdminUiRoot).forEach((file) => {
        assert.doesNotMatch(
            fs.readFileSync(file, 'utf8'),
            /Ergonode[\\_](?:Product)?AttributePublisherAdminUi|ergonode\/module-(?:product-)?attribute-publisher-admin-ui|create-ergonode-/,
            `${path.relative(baseAdminUiRoot, file)} must not depend on the optional publisher module`
        );
    });
});

test('both existing mapping screens receive an isolated publisher initializer', () => {
    assert.match(attributeLayout, /Ergonode_CoreAdminUi::css\/ergonode-actions\.css/);
    assert.match(attributeLayout, /target_selector[^>]*xsi:type="string">#ergonode-attribute-mapping/);
    assert.match(attributeLayout, /name="mode"[^>]*>attribute</);
    assert.match(attributeLayout, /Ergonode_CoreAdminUi::css\/bulk-publish-progress\.css/);
    assert.match(optionLayout, /Ergonode_CoreAdminUi::css\/ergonode-actions\.css/);
    assert.match(optionLayout, /target_selector[^>]*xsi:type="string">#ergonode-option-mapping/);
    assert.match(optionLayout, /name="mode"[^>]*>option</);
    assert.match(optionLayout, /Ergonode_CoreAdminUi::css\/bulk-publish-progress\.css/);
});

test('pending Ergonode writes are processed in retryable frontend batches before local save', () => {
    assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/bulk-publish-progress/);
    assert.match(mappingScript, /function pendingPublishItems\(root\)/);
    assert.match(mappingScript, /Math\.min\(50, Number\(config\.batch_size \|\| 20\)\)/);
    assert.match(mappingScript, /config\.urls \? config\.urls\.batch_create/);
    assert.match(mappingScript, /retry_after_seconds/);
    assert.match(mappingScript, /return processAt\(offset\)/);
    assert.match(mappingScript, /event\.stopImmediatePropagation\(\)/);
    assert.match(mappingScript, /context\.serialize\(\)/);
    assert.match(mappingScript, /payloadWithoutPendingCreates/);
    assert.match(mappingScript, /request\.post\(pageConfig\.urls \? pageConfig\.urls\.save/);
    assert.match(publisherBlock, /'batch_size' => 20/);
    assert.match(publisherBlock, /'batch_create' => \$this->getUrl\(\$this->getBatchRoute\(\)\)/);
    assert.match(routesXml, /frontName="ergonode_attribute_publish"/);
    assert.match(attributeBatchController, /ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_save'/);
    assert.match(optionBatchController, /ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_save'/);
    assert.match(attributeBatchController, /extends AbstractBatchCreate/);
    assert.match(optionBatchController, /extends AbstractBatchCreate/);
    assert.match(sharedBatchController, /retry_after_seconds/);
    assert.match(sharedProgress, /data-role="publish-progress-bar"/);
    assert.match(sharedProgress, /Szacowany czas do końca/);
    assert.match(sharedProgress, /Błędy wymagające uwagi/);
    assert.match(sharedProgress, /data-role="publish-warning-count"/);
    assert.match(sharedProgress, /item\.status === 'existing'/);
});

test('production batch contains only attributes selected in the mapping UI', () => {
    assert.doesNotMatch(publisherBlock, /duplicate_probe/);
    assert.doesNotMatch(mappingScript, /veaAttributePublisherDuplicateProbe|duplicate_probe/);
});

test('progress copy is specific to attributes, category attributes and options', () => {
    assert.match(mappingScript, /Tworzenie atrybutów w Ergonode/);
    assert.match(mappingScript, /Tworzenie atrybutów kategorii w Ergonode/);
    assert.match(mappingScript, /Tworzenie opcji w Ergonode/);
    assert.match(mappingScript, /Postęp tworzenia atrybutów kategorii/);
    assert.match(mappingScript, /Postęp tworzenia opcji/);
});

test('publisher action is offered only for an empty Ergonode slot backed by Magento data', () => {
    const eligibility = mappingScript.slice(
        mappingScript.indexOf('function isEligibleRow'),
        mappingScript.indexOf('function addActions')
    );

    assert.match(mappingScript, /data-side=\\?"ergo\\?"/);
    assert.match(mappingScript, /data-side=\\?"magento\\?"/);
    assert.match(eligibility, /var slots = rowSlots\(row\);/);
    assert.match(eligibility, /var source = mappingElements\.slotPayload\(slots\.magento\);/);
    assert.match(
        eligibility,
        /slots\.ergo && !slots\.ergo\.getAttribute\('data-code'\) && source/
    );
    assert.match(mappingScript, /data-role=\\?"create-ergonode-/);
    assert.match(mappingScript, /data-pending-create', '1'/);
    assert.equal((mappingScript.match(/veui-create-ergonode-icon/g) || []).length, 1);
    assert.doesNotMatch(mappingScript, /class="vea-create-option-icon"/);
    assert.doesNotMatch(typePickerCss, /\.vea-empty-create-ergonode/);
});

test('publisher does not add an action to the empty Ergonode attribute list', () => {
    assert.doesNotMatch(mappingScript, /attribute-empty-actions/);
    assert.doesNotMatch(mappingScript, /create-ergonode-attribute-empty/);
    assert.doesNotMatch(mappingScript, /vea-empty-create-ergonode/);
    assert.doesNotMatch(mappingScript, /emptyAttributeModal/);
    assert.doesNotMatch(mappingScript, /empty-attribute-picker/);
    assert.doesNotMatch(typePickerCss, /vea-empty-attribute-picker/);
});

test('publisher creation requires an active language mapping and reuses the refresh error action', () => {
    assert.match(publisherBlock, /LanguageStoreMappingProviderInterface/);
    assert.match(publisherBlock, /getLanguageCodes\(\)/);
    assert.match(publisherBlock, /catch \(NoActiveLanguageMappingException \$exception\)/);
    assert.match(publisherBlock, /'url' => \$this->getUrl\('ergonode\/language\/index'\)/);
    assert.match(mappingScript, /language_mapping\.active !== false/);
    assert.match(mappingScript, /function showLanguageMappingRequired\(root, config\)/);
    assert.match(mappingScript, /root\.veaContext\.message\.error\([\s\S]*?Nie udało się odświeżyć atrybutów/);
    assert.match(mappingScript, /label: \$t\('Przejdź do mapowania języków'\)/);
    assert.equal((mappingScript.match(/if \(!hasActiveLanguageMapping\(config\)\)/g) || []).length, 1);
});

test('optional module contributes Ergonode creation through the core completion contract', () => {
    assert.match(mappingScript, /data-role=\\?"create-ergonode-/);
    assert.match(mappingScript, /data-completes-missing-side="1"/);
    assert.doesNotMatch(mappingScript, /create-missing-ergonode|Utwórz brakujące w Ergonode/);
    assert.match(
        mappingScript,
        /row = button\.closest\('\[data-role="mapping-row"\]'\);[\s\S]*createMissingForRow\(row, mode, element\.veaAttributeCompatibility\);/
    );
});

test('new mappings stay amber until saved mappings are loaded again', () => {
    const pendingTone = mappingScript.slice(
        mappingScript.indexOf('function markRowPending'),
        mappingScript.indexOf('function selectPendingType')
    );

    assert.match(pendingTone, /classList\.add\('vea-status-tone-warning'\)/);
    assert.match(pendingTone, /setAttribute\('data-status-tone', 'warning'\)/);
    assert.match(pendingTone, /Mapowanie oczekuje na zapis/);
    assert.doesNotMatch(pendingTone, /classList\.add\('vea-status-tone-ok'\)/);
});

test('pending attribute type modal exposes only types accepted by the base mapping contract', () => {
    const allowedTypes = mappingScript.slice(
        mappingScript.indexOf('function allowedErgonodeAttributeTypes'),
        mappingScript.indexOf('function pendingTypePickerHtml')
    );

    assert.doesNotMatch(mappingScript, /ProductAttributeConsumerAdminUi/);
    assert.doesNotMatch(mappingScript, /var compatibleMagentoTypes =/);
    assert.match(mappingScript, /config.attribute_compatibility/);
    assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/text/);
    assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/mapping-elements/);
    assert.doesNotMatch(mappingScript, /function escapeHtml|function typeBadgeHtml|function slotPayload/);
    assert.match(mappingScript, /mappingElements\.pendingBadgeHtml\(\)/);
    assert.match(mappingScript, /Magento_Ui\/js\/modal\/modal/);
    assert.equal(
        composerJson.require['ergonode/module-attribute-publisher-admin-ui'],
        'dev-main@dev'
    );
    assert.match(allowedTypes, /compatibility \|\| \{\}\)\[ergonodeType\]/);
    assert.match(mappingScript, /data-role="pending-ergonode-type-trigger"/);
    assert.match(mappingScript, /aria-haspopup="dialog"/);
    assert.match(mappingScript, /data-role', 'pending-ergonode-type-modal'/);
    assert.match(mappingScript, /type: 'popup'/);
    assert.match(mappingScript, /title: \$t\('Wybierz typ atrybutu Ergonode'\)/);
    assert.match(mappingScript, /state\.widget\.modal\('openModal'\)/);
    assert.match(mappingScript, /data-role="pending-ergonode-type-option"/);
    assert.match(mappingScript, /slot\.getAttribute\('data-pending-create'\) !== '1'/);
    assert.match(mappingScript, /allowedTypes\.length < 2/);
    assert.doesNotMatch(mappingScript, /data-role="pending-ergonode-type-popup"/);
    assert.match(attributeLayout, /pending-type-picker\.css/);
    assert.match(typePickerCss, /\.vea-pending-type-modal-options/);
    assert.doesNotMatch(typePickerCss, /position: absolute/);
    assert.match(attributePlugin, /\$source\['target_type'\] = \$left\['type'\]/);
});

test('publisher translates Magento-only storage types into canonical Ergonode creation types', () => {
    assert.match(mappingScript, /function suggestedErgonodeAttributeType\(type\)/);
    assert.match(mappingScript, /type === 'boolean'[\s\S]*return 'select'/);
    assert.match(mappingScript, /type === 'decimal'[\s\S]*return 'numeric'/);
    assert.match(mappingScript, /supportedErgonodeAttributeTypes = \[[\s\S]*'numeric'/);
    assert.doesNotMatch(
        mappingScript.match(/supportedErgonodeAttributeTypes = \[[\s\S]*?\];/)[0],
        /'boolean'|'decimal'/
    );
});

test('save plugins finish GraphQL synchronization before persisting local mappings', () => {
    const attributeBeforeProceed = new RegExp([
        String.raw`attributeCreator->synchronizeFromMagento\(\$source\);`,
        String.raw`[\s\S]*return \$proceed\(\$mappings, \$visibility\);`
    ].join(''));
    const optionBeforeProceed = new RegExp([
        String.raw`optionCreator->synchronizeFromMagento\(\s*\$attributeCode,\s*\$source\['state'\],\s*\$source\['code'\]\s*\);`,
        String.raw`[\s\S]*return \$proceed\(\$attributeMappingId, \$mappings, \$visibility\);`
    ].join(''));

    assert.match(diXml, /AttributeMappingSaverPlugin/);
    assert.match(diXml, /OptionMappingSaverPlugin/);
    assert.match(attributePlugin, attributeBeforeProceed);
    assert.match(optionPlugin, optionBeforeProceed);
    assert.match(attributePlugin + optionPlugin, /catch \(Throwable \$exception\)/);
    assert.match(attributePlugin + optionPlugin, /throw new LocalizedException/);
});

test('writes delegate to stateless GraphQL publisher contracts', () => {
    assert.equal(composerJson.require['ergonode/module-product-attribute-publisher'], '*');
    assert.match(moduleXml, /<module name="Ergonode_ProductAttributePublisher"\/>/);
    assert.match(attributeCreator, /AttributeDefinitionPublisherInterface/);
    assert.match(optionCreator, /OptionDefinitionPublisherInterface/);
    assert.doesNotMatch(attributeCreator + optionCreator, /RestClient|ErgonodeAttributeGateway/);
});
