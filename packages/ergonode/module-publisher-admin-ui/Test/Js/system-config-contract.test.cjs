'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const projectRoot = path.resolve(moduleRoot, '../../..');
const composer = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');
const systemXml = fs.readFileSync(path.join(moduleRoot, 'etc/adminhtml/system.xml'), 'utf8');
const deploymentConfig = fs.readFileSync(path.join(projectRoot, 'app/etc/config.php'), 'utf8');

test('shared Ergonode publisher Admin UI module owns write-operation configuration', () => {
    assert.equal(composer.name, 'ergonode/module-publisher-admin-ui');
    assert.equal(composer.version, undefined);
    assert.equal(composer.extra.ergonode.lifecycle, 'development');
    assert.match(moduleXml, /<module name="Ergonode_PublisherAdminUi">/);
    assert.match(systemXml, /<section id="ergonode_connection">/);
    assert.match(systemXml, /<group id="test"/);
    assert.match(systemXml, /<group id="production"/);
    assert.equal((systemXml.match(/<group id="publisher"/g) || []).length, 2);
    assert.equal((systemXml.match(/<field id="api_key"/g) || []).length, 2);
    assert.equal((systemXml.match(/<field id="ergonode_connection\/general\/mode">write<\/field>/g) || []).length, 2);
    assert.equal((systemXml.match(/read-and-write API key without an assigned segment/g) || []).length, 2);
    assert.doesNotMatch(systemXml, /<field id="rest_connection"/);
    assert.doesNotMatch(systemXml, /<config_path>|write_operations|<field id="status"/);
    assert.match(systemXml, /Model\\Config\\Backend\\ApiKey/);
    assert.match(systemXml, /Block\\Adminhtml\\System\\Config\\TestConnection/);
    assert.equal(composer.require['ergonode/module-publisher'], 'dev-main@dev');
    assert.doesNotMatch(systemXml, /<section id="ergonode_products"/);
    assert.match(deploymentConfig, /'Ergonode_PublisherAdminUi' => 1/);
});

test('publisher infrastructure feature modules depend directly on the shared publisher Admin UI', () => {
    const featureRoot = path.join(projectRoot, 'packages/ergonode/module-attribute-publisher-admin-ui');
    const featureComposer = JSON.parse(fs.readFileSync(path.join(featureRoot, 'composer.json'), 'utf8'));
    const featureModuleXml = fs.readFileSync(path.join(featureRoot, 'etc/module.xml'), 'utf8');

    assert.equal(featureComposer.require['ergonode/module-publisher-admin-ui'], 'dev-main@dev');
    assert.match(featureModuleXml, /<module name="Ergonode_PublisherAdminUi"\/>/);
});
