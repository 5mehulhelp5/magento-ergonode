'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');

test('language refresh error links to the Ergonode connection configuration', () => {
    const block = fs.readFileSync(
        path.join(moduleRoot, 'Block/Adminhtml/Language/Mapping.php'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/language-mapping.js'),
        'utf8'
    );

    assert.match(
        block,
        /'configuration' => \$this->getUrl\([\s\S]*?'section' => 'ergonode_connection'/
    );
    assert.match(
        script,
        /label: \$t\('Przejdź do konfiguracji'\)[\s\S]*?config\.urls\.configuration/
    );
});

test('language module does not expose an empty system configuration section', () => {
    assert.equal(fs.existsSync(path.join(moduleRoot, 'etc/adminhtml/system.xml')), false);
});
