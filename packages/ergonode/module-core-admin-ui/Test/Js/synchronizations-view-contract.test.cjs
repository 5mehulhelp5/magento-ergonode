'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {projectRoot, modulePath} = require('./module-paths.cjs');

const backendRoot = projectRoot;

function read(relativePath) {
    const moduleFile = relativePath.match(/^app\/code\/Ergonode\/([^/]+)\/(.*)$/);
    const file = moduleFile
        ? path.join(modulePath(moduleFile[1]), moduleFile[2])
        : path.join(backendRoot, relativePath);

    return fs.readFileSync(file, 'utf8');
}

function templateConfiguration() {
    return fs.readFileSync(path.join(modulePath('TemplateConsumer'), 'etc/di.xml'), 'utf8');
}

test('synchronization view uses the shared Ergonode workspace contract', () => {
    const layout = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/layout/ergonode_synchronization_index.xml');
    const template = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/templates/synchronizations.phtml');
    const styles = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/css/synchronizations.css');

    assert.match(layout, /ergonode-workspace\.css/);
    assert.match(layout, /synchronizations\.css/);
    assert.match(template, /veui-workspace veui-workspace-viewbar ves-synchronizations/);
    assert.match(template, /SectionNavigation::SECTION_SYNCHRONIZATIONS/);
    assert.match(template, /<table class="ves-synchronizations-table">/);
    assert.doesNotMatch(template, /class="ves-synchronizations-summary"/);
    assert.match(template, /ves-synchronizations-summary-icon/);
    assert.match(template, /data-role="process-select"/);
    assert.match(template, /data-role="run-selected"/);
    assert.match(template, /data-role="reset-selected"/);
    assert.match(styles, /var\(--veui-accent-soft\)/);
    assert.match(styles, /var\(--veui-border-soft\)/);
});

test('enabled domain modules own synchronization registrations', () => {
    const coreAdminDi = read('app/code/Ergonode/CoreAdminUi/etc/di.xml');
    const attributeDi = read('packages/ergonode/module-attribute-consumer/etc/di.xml');
    const categoryDi = read('app/code/Ergonode/CategoryConsumer/etc/di.xml');
    const templateDi = templateConfiguration();

    assert.doesNotMatch(coreAdminDi, /attributeStream|category_tree_stream|template_stream/);
    assert.match(attributeDi, /<item name="attributeStream"/);
    assert.match(categoryDi, /<item name="category_tree_stream"/);
    assert.match(templateDi, /<item name="template_stream"/);
});

test('domain runtime modules own executable synchronization operations', () => {
    const categoryDi = read('app/code/Ergonode/CategoryConsumer/etc/di.xml');
    const templateDi = templateConfiguration();

    assert.match(categoryDi, /<item name="category_tree_stream"[^>]*>[^<]*SynchronizationOperation/);
    assert.match(templateDi, /<item name="template_stream"[^>]*>[^<]*SynchronizationOperation/);
});

test('synchronization mutations are POST-only and use dedicated ACL resources', () => {
    const runController = read(
        'app/code/Ergonode/CoreAdminUi/Controller/Adminhtml/Synchronization/Run.php'
    );
    const resetController = read(
        'app/code/Ergonode/CoreAdminUi/Controller/Adminhtml/Synchronization/Reset.php'
    );
    const acl = read('app/code/Ergonode/Core/etc/acl.xml');

    assert.match(runController, /implements HttpPostActionInterface/);
    assert.match(runController, /Ergonode_Core::synchronizations_run/);
    assert.match(resetController, /implements HttpPostActionInterface/);
    assert.match(resetController, /Ergonode_Core::synchronizations_reset/);
    assert.match(acl, /id="Ergonode_Core::synchronizations_run"/);
    assert.match(acl, /id="Ergonode_Core::synchronizations_reset"/);
});

test('synchronization story covers active, future-template and empty states', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/Synchronizations.stories.js');

    assert.match(story, /synchronizations\.css/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /with-template/);
    assert.match(story, /template_stream/);
    assert.match(story, /export const Dostepnosc/);
    assert.match(story, /export const ZaznaczanieMysza/);
    assert.match(story, /synchronizations\.js\?raw/);
    assert.match(story, /getByRole\('table'/);
});
