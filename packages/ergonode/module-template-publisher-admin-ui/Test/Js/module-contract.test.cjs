'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {backendRoot, moduleRoot: findModuleRoot} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const composer = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');
const registration = fs.readFileSync(path.join(moduleRoot, 'registration.php'), 'utf8');
const routes = fs.readFileSync(path.join(moduleRoot, 'etc/adminhtml/routes.xml'), 'utf8');
const deploymentConfig = fs.readFileSync(path.join(backendRoot, 'app/etc/config.php'), 'utf8');
const consumerRoot = findModuleRoot('TemplateAdminUi');
const consumerComposer = JSON.parse(fs.readFileSync(
    path.join(consumerRoot, 'composer.json'),
    'utf8'
));
const consumerModuleXml = fs.readFileSync(
    path.join(consumerRoot, 'etc/module.xml'),
    'utf8'
);

test('template publisher Admin UI owns the optional publication integration', () => {
    assert.equal(composer.name, 'ergonode/module-template-publisher-admin-ui');
    assert.equal(composer.autoload['psr-4']['Ergonode\\TemplatePublisherAdminUi\\'], '');
    assert.equal(composer.extra.ergonode.lifecycle, 'development');
    assert.match(registration, /'Ergonode_TemplatePublisherAdminUi'/);
    assert.match(moduleXml, /<module name="Ergonode_TemplatePublisherAdminUi">/);
    assert.match(moduleXml, /<module name="Ergonode_TemplatePublisher"\/>/);
    assert.match(moduleXml, /<module name="Ergonode_TemplateAdminUi"\/>/);
    assert.match(routes, /frontName="ergonode_template_publication"/);
    assert.match(deploymentConfig, /'Ergonode_TemplatePublisherAdminUi' => 1/);
});

test('template consumer Admin UI has no reverse dependency on template publication', () => {
    assert.equal(consumerComposer.require['ergonode/module-template-publisher'], undefined);
    assert.doesNotMatch(consumerModuleXml, /Ergonode_TemplatePublisher/);
});
