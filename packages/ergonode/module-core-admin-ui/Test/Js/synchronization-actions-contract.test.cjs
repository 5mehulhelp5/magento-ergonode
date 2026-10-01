'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');

function read(relativePath) {
    return fs.readFileSync(path.join(moduleRoot, relativePath), 'utf8');
}

test('shared synchronization component renders cursor and sync actions', () => {
    const block = read('Block/Adminhtml/SynchronizationActions.php');
    const template = read('view/adminhtml/templates/synchronization/actions.phtml');

    assert.match(block, /Ergonode_CoreAdminUi::synchronization\/actions\.phtml/);
    assert.match(template, /veui-split-button veui-split-button-align-end/);
    assert.match(template, /data-synchronization-action="sync"/);
    assert.match(template, /data-synchronization-action="reset-cursor"/);
    assert.match(template, /data-synchronization-action="reset-cursor-and-sync"/);
    assert.match(template, /veui-sync-ergonode-icon/);
    assert.match(template, /veui-reset-cursor-icon/);
});

test('shared synchronization component owns one split-button visual and keyboard contract', () => {
    const sharedStyles = read('view/adminhtml/web/css/ergonode-workspace.css');
    const actionStyles = read('view/adminhtml/web/css/ergonode-actions.css');
    const behavior = read('view/adminhtml/web/js/synchronization-actions.js');
    const story = read('Test/Storybook/SynchronizationActions.stories.js');

    assert.match(sharedStyles, /\.veui-split-button\s*\{/);
    assert.match(sharedStyles, /\.veui-split-button-menu\s*\{/);
    assert.match(sharedStyles, /\.veui-split-button-option\s*\{/);
    assert.doesNotMatch(actionStyles, /\.veui-synchronization-action-(?:main|options|toggle|menu|option)/);
    assert.match(behavior, /event\.key !== 'Enter' && event\.key !== ' '/);
    assert.match(behavior, /event\.key !== 'Escape'/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const Menu/);
    assert.match(story, /export const Keyboard/);
});
