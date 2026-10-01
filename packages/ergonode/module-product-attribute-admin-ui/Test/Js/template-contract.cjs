'use strict';

const fs = require('node:fs');
const path = require('node:path');

function read(moduleRoot, relativePath) {
    return fs.readFileSync(path.join(moduleRoot, relativePath), 'utf8');
}

function readMappingTemplate(moduleRoot, entity) {
    const coreAdminUiRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
    const sourceStateActions = read(
        coreAdminUiRoot,
        'view/adminhtml/templates/entity/source-state-actions.phtml'
    );
    const mappingActions = read(
        coreAdminUiRoot,
        'view/adminhtml/templates/mapping/actions.phtml'
    );
    const mappingInit = read(
        coreAdminUiRoot,
        'view/adminhtml/templates/mapping/init.phtml'
    );
    const dropZone = read(
        coreAdminUiRoot,
        'view/adminhtml/templates/mapping/drop-zone.phtml'
    );
    const renderDropZone = (kind) => dropZone.replace(
        /<\?= \$escaper->escapeHtmlAttr\(\$kind\) \?>/g,
        kind
    );

    return read(coreAdminUiRoot, `view/adminhtml/templates/${entity}/mapping.phtml`)
        .replace(/<\?= \/\* @noEscape \*\/ \$sourceStateActionsHtml \?>/g, sourceStateActions)
        .replace(/<\?= \/\* @noEscape \*\/ \$mappingActionsHtml \?>/g, mappingActions)
        .replace(/<\?= \/\* @noEscape \*\/ \$disableDropZoneHtml \?>/g, renderDropZone('disable'))
        .replace(/<\?= \/\* @noEscape \*\/ \$createDropZoneHtml \?>/g, renderDropZone('create'))
        .replace(/<\?= \/\* @noEscape \*\/ \$mappingInitHtml \?>/g, mappingInit);
}

function readPairTemplate(moduleRoot, entity) {
    const coreAdminUiRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
    const templates = [
        `view/adminhtml/templates/${entity}/pair.phtml`,
        'view/adminhtml/templates/mapping/pair.phtml',
        'view/adminhtml/templates/mapping/pair-slot.phtml',
        `view/adminhtml/templates/${entity}/empty-slot.phtml`
    ];

    if (entity === 'attribute') {
        templates.push('view/adminhtml/templates/attribute/option-progress.phtml');
    }

    return templates.map((template) => read(coreAdminUiRoot, template)).join('\n');
}

module.exports = {readMappingTemplate, readPairTemplate};
