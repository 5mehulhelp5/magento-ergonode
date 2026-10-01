import { expect, userEvent, within } from 'storybook/test';

import { createNavigation, sections } from '@ergonode-storybook/section-navigation.js';

function renderNavigation(args) {
    const toolbar = document.createElement('div');

    toolbar.className = 'veui-toolbar veui-viewbar';
    toolbar.append(createNavigation(args));

    return toolbar;
}

function renderCategorySwitcher() {
    const toolbar = document.createElement('div');

    toolbar.className = 'veui-toolbar veui-viewbar';
    toolbar.append(createNavigation({
        currentSection: 'categories',
        categoryOptionsAvailable: true
    }));

    return toolbar;
}

function renderAttributeSwitcher() {
    const toolbar = document.createElement('div');

    toolbar.className = 'veui-toolbar veui-viewbar';
    toolbar.append(createNavigation({ currentSection: 'products' }));

    return toolbar;
}

function renderExample(title, currentSection) {
    const example = document.createElement('section');

    example.className = 'veui-storybook-example';
    example.append(Object.assign(document.createElement('h3'), { textContent: title }));
    const navigation = renderNavigation({ currentSection });
    navigation.querySelector('nav').setAttribute('aria-label', `Ergonode sections: ${title}`);
    example.append(navigation);

    return example;
}

const meta = {
    id: 'ergo-c-034',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-034 · Nawigacja sekcji',
    tags: ['autodocs'],
    render: renderNavigation,
    args: {
        currentSection: 'attributes',
        allowedSections: sections.filter(({ code }) => code !== 'templates').map(({ code }) => code),
        categoryOptionsAvailable: false
    },
    argTypes: {
        currentSection: {
            control: 'select',
            options: sections.filter(({ code }) => code !== 'templates').map(({ code }) => code),
            description: 'Bieżąca sekcja pozostaje widoczna i aktywna, bez odnośnika do tej samej strony.'
        },
        allowedSections: {
            control: 'check',
            options: sections.filter(({ code }) => code !== 'templates').map(({ code }) => code),
            description: 'Sekcje dostępne dla roli administratora.'
        },
        categoryOptionsAvailable: {
            control: 'boolean',
            description: 'Moduł mapowania atrybutów kategorii rozszerza przycisk Categories o menu.'
        }
    },
    parameters: {
        docs: {
            description: {
                component: 'Wspólna nawigacja po sekcjach Ergonode, wyrównana do lewej strony toolbara.'
            }
        }
    }
};

export default meta;

export const Playground = {};

export const BiezacyWidok = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        sections.forEach(({ code, label }) => grid.append(renderExample(`Bieżący widok: ${label}`, code)));

        return grid;
    }
};

export const OgraniczoneUprawnienia = {
    args: {
        currentSection: 'attributes',
        allowedSections: ['attributes', 'categories', 'languages']
    }
};

export const KategorieBezOpcji = {
    args: {
        currentSection: 'categories',
        categoryOptionsAvailable: false
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('link', { name: 'Categories' })).toHaveAttribute('aria-current', 'page');
        await expect(canvas.getByRole('link', { name: 'Categories' })).not.toHaveAttribute('href');
        await expect(canvas.queryByRole('button', { name: 'Category options' })).not.toBeInTheDocument();
    }
};

export const KategorieZMenuOpcji = {
    args: {
        currentSection: 'categories',
        categoryOptionsAvailable: true
    },
    render: renderCategorySwitcher,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const categoryGroup = within(canvas.getByRole('group', { name: 'Categories sections' }));

        await userEvent.click(canvas.getByRole('button', { name: 'Category options' }));
        await expect(canvas.getByRole('link', { name: 'Tree' })).toHaveAttribute('aria-current', 'page');
        await expect(categoryGroup.getByRole('link', { name: 'Attributes' })).toBeVisible();
        await expect(canvas.getByRole('link', { name: 'Languages' })).toBeVisible();
    }
};

export const AtrybutyZMenuOpcji = {
    render: renderAttributeSwitcher,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const attributeGroup = within(canvas.getByRole('group', { name: 'Product sections' }));

        await userEvent.click(canvas.getByRole('button', { name: 'Attribute options' }));
        await expect(canvas.getByRole('link', { name: 'List' })).toHaveAttribute('aria-current', 'page');
        await expect(attributeGroup.getByRole('link', { name: 'Product' })).toHaveAttribute('href', '#attributes');
        await expect(attributeGroup.getByRole('link', { name: 'Attributes' })).toHaveAttribute('href', '#attributes');
        await expect(attributeGroup.getByRole('link', { name: 'List' })).not.toHaveAttribute('href');
    }
};

export const NawigacjaKlawiatura = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const firstLink = canvas.getByRole('link', { name: 'Categories' });

        await expect(firstLink.querySelector('.veui-section-navigation-icon-list-tree')).not.toBeNull();
        await userEvent.hover(firstLink);
        await expect(firstLink).toHaveClass('veui-section-navigation-button');
        await userEvent.tab();
        await expect(firstLink).toHaveFocus();
    }
};

export const IkonySekcji = {
    args: {
        currentSection: 'attributes'
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const categoriesLink = canvas.getByRole('link', { name: 'Categories' });
        const attributesLink = canvas.getByRole('link', { name: 'Product' });
        const languagesLink = canvas.getByRole('link', { name: 'Languages' });

        await expect(categoriesLink.querySelector('.veui-section-navigation-icon-list-tree')).not.toBeNull();
        await expect(attributesLink.querySelector('.veui-section-navigation-icon-attribution-pen')).not.toBeNull();
        await expect(canvasElement.querySelector('nav > a[href="#options"]')).toBeNull();
        await expect(languagesLink.querySelector('.veui-section-navigation-icon-english')).not.toBeNull();
    }
};

export const AktywnyProduct = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const product = canvas.getByRole('link', { name: 'Product' });
        await expect(product).toHaveAttribute('aria-current', 'page');
        await expect(product).not.toHaveAttribute('href');
        const toggle = canvas.getByRole('button', { name: 'Attribute options' });
        await userEvent.click(toggle);
        await expect(canvas.getByRole('link', { name: 'Attributes' })).not.toHaveAttribute('href');
        const options = canvas.getByRole('link', { name: 'List' });
        await expect(options).toBeVisible();
        toggle.focus();
        await userEvent.tab();
        await expect(options).toHaveFocus();
        await expect(options).toHaveAttribute('href', '#products');
        const labels = Array.from(canvasElement.querySelectorAll('.veui-section-navigation-button'))
            .map((element) => element.textContent.trim());
        await expect(labels).toEqual(['Categories', 'Languages', 'Product', 'Readiness', 'Synchronizations']);
    }
};
