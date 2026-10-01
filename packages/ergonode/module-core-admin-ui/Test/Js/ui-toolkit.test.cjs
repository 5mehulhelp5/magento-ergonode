'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');

function load(relativePath, dependencies = {}, globals = {}) {
    const source = fs.readFileSync(path.join(moduleRoot, relativePath), 'utf8');
    let exported;
    const context = Object.assign({
        Promise,
        URLSearchParams,
        WeakMap,
        WeakSet,
        define: (names, factory) => {
            exported = factory(...names.map((name) => (
                name === 'mage/translate' ? (value) => value : dependencies[name]
            )));
        }
    }, globals);

    vm.runInNewContext(source, context);

    return exported;
}

function requestWindow(overrides = {}) {
    return Object.assign({
        AbortController,
        FORM_KEY: 'form-key',
        clearTimeout,
        setTimeout
    }, overrides);
}

class EventTargetStub {
    constructor() {
        this.listeners = new Map();
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    removeEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        this.listeners.set(type, listeners.filter((candidate) => candidate !== listener));
    }

    contains() {
        return true;
    }

    emit(type, target) {
        (this.listeners.get(type) || []).slice().forEach((listener) => listener({type, target}));
    }
}

test('workspace mounts once, delegates dynamic HTML and removes listeners on destroy', () => {
    const workspace = load('view/adminhtml/web/js/workspace.js');
    const root = new EventTargetStub();
    let setups = 0;
    let clicks = 0;
    const first = workspace.mount(root, (scope) => {
        setups += 1;
        scope.delegate('click', '.dynamic-action', () => {
            clicks += 1;
        });
    });
    const second = workspace.mount(root, () => {
        setups += 1;
    });
    const dynamicAction = {
        closest: (selector) => selector === '.dynamic-action' ? dynamicAction : null
    };

    root.emit('click', dynamicAction);
    assert.equal(first, second);
    assert.equal(setups, 1);
    assert.equal(clicks, 1);

    first.destroy();
    root.emit('click', dynamicAction);
    assert.equal(clicks, 1);
    assert.equal(root.listeners.get('click').length, 0);
    assert.equal(workspace.get(root), null);

    const remounted = workspace.mount(root, (scope) => {
        assert.equal(scope.claim(dynamicAction, 'dynamic'), true);
        assert.equal(scope.claim(dynamicAction, 'dynamic'), false);
        scope.delegate('click', '.dynamic-action', () => {
            clicks += 1;
        });
    });
    root.emit('click', dynamicAction);
    assert.equal(clicks, 2);
    remounted.destroy();
});

test('dirty state handles empty data and captures a new saved baseline', () => {
    const dirtyState = load('view/adminhtml/web/js/dirty-state.js');
    let value = [];
    const tracker = dirtyState.create(() => value);

    assert.equal(tracker.isDirty(), false);
    tracker.capture();
    assert.equal(tracker.isDirty(), false);
    value = [{id: 1}];
    assert.equal(tracker.isDirty(), true);
    tracker.capture();
    assert.equal(tracker.isDirty(), false);
});

test('request rejects missing URLs, request failures and unsuccessful responses', async () => {
    const windowStub = requestWindow();
    const request = load('view/adminhtml/web/js/request.js', {}, {window: windowStub});

    windowStub.fetch = async () => ({json: async () => ({success: false, message: 'server error'})});
    await assert.rejects(request.post('', {}, {}), /Brak URL akcji/);
    await assert.rejects(request.post('/save', {}, {}), /server error/);

    windowStub.fetch = async () => {
        throw new Error('network error');
    };
    await assert.rejects(request.post('/save', {}, {}), /network error/);
});

test('request reports an expired Magento session instead of parsing redirected HTML', async () => {
    let parsed = false;
    const request = load('view/adminhtml/web/js/request.js', {}, {
        window: requestWindow({
            fetch: async () => ({
                redirected: true,
                headers: {get: () => 'text/html; charset=UTF-8'},
                json: async () => {
                    parsed = true;
                }
            })
        })
    });

    await assert.rejects(
        request.post('/save', {}, {}),
        /Sesja administratora Magento wygasła/
    );
    assert.equal(parsed, false);
});

test('request replaces malformed JSON parser errors with an administrator-facing message', async () => {
    const request = load('view/adminhtml/web/js/request.js', {}, {
        window: requestWindow({
            fetch: async () => ({
                redirected: false,
                headers: {get: () => 'application/json'},
                json: async () => {
                    throw new SyntaxError("Unexpected token '<'");
                }
            })
        })
    });

    await assert.rejects(request.post('/save', {}, {}), /Nieprawidłowa odpowiedź serwera/);
});

test('paginated request walks all pages and reports each page', async () => {
    const bodies = [];
    const pages = [];
    const responses = [
        {success: true, has_more: true, cursor: 'next', page_size: 25},
        {success: true, has_more: false}
    ];
    const windowStub = requestWindow({
        FORM_KEY: 'key',
        fetch: async (url, options) => {
            bodies.push(options.body);
            return {json: async () => responses.shift()};
        }
    });
    const request = load('view/adminhtml/web/js/request.js', {}, {window: windowStub});

    await request.paginate({
        url: '/refresh',
        config: {},
        pageSize: 25,
        data: () => ({context: 'attribute'}),
        onPage: (response) => pages.push(response.has_more)
    });

    assert.deepEqual(pages, [true, false]);
    assert.match(bodies[0], /cursor=&page_size=25/);
    assert.match(bodies[1], /cursor=next&page_size=25/);
});

test('request aborts and rejects after the 60-second deadline', async () => {
    let abortController;
    let clearTimeoutId;
    let requestOptions;
    let timeoutCallback;
    let timeoutDelay;

    class AbortControllerStub {
        constructor() {
            this.signal = {aborted: false};
            abortController = this;
        }

        abort() {
            this.signal.aborted = true;
        }
    }

    const windowStub = requestWindow({
        AbortController: AbortControllerStub,
        clearTimeout: (timeoutId) => {
            clearTimeoutId = timeoutId;
        },
        fetch: async (url, options) => {
            requestOptions = options;
            return new Promise(() => {});
        },
        setTimeout: (callback, delay) => {
            timeoutCallback = callback;
            timeoutDelay = delay;
            return 17;
        }
    });
    const request = load('view/adminhtml/web/js/request.js', {}, {window: windowStub});
    const pendingRequest = request.post('/save', {}, {});

    await Promise.resolve();
    assert.equal(timeoutDelay, 60000);
    assert.equal(requestOptions.signal, abortController.signal);

    timeoutCallback();
    await assert.rejects(pendingRequest, /limit 60 sekund/);
    assert.equal(abortController.signal.aborted, true);
    assert.equal(clearTimeoutId, 17);
});

test('messages handle missing markup and expose accessible error state', () => {
    let scheduled;
    let closeButton;
    const classNames = new Set();
    const content = {textContent: ''};
    const attributes = {};
    const createCloseButton = () => {
        const buttonAttributes = {};
        const listeners = {};

        return {
            attributes: buttonAttributes,
            listeners,
            addEventListener: (name, callback) => { listeners[name] = callback; },
            removeEventListener: (name, callback) => {
                if (listeners[name] === callback) {
                    delete listeners[name];
                }
            },
            setAttribute: (name, value) => { buttonAttributes[name] = value; }
        };
    };
    const container = {
        hidden: true,
        ownerDocument: {createElement: createCloseButton},
        classList: {
            add: (name) => classNames.add(name),
            remove: (name) => classNames.delete(name)
        },
        appendChild: (element) => { closeButton = element; },
        querySelector: (selector) => selector === '.text' ? content : closeButton || null,
        setAttribute: (name, value) => { attributes[name] = value; },
        removeAttribute: (name) => { delete attributes[name]; }
    };
    const windowStub = {
        clearTimeout: () => { scheduled = null; },
        setTimeout: (callback, delay) => {
            scheduled = {callback, delay};

            return 1;
        }
    };
    const messages = load('view/adminhtml/web/js/messages.js', {
        'mage/translate': (value) => value
    }, {window: windowStub});
    const bus = messages.create({querySelector: () => container}, {
        containerSelector: '.message',
        textSelector: '.text',
        toneClasses: {error: 'message-error'}
    });

    bus.show('error', 'failed');
    assert.equal(container.hidden, false);
    assert.equal(content.textContent, 'failed');
    assert.equal(attributes.role, 'alert');
    assert.equal(attributes['aria-live'], 'assertive');
    assert.equal(classNames.has('message-error'), true);
    assert.equal(scheduled.delay, 30000);
    assert.equal(closeButton.textContent, '×');
    assert.equal(closeButton.attributes['aria-label'], 'Close message');

    closeButton.listeners.click();
    assert.equal(container.hidden, true);
    assert.equal(content.textContent, '');
    assert.equal(scheduled, null);

    bus.show('info', 'persistent', 0);
    assert.equal(scheduled, null);
    bus.destroy();
    assert.equal(closeButton.listeners.click, undefined);
    assert.doesNotThrow(() => messages.create({querySelector: () => null}, {
        containerSelector: '.missing',
        textSelector: '.missing-text'
    }).show('info', 'ignored'));

    bus.error('Title', {message: '<unsafe>'}, 'fallback');
    assert.equal(content.textContent, 'Title: <unsafe>');
    assert.equal(attributes.role, 'alert');
});

test('mapping elements share safe HTML and tolerate empty slots', () => {
    const text = load('view/adminhtml/web/js/text.js');
    const elements = load('view/adminhtml/web/js/mapping-elements.js', {
        'Ergonode_CoreAdminUi/js/text': text
    });
    const attributes = {
        'data-side': 'magento',
        'data-code': 'color',
        'data-label': '<Color>',
        'data-type': 'select',
        'data-scope': 'global'
    };
    const slot = {getAttribute: (name) => attributes[name] || ''};

    assert.equal(elements.slotPayload(null), null);
    assert.equal(elements.slotPayload({getAttribute: () => ''}), null);
    assert.equal(elements.slotPayload(slot).source, 'magento');
    assert.match(elements.metaHtml(elements.slotPayload(slot)), /&lt;Color&gt;|color/);
    assert.match(elements.typeBadgeHtml('<select>'), /&lt;select&gt;/);
});

test('buttons and drag payloads expose deterministic empty and busy states', async () => {
    const button = {
        disabled: false,
        attributes: {},
        classList: {toggle: () => {}},
        getAttribute(name) { return this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; }
    };
    const buttons = load('view/adminhtml/web/js/buttons.js');
    const dragDrop = load('view/adminhtml/web/js/drag-drop.js');
    const stored = {};
    const firstRoot = {};
    const secondRoot = {};
    const transfer = {
        setData: (type, value) => { stored[type] = value; },
        getData: (type) => stored[type] || ''
    };

    buttons.setBusy(button, true);
    assert.equal(button.disabled, true);
    assert.equal(button.attributes['aria-busy'], 'true');
    buttons.setBusy(button, false);
    assert.equal(button.disabled, false);
    assert.equal(button.attributes['aria-busy'], undefined);
    assert.equal(buttons.isPressed(button), false);
    assert.equal(buttons.setPressed(button, true), true);
    assert.equal(buttons.isPressed(button), true);
    assert.equal(buttons.togglePressed(button), false);
    assert.equal(button.attributes['aria-pressed'], 'false');

    dragDrop.write(transfer, {id: 7}, 'application/example');
    assert.equal(dragDrop.read(transfer, 'application/example').id, 7);
    stored['application/example'] = '{invalid';
    stored['text/plain'] = '';
    assert.equal(dragDrop.read(transfer, 'application/example'), null);
    assert.equal(dragDrop.read(null, 'application/example'), null);

    dragDrop.state(firstRoot).payload = {id: 1};
    assert.equal(dragDrop.state(secondRoot).payload, null);
    dragDrop.clear(firstRoot);
    assert.equal(dragDrop.state(firstRoot).payload, null);
});
