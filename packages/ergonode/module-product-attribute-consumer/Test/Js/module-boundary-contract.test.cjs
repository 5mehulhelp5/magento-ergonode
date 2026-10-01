'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const di = fs.readFileSync(path.join(moduleRoot, 'etc/di.xml'), 'utf8');

test('product attribute consumer owns the executable attribute synchronization operation', () => {
    assert.match(
        di,
        /<item name="attributeStream"[^>]*>[^<]*ProductAttributeConsumer\\Model\\Import\\AttributeSynchronizationOperation\\Proxy<\/item>/
    );
});
