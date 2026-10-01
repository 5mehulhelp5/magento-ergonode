'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = modulePath('Core');
const read = (root, relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('readiness is an ACL-protected Ergonode admin destination', () => {
    const acl = read(coreRoot, 'etc/acl.xml');
    const menu = read(moduleRoot, 'etc/adminhtml/menu.xml');
    const controller = read(moduleRoot, 'Controller/Adminhtml/Readiness/Index.php');
    const navigation = read(moduleRoot, 'Block/Adminhtml/SectionNavigation.php');
    const moduleDi = read(moduleRoot, 'etc/di.xml');

    assert.match(acl, /Ergonode_Core::readiness/);
    assert.match(menu, /id="Ergonode_Core::readiness"[\s\S]*action="ergonode\/readiness\/index"/);
    assert.match(controller, /ADMIN_RESOURCE = 'Ergonode_Core::readiness'/);
    assert.match(controller, /resultFactory->create\(ResultFactory::TYPE_PAGE\)/);
    assert.match(navigation, /SECTION_READINESS = 'readiness'/);
    assert.match(moduleDi, /<item name="readiness" xsi:type="array">[\s\S]*ergonode\/readiness\/index/);
});

test('readiness view renders production report states and remediation actions', () => {
    const layout = read(moduleRoot, 'view/adminhtml/layout/ergonode_readiness_index.xml');
    const template = read(moduleRoot, 'view/adminhtml/templates/readiness.phtml');
    const styles = read(moduleRoot, 'view/adminhtml/web/css/readiness.css');
    const checks = read(moduleRoot, 'ViewModel/ReadinessChecks.php');

    assert.match(layout, /Ergonode_CoreAdminUi::css\/ergonode-workspace\.css/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/readiness\.css/);
    assert.match(layout, /Ergonode\\CoreAdminUi\\ViewModel\\ReadinessChecks/);
    assert.match(template, /SectionNavigation::SECTION_READINESS/);
    assert.match(template, /getIssuesByDomain\(\)/);
    assert.match(template, /getFlows\(\$block->getIssuesByDomain\(\)\)/);
    assert.match(template, /ver-readiness-flow/);
    assert.match(template, /getStatusLabel\(\$check\['status'\]\)/);
    assert.match(template, /getRemediationUrl\(\$issue\)/);
    assert.match(template, /class="veui-button ver-readiness-action"/);
    assert.doesNotMatch(template, /All checks passed/);
    const moduleDi = read(moduleRoot, 'etc/di.xml');

    assert.match(moduleDi, /connection\.graphql_url_missing/);
    assert.doesNotMatch(checks, /categories\.|attributes\.|templates\.|languages\./);
    assert.match(moduleDi, /connection\.unavailable/);
    assert.match(moduleDi, /connection\.api_key_missing/);
    assert.doesNotMatch(moduleDi, /products\.write_api_key_missing/);
    assert.match(checks, /Receive data from Ergonode[\s\S]*Send data to Ergonode/);
    assert.doesNotMatch(checks, /'domains' => \[/);
    assert.match(checks, /STATUS_REQUIRES_LOGIN/);
    assert.doesNotMatch(checks, /products\.status_mapping_missing/);
    assert.match(checks, /STATUS_NOT_CHECKED/);
    assert.match(styles, /\.ver-readiness-summary\.is-blocked/);
    assert.match(styles, /\.ver-readiness-check\.is-warning/);
    assert.match(styles, /\.ver-readiness-flow\.is-write/);
    assert.match(styles, /\.ver-readiness-flow-kind/);
    assert.match(styles, /\.ver-readiness-grid[\s\S]*repeat\(auto-fit, minmax\(min\(100%, 320px\), 1fr\)\)/);
    assert.match(styles, /\.ver-readiness-check-status/);
    assert.match(styles, /max-width: 1800px/);
    assert.match(styles, /\.ver-readiness-issues > li[\s\S]*var\(--veui-warning-soft\)/);
});

test('optional readiness domains are contributed only by their owning modules', () => {
    const ownerConfigs = new Map([
        ['CategoryConsumerAdminUi', 'categories'],
        ['LanguageAdminUi', 'languages'],
        ['TemplateAdminUi', 'templates'],
    ]);
    const coreDi = read(moduleRoot, 'etc/di.xml');

    ownerConfigs.forEach((domain, moduleName) => {
        const ownerDi = read(modulePath(moduleName), 'etc/di.xml');

        const typeConfigs = [...ownerDi.matchAll(/<type name="([^"]+)">([\s\S]*?)<\/type>/g)];
        const readiness = typeConfigs.find((match) => match[1] === 'Ergonode\\CoreAdminUi\\Block\\Adminhtml\\Readiness')?.[2] || '';
        const checks = typeConfigs.find((match) => match[1] === 'Ergonode\\CoreAdminUi\\ViewModel\\ReadinessChecks')?.[2] || '';

        [
            [readiness, 'domainLabels', 'string'],
            [readiness, 'remediationRoutes', 'string'],
            [checks, 'definitions', 'array'],
            [checks, 'domainSortOrders', 'number'],
        ].forEach(([config, argument, itemType]) => {
            const argumentConfig = [...config.matchAll(/<argument name="([^"]+)" xsi:type="array">([\s\S]*?)<\/argument>/g)]
                .find((match) => match[1] === argument)?.[2] || '';

            assert.match(argumentConfig, new RegExp(`<item name="${domain}" xsi:type="${itemType}">`), `${moduleName}: ${argument}`);
        });
        assert.doesNotMatch(coreDi, new RegExp(`<item name="${domain}"`));
    });
});

test('readiness Storybook covers playground, states, mouse and keyboard', () => {
    const story = read(moduleRoot, 'Test/Storybook/Readiness.stories.js');

    assert.match(story, /readiness\.css/);
    assert.match(story, /const flows/);
    assert.match(story, /Receive data from Ergonode[\s\S]*Send data to Ergonode/);
    assert.match(story, /domains: \['connection', 'languages', 'categories'\]/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const AkcjaMysza/);
    assert.match(story, /userEvent\.click/);
    assert.match(story, /export const AkcjaKlawiatura/);
    assert.match(story, /userEvent\.tab/);
    assert.match(story, /messages\.map/);
    assert.match(story, /Connect at least one active Ergonode category tree/);
    assert.doesNotMatch(story, /All checks passed/);
});
