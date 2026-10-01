'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {modulePath} = require('./module-paths.cjs');

const systemXml = fs.readFileSync(
    path.resolve(__dirname, '../../etc/adminhtml/system.xml'),
    'utf8'
);

function groupConfiguration(groupId) {
    const start = systemXml.indexOf(`<group id="${groupId}"`);
    assert.notEqual(start, -1, `Missing group ${groupId}`);

    const nextGroup = systemXml.indexOf('\n            <group id="', start + 1);
    const end = nextGroup === -1 ? systemXml.indexOf('\n        </section>', start) : nextGroup;
    assert.notEqual(end, -1, `Missing end of group ${groupId}`);

    return systemXml.slice(start, end);
}

test('shared form exposes two environments and one operating mode without credential ownership', () => {
    const general = groupConfiguration('general');
    assert.match(general, /<field id="environment"/);
    assert.match(general, /<field id="mode"/);
    for (const environment of ['test', 'production']) {
        const group = groupConfiguration(environment);
        assert.match(group, /<field id="url"/);
        assert.match(group, /<field id="requests_per_minute"/);
        assert.match(group, /OpenFieldset/);
        assert.ok(group.includes(`<field id="ergonode_connection/general/environment">${environment}</field>`));
    }
    assert.doesNotMatch(systemXml, /<config_path>|read_operations|write_operations|api_key/);
});

test('global status gates selectors and every nested credential group', () => {
    const general = groupConfiguration('general');
    assert.equal((systemXml.match(/<label>Status<\/label>/g) || []).length, 1);
    assert.match(general, /<field id="enabled"/);
    assert.equal((general.match(/<field id="enabled">1<\/field>/g) || []).length, 2);
    for (const environment of ['test', 'production']) {
        const group = groupConfiguration(environment);
        assert.ok(group.includes('<field id="ergonode_connection/general/enabled">1</field>'));
        assert.ok(group.includes('<validate>required-entry validate-url</validate>'));
    }
    for (const moduleName of ['ConsumerAdminUi', 'PublisherAdminUi']) {
        const xml = fs.readFileSync(path.join(modulePath(moduleName), 'etc/adminhtml/system.xml'), 'utf8');
        for (const dependency of xml.match(/<depends>[\s\S]*?<\/depends>/g)) {
            assert.ok(dependency.includes('<field id="ergonode_connection/general/enabled">1</field>'));
        }
        for (const field of xml.match(/<field id="api_key"[\s\S]*?<\/field>/g)) {
            assert.ok(field.includes('<validate>required-entry</validate>'));
            assert.ok(field.includes('RequiredField</frontend_model>'));
        }
    }
});

test('connection test endpoint is POST-only and protected by Ergonode configuration ACL', () => {
    const controller = fs.readFileSync(
        path.resolve(__dirname, '../../Controller/Adminhtml/Connection/TestConnection.php'),
        'utf8'
    );
    const javascript = fs.readFileSync(
        path.resolve(__dirname, '../../view/adminhtml/web/js/test-connection.js'),
        'utf8'
    );

    assert.match(controller, /implements HttpPostActionInterface/);
    assert.match(controller, /ADMIN_RESOURCE = 'Ergonode_Core::config'/);
    assert.match(javascript, /\$\.widget\('ergonode\.testConnection'/);
    assert.match(javascript, /return \$\.ergonode\.testConnection/);
    assert.match(javascript, /type: 'POST'/);
    assert.match(javascript, /timeout: 60000/);
    assert.match(javascript, /form_key: window\.FORM_KEY/);
});

test('Magento mage-init export binds the connection button and submits its current fields', () => {
    const javascript = fs.readFileSync(
        path.resolve(__dirname, '../../view/adminhtml/web/js/test-connection.js'),
        'utf8'
    );
    const button = {
        handlers: {},
        prop(name, value) {
            this[name] = value;
        }
    };
    const result = {
        classes: new Set(),
        removeClass(names) {
            names.split(' ').forEach((name) => this.classes.delete(name));
            return this;
        },
        addClass(name) {
            this.classes.add(name);
            return this;
        },
        text(value) {
            this.value = value;
            return this;
        }
    };
    const fields = {
        '#ergonode_connection_test_consumer_connection_result': result,
        '#ergonode_connection_test_url': {val: () => 'https://tenant.ergonode.cloud'},
        '#ergonode_connection_test_consumer_api_key': {val: () => 'secret'}
    };
    let ajaxRequest;
    let exported;

    function jquery(selector) {
        return fields[selector];
    }

    jquery.widget = (qualifiedName, prototype) => {
        const [namespace, name] = qualifiedName.split('.');

        assert.ok(namespace);
        assert.ok(name, 'The widget name must use the namespace.widget format.');
        jquery[namespace] = jquery[namespace] || {};
        jquery[namespace][name] = (options, element) => {
            const instance = Object.assign({}, prototype);

            instance.options = Object.assign({}, prototype.options, options);
            instance.element = element;
            instance._on = (handlers) => {
                Object.entries(handlers).forEach(([event, handler]) => {
                    element.handlers[event] = handler.bind(instance);
                });
            };
            instance._create();

            return instance;
        };
    };
    jquery.ajax = (request) => {
        ajaxRequest = request;

        return {
            done(callback) {
                callback({success: true});
                return this;
            },
            fail() {
                return this;
            },
            always(callback) {
                callback();
                return this;
            }
        };
    };

    vm.runInNewContext(javascript, {
        define: (dependencies, factory) => {
            exported = factory(jquery, (value) => value, () => {});
        },
        window: {FORM_KEY: 'form-key'}
    });

    exported({
        url: '/ergonode/connection/testConnection',
        elementId: 'ergonode_connection_test_consumer_connection',
        mode: 'read',
        environment: 'test',
        urlFieldId: 'ergonode_connection_test_url',
        apiKeyFieldId: 'ergonode_connection_test_consumer_api_key'
    }, button);
    button.handlers.click();

    assert.equal(ajaxRequest.url, '/ergonode/connection/testConnection');
    assert.equal(ajaxRequest.type, 'POST');
    assert.equal(ajaxRequest.data.form_key, 'form-key');
    assert.equal(ajaxRequest.data.mode, 'read');
    assert.equal(ajaxRequest.data.environment, 'test');
    assert.equal(ajaxRequest.data.ergonode_url, 'https://tenant.ergonode.cloud');
    assert.equal(ajaxRequest.data.api_key, 'secret');
    assert.equal(button.disabled, false);
    assert.equal(result.value, 'Connection successful.');
    assert.equal(result.classes.has('success'), true);
});

test('all required Ergonode system fields render the required marker before submit', () => {
    const requiredRenderer = 'Ergonode\\CoreAdminUi\\Block\\Adminhtml\\System\\Config\\RequiredField';
    const moduleNames = [
        'CoreAdminUi',
        'PublisherAdminUi'
    ];
    if (fs.existsSync(modulePath('CategoryAttributeConsumerAdminUi'))) {
        moduleNames.push('CategoryAttributeConsumerAdminUi');
    }

    for (const moduleName of moduleNames) {
        const moduleSystemXml = fs.readFileSync(
            path.join(modulePath(moduleName), 'etc/adminhtml/system.xml'),
            'utf8'
        );
        const fields = moduleSystemXml.match(
            /^ {16}<field\b[\s\S]*?^ {16}<\/field>/gm
        ) || [];
        const requiredFields = fields.filter((field) => /<validate>[^<]*\brequired-entry\b/.test(field));

        for (const field of requiredFields) {
            assert.match(field, new RegExp(`<frontend_model>${requiredRenderer.replace(/\\/g, '\\\\')}<\\/frontend_model>`));
        }
    }

    const renderer = fs.readFileSync(
        path.resolve(__dirname, '../../Block/Adminhtml/System/Config/RequiredField.php'),
        'utf8'
    );
    assert.match(renderer, /class RequiredField extends Field/);
    assert.match(renderer, /class="required _required"/);
});
