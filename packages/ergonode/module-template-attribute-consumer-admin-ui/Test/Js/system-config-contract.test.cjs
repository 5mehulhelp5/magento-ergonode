'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const systemConfig = fs.readFileSync(
    path.resolve(__dirname, '../../etc/adminhtml/system.xml'),
    'utf8'
);

test('template attribute configuration enriches base import settings', () => {
    assert.match(systemConfig, /section id="ergonode_templates"/);
    assert.match(systemConfig, /group id="import"/);
    assert.match(systemConfig, /field id="sync_attributes"/);
    assert.match(
        systemConfig,
        /field id="sync_sections"[\s\S]*?<depends>[\s\S]*?<field id="sync_attributes">1<\/field>/
    );
    assert.doesNotMatch(systemConfig, /field id="create_attribute_sets"|field id="cron_expr"/);
});
