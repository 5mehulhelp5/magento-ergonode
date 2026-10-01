const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');

test('refresh and sync controllers delegate to separate template operations', () => {
    const controller = fs.readFileSync(
        path.join(moduleRoot, 'Controller/Adminhtml/Template/Refresh.php'),
        'utf8'
    );
    const syncController = fs.readFileSync(
        path.join(moduleRoot, 'Controller/Adminhtml/Template/Sync.php'),
        'utf8'
    );

    assert.match(controller, /TemplateSnapshotRefresherInterface/);
    assert.match(controller, /templateSnapshotRefresher->refresh\(\)/);
    assert.doesNotMatch(controller, /TemplateSynchronizerInterface/);
    assert.match(syncController, /TemplateSynchronizerInterface/);
    assert.match(syncController, /templateSynchronizer->resetCursor\(\)/);
    assert.match(syncController, /templateSynchronizer->execute\([\s\S]*?ACTION_RESET_CURSOR_AND_SYNC/);
    assert.match(syncController, /synchronization_action/);
    assert.doesNotMatch(controller, /getParam\(['"]cursor/);
});

test('refresh and sync UI use distinct server-owned operations', () => {
    const source = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/template-consumer.js'),
        'utf8'
    );
    const refreshFunction = source.slice(
        source.indexOf('function refreshTemplates'),
        source.indexOf('function synchronizeTemplates')
    );
    const syncFunction = source.slice(
        source.indexOf('function synchronizeTemplates'),
        source.indexOf('function setTemplateRefreshState')
    );

    assert.match(refreshFunction, /request\.post\(config\.urls\.refresh/);
    assert.doesNotMatch(refreshFunction, /config\.urls\.sync/);
    assert.match(syncFunction, /request\.post\(config\.urls\.sync/);
    assert.match(refreshFunction, /var summary = \{changed: 0, unchanged: 0\}/);
    assert.doesNotMatch(refreshFunction, /refreshNextBatch/);
    assert.doesNotMatch(refreshFunction, /retry_after/);
    assert.doesNotMatch(refreshFunction, /cursor:/);
});

test('refresh and snapshot removal actions require their dedicated ACL flags', () => {
    const block = fs.readFileSync(
        path.join(moduleRoot, 'Block/Adminhtml/Template/Actions.php'),
        'utf8'
    );
    const deleteController = fs.readFileSync(
        path.join(moduleRoot, 'Controller/Adminhtml/Template/DeleteSnapshot.php'),
        'utf8'
    );
    const refreshController = fs.readFileSync(
        path.join(moduleRoot, 'Controller/Adminhtml/Template/Refresh.php'),
        'utf8'
    );
    const source = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/template-consumer.js'),
        'utf8'
    );

    assert.match(block, /'can_refresh_templates' => \$this->canRefreshTemplates\(\)/);
    assert.match(block, /'can_delete_snapshots' => \$this->canDeleteSnapshots\(\)/);
    assert.match(source, /if \(config\.can_delete_snapshots\) \{[\s\S]*?snapshotRemoval\.bind/);
    assert.match(source, /if \(config\.can_refresh_templates\) \{[\s\S]*?role: 'refresh-templates'/);
    assert.match(source, /config\.can_delete_snapshots[\s\S]*?snapshotRemoval\.enhance/);
    assert.match(
        refreshController,
        /ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_refresh'/
    );
    assert.match(
        deleteController,
        /ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_save'/
    );
});

test('workspace exposes refresh and snapshot removal only for their dedicated permissions', () => {
    const source = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/template-consumer.js'),
        'utf8'
    );
    const cases = [
        ['mapping only', false, false],
        ['refresh only', true, false],
        ['save only', false, true],
        ['sync only', false, false]
    ];

    cases.forEach(([label, canRefresh, canDelete]) => {
        const calls = {
            delegatedSelectors: [],
            entityEnhancements: [],
            refreshActions: 0,
            snapshotBindings: 0,
            snapshotEnhancements: []
        };
        const entityOptions = {
            bind() {},
            createAction() {
                calls.refreshActions += 1;

                return {cloneNode() { return this; }, classList: {add() {}}};
            },
            enhance(card, options) {
                calls.entityEnhancements.push(options);
            }
        };
        const snapshotRemoval = {
            bind() {
                calls.snapshotBindings += 1;
            },
            enhance(card, options) {
                calls.snapshotEnhancements.push(options);
            }
        };
        let adapter;
        const dependencies = [
            (value) => value,
            {},
            {},
            {bind() {}},
            entityOptions,
            snapshotRemoval,
            {create() { return {clear() {}, show() {}}; }}
        ];
        vm.runInNewContext(source, {
            define(moduleNames, factory) {
                adapter = factory(...dependencies);
            }
        });
        const card = {
            classList: {contains() { return false; }},
            getAttribute(attribute) {
                return {
                    'data-label': 'Template',
                    'data-source-active': '1',
                    'data-template-code': 'template'
                }[attribute];
            },
            querySelector() {
                return {};
            }
        };
        const element = {
            addEventListener() {},
            querySelector(selector) {
                if (selector === '[data-template-source-options] .veui-entity-options-menu') {
                    return {prepend() {}};
                }

                return null;
            },
            querySelectorAll() {
                return [card];
            },
            veaContext: {
                autosave: {},
                config: {
                    can_delete_snapshots: canDelete,
                    can_refresh_templates: canRefresh
                },
                dirty: {isDirty() { return false; }},
                message: {}
            },
            veaWorkspace: {
                claim() { return true; },
                delegate(event, selector) {
                    calls.delegatedSelectors.push(selector);
                },
                listen() {}
            }
        };

        adapter({}, element);

        assert.equal(calls.refreshActions, canRefresh ? 1 : 0, `${label}: refresh action`);
        assert.equal(
            calls.delegatedSelectors.includes('[data-role="refresh-templates"]'),
            canRefresh,
            `${label}: refresh delegate`
        );
        assert.equal(calls.snapshotBindings, canDelete ? 1 : 0, `${label}: snapshot removal binding`);
        assert.equal(calls.snapshotEnhancements.length, canDelete ? 1 : 0, `${label}: snapshot removal action`);
        assert.equal(calls.entityEnhancements.length, canDelete ? 0 : 1, `${label}: mapping action fallback`);
        if (!canDelete) {
            assert.equal(calls.entityEnhancements[0].actions[0].role, 'entity-add-to-mapping');
        }
    });
});
