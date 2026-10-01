'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const source = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/visibility-toggle.js'),
    'utf8'
);
const buttons = {
    isPressed: (button) => Boolean(button && button.attributes['aria-pressed'] === 'true'),
    setPressed: (button, pressed) => button.setAttribute('aria-pressed', pressed ? 'true' : 'false')
};
let visibilityToggle;

vm.runInNewContext(source, {
    define: (dependencies, factory) => {
        assert.deepEqual(Array.from(dependencies), ['Ergonode_CoreAdminUi/js/buttons']);
        visibilityToggle = factory(buttons);
    }
});

function createButton() {
    return {
        attributes: {
            'aria-pressed': 'false',
            'data-show-hint': 'Pokaż pominięte elementy',
            'data-hide-hint': 'Ukryj pominięte elementy'
        },
        getAttribute(name) {
            return this.attributes[name] || '';
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        }
    };
}

test('synchronizes the tooltip and accessible name with the current state', () => {
    const button = createButton();

    assert.equal(visibilityToggle.sync(button), false);
    assert.equal(button.attributes.title, 'Pokaż pominięte elementy');
    assert.equal(button.attributes['aria-label'], 'Pokaż pominięte elementy');

    assert.equal(visibilityToggle.toggle(button), true);
    assert.equal(button.attributes['aria-pressed'], 'true');
    assert.equal(button.attributes.title, 'Ukryj pominięte elementy');
    assert.equal(button.attributes['aria-label'], 'Ukryj pominięte elementy');
});

test('sets an explicit visibility state without changing the component label', () => {
    const button = createButton();

    assert.equal(visibilityToggle.setVisible(button, true), true);
    assert.equal(button.attributes['aria-pressed'], 'true');
    assert.equal(button.attributes.title, 'Ukryj pominięte elementy');
});
