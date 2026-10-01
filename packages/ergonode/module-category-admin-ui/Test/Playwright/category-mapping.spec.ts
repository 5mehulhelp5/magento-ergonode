import {
    expect,
    test,
} from "../../../../../dev/tests/playwright/fixtures/e2e";

import {
    openErgonodeAdminPage,
    toggleAndRestore,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

test(
    "[ERG-CAT-001] Admin can inspect category tree mappings",
    {
        annotation: [
            { type: "id", description: "ERG-CAT-001" },
            { type: "module", description: "Ergonode_CategoryAdminUi" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Inspect category tree mappings, configuration and actions in the real admin UI, then restore the visibility toggle.",
            },
            {
                type: "requires-module",
                description: "Ergonode_CategoryConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_CategoryConsumer::category_tree_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_CategoryConsumer::category_tree_sync",
            },
            {
                type: "data-policy",
                description: "read-only; restores the visibility toggle",
            },
        ],
    },
    async ({ e2e }) => {
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/category_tree_mapping/edit",
            "#ergonode-category-tree-mapping",
        );

        await expect(
            workspace.locator('[data-role="ergo-search"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="magento-search"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="category-tree-configuration-list"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="sync-category-trees"]'),
        ).toBeVisible();
        await workspace.locator(
            '[data-role="synchronization-action-options"] > summary',
        ).click();
        await expect(
            workspace.locator('[data-synchronization-action="reset-cursor"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-synchronization-action="reset-cursor-and-sync"]'),
        ).toBeVisible();
        await workspace.locator(
            '[data-role="synchronization-action-options"] > summary',
        ).click();
        await expect(
            workspace.locator('[data-bulk-options-source]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="create-magento-category"]'),
        ).toHaveCount(0);
        const sourceRootSelection = workspace.locator(
            '.vec-source-panel .vec-configured-root-card ' +
                '[data-role="bulk-category-select"]',
        );
        const targetRootSelection = workspace.locator(
            '.vec-target-panel .is-configured-root ' +
                '[data-role="bulk-category-select"]',
        );

        await expect(sourceRootSelection).toBeVisible();
        await expect(targetRootSelection).toBeVisible();
        await expect(
            workspace.locator('.vec-tree-root-mark'),
        ).toHaveCount(0);
        await sourceRootSelection.click();
        await expect(sourceRootSelection).toBeChecked();
        await sourceRootSelection.click();
        await expect(sourceRootSelection).not.toBeChecked();
        await expect(
            workspace.locator('[data-role="visibility-toggle"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator(
                '[data-role="include-selected-categories"], ' +
                    '[data-role="exclude-selected-categories"]',
            ),
        ).toHaveCount(0);
        await expect(
            workspace.locator('.vec-toolbar [data-role="visibility-toggle"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('[data-role="select-visible-categories"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('[data-role="clear-category-selection"]'),
        ).toHaveCount(0);
        const targetTools = workspace.locator(".vec-target-tools");

        await expect(
            workspace.locator(
                '.vec-toolbar [data-role="save-categories"]',
            ),
        ).toHaveCount(0);
        await expect(
            targetTools.locator(":scope > *"),
        ).toHaveCount(3);
        await expect(
            targetTools.locator(":scope > *").nth(0).locator(
                '[data-role="magento-search"]',
            ),
        ).toBeVisible();
        await expect(
            targetTools.locator(":scope > *").nth(1),
        ).toHaveAttribute("data-role", "save-categories");
        await expect(
            targetTools.locator(":scope > *").nth(2),
        ).toHaveAttribute("data-bulk-options-source", "magento");
        const visibilityToggles = workspace.locator(
            '[data-role="visibility-toggle"]',
        );

        for (let index = 0; index < 2; index += 1) {
            const toggle = visibilityToggles.nth(index);
            const options = toggle.locator("xpath=ancestor::details[1]");

            await options.locator(":scope > summary").click();
            await expect(toggle).toBeVisible();
            await expect(toggle).toHaveAttribute("aria-pressed", "false");
            if (!(await toggle.isDisabled())) {
                await toggle.click();
                await expect(toggle).toHaveAttribute("aria-pressed", "true");
                if ((await options.getAttribute("open")) === null) {
                    await options.locator(":scope > summary").click();
                }
                await toggle.click();
                await expect(toggle).toHaveAttribute("aria-pressed", "false");
            }
            if ((await options.getAttribute("open")) !== null) {
                await options.locator(":scope > summary").click();
            }
        }
    },
);

test(
    "[ERG-CAT-ATTR-001] Admin can inspect category attribute mappings",
    {
        annotation: [
            { type: "id", description: "ERG-CAT-ATTR-001" },
            { type: "module", description: "Ergonode_CategoryAdminUi" },
            {
                type: "requires-module",
                description: "Ergonode_CategoryConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description:
                    "Ergonode_CategoryConsumer::category_attribute_mapping",
            },
            {
                type: "data-policy",
                description: "read-only; restores local view controls",
            },
        ],
    },
    async ({ e2e }) => {
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/category_attribute/index",
            "#ergonode-category-attribute-mapping",
        );

        await expect(
            workspace.locator('[data-role="attribute-side"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="mapping-panel"]'),
        ).toBeVisible();
        await toggleAndRestore(workspace, "visibility-toggle");
    },
);
