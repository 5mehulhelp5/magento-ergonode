const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const root = path.resolve(__dirname, '../..');
const coreRoot = modulePath('Core');

test('internal request limit belongs to each environment, defaults to zero and has server validation', () => {
    const system = fs.readFileSync(path.join(root, 'etc/adminhtml/system.xml'), 'utf8');
    const config = fs.readFileSync(path.join(coreRoot, 'etc/config.xml'), 'utf8');
    const di = fs.readFileSync(path.join(coreRoot, 'etc/di.xml'), 'utf8');
    const field = system.match(/<field id="requests_per_minute"[\s\S]*?<\/field>/)[0];
    assert.match(field, /showInDefault="1" showInWebsite="0" showInStore="0"/);
    assert.match(field, /required-entry validate-digits/);
    assert.match(field, /<backend_model>Ergonode\\CoreAdminUi\\Model\\Config\\Backend\\RequestsPerMinute<\/backend_model>/);
    assert.equal((config.match(/<requests_per_minute>0<\/requests_per_minute>/g) || []).length, 2);
    assert.equal((system.match(/<field id="requests_per_minute"/g) || []).length, 2);
    assert.doesNotMatch(di, /name="requestsPerMinute"/);
});
