import {
    expect,
    test,
} from "../../../../../../dev/tests/playwright/fixtures/e2e";

import {
    openErgonodeAdminPage,
    toggleAndRestore,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

test(
    "[ERG-ATTR-001] Admin can inspect product attribute mappings",
    {
        annotation: [
            { type: "id", description: "ERG-ATTR-001" },
            {
                type: "module",
                description: "Ergonode_ProductAttributeAdminUi",
            },
            {
                type: "requires-module",
                description: "Ergonode_AttributeConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_AttributeConsumer::attribute_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_AttributeConsumer::attribute_save",
            },
            {
                type: "acl-resource",
                description: "Ergonode_ProductAttributeConsumer::attribute_sync",
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
            "ergonode/attribute/index",
            "#ergonode-attribute-mapping",
        );

        await expect(
            workspace.locator('[data-role="attribute-side"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="mapping-panel"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="sync-ergonode"]'),
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
        const mappingOptions = workspace
            .locator(
                '[data-role="mapping-panel"] [data-role="entity-options"]',
            )
            .first();
        const autoMatch = mappingOptions.locator('[data-role="auto-match"]');

        await mappingOptions.locator("summary").click();
        await expect(autoMatch).toHaveAttribute(
            "data-available-count",
            /^\d+$/,
        );
        const availableCount = Number(
            await autoMatch.getAttribute("data-available-count"),
        );

        if (availableCount === 0) {
            await expect(autoMatch).toBeDisabled();
        } else {
            await expect(autoMatch).toBeEnabled();
        }
        await mappingOptions.locator("summary").click();
        const visibilityToggle = workspace
            .locator('[data-role="visibility-toggle"]')
            .first();

        if (await visibilityToggle.isEnabled()) {
            await toggleAndRestore(workspace, "visibility-toggle");
        } else {
            await expect(visibilityToggle).toBeDisabled();
        }
    },
);

test(
    "[ERG-OPT-001] Admin can inspect product option mappings",
    {
        annotation: [
            { type: "id", description: "ERG-OPT-001" },
            {
                type: "module",
                description: "Ergonode_ProductAttributeAdminUi",
            },
            {
                type: "requires-module",
                description: "Ergonode_AttributeConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_AttributeConsumer::option_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_ProductAttributeConsumer::option_sync",
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
            "ergonode/option/index",
            "#ergonode-option-mapping",
        );

        await expect(
            workspace.locator('[data-role="attribute-side"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="option-context-select"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="sync-ergonode"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="synchronization-action-options"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('.vea-viewbar > [data-role="auto-match"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('.vea-viewbar > [data-role="visibility-toggle"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('[data-role="toggle-full-view"]'),
        ).toHaveCount(0);

        const sourceOptions = workspace
            .locator(
                '[data-role="attribute-side"][data-source-panel="ergo"] [data-role="entity-options"]',
            )
            .first();
        const autoMatch = sourceOptions.locator('[data-role="auto-match"]');

        await sourceOptions.locator("summary").click();
        await expect(autoMatch).toBeVisible();
        await expect(autoMatch).toHaveClass(/veui-entity-options-action/);
        await expect(
            sourceOptions.locator('[data-role="visibility-toggle"]'),
        ).toContainText("Wykluczone");
        await sourceOptions.locator("summary").click();

        const visibilityControls = workspace.locator(
            '[data-role="attribute-side"] [data-role="visibility-toggle"]',
        );

        await expect(visibilityControls).toHaveCount(2);
        await expect(visibilityControls.nth(0)).toHaveClass(
            /veui-entity-options-action/,
        );
        await expect(visibilityControls.nth(1)).toHaveClass(
            /veui-entity-options-action/,
        );
    },
);
