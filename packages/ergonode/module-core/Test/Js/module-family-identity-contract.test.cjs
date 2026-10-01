'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
let projectRoot = moduleRoot;
while (!fs.existsSync(path.join(projectRoot, 'app/etc/config.php'))) {
    const parent = path.dirname(projectRoot);
    if (parent === projectRoot) {
        throw new Error('Cannot locate the Magento project root.');
    }
    projectRoot = parent;
}
const deploymentConfig = fs.readFileSync(path.join(projectRoot, 'app/etc/config.php'), 'utf8');

function modulePath(directory, packageName) {
    const appCodePath = path.join(projectRoot, 'app/code/Ergonode', directory);
    return fs.existsSync(appCodePath)
        ? appCodePath
        : path.join(projectRoot, 'packages', packageName);
}

const modules = [
    ['Core', 'Ergonode_Core', 'Ergonode\\Core', 'ergonode/module-core'],
    ['CoreAdminUi', 'Ergonode_CoreAdminUi', 'Ergonode\\CoreAdminUi', 'ergonode/module-core-admin-ui'],
    ['Attribute', 'Ergonode_Attribute', 'Ergonode\\Attribute', 'ergonode/module-attribute'],
    ['AttributeConsumer', 'Ergonode_AttributeConsumer', 'Ergonode\\AttributeConsumer', 'ergonode/module-attribute-consumer'],
    ['CategoryConsumer', 'Ergonode_CategoryConsumer', 'Ergonode\\CategoryConsumer', 'ergonode/module-category-consumer'],
    ['CategoryConsumerAdminUi', 'Ergonode_CategoryConsumerAdminUi', 'Ergonode\\CategoryConsumerAdminUi', 'ergonode/module-category-consumer-admin-ui'],
    ['Language', 'Ergonode_Language', 'Ergonode\\Language', 'ergonode/module-language'],
    ['LanguageAdminUi', 'Ergonode_LanguageAdminUi', 'Ergonode\\LanguageAdminUi', 'ergonode/module-language-admin-ui'],
];

if (fs.existsSync(modulePath('TemplateAttributeConsumerAdminUi', 'ergonode/module-template-attribute-consumer-admin-ui'))) {
    modules.push([
        'TemplateAttributeConsumerAdminUi',
        'Ergonode_TemplateAttributeConsumerAdminUi',
        'Ergonode\\TemplateAttributeConsumerAdminUi',
        'ergonode/module-template-attribute-consumer-admin-ui'
    ]);
}

test('Ergonode module family exposes only its target identities', () => {
    modules.forEach(([directory, moduleName, namespace, packageName]) => {
        const root = modulePath(directory, packageName);
        const composer = JSON.parse(fs.readFileSync(path.join(root, 'composer.json'), 'utf8'));
        const registration = fs.readFileSync(path.join(root, 'registration.php'), 'utf8');
        const moduleXml = fs.readFileSync(path.join(root, 'etc/module.xml'), 'utf8');

        assert.equal(composer.name, packageName);
        assert.equal(composer.autoload['psr-4'][`${namespace}\\`], '');
        assert.equal(composer.extra.ergonode.lifecycle, 'development');
        assert.equal(composer.version, undefined);
        assert.match(registration, new RegExp(`'${moduleName}'`));
        assert.match(moduleXml, new RegExp(`<module name="${moduleName}"(?:>|/>)`));
        assert.match(deploymentConfig, new RegExp(`'${moduleName}' => 1`));
    });
});
