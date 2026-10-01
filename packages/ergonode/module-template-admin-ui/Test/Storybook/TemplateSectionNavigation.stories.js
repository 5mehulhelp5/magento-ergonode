import { createNavigation } from '@ergonode-storybook/section-navigation.js';
import { expect, within } from 'storybook/test';
import '../../view/adminhtml/web/css/section-navigation.css';

function renderTemplateNavigation() {
    return createNavigation({currentSection: '', allowedSections: ['templates']});
}

export default {
    id: 'ergo-v-058-02',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.02 · Nawigacja sekcji',
    tags: ['autodocs'],
    render: renderTemplateNavigation,
};

export const Playground = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const link = canvas.getByRole('link', { name: 'Templates' });

        await expect(link.querySelector('.veui-section-navigation-icon-template')).not.toBeNull();
    },
};
