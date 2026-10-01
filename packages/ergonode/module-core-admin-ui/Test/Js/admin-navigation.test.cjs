'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath, moduleDirectories} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');

function read(relativePath) {
    return fs.readFileSync(path.resolve(moduleRoot, relativePath), 'utf8');
}

test('Ergonode owns a grouped top-level admin menu with configuration and migrated links', () => {
    const menuXml = read('etc/adminhtml/menu.xml');

    assert.match(menuXml, /id="Ergonode_Core::main"[^>]*resource="Ergonode_Core::main"\/>/);
    assert.doesNotMatch(menuXml, /id="Ergonode_Core::main"[^>]*parent=/);
    assert.match(
        menuXml,
        /id="Ergonode_Core::export"[\s\S]*title="Export"[\s\S]*parent="Ergonode_Core::main"/
    );
    assert.match(
        menuXml,
        /id="Ergonode_Core::config"[\s\S]*parent="Ergonode_Core::system"[\s\S]*action="adminhtml\/system_config\/edit\/section\/ergonode_connection"/
    );
    assert.match(
        menuXml,
        /id="Ergonode_Core::categories"[^>]*title="Categories"[^>]*parent="Ergonode_Core::main"/
    );
    assert.match(
        menuXml,
        /id="Ergonode_Core::products"[^>]*title="Products"[^>]*parent="Ergonode_Core::main"/
    );

    const categoryMenu = fs.readFileSync(
        path.join(modulePath('CategoryAdminUi'), 'etc/adminhtml/menu.xml'),
        'utf8'
    );
    assert.match(
        categoryMenu,
        /title="Tree"[\s\S]*parent="Ergonode_Core::categories"/
    );
    const categoryAttributeMenu = fs.readFileSync(
        path.join(modulePath('CategoryAttributeAdminUi'), 'etc/adminhtml/menu.xml'),
        'utf8'
    );

    assert.match(
        categoryAttributeMenu,
        /title="Attributes"[\s\S]*parent="Ergonode_Core::categories"/
    );
    const standaloneMenus = [['LanguageAdminUi', 'system'], ['TemplateAdminUi', 'products']];
    standaloneMenus.forEach(([moduleName, parentId]) => {
        const childMenu = fs.readFileSync(
            path.join(modulePath(moduleName), 'etc/adminhtml/menu.xml'),
            'utf8'
        );

        assert.match(childMenu, new RegExp(`parent="Ergonode_Core::${parentId}"`));
    });
});

test('Ergonode owns a branded system configuration tab', () => {
    const systemXml = read('etc/adminhtml/system.xml');
    const styles = read('view/adminhtml/web/css/source/_module.less');

    assert.match(systemXml, /<tab id="ergonode"[^>]*class="ergonode-tab">/);
    assert.match(systemXml, /<section id="ergonode_connection"[\s\S]*?<tab>ergonode<\/tab>/);
    assert.match(styles, /\[id='menu-ergonode-core-main'\]/);
    assert.doesNotMatch(styles, /(?:#menu-ergonode-main|\[id='menu-ergonode-main'\])/);
    assert.match(styles, /\.ergonode-tab/);

    [
        'm2_menu_inactive.svg',
        'm2_menu_active.svg',
        'm2_configuration.svg'
    ].forEach((asset) => {
        assert.equal(
            fs.existsSync(path.join(moduleRoot, 'view/adminhtml/web/images', asset)),
            true,
            `${asset} should exist`
        );
    });
});

test('Ergonode configuration is split into domain-owned sections', () => {
    const sections = [
        ['CoreAdminUi', 'ergonode_connection'],
        ['CategoryConsumerAdminUi', 'ergonode_categories'],
        ['TemplateConsumerAdminUi', 'ergonode_templates'],
    ];

    sections.forEach(([moduleName, sectionId]) => {
        const systemXml = fs.readFileSync(
            path.join(modulePath(moduleName), 'etc/adminhtml/system.xml'),
            'utf8'
        );

        assert.match(systemXml, new RegExp(`<section id="${sectionId}"`));
        assert.match(systemXml, /<tab>ergonode<\/tab>/);
    });
});

test('domain crons use native configurable schedules', () => {
    const categoryCrontabXml = fs.readFileSync(
        path.join(modulePath('CategoryConsumer'), 'etc/crontab.xml'),
        'utf8'
    );

    assert.match(
        categoryCrontabXml,
        /<config_path>ergonode_categories\/cron\/schedule<\/config_path>/
    );
    const templateSystemXml = fs.readFileSync(
        path.join(modulePath('TemplateConsumerAdminUi'), 'etc/adminhtml/system.xml'),
        'utf8'
    );
    const templateCrontabXml = fs.readFileSync(
        path.join(modulePath('TemplateConsumer'), 'etc/crontab.xml'),
        'utf8'
    );

    assert.match(templateSystemXml, /<group id="import"/);
    assert.match(templateSystemXml, /<field id="cron_expr"/);
    assert.doesNotMatch(templateSystemXml, /<config_path>/);
    assert.match(
        templateCrontabXml,
        /<config_path>ergonode_templates\/cron\/cron_expr<\/config_path>/
    );
});

test('connection-dependent Ergonode cron jobs use the dedicated group', () => {
    const cronGroupsXml = fs.readFileSync(
        path.join(modulePath('Core'), 'etc/cron_groups.xml'),
        'utf8'
    );
    const crontabPaths = moduleDirectories()
        .map((root) => path.join(root, 'etc/crontab.xml'))
        .filter((crontabPath) => fs.existsSync(crontabPath));

    assert.match(cronGroupsXml, /<group id="ergonode">/);
    assert.match(cronGroupsXml, /<use_separate_process>1<\/use_separate_process>/);
    assert.notEqual(crontabPaths.length, 0);

    const independentCronModules = new Set([
        'CategoryAttributeHistory',
        'CategoryConsumerHistory',
        'ProductAttributeHistory'
    ]);

    crontabPaths.forEach((crontabPath) => {
        const crontabXml = fs.readFileSync(crontabPath, 'utf8');
        const groupIds = [...crontabXml.matchAll(/<group id="([^"]+)"/g)]
            .map((match) => match[1]);
        const moduleName = path.basename(path.dirname(path.dirname(crontabPath)));
        const expectedGroup = independentCronModules.has(moduleName) ? 'default' : 'ergonode';

        assert.deepEqual(groupIds, [expectedGroup], crontabPath);
    });
});
