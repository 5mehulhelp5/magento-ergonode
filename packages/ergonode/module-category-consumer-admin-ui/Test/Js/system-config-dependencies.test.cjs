'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const {moduleRoot: findModuleRoot} = require('./project-paths.cjs');
const systemXml = fs.readFileSync(
    path.join(moduleRoot, 'etc/adminhtml/system.xml'),
    'utf8'
);
const defaultConfigXml = fs.readFileSync(
    path.join(findModuleRoot('CategoryConsumer', 'module-category-consumer'), 'etc/config.xml'),
    'utf8'
);
const crontabXml = fs.readFileSync(
    path.join(findModuleRoot('CategoryConsumer', 'module-category-consumer'), 'etc/crontab.xml'),
    'utf8'
);

test('category structure stream exposes an independently disabled cron', () => {
    assert.match(systemXml, /<section id="ergonode_categories"/);
    assert.match(systemXml, /<group id="cron"/);
    assert.match(systemXml, /<field id="status"/);
    assert.match(systemXml, /<field id="schedule"/);
    assert.match(systemXml, /Ergonode\\CoreAdminUi\\Model\\Config\\Backend\\CronSchedule/);
    assert.match(
        systemXml,
        /<field id="schedule"[\s\S]*?<field id="status">1<\/field>/
    );
    assert.match(defaultConfigXml, /<cron>[\s\S]*?<status>0<\/status>/);
    assert.match(defaultConfigXml, /<schedule>\*\/20 \* \* \* \*<\/schedule>/);
    assert.match(
        crontabXml,
        /<config_path>ergonode_categories\/cron\/schedule<\/config_path>/
    );
});
