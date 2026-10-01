const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

test('read-only extension contributes native credential paths to both environments', () => {
    const xml = fs.readFileSync(path.join(__dirname, '../../etc/adminhtml/system.xml'), 'utf8');
    assert.match(xml, /<group id="test"/);
    assert.match(xml, /<group id="production"/);
    assert.equal((xml.match(/<group id="consumer"/g) || []).length, 2);
    assert.equal((xml.match(/<field id="api_key"/g) || []).length, 2);
    assert.equal((xml.match(/<field id="ergonode_connection\/general\/mode">read<\/field>/g) || []).length, 2);
    assert.doesNotMatch(xml, /<config_path>|<field id="status"|<field id="url"|requests_per_minute/);
});
