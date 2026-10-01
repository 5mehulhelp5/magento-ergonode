const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleRoot} = require('./module-paths.cjs');
function files(dir) {
    return fs.readdirSync(dir, {withFileTypes:true}).flatMap(entry => {
        const file = path.join(dir, entry.name);
        return entry.isDirectory() ? (entry.name === 'Test' ? [] : files(file)) : [file];
    });
}
test('neutral screen and structure presentation load without Consumer or Publisher code', () => {
    for (const module of ['Template', 'TemplateAdminUi', 'TemplateAttribute', 'TemplateAttributeAdminUi']) {
        for (const file of files(moduleRoot(module)).filter(file => /\.(php|js)$/.test(file))) {
            const source = fs.readFileSync(file, 'utf8');
            assert.doesNotMatch(source, /(?:use|namespace) Ergonode\\Template(?:Consumer|Publisher)/, file);
            assert.doesNotMatch(source, /Ergonode_Template(?:Consumer|Publisher)(?:AdminUi)?\/js\//, file);
        }
    }
});
test('Consumer and Publisher attach independently to the neutral workspace', () => {
    for (const [module, forbidden] of [['TemplateConsumerAdminUi', 'TemplatePublisher'], ['TemplatePublisherAdminUi', 'TemplateConsumer']]) {
        const composer = JSON.parse(fs.readFileSync(path.join(moduleRoot(module), 'composer.json')));
        assert.ok(composer.require['ergonode/module-template-admin-ui']);
        for (const file of files(moduleRoot(module)).filter(file => /\.(php|js|xml)$/.test(file))) {
            const source = fs.readFileSync(file, 'utf8');
            assert.ok(!source.includes('Ergonode\\' + forbidden + '\\'), file);
            assert.ok(!source.includes('Ergonode_' + forbidden + 'AdminUi'), file);
        }
    }
});
