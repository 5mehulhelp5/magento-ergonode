define(['mage/translate'], function ($t) {
    'use strict';

    function append(parent, tag, text, className) {
        var node = document.createElement(tag);

        node.textContent = text;
        if (className) {
            node.className = className;
        }
        parent.appendChild(node);
        return node;
    }

    function title(state) {
        if (state.status === 'missing') {
            return $t('Nie znaleziono drzewa w Ergonode');
        }
        if (state.status === 'unavailable') {
            return $t('Nie udało się sprawdzić źródła');
        }
        return $t('Źródło wymaga ponownego pobrania');
    }

    function isBlocked(state) {
        return !!state.requires_refresh || ['missing', 'unavailable'].indexOf(state.status) !== -1;
    }

    return function (root, actions) {
        var panel = root.querySelector('[data-role="source-issues"]');

        return function (issues, trees, selected, permissions) {
            var missing = issues.missing_categories || [];
            var blocked = isBlocked(issues);
            var focused = panel && panel.contains(document.activeElement)
                ? document.activeElement.getAttribute('data-source-action') : null;
            var controls;
            var details;
            var list;

            root.classList.toggle('has-source-issue', blocked);
            root.querySelectorAll('[data-column-role="source"] > *, ' +
                '[data-column-role="target"] > :not([data-role="source-issues"])').forEach(function (content) {
                content.hidden = blocked;
            });
            if (blocked) {
                root.querySelectorAll('[data-role="ergo-list"], [data-role="magento-list"]').forEach(function (tree) {
                    tree.replaceChildren();
                });
            }
            root.querySelectorAll('[data-role="category-tree-configuration"]').forEach(function (card) {
                var tree = (trees || []).find(function (item) {
                    return Number(item.category_tree_id) === Number(card.getAttribute('data-category-tree-id'));
                });
                var badge = card.querySelector('[data-role="source-status-badge"]');
                var state = Number(card.getAttribute('data-category-tree-id')) === Number(selected.category_tree_id)
                    ? issues : tree && tree.source_state || {};
                var hint;
                var icon;

                card.classList.toggle('has-source-issue', isBlocked(state));
                if (badge) {
                    badge.hidden = !isBlocked(state);
                    badge.replaceChildren();
                    badge.setAttribute('aria-label', title(state));
                    icon = append(badge, 'span', 'i', 'vec-information-icon');
                    icon.setAttribute('aria-hidden', 'true');
                    hint = append(badge, 'span', title(state), 'vec-mapping-hint vec-configuration-disabled-tooltip');
                    hint.id = 'vec-source-hint-' + card.getAttribute('data-category-tree-id');
                    hint.setAttribute('role', 'tooltip');
                    badge.setAttribute('aria-describedby', hint.id);
                }
            });
            if (!panel) {
                return;
            }
            panel.replaceChildren();
            panel.hidden = !blocked && !missing.length;
            if (panel.hidden) {
                if (focused) {
                    root.focus();
                }
                return;
            }
            append(panel, 'strong', blocked ? title(issues) : $t('Brak kategorii w źródłowym drzewie'));
            append(panel, 'p', $t('Drzewo: %1').replace('%1', selected.tree_code || ''));
            append(panel, 'p', blocked
                ? $t('Synchronizacja tego powiązania jest zablokowana. Kategorie i mapowania Magento zachowano.')
                : $t('Poniższe kategorie i ich mapowania zachowano w Magento. Brak w drzewie nie oznacza usunięcia kategorii z całego Ergonode. Usunięcie z Magento wymaga ręcznej decyzji.'));
            if (issues.checked_at) {
                append(panel, 'p', $t('Ostatnie sprawdzenie (UTC): %1').replace('%1', issues.checked_at));
            }
            if (issues.snapshot_at) {
                append(panel, 'p', $t('Ostatnie poprawne pobranie (UTC): %1').replace('%1', issues.snapshot_at));
            }
            if (missing.length) {
                details = document.createElement('details');
                panel.appendChild(details);
                append(details, 'summary', $t('Zachowane kategorie (%1)').replace('%1', String(missing.length)));
                list = document.createElement('ul');
                details.appendChild(list);
                missing.forEach(function (category) {
                    append(list, 'li', category.label + ' — ' + category.code + ' → Magento #' + category.magento_category_id);
                });
            }
            controls = document.createElement('div');
            controls.className = 'vec-source-issue-actions';
            panel.appendChild(controls);
            if (permissions.refresh && selected.is_active) {
                var retry = append(controls, 'button', $t('Sprawdź ponownie'), 'veui-button veui-button-toolbar');

                retry.type = 'button';
                retry.setAttribute('data-source-action', 'retry');
                retry.addEventListener('click', function () { actions.retry(retry); });
            }
            if (permissions.disable && selected.is_active) {
                var disable = append(controls, 'button', $t('Wyłącz powiązanie'), 'veui-button veui-button-toolbar');

                disable.type = 'button';
                disable.setAttribute('data-source-action', 'disable');
                disable.addEventListener('click', actions.disable);
            }
            if (focused) {
                var nextFocus = panel.querySelector('[data-source-action="' + focused + '"]');

                if (nextFocus) {
                    nextFocus.focus();
                }
            }
        };
    };
});
