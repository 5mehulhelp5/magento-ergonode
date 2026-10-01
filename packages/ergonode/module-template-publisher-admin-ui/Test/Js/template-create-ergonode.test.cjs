'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {moduleRoot: findModuleRoot} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const consumerRoot = findModuleRoot('TemplateAdminUi');
const consumer = fs.readFileSync(
    path.join(consumerRoot, 'view/adminhtml/web/js/template-admin.js'),
    'utf8'
);
const publisher = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/template-publisher.js'),
    'utf8'
);
const block = fs.readFileSync(path.join(moduleRoot, 'Block/Adminhtml/TemplatePublisher.php'), 'utf8');
const layout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_template_index.xml'),
    'utf8'
);
const template = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/templates/template-publisher-init.phtml'),
    'utf8'
);
const source = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/js/template-code.js'), 'utf8');

function loadCodeModule() {
    let exported;
    vm.runInNewContext(source, {
        define: (dependencies, factory) => {
            exported = factory();
        }
    });

    return exported;
}

test('template publication extends the consumer workspace without a reverse dependency', () => {
    assert.match(consumer, /registerDraftTemplateAction/);
    assert.match(consumer, /function createDraftTemplate\(draftId, options\)/);
    assert.match(consumer, /ergonode:template-workspace-ready/);
    assert.doesNotMatch(consumer, /Ergonode_TemplatePublisherAdminUi/);
    assert.doesNotMatch(consumer, /create_ergonode_template/);
    assert.match(publisher, /registerDraftTemplateAction/);
    assert.match(publisher, /dataset\.role = 'create-ergonode-template'/);
    assert.match(publisher, /request\.post\(config\.urls\.create/);
    assert.match(publisher, /template_code: code/);
    assert.match(publisher, /attribute_set_id: attributeSet\.id/);
    assert.match(block, /ergonode_template_publication\/template\/create/);
    assert.match(layout, /after="ergonode\.template\.index"/);
});

test('template publication initializes only for a role allowed to create templates', () => {
    assert.match(block, /Create::ADMIN_RESOURCE/);
    assert.match(
        template,
        /<\?php if \(\$block->canCreateTemplate\(\)\): \?>[\s\S]*text\/x-magento-init[\s\S]*<\?php endif; \?>/
    );
});

test('template codes are deterministic and normalized', () => {
    const templateCode = loadCodeModule();

    assert.equal(templateCode.normalize(' Kolekcja Łóżek 2026! '), 'kolekcja_lozek_2026');
    assert.equal(templateCode.candidateFromAttributeSet('Summer Collection', 12), 'summer_collection');
});

test('template creation collision checks code before localized names', () => {
    const templateCode = loadCodeModule();
    const templates = [
        { code: 'other_template', names: ['Summer Collection', 'Letnia kolekcja'] },
        { code: 'summer_collection', names: ['Different name'] }
    ];

    assert.deepEqual(
        JSON.parse(JSON.stringify(templateCode.findCreationCollision('Summer Collection', 12, templates))),
        {
            candidateCode: 'summer_collection',
            source: 'code',
            template: templates[1]
        }
    );
});

test('template creation collision checks every localized name', () => {
    const templateCode = loadCodeModule();
    const template = {
        code: 'bags_legacy',
        names: ['Bags', 'Torby', 'Taschen']
    };

    assert.deepEqual(
        JSON.parse(JSON.stringify(templateCode.findCreationCollision('Torby', 15, [template]))),
        {
            candidateCode: 'torby',
            matchedName: 'Torby',
            source: 'name',
            template: template
        }
    );
    assert.equal(templateCode.findCreationCollision('Shoes', 16, [template]), null);
});
