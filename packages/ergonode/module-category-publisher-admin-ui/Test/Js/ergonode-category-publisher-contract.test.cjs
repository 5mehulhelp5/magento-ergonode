'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

function findProjectRoot(start) {
    let current = start;

    while (current !== path.dirname(current)) {
        if (
            fs.existsSync(path.join(current, 'composer.json'))
            && fs.existsSync(path.join(current, 'vendor/ergonode/module-core-admin-ui'))
        ) {
            return current;
        }
        current = path.dirname(current);
    }

    throw new Error(`Cannot resolve project root from ${start}`);
}

const moduleRoot = path.resolve(__dirname, '../..');
const projectRoot = findProjectRoot(moduleRoot);
const composer = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');
const diXml = fs.readFileSync(path.join(moduleRoot, 'etc/di.xml'), 'utf8');
const script = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/js/ergonode-category-publisher-mapping.js'), 'utf8');
const publisherStory = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/CreateErgonodeTreeButton.stories.js'),
    'utf8'
);
const categoryCodeGenerator = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/category-code-generator.js'),
    'utf8'
);
const jsonPost = fs.readFileSync(path.join(moduleRoot, '../module-publisher-admin-ui/view/adminhtml/web/js/json-post.js'), 'utf8');
const manualAuth = fs.readFileSync(path.join(moduleRoot, '../module-publisher-admin-ui/view/adminhtml/web/js/manual-auth.js'), 'utf8');
const progress = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/js/category-publish-progress.js'), 'utf8');
const sharedProgress = fs.readFileSync(
    path.join(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/bulk-publish-progress.js'),
    'utf8'
);
const plugin = fs.readFileSync(path.join(moduleRoot, 'Plugin/CategoryLayoutSaverPlugin.php'), 'utf8');
const client = fs.readFileSync(path.join(moduleRoot, 'Model/ManualRest/Client.php'), 'utf8');
const manualActions = fs.readFileSync(path.join(moduleRoot, 'Block/Adminhtml/ManualActions.php'), 'utf8');
const batchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Category/Batch/Create.php'),
    'utf8'
);
const sharedBatchController = fs.readFileSync(
    path.join(projectRoot, 'vendor/ergonode/module-publisher-admin-ui/Controller/Adminhtml/AbstractBatchCreate.php'),
    'utf8'
);
const styles = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/css/ergonode-category-publisher.css'), 'utf8');
const pendingIcon = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/images/pending-category-info.svg'), 'utf8');
const createCategoryIcon = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/images/create-ergonode-category.svg'),
    'utf8'
);

test('manual tree writer remains an admin UI adapter', () => {
    assert.equal(composer.name, 'ergonode/module-category-publisher-admin-ui');
    assert.equal(composer.require['ergonode/module-category-publisher'], 'dev-main@dev');
    assert.equal(composer.require['ergonode/module-category-tree-publisher'], undefined);
    assert.match(plugin, /CategoryTreePublisher/);
});

test('module owns its optional boundaries and shared publisher dependency', () => {
    assert.equal(composer.require['ergonode/module-publisher-admin-ui'], 'dev-main@dev');
    assert.match(moduleXml, /<module name="Ergonode_PublisherAdminUi"\/>/);

    Object.keys(composer.require).forEach((dependency) => {
        assert.doesNotMatch(dependency, /category-attribute/);
    });
    assert.doesNotMatch(moduleXml, /Ergonode_CategoryAttribute/);

    [
        'ergonode/module-template-consumer',
        'ergonode/module-template-attribute-consumer-admin-ui',
        'ergonode/module-template-publisher',
        'ergonode/module-template-attribute-publisher-admin-ui'
    ].forEach((dependency) => {
        assert.equal(composer.require[dependency], undefined);
    });
    assert.doesNotMatch(moduleXml, /Ergonode_TemplateConsumer|Ergonode_TemplatePublisher/);
});

test('runtime and story reuse the shared entity options implementation', () => {
    assert.match(script, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(script, /entityOptions\.createAction\(/);
    assert.match(publisherStory, /entity-options\.js\?raw/);
    assert.match(publisherStory, /productionEntityOptions\.create\(/);
    assert.match(publisherStory, /productionEntityOptions\.createAction\(/);
});

test('manual actions explain operation-time login requirements and keep Bearer server-side', () => {
    assert.match(script, /Ergonode_PublisherAdminUi\/js\/manual-auth/);
    assert.match(manualAuth, /Log in to Ergonode/);
    assert.match(manualAuth, /manual-auth-logo/);
    assert.match(manualActions, /'logo_url'/);
    assert.match(manualActions, /Ergonode_CoreAdminUi::images\/m2_configuration\.svg/);
    assert.match(manualAuth, /Why is login required\?/);
    assert.match(manualAuth, /Ergonode REST API/);
    assert.match(manualAuth, /must have permission/);
    assert.match(manualAuth, /remember_me: form.elements.remember_me.checked \? 1 : 0/);
    assert.doesNotMatch(manualActions, /remembered_username|RememberedLogin/);
    assert.match(manualAuth, /form\.elements\.password\.value = ''/);
    assert.match(manualAuth, /urls\.status/);
    assert.doesNotMatch(script, /function createAuth/);
    assert.match(script, /pending_create: true/);
    assert.doesNotMatch(script + manualAuth + jsonPost, /Authorization|Bearer/);
    assert.match(client, /ClientInterface/);
    assert.doesNotMatch(client, /\$headers\['Authorization'\]/);
    assert.match(jsonPost, /timeout: 60000/);
    assert.doesNotMatch(diXml, /ManualRest\\\\Client/);
});

test('only saves requiring the Ergonode API are intercepted for login', () => {
    const predicate = script.match(
        /function requiresCategoryApi\(category\) \{[\s\S]*?\n        \}/
    );

    assert.ok(predicate);
    assert.match(script, /requiresManualApi/);
    assert.match(script, /button\.disabled = !!busy \|\| !requiresManualApi\(\)/);
    assert.doesNotMatch(script, /requiresManualWrite/);
    assert.match(script, /stopImmediatePropagation/);
    assert.match(predicate[0], /extension && extension\.pending_create/);
    assert.doesNotMatch(predicate[0], /category\.ergonode_category_id/);
    assert.doesNotMatch(predicate[0], /source_parent_code|source_sort_order/);
    assert.match(script, /categories\.filter\(requiresCategoryApi\)/);
});

test('Magento categories support selection and bulk creation in hierarchy order', () => {
    assert.match(script, /role: 'create-selected-ergonode-categories'/);
    assert.match(script, /ergonode:category-mapping:selection-changed/);
    assert.match(script, /function selectedMagentoIdsForCreation\(models\)/);
    assert.match(script, /api\.getBulkSelection\('magento'\)/);
    assert.match(script, /\.vec-magento-card\.is-configured-root/);
    assert.match(script, /api\.clearBulkSelection\('magento'\)/);
    assert.doesNotMatch(script, /select-ergonode-category|select-all-ergonode-categories/);
    assert.doesNotMatch(script, /category-tree-subtree-mapping|function injectRootSelection/);
    assert.doesNotMatch(script, /\.prop\('hidden', count === 0\)/);
    assert.match(script, /\[data-bulk-options-source="magento"\] \.veui-entity-options-menu/);
    assert.match(script, /\$menu\.prepend\(action\)/);
    assert.match(script, /\$t\('Utwórz w Ergonode'\)/);
    assert.doesNotMatch(script, /Zaznacz dostępne|Utwórz zaznaczone w Ergonode/);
    assert.match(script, /function createSelectedCategories\(\)/);
    assert.match(script, /Number\(first\.level \|\| 0\) - Number\(second\.level \|\| 0\)/);
    assert.match(script, /addPendingCategory\(Number\(id\), models\)/);
    assert.doesNotMatch(styles, /\.vec-ergonode-bulk-actions/);
    assert.doesNotMatch(styles, /has-ergonode-selection|has-ergonode-root-selection/);
});

test('category creation is processed in retryable frontend batches with visible progress', () => {
    assert.match(script, /Ergonode_CategoryPublisherAdminUi\/js\/category-publish-progress/);
    assert.match(script, /Math\.min\(50, Number\(config\.category_batch_size \|\| 50\)\)/);
    assert.match(manualActions, /'category_batch_size' => CategoryBatchPublisher::MAX_BATCH_SIZE/);
    assert.match(script, /config\.urls\.create_category_batch/);
    assert.match(script, /config\.urls\.report_category_collision/);
    assert.match(script, /retry_after_seconds/);
    assert.match(script, /return processAt\(offset\)/);
    assert.match(script, /hasBlockedAncestor/);
    assert.match(script, /saveLayoutWithRetry\(\s*outcome\.excludedCodes,/);
    assert.match(progress, /Ergonode_CoreAdminUi\/js\/bulk-publish-progress/);
    assert.match(progress, /Postęp tworzenia kategorii/);
    assert.match(sharedProgress, /data-role="publish-progress-bar"/);
    assert.match(sharedProgress, /Szacowany czas do końca/);
    assert.match(sharedProgress, /Ponawiam za %1 s/);
    assert.match(sharedProgress, /Błędy wymagające uwagi/);
    assert.doesNotMatch(styles, /vec-category-publish-progress|vec-publish-progress-/);
    assert.match(manualActions, /create_category_batch/);
    assert.match(batchController, /extends AbstractBatchCreate/);
    assert.match(batchController, /category_tree_mapping_save/);
    assert.match(sharedBatchController, /HttpPostActionInterface/);
    assert.match(sharedBatchController, /retry_after_seconds/);
});

test('readable category-code collisions are skipped without generated suffixes', () => {
    assert.match(script, /function reportCategoryCollision\(magento, existing, code\)/);
    assert.match(script, /Kategoria "%1" została pominięta: kod Ergonode "%2" jest już używany/);
    assert.doesNotMatch(script, /base\.slice\(0, 118\).*magento\.id/);
    assert.doesNotMatch(script, /magento_category_' \+ magento\.id/);
});

test('category codes use normalized Magento names from the full path without the configured root', () => {
    assert.match(script, /function categoryPathCode\(magento, models\)/);
    assert.match(script, /models\.pathCodes/);
    assert.match(script, /categoryCodeGenerator\.fromPathLabels/);
    assert.match(script, /Ergonode_CategoryPublisherAdminUi\/js\/category-code-generator/);
    assert.doesNotMatch(script, /function normalizeCode/);
    assert.match(categoryCodeGenerator, /function normalizeCode\(value\)/);
    assert.match(categoryCodeGenerator, /\.join\('__'\)\.slice\(0, 128\)/);
    assert.doesNotMatch(script, /normalizeCode\(magento\.url_key\)/);
});

test('individual category action creates, maps and refreshes both columns without a page reload', () => {
    assert.match(script, /function createCategoryImmediately\(\$button, magentoId\)/);
    assert.match(script, /sendSingleCategoryWithRetry\(category\)/);
    assert.match(script, /api\.markCategoryRemotePrepared\(category\.code, item\.remote_id\)/);
    assert.match(script, /return saveLayoutWithRetry\([\s\S]*?\[\],[\s\S]*?waitForRetry/);
    assert.doesNotMatch(script, /saveSingleLayoutWithRetry/);
    assert.match(script, /api\.saveLayout\(excludedCodes, beforeRender\)/);
    assert.match(script, /api\.markCategoryPublished\(category\.code\)/);
    assert.doesNotMatch(script, /api\.render\(\)/);
    assert.match(script, /item\.status === 'existing'/);
    assert.match(script, /Istniejąca kategoria Ergonode została przypięta do drzewa i zmapowana z Magento/);
    assert.match(script, /Kategoria została utworzona w Ergonode i zmapowana z Magento/);
    assert.doesNotMatch(script, /window\.location\.reload/);
});

test('removed category selection and API logout leave no production remnants', () => {
    assert.equal(fs.existsSync(path.join(
        moduleRoot,
        'Controller/Adminhtml/Session/Logout.php'
    )), false);
    assert.doesNotMatch(manualActions, /session\/logout|'logout'/);
    assert.doesNotMatch(styles, /vec-ergonode-category-select/);
    assert.doesNotMatch(styles, /--vecp-c-047857|--vecp-c-ecfdf5|--vecp-c-fff7f7/);
});

test('individual creation action uses the Magento category options menu and the Ergonode icon', () => {
    assert.match(script, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(script, /var \$optionsMenu = \$card\.find\('\.veui-entity-options-menu'\)\.first\(\)/);
    assert.match(script, /!\$optionsMenu\.length/);
    assert.match(script, /\$optionsMenu\.prepend\(entityOptions\.createAction\(/);
    assert.match(script, /className: 'vec-create-ergonode-category'/);
    assert.match(script, /iconClass: 'vec-create-ergonode-category-icon'/);
    assert.match(script, /label: \$t\('Utwórz w Ergonode'\)/);
    assert.doesNotMatch(script, /\$mapping\.append\(\$\('<button\/>'[\s\S]*?create-ergonode-category/);
    assert.doesNotMatch(styles, /\.vec-create-ergonode-category\s*\{[\s\S]*?(?:border-radius|background):/);
    assert.match(styles, /background: url\('\.\.\/images\/create-ergonode-category\.svg'\) center \/ 16px 16px no-repeat/);
    assert.match(createCategoryIcon, /fill="#eb5202"/);
    assert.match(createCategoryIcon, /stroke="#fff"/);
    assert.doesNotMatch(createCategoryIcon, /<rect/);
});

test('unmapping a pending category cancels its draft creation', () => {
    assert.match(script, /function cancelPendingCategory\(code\)/);
    assert.match(script, /extension && extension\.pending_create/);
    assert.match(script, /api\.removeCategory\(code\)/);
    assert.match(script, /event\.stopImmediatePropagation\(\)/);
});

test('pending category creation uses one accessible tooltip indicator without category borders', () => {
    assert.match(script, /Utwórz kategorię w Ergonode i zapisz mapowanie z kategorią Magento/);
    assert.doesNotMatch(script, /Zmiana zostanie wykonana po zapisaniu/);
    assert.match(script, /function decoratePendingCategories\(\)/);
    assert.match(script, /nie istnieje jeszcze w Ergonode/);
    assert.match(script, /Kliknij „Zapisz”, aby ją utworzyć i zapisać mapowanie/);
    assert.match(script, /\$icon = \$\('<button\/>'[\s\S]*?class: 'vec-pending-ergonode-category'/);
    assert.match(script, /'aria-describedby': tooltipId/);
    assert.match(script, /class: 'vec-pending-ergonode-category-tooltip'[\s\S]*?role: 'tooltip'/);
    assert.doesNotMatch(script, /title: pendingMessage/);
    assert.doesNotMatch(script, /tabindex: '0'/);
    assert.doesNotMatch(script, /class: 'veui-pending-badge vec-pending-ergonode-category'/);
    assert.match(script, /is-pending-create/);
    assert.doesNotMatch(styles, /\.vec-ergo-card\.is-pending-create,[\s\S]*border-color: #f59e0b/);
    assert.match(styles, /\.vec-magento-card\.is-pending-create > \.vec-magento-mapping\s*\{[\s\S]*?display: none;/);
    assert.match(styles, /pending-category-info\.svg/);
    assert.match(styles, /\.vec-pending-ergonode-category-tooltip\s*\{[\s\S]*?z-index: 300;/);
    assert.match(styles, /\.vec-pending-ergonode-category:hover,[\s\S]*?\.vec-pending-ergonode-category:focus\s*\{[\s\S]*?z-index: 300;/);
    assert.doesNotMatch(styles, /cursor:\s*help/);
    assert.match(pendingIcon, /viewBox="0 0 24 24"/);
    assert.match(pendingIcon, /M7 3\.34A10 10 0 1 1 3\.34 7/);
    assert.doesNotMatch(pendingIcon, /<rect/);
});
