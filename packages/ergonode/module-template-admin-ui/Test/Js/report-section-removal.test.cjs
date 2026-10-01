'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleRoot} = require('./module-paths.cjs');

function read(relativePath) {
    return fs.readFileSync(path.resolve(__dirname, relativePath), 'utf8');
}

function readConsumer(relativePath) {
    return fs.readFileSync(path.join(moduleRoot('TemplateConsumerAdminUi'), relativePath), 'utf8');
}

test('template admin view exposes sync without restoring the removed report section', () => {
    const controller = path.join(moduleRoot('TemplateConsumerAdminUi'), 'Controller/Adminhtml/Template/Sync.php');
    const template = read('../../view/adminhtml/templates/template/index.phtml');
    const script = readConsumer('view/adminhtml/web/js/template-consumer.js');
    const styles = read('../../view/adminhtml/web/css/template-admin.css');

    assert.equal(fs.existsSync(controller), true);
    assert.doesNotMatch(template, /vet-report|data-role="report-|>Raport</);
    assert.doesNotMatch(template, /data-role="dry-run-template"|data-role="remove-obsolete"|Dry-run|Usuń przestarzałe/);
    assert.match(readConsumer('view/adminhtml/templates/template/actions.phtml'), /'primary_role' => 'sync-templates'/);
    assert.match(script, /synchronizeTemplates/);
    assert.doesNotMatch(script, /\$report|renderReport|buildReportEntry|clearReport|response\.(?:stats|report)/);
    assert.doesNotMatch(script, /\$dryRunButton|\$removeObsolete|runSync\(/);
    assert.doesNotMatch(styles, /\.vet-report/);
    assert.doesNotMatch(styles, /\.vet-icon-preview|\.vet-switch/);
});
