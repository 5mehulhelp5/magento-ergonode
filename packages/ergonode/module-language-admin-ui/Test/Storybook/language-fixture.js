import {markRequiredEntityItem} from '@ergonode-storybook/entity-item.js';

export function createLanguageLoader() {
    const loader = document.createElement('div');
    const icon = document.createElement('span');
    const label = document.createElement('span');

    loader.className = 'vel-language-loader';
    loader.dataset.role = 'language-loader';
    loader.setAttribute('role', 'status');
    loader.setAttribute('aria-live', 'polite');
    loader.hidden = true;
    icon.className = 'vel-language-loader-icon';
    icon.setAttribute('aria-hidden', 'true');
    label.textContent = 'Zapisywanie zmian...';
    loader.append(icon, label);

    return loader;
}

export function decorateStoreViewCard(card, {id, code, label, locale, scope, required = false, mapped = false}) {
    card.classList.add('vel-source-card');
    card.classList.toggle('is-mapped', mapped);
    card.dataset.code = id;
    card.dataset.storeCode = code;
    card.dataset.locale = locale;
    card.dataset.search = `${label} ${code} ${locale} ${scope}`;
    card.setAttribute('aria-label', `Wybierz Magento Store View: ${label}`);
    card.querySelector('.vea-card-subline code').textContent = code;
    const subline = card.querySelector('.vea-card-subline');
    const localeBadge = document.createElement('span');
    localeBadge.className = 'vea-type-badge vea-type-store-view';
    localeBadge.textContent = locale;
    const scopeLabel = document.createElement('span');
    scopeLabel.className = 'vea-scope';
    scopeLabel.textContent = scope;
    subline.append(localeBadge, scopeLabel);
    if (!required) return card;

    markRequiredEntityItem(card, {
        label: 'Wybierz język domyślny',
        description: 'Przypisz język Ergonode, który ma być używany jako domyślny. Bez tego synchronizacja nie może się rozpocząć.',
        tooltipId: `language-store-requirement-${id}`,
        missing: !mapped
    });
    subline.insertBefore(subline.querySelector('.veui-required-badge'), scopeLabel);
    return card;
}
