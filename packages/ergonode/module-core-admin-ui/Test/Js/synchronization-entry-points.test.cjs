'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleDirectories, modulePath} = require('./module-paths.cjs');

test('Ergonode system configuration does not expose manual synchronization groups', () => {
    for (const root of moduleDirectories()) {
        const systemXmlPath = path.join(root, 'etc/adminhtml/system.xml');
        if (!fs.existsSync(systemXmlPath)) {
            continue;
        }
        const systemXml = fs.readFileSync(systemXmlPath, 'utf8');

        const synchronizationGroup = systemXml.match(/<group\b[^>]*id="synchronization"[^>]*>([\s\S]*?)<\/group>/);
        if (synchronizationGroup) {
            assert.doesNotMatch(synchronizationGroup[1], /<field\b[^>]*type="button"/);
        }
        assert.doesNotMatch(systemXml, /SynchronizationButton/);
    }
});

test('legacy configuration synchronization actions and shared UI are removed', () => {
    const removedFiles = [
        ['CategoryConsumerAdminUi', 'Controller/Adminhtml/Category/Synchronize.php'],
        ['CategoryConsumerAdminUi', 'Block/Adminhtml/System/Config/ManageCategoryTreesLink.php'],
        ['ProductPublisherAdminUi', 'Controller/Adminhtml/Product/SynchronizeProducts.php'],
        ['ProductPublisherAdminUi', 'Model/AllProductPublicationScheduler.php'],
        ['LanguageAdminUi', 'Controller/Adminhtml/Language/Synchronize.php'],
        ['CoreAdminUi', 'Block/Adminhtml/System/Config/SynchronizationButton.php'],
        ['CoreAdminUi', 'view/adminhtml/templates/system/config/synchronization-button.phtml'],
        ['CoreAdminUi', 'view/adminhtml/web/js/synchronization-button.js'],
        ['CoreAdminUi', 'Test/Storybook/SynchronizationButton.stories.js']
    ];

    for (const [moduleName, relativePath] of removedFiles) {
        assert.equal(
            fs.existsSync(path.join(modulePath(moduleName), relativePath)),
            false,
            `${moduleName}/${relativePath} should not exist`
        );
    }
    const productSettings = fs.readFileSync(
        path.join(modulePath('ProductPublisherAdminUi'), 'etc/adminhtml/system.xml'),
        'utf8'
    );
    assert.match(productSettings, /<section id="ergonode_products"/);
    assert.doesNotMatch(productSettings, /SynchronizationButton|SynchronizeProducts/);
});
