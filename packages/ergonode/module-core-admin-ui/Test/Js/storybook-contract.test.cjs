const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {projectRoot, modulePath} = require('./module-paths.cjs');

const backendRoot = projectRoot;
const categoryAttributeStoryPath = path.join(
    modulePath('CategoryAttributeConsumerAdminUi'),
    'Test/Storybook/CategoryAttributeMapping.stories.js'
);

function read(relativePath) {
    const moduleFile = relativePath.match(/^app\/code\/Ergonode\/CoreAdminUi\/(.*)$/);
    const file = moduleFile
        ? path.join(modulePath('CoreAdminUi'), moduleFile[1])
        : path.join(backendRoot, relativePath);

    return fs.readFileSync(file, 'utf8');
}

test('backend exposes a dedicated HTML Storybook for Ergonode Admin UI', () => {
    const packageJson = JSON.parse(read('package.json'));
    const main = read('dev/tools/ergonode-storybook/.storybook/main.js');
    const gitignore = read('.gitignore');
    const ddevConfig = read('.ddev/config.yaml');

    assert.match(packageJson.scripts['storybook:ergonode'], /storybook dev/);
    assert.match(packageJson.scripts['storybook:ergonode:build'], /storybook build/);
    assert.equal(packageJson.devDependencies['@storybook/html-vite'], '10.4.1');
    assert.match(main, /@storybook\/html-vite/);
    assert.match(main, /discoverModules\(backendRoot\)/);
    assert.match(main, /moduleAliases\(modules\)/);
    assert.match(main, /@storybook\/addon-a11y/);
    assert.match(main, /const allowedHosts = \['localhost', '127\.0\.0\.1', ddevHost\]/);
    assert.match(main, /clientPort: 6007/);
    assert.match(gitignore, /!dev\/tools\/ergonode-storybook\/\*\*/);
    assert.match(ddevConfig, /name: ergonode-storybook[\s\S]*?container_port: 6007[\s\S]*?https_port: 6007/);
});

test('preview consumes production CoreAdminUi styles instead of copied CSS', () => {
    const preview = read('dev/tools/ergonode-storybook/.storybook/preview.js');

    [
        'ergonode-workspace.css',
        'attribute-mapping.css',
        'ergonode-actions.css'
    ].forEach((stylesheet) => assert.match(preview, new RegExp(stylesheet.replace('.', '\\.'))));
    assert.doesNotMatch(preview, /\.vea-attribute-card\s*\{/);
    assert.match(preview, /isWorkspaceRoot/);
    assert.match(preview, /containsWorkspace/);
    assert.match(preview, /surface\.dataset\.storybookSurface = 'magento-admin'/);
});

test('stories can execute production AMD behavior with explicit dependencies', () => {
    const loader = read('dev/tools/ergonode-storybook/src/load-amd-module.js');
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/EntityItem.stories.js');

    assert.match(loader, /Object\.hasOwn\(dependencies, dependency\)/);
    assert.match(loader, /Function\('define'/);
    assert.match(story, /buttons\.js\?raw/);
    assert.match(story, /drag-drop\.js\?raw/);
    assert.match(story, /workspace\.js\?raw/);
    assert.match(story, /loadAmdModule/);
    assert.match(story, /play: async/);
});

test('section navigation has playground, state matrix and keyboard coverage', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/SectionNavigation.stories.js');
    const navigation = read('dev/tools/ergonode-storybook/src/section-navigation.js');
    const styles = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css');

    assert.match(story, /export const Playground/);
    assert.match(story, /export const BiezacyWidok/);
    assert.match(story, /export const OgraniczoneUprawnienia/);
    assert.match(story, /export const KategorieBezOpcji/);
    assert.match(story, /categoryOptionsAvailable: false/);
    assert.match(story, /export const KategorieZMenuOpcji/);
    assert.match(story, /categoryOptionsAvailable: true/);
    assert.match(story, /export const AtrybutyZMenuOpcji/);
    assert.doesNotMatch(navigation, /code !== currentSection/);
    assert.match(navigation, /setDestination\(link, `#\$\{code\}`, currentSection === code\)/);
    assert.match(story, /export const AktywnyProduct/);
    assert.match(navigation, /allowedSections\.includes\(code\)/);
    assert.match(story, /userEvent\.hover/);
    assert.match(story, /userEvent\.tab/);
    assert.match(styles, /\.veui-section-navigation\s*\{/);
});

test('shared message story covers the production component and both dismissal inputs', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/Message.stories.js');

    assert.match(story, /messages\.js\?raw/);
    assert.match(story, /loadAmdModule/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const ZamykanieMysza/);
    assert.match(story, /userEvent\.click/);
    assert.match(story, /export const ZamykanieKlawiatura/);
    assert.match(story, /userEvent\.tab/);
    assert.match(story, /userEvent\.keyboard\('\{Enter\}'\)/);
});

test('visibility toggle has one production-backed story with all states and input methods', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/VisibilityToggle.stories.js');

    assert.match(story, /visibility-toggle\.js\?raw/);
    assert.match(story, /ergonode-workspace\.css/);
    assert.match(story, /loadAmdModule/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /visible: false/);
    assert.match(story, /visible: true/);
    assert.match(story, /export const PrzelaczanieMysza/);
    assert.match(story, /userEvent\.click/);
    assert.match(story, /export const PrzelaczanieKlawiatura/);
    assert.match(story, /userEvent\.tab/);
    assert.match(story, /userEvent\.keyboard\('\{Enter\}'\)/);
});

test('readiness view has production CSS and interaction coverage', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/Readiness.stories.js');

    assert.match(story, /readiness\.css/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const AkcjaMysza/);
    assert.match(story, /userEvent\.click/);
    assert.match(story, /export const AkcjaKlawiatura/);
    assert.match(story, /userEvent\.tab/);
});

test('mapping domains share one entity card contract and a state matrix', () => {
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/EntityItem.stories.js');
    const factory = read('dev/tools/ergonode-storybook/src/entity-item.js');
    const styles = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css');
    const inactiveRule = styles.match(/\.vea-attribute-card\.is-inactive\s*\{[^}]+\}/)?.[0] || '';

    assert.match(story, /options: \['attribute', 'category-attribute', 'option', 'language', 'store-view', 'template'\]/);
    assert.match(story, /export const RodzajeDanych/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const ZmianaAktywnosci/);
    assert.doesNotMatch(story, /(?:Attribute|Option|Template)Item/);
    assert.match(factory, /dataset\.role = 'entity-card'/);
    assert.match(factory, /dataset\.role = toggleRole/);
    assert.doesNotMatch(factory, /veui-entity-item|entity-active-toggle/);
    assert.match(factory, /buttons\.togglePressed/);
    assert.match(factory, /dragDrop\.write/);
    assert.doesNotMatch(inactiveRule, /opacity/);
    assert.match(styles, /\.vea-card-copy code,[\s\S]*?color: var\(--veui-text-muted-strong\)/);
    assert.match(styles, /\.vea-scope\s*\{[\s\S]*?color: var\(--veui-text-muted-strong\)/);
});

test('shared mapping views use one production-shaped Storybook composition', () => {
    const factory = read('dev/tools/ergonode-storybook/src/mapping-workspace.js');
    const story = read('app/code/Ergonode/CoreAdminUi/Test/Storybook/MappingWorkspace.stories.js');
    const categoryStory = fs.existsSync(categoryAttributeStoryPath)
        ? fs.readFileSync(categoryAttributeStoryPath, 'utf8')
        : null;

    assert.match(factory, /mappingViews = \['language', 'template', 'attribute', 'option', 'category-attribute'\]/);
    assert.match(factory, /createEntityItem/);
    assert.match(factory, /data-role=\"global-message-text\"/);
    assert.match(factory, /veui-autosave-region/);
    assert.match(factory, /vea-option-context/);
    assert.match(story, /export const Playground/);
    assert.match(story, /export const WspolneWidoki/);
    assert.match(story, /mappingViews\.filter\(\(view\) => view !== 'template'\)/);
    assert.match(story, /export const Stany/);
    assert.match(story, /export const Klawiatura/);
    assert.match(story, /export const ParowanieDwuklikiem/);
    assert.match(story, /userEvent\.dblClick/);
    assert.match(story, /export const ParowanieKlawiatura/);
    assert.match(factory, /function addCardByShortcut/);
    if (categoryStory !== null) {
        assert.doesNotMatch(categoryStory, /data-role=\"mapping-back\"/);
        assert.match(categoryStory, /createMappingWorkspace\(\{view: 'category-attribute', state: 'empty'\}\)/);
        assert.match(categoryStory, /mountMappingInteractions\(root\)/);
        const interactions = read('dev/tools/ergonode-storybook/src/mapping-interactions.js');
        assert.match(interactions, /entity-options\.js\?raw/);
        assert.match(interactions, /source-bulk-transfer\.js\?raw/);
        assert.match(interactions, /entityOptions\.bind/);
        assert.match(interactions, /sourceBulkTransfer\.bind/);
        assert.doesNotMatch(categoryStory, /function (panel|mappingTools)\(/);
        assert.match(factory, /veui-panel-head-tools/);
        assert.match(factory, /data-role=\"auto-match\"/);
        assert.match(factory, /data-role=\"complete-missing\"/);
        assert.doesNotMatch(categoryStory, /title: 'Ergonode\//);
    }
});

test('autosave and global messages are shared CoreAdminUi primitives', () => {
    const autosave = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/js/autosave.js');
    const messages = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/js/messages.js');
    const styles = read('app/code/Ergonode/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css');

    assert.match(autosave, /function create\(root, options\)/);
    assert.match(autosave, /options\.persist\(snapshot\)/);
    assert.match(messages, /\[data-role=\"global-message\"\]/);
    assert.match(messages, /success: 'veui-message-success'/);
    assert.match(styles, /\.veui-autosave-region/);
    assert.match(styles, /\.veui-message-error/);
});

test('backend agent router points UI changes at the Storybook contract', () => {
    const agents = read('AGENTS.md');
    const guide = read('dev/tools/ergonode-storybook/README.md');

    assert.match(agents, /dev\/tools\/ergonode-storybook\/README\.md/);
    assert.match(guide, /Nie twórz osobnych komponentów dla atrybutu, opcji i template'u/);
    assert.match(guide, /ddev exec npm run storybook:ergonode:build/);
});
