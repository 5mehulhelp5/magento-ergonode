'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const repositoryRoot = path.resolve(moduleRoot, '../../..');
const coreModal = fs.readFileSync(path.join(
    repositoryRoot,
    'packages/ergonode/module-core-admin-ui/view/adminhtml/web/js/option-mapping-modal.js'
), 'utf8');
const layout = fs.readFileSync(path.join(
    moduleRoot,
    'view/adminhtml/layout/ergonode_attribute_index.xml'
), 'utf8');
const template = fs.readFileSync(path.join(
    moduleRoot,
    'view/adminhtml/templates/option-modal-publisher-init.phtml'
), 'utf8');
const initializer = fs.readFileSync(path.join(
    moduleRoot,
    'view/adminhtml/web/js/option-modal-publisher-init.js'
), 'utf8');

test('attribute page registers the option publisher with option mode', () => {
    assert.match(layout, /name="ergonode.option.modal.publisher.init"/);
    assert.match(layout, /template="Ergonode_ProductAttributePublisherAdminUi::option-modal-publisher-init.phtml"/);
    assert.match(layout, /<argument name="mode" xsi:type="string">option<\/argument>/);
    assert.match(layout, /<argument name="target_selector" xsi:type="string">#ergonode-attribute-mapping<\/argument>/);
    assert.match(template, /Ergonode_ProductAttributePublisherAdminUi\/js\/option-modal-publisher-init/);
    assert.match(coreModal, /optionMapping\(response\.config \|\| \{\}, workspace\);[\s\S]*state\.root\.dispatchEvent\(new CustomEvent\('vea:option-mapping-ready'/);
});

test('each loaded modal gets the existing publisher behavior and listener is removed on cleanup', () => {
    const published = [];
    const handlers = new Map();
    const cleanup = [];
    const config = {mode: 'option'};
    const element = {
        veaWorkspace: {cleanup: (callback) => cleanup.push(callback)},
        addEventListener: (type, handler) => handlers.set(type, handler),
        removeEventListener: (type, handler) => {
            if (handlers.get(type) === handler) {
                handlers.delete(type);
            }
        }
    };
    let initialize;

    vm.runInNewContext(initializer, {
        define: (_dependencies, factory) => {
            initialize = factory((publisherConfig, workspace) => {
                published.push({config: publisherConfig, workspace});
            });
        }
    });
    initialize(config, element);

    const first = {veaWorkspace: {}};
    const second = {veaWorkspace: {}};
    handlers.get('vea:option-mapping-ready')({detail: {workspace: first}});
    handlers.get('vea:option-mapping-ready')({detail: {workspace: second}});
    handlers.get('vea:option-mapping-ready')({detail: {workspace: {}}});

    assert.deepEqual(published, [
        {config, workspace: first},
        {config, workspace: second}
    ]);
    cleanup[0]();
    assert.equal(handlers.has('vea:option-mapping-ready'), false);
});
