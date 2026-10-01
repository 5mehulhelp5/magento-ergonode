"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const {modulePath} = require("./module-paths.cjs");

const adminUiRoot = path.resolve(__dirname, "../..");
const categoryAdminUiRoot = modulePath("CategoryAdminUi");
const templateAdminUiRoot = modulePath("TemplateAdminUi");

function read(root, relativePath) {
    return fs.readFileSync(path.join(root, relativePath), "utf8");
}

test("shared Ergonode workspace owns the common layout and component dimensions", () => {
    const styles = read(
        adminUiRoot,
        "view/adminhtml/web/css/ergonode-workspace.css",
    );
    const normalizedStyles = styles.replace(/\s+/g, " ");

    assert.match(styles, /\.veui-workspace\s*\{[\s\S]*?--veui-bg:/);
    assert.match(styles, /\.veui-toolbar\s*\{[\s\S]*?max-width:\s*1800px;/);
    assert.ok(
        normalizedStyles.includes(
            "grid-template-columns: minmax(280px, 1.06fr) minmax(500px, 1.46fr) minmax( 280px, 1.06fr );",
        ),
    );
    assert.match(styles, /@media \(max-width: 1180px\)[\s\S]*?min-width:\s*1088px;/);
    assert.doesNotMatch(styles, /@media \(max-width: 1280px\)[\s\S]*?min-width:\s*1270px;/);
    assert.match(
        styles,
        /\.veui-panel-head\s*\{[\s\S]*?height:\s*58px;[\s\S]*?padding:\s*13px 16px;/,
    );
    assert.match(
        styles,
        /\.veui-panel-title\s*\{[\s\S]*?font-size:\s*13px;[\s\S]*?font-weight:\s*800;/,
    );
    assert.match(styles, /\.veui-brand-ergonode\s*\{/);
    assert.match(styles, /\.veui-brand-magento\s*\{/);
});

test("shared messages are content-sized and centered in the viewbar", () => {
    const styles = read(
        adminUiRoot,
        "view/adminhtml/web/css/ergonode-workspace.css",
    );
    const attributeStyles = read(
        adminUiRoot,
        "view/adminhtml/web/css/attribute-mapping.css",
    );

    assert.match(
        styles,
        /\.veui-message\s*\{[\s\S]*?left:\s*50%;[\s\S]*?max-width:\s*min\(560px, calc\(100% - 48px\)\);[\s\S]*?padding:\s*16px 22px;[\s\S]*?pointer-events:\s*auto;[\s\S]*?top:\s*50px;[\s\S]*?transform:\s*translate\(-50%, -50%\);[\s\S]*?width:\s*fit-content;/,
    );
    assert.match(styles, /\.veui-message-close\s*\{[\s\S]*?cursor:\s*pointer;[\s\S]*?height:\s*24px;[\s\S]*?width:\s*24px;/);
    assert.match(styles, /\.veui-message-close:focus-visible/);
    assert.doesNotMatch(styles, /\.veui-workspace-viewbar \.veui-message\s*\{/);
    assert.doesNotMatch(attributeStyles, /full-view|is-full-view/);
});

test("workspace stays inside the content viewport and preserves the Magento footer", () => {
    const styles = read(
        adminUiRoot,
        "view/adminhtml/web/css/ergonode-workspace.css",
    );

    assert.match(
        styles,
        /body\.ergonode-attribute-index \.page-footer,[\s\S]*?display:\s*block;[\s\S]*?flex:\s*0 0 auto;/,
    );
    assert.match(styles, /body\.ergonode-category_option-index/);
    assert.match(styles, /body\.ergonode_product-product-index \.page-wrapper/);
    assert.match(styles, /body\.ergonode_product-product-index #anchor-content\.page-content/);
    assert.match(styles, /body\.ergonode_product-product-index \.page-footer/);
    assert.match(
        styles,
        /body\.ergonode-category-export \.page-footer \{[\s\S]*?display:\s*block;[\s\S]*?flex:\s*0 0 auto;/,
    );
    assert.match(
        styles,
        /body\.ergonode-category_tree_mapping-edit \.page-wrapper/,
    );
    assert.doesNotMatch(styles, /ergonode-category-tree-mapping-edit/);
    assert.match(styles, /\.veui-workspace\s*\{[\s\S]*?margin:\s*0 -2rem;/);
    assert.doesNotMatch(styles, /margin:\s*-2rem -3rem 0;/);
});

test("feature styles do not redefine shared workspace primitives", () => {
    const attributeStyles = read(
        adminUiRoot,
        "view/adminhtml/web/css/attribute-mapping.css",
    );
    const categoryStyles = read(
        categoryAdminUiRoot,
        "view/adminhtml/web/css/category-tree-mapping.css",
    );

    assert.doesNotMatch(attributeStyles, /^\.vea-shell\s*\{/m);
    assert.doesNotMatch(attributeStyles, /^\.vea-panel\s*\{/m);
    assert.doesNotMatch(attributeStyles, /^\.vea-panel-head\s*\{/m);
    const templateStyles = read(
        templateAdminUiRoot,
        "view/adminhtml/web/css/template-admin.css",
    );

    assert.doesNotMatch(templateStyles, /^\.vet-mapping-layout\s*\{/m);
    assert.doesNotMatch(templateStyles, /^\.vet-panel\s*\{/m);
    assert.doesNotMatch(templateStyles, /^\.vet-panel-head\s*\{/m);
    assert.doesNotMatch(templateStyles, /^\.vet-search\s*\{/m);
    assert.doesNotMatch(categoryStyles, /^\.vec-layout\s*\{/m);
    assert.doesNotMatch(categoryStyles, /^\.vec-panel\s*\{/m);
    assert.doesNotMatch(categoryStyles, /^\.vec-panel-head\s*\{/m);
    assert.doesNotMatch(categoryStyles, /^\.vec-search\s*\{/m);
});

test("feature styles consume the shared semantic palette", () => {
    const workspaceStyles = read(
        adminUiRoot,
        "view/adminhtml/web/css/ergonode-workspace.css",
    );
    const featureStyles = [
        read(adminUiRoot, "view/adminhtml/web/css/attribute-mapping.css"),
        read(categoryAdminUiRoot, "view/adminhtml/web/css/category-tree-mapping.css"),
        read(
            modulePath("LanguageAdminUi"),
            "view/adminhtml/web/css/language-mapping.css",
        ),
    ];
    featureStyles.push(
        read(templateAdminUiRoot, "view/adminhtml/web/css/template-admin.css"),
    );

    assert.match(workspaceStyles, /:root\s*\{[\s\S]*?--veui-surface: #ffffff/);
    assert.match(workspaceStyles, /--veui-border-strong: #cbd5e1/);
    assert.match(workspaceStyles, /--veui-success-soft: #ecfdf5/);
    featureStyles.forEach((styles) => {
        assert.doesNotMatch(
            styles,
            /#(?:ffffff|f8fafc|f1f5f9|cbd5e1|2563eb|ecfdf5|fff7ed|fecaca)\b/i,
        );
    });
});

test("all mapping layouts load shared styles before their feature styles", () => {
    const layouts = [
        read(
            categoryAdminUiRoot,
            "view/adminhtml/layout/ergonode_category_tree_mapping_edit.xml",
        ),
    ];
    layouts.push(
        read(
            templateAdminUiRoot,
            "view/adminhtml/layout/ergonode_template_index.xml",
        ),
    );

    layouts.forEach((layout) => {
        const sharedIndex = layout.indexOf(
            "Ergonode_CoreAdminUi::css/ergonode-workspace.css",
        );
        const featureIndex = [
            "attribute-mapping.css",
            "template-admin.css",
            "category-tree-mapping.css",
        ].map((stylesheet) => layout.indexOf(stylesheet)).find((index) => index >= 0);

        assert.ok(sharedIndex >= 0);
        assert.ok(featureIndex !== undefined && featureIndex > sharedIndex);
    });
});

test("template mapping uses shared components and central brand assets", () => {
    const template = read(
        templateAdminUiRoot,
        "view/adminhtml/templates/template/index.phtml",
    );

    assert.match(template, /class="veui-workspace vet-admin/);
    assert.match(template, /class="veui-layout vet-mapping-layout veui-autosave-region"/);
    assert.equal((template.match(/class="veui-panel /g) || []).length, 3);
    assert.match(
        template,
        /Ergonode_CoreAdminUi::images\/m2_configuration\.svg/,
    );
    assert.match(
        template,
        /Ergonode_CoreAdminUi::images\/magento-mark\.svg/,
    );
    assert.match(template, /class="veui-brand-mark veui-brand-ergonode"/);
    assert.match(template, /class="veui-brand-mark veui-brand-magento"/);
});
