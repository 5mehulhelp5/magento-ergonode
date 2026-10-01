const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const coreRoot = path.resolve(__dirname, '../..');

const read = (file) => fs.readFileSync(file, 'utf8');

const views = [
    {
        section: 'CATEGORIES',
        template: path.join(modulePath('CategoryAdminUi'), 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
    },
    {
        section: 'LANGUAGES',
        template: path.join(modulePath('LanguageAdminUi'), 'view/adminhtml/templates/language/mapping.phtml'),
    },
    {
        section: 'NAVIGATION_SECTION',
        template: path.join(
            modulePath('TemplateAdminUi'),
            'view/adminhtml/templates/template/index.phtml'
        ),
        block: path.join(
            modulePath('TemplateAdminUi'),
            'Block/Adminhtml/Template/Index.php'
        ),
    },
];

test('all Ergonode mapping views render the shared navigation with their current section', () => {
    for (const view of views) {
        const template = read(view.template);

        assert.match(template, /SectionNavigation::class/);
        if (view.section === 'NAVIGATION_SECTION') {
            assert.match(template, /Index::NAVIGATION_SECTION/);
            assert.match(read(view.block), /NAVIGATION_SECTION = 'templates'/);
        } else if (view.block) {
            assert.match(template, /\$block->getCurrentNavigationSection\(\)/);
            assert.match(read(view.block), new RegExp(`SectionNavigation::SECTION_${view.section}`));
        } else if (view.section === 'CATEGORIES') {
            assert.match(template, /CategoryTreeNavigationItemProvider::SECTION_CODE/);
        } else {
            assert.match(template, new RegExp(`SectionNavigation::SECTION_${view.section}`));
        }
        assert.match(template, /\$sectionNavigation->toHtml\(\)/);
    }
});

test('section navigation groups category and attribute destinations and retains current standalone sections', () => {
    const block = read(path.join(coreRoot, 'Block/Adminhtml/SectionNavigation.php'));
    const categoryTreeProvider = read(path.join(
        modulePath('CategoryAdminUi'),
        'Model/Navigation/CategoryTreeNavigationItemProvider.php'
    ));
    const categoryGroupProvider = read(path.join(
        modulePath('CategoryAdminUi'),
        'Model/Navigation/CategoryNavigationGroupProvider.php'
    ));
    const moduleDi = read(path.join(coreRoot, 'etc/di.xml'));
    const categoryDi = read(path.join(modulePath('CategoryAdminUi'), 'etc/di.xml'));
    const languageDi = read(path.join(modulePath('LanguageAdminUi'), 'etc/di.xml'));
    const template = read(path.join(coreRoot, 'view/adminhtml/templates/section-navigation.phtml'));
    const styles = read(path.join(coreRoot, 'view/adminhtml/web/css/ergonode-workspace.css'));
    const attributesIcon = read(path.join(coreRoot, 'view/adminhtml/web/images/attribution-pen.svg'));
    const categoriesIcon = read(path.join(coreRoot, 'view/adminhtml/web/images/list-tree.svg'));
    const languagesIcon = read(path.join(coreRoot, 'view/adminhtml/web/images/english.svg'));
    const optionsIcon = read(path.join(coreRoot, 'view/adminhtml/web/images/circle-ellipsis-vertical.svg'));
    const readinessIcon = read(path.join(coreRoot, 'view/adminhtml/web/images/complete-missing.svg'));

    assert.match(moduleDi, /ergonode\/readiness\/index/);
    assert.match(categoryTreeProvider, /ergonode\/category_tree_mapping\/edit/);
    assert.match(languageDi, /ergonode\/language\/index/);
    assert.doesNotMatch(block, /ergonode\/(?:attribute|category_tree_mapping|language|option|template)\//);
    assert.doesNotMatch(moduleDi, /Ergonode_(?:AttributeConsumer|CategoryConsumer|Language|TemplateConsumer)::/);

    assert.match(block, /\$sectionCode === \$currentSection/);
    assert.match(block, /getAuthorization\(\)->isAllowed/);
    assert.match(block, /private array \$sections/);
    assert.match(languageDi, /english/);
    assert.match(categoryTreeProvider, /'icon' => 'list-tree'/);
    assert.match(categoryTreeProvider, /ergonode\/category_tree_mapping\/edit/);
    assert.match(categoryTreeProvider, /category_tree_mapping/);
    assert.match(categoryDi, /navigationGroupProviders/);
    assert.match(categoryDi, /CategoryNavigationGroupProvider/);
    assert.match(categoryDi, /itemProviders/);
    assert.match(categoryDi, /CategoryTreeNavigationItemProvider/);
    assert.match(moduleDi, /complete-missing/);
    assert.match(template, /class="veui-section-navigation"/);
    assert.match(template, /veui-section-navigation-icon-/);
    assert.match(block, /function getNavigationGroups\(\)/);
    assert.match(block, /function getAttributeNavigationItems\(\)/);
    assert.match(block, /function getAttributeSwitcherLabel\(\): Phrase/);
    assert.match(block, /NavigationGroupProviderInterface/);
    assert.match(block, /navigationGroupProviders/);
    assert.match(block, /\$provider->getGroup\(\$currentSection\)/);
    assert.doesNotMatch(block, /Categories|Tree|CategoryNavigation|SECTION_CATEGORIES|category_tree/);
    assert.match(categoryGroupProvider, /CategoryNavigationItemProviderInterface/);
    assert.match(categoryGroupProvider, /CategoryTreeNavigationItemProvider::SECTION_CODE/);
    assert.match(categoryGroupProvider, /'label' => __\('Categories'\)/);
    assert.match(categoryGroupProvider, /'options_label' => __\('Category options'\)/);
    assert.match(block, /SECTION_ATTRIBUTES, self::SECTION_PRODUCTS/);
    assert.doesNotMatch(block, /SECTION_TEMPLATES|ergonode\/template/);
    assert.match(block, /function isAttributeSwitcher\(\)/);
    assert.match(block, /function getAttributeUrl\(\)/);
    assert.match(template, /veui-split-button-main/);
    assert.match(template, /class="veui-split-button-options"/);
    assert.doesNotMatch(template, /Categories|Tree|category/);
    assert.doesNotMatch(template, /__\('Attributes'\)/);
    assert.doesNotMatch(template, /veui-section-navigation-icon-circle-ellipsis-vertical/);
    assert.match(block, /in_array\(\$sectionCode, \$groupedSections, true\)/);
    assert.match(template, /<summary class="veui-split-button-toggle"[\s\S]*?role="button"/);
    assert.match(template, /aria-current="page" aria-disabled="true"/);
    assert.match(template, /getNavigationEntries\(\)/);
    assert.match(styles, /\.veui-split-button-menu\s*\{/);
    assert.doesNotMatch(template, /veui-(?:attribute|section-navigation)-switcher/);
    assert.doesNotMatch(styles, /\.veui-(?:attribute|section-navigation)-switcher/);
    assert.doesNotMatch(styles, /\.veui-template-switcher/);
    assert.match(styles, /\.veui-split-button\s*\{[\s\S]*?border:\s*1px solid var\(--veui-border\);/);
    assert.match(styles, /\.veui-toolbar \.veui-split-button > \.veui-split-button-main\s*\{/);
    assert.match(styles, /\.veui-split-button:focus-within\s*\{/);
    assert.match(styles, /\.veui-split-button-options > summary\s*\{/);
    assert.doesNotMatch(template, /class="veui-workspace veui-section-navigation-workspace"/);
    assert.match(styles, /\.veui-section-navigation\s*\{[\s\S]*?margin-right:\s*auto;/);
    assert.match(styles, /\.veui-section-navigation-icon\s*\{[\s\S]*?height:\s*14px;[\s\S]*?width:\s*14px;/);
    assert.match(styles, /mask:\s*url\('\.\.\/images\/attribution-pen\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(styles, /mask:\s*url\('\.\.\/images\/circle-ellipsis-vertical\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(styles, /mask:\s*url\('\.\.\/images\/english\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(styles, /mask:\s*url\('\.\.\/images\/list-tree\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(styles, /mask:\s*url\('\.\.\/images\/complete-missing\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(attributesIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(categoriesIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(languagesIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(optionsIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(readinessIcon, /<svg[^>]*viewBox="0 0 24 24"/);
});

test('optional navigation destinations are registered only by their owning modules', () => {
    const ownerConfigs = new Map([
        ['LanguageAdminUi', ['languages']],
        ['TemplateAdminUi', ['templates']],
    ]);
    const coreDi = read(path.join(coreRoot, 'etc/di.xml'));

    ownerConfigs.forEach((sections, moduleName) => {
        const ownerDi = read(path.join(modulePath(moduleName), 'etc/di.xml'));
        const navigationConfig = [...ownerDi.matchAll(/<type name="([^"]+)">([\s\S]*?)<\/type>/g)]
            .find((match) => match[1] === 'Ergonode\\CoreAdminUi\\Block\\Adminhtml\\SectionNavigation')?.[2] || '';
        const registeredSections = [...navigationConfig.matchAll(/<argument name="([^"]+)" xsi:type="array">([\s\S]*?)<\/argument>/g)]
            .find((match) => match[1] === 'sections')?.[2] || '';

        sections.forEach((section) => {
            assert.match(registeredSections, new RegExp(`<item name="${section}" xsi:type="array">`), moduleName);
            assert.doesNotMatch(coreDi, new RegExp(`<item name="${section}"`));
        });
    });

    const categoryDi = read(path.join(modulePath('CategoryAdminUi'), 'etc/di.xml'));

    assert.match(categoryDi, /<item name="categories" xsi:type="object">/);
    assert.doesNotMatch(coreDi, /<item name="categories"/);
});

test('category mapping layout mounts the current shared workspace view', () => {
    const layout = read(path.join(
        modulePath('CategoryAdminUi'),
        'view/adminhtml/layout/ergonode_category_tree_mapping_edit.xml'
    ));

    assert.match(layout, /Ergonode\\CategoryAdminUi\\Block\\Adminhtml\\CategoryTreeMapping\\Index/);
    assert.match(layout, /Ergonode_CategoryAdminUi::category-tree-mapping\/index\.phtml/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/ergonode-workspace\.css/);
});

test('category mapping renders navigation inside the shared workspace toolbar', () => {
    const template = read(path.join(
        modulePath('CategoryAdminUi'),
        'view/adminhtml/templates/category-tree-mapping/index.phtml'
    ));

    assert.match(template, /CategoryTreeNavigationItemProvider::SECTION_CODE/);
    assert.doesNotMatch(template, /'category_switcher' => true/);
    assert.doesNotMatch(template, /'standalone' => true/);
    assert.match(template, /class="veui-toolbar veui-viewbar vec-toolbar"[\s\S]*\$sectionNavigation->toHtml\(\)/);
});
