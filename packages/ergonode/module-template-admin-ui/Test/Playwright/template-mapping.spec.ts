import type { Locator } from "@playwright/test";

import { expect, test } from "@packhauer/playwright-runner/fixtures";

import {
    loginToErgonodeAdmin,
    openErgonodeAdminPage,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

test(
    "[ERG-TPL-001] Admin can inspect template mappings",
    {
        annotation: [
            { type: "id", description: "ERG-TPL-001" },
            {
                type: "description",
                description:
                    "Inspect template mapping controls and source sorting in the admin workspace.",
            },
            { type: "kind", description: "e2e" },
            { type: "module", description: "Ergonode_TemplateAdminUi" },
            {
                type: "requires-module",
                description: "Ergonode_TemplateConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_TemplateConsumer::template_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_TemplateConsumer::template_sync",
            },
            {
                type: "data-policy",
                description: "read-only; restores the visibility toggle",
            },
        ],
    },
    async ({ e2e }) => {
        await loginToErgonodeAdmin(e2e);
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/template/index",
            "#ergonode-template-admin",
        );

        await expect(
            workspace.locator('[data-role="template-search"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="attribute-set-search"]'),
        ).toBeVisible();
        await expect(
            workspace.locator('[data-role="sync-templates"]'),
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
            workspace.locator('[data-role="save-template-mapping"]'),
        ).toHaveCount(0);
        await expect(
            workspace.locator('[data-role="autosave-region"]'),
        ).toHaveAttribute("data-autosave-state", "saved");
        const sourceOptions = workspace.locator(
            "[data-template-source-options]",
        );
        const trigger = sourceOptions.locator("summary");
        const attributeSetOptions = workspace.locator(
            "[data-attribute-set-source-options]",
        );
        const visibility = sourceOptions.locator(
            '[data-role="visibility-toggle"]',
        );
        const sortDirection = sourceOptions.locator(
            '[data-role="source-sort-direction"]',
        );
        const sortField = sourceOptions.locator(
            '[data-role="source-sort-toggle"]',
        );

        await expect(trigger).toBeVisible();
        await expect(attributeSetOptions.locator("summary")).toBeVisible();
        await expect(
            workspace.locator(
                '[data-role="unmapped-template-list"] [data-role="entity-create-magento-attribute-set"]',
            ),
        ).toHaveCount(0);
        await trigger.click();
        await expect(visibility).toBeVisible();
        await expect(sortDirection).toHaveAttribute("data-direction", "asc");
        await expect(sortField).toHaveAttribute("data-sort-value", "label");
        if (await visibility.isEnabled()) {
            await expect(visibility).toHaveAttribute("aria-pressed", "false");
            await visibility.click();
            await expect(visibility).toHaveAttribute("aria-pressed", "true");
            await trigger.click();
            await visibility.click();
            await expect(visibility).toHaveAttribute("aria-pressed", "false");
        }
        if (!(await sourceOptions.evaluate((menu) => menu.hasAttribute("open")))) {
            await trigger.click();
        }
        await sortField.click();
        await expect(sortField).toHaveAttribute("data-sort-value", "code");

        const attributeSetTrigger = attributeSetOptions.locator("summary");
        const attributeSetSortField = attributeSetOptions.locator(
            '[data-role="source-sort-toggle"]',
        );

        await attributeSetTrigger.click();
        await expect(attributeSetSortField).toHaveAttribute(
            "data-sort-value",
            "label",
        );
        await attributeSetSortField.click();
        await expect(attributeSetSortField).toHaveAttribute(
            "data-sort-value",
            "code",
        );
    },
);

test(
    "[ERG-TPL-002] Completing a mapping from either source saves immediately",
    {
        annotation: [
            { type: "id", description: "ERG-TPL-002" },
            {
                type: "description",
                description:
                    "Complete and remove a template mapping starting from each source list, checking persistence after reload.",
            },
            { type: "kind", description: "e2e" },
            { type: "module", description: "Ergonode_TemplateAdminUi" },
            {
                type: "requires-module",
                description: "Ergonode_TemplateConsumer",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_TemplateConsumer::template_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_TemplateConsumer::template_save",
            },
            {
                type: "data-policy",
                description:
                    "creates, reloads, and removes one temporary complete mapping",
            },
        ],
    },
    async ({ e2e }) => {
        await loginToErgonodeAdmin(e2e);
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/template/index",
            "#ergonode-template-admin",
        );
        const leftSource = workspace
            .locator(
                '[data-role="unmapped-template-list"] [data-drag-type="template"][draggable="true"]',
            )
            .first();
        const rightSource = workspace
            .locator(
                '[data-role="unmapped-set-list"] [data-drag-type="attribute_set"][draggable="true"]',
            )
            .first();
        const autosaveRegion = workspace.locator(
            '[data-role="autosave-region"]',
        );
        const waitForWorkspaceReady = async (): Promise<void> => {
            await expect
                .poll(() =>
                    workspace.evaluate((element) =>
                        Boolean(
                            (
                                element as HTMLElement & {
                                    veaWorkspace?: unknown;
                                }
                            ).veaWorkspace,
                        ),
                    ),
                )
                .toBe(true);
        };

        await waitForWorkspaceReady();

        const waitForSave = () =>
            e2e.page.waitForResponse(
                (response) =>
                    response.request().method() === "POST" &&
                    /\/ergonode\/template\/savemapping(?:\/|$)/i.test(
                        response.url(),
                    ),
            );
        const expectSuccessfulSave = async (
            responsePromise: ReturnType<typeof waitForSave>,
        ): Promise<void> => {
            const response = await responsePromise;

            expect(response.ok()).toBe(true);
            expect((await response.json()).success).toBe(true);
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
        };
        const templateCode = await leftSource.getAttribute("data-template-code");
        const attributeSetId = await rightSource.getAttribute(
            "data-attribute-set-id",
        );

        expect(templateCode).not.toBeNull();
        expect(attributeSetId).not.toBeNull();

        const mapping = (): Locator =>
            workspace.locator(
                `[data-role="mapped-pair-list"] .vet-pair-card[data-template-code="${templateCode}"]`,
            );
        const completeAndRemoveMapping = async (
            firstSource: Locator,
            secondSource: Locator,
            sideSelector: string,
            identifierAttribute: string,
        ): Promise<void> => {
            await expect(firstSource).toBeVisible();
            const identifier = await firstSource.getAttribute(
                identifierAttribute,
            );

            expect(identifier).not.toBeNull();
            const draft = (): Locator =>
                workspace.locator(".vet-draft-card").filter({
                    has: e2e.page.locator(
                        `${sideSelector}[${identifierAttribute}="${identifier}"]`,
                    ),
                });
            const draftSave = waitForSave();

            await firstSource.focus();
            await firstSource.press("Enter");
            await expect(draft()).toBeVisible();
            await expectSuccessfulSave(draftSave);

            let mappingMayExist = true;
            const cancelCleanup = e2e.registerCleanup(
                `remove template mapping ${templateCode}`,
                async () => {
                    if (!mappingMayExist) {
                        return;
                    }
                    await e2e.page.reload();
                    await expect(workspace).toBeVisible();
                    await waitForWorkspaceReady();
                    const cleanupMapping = mapping();

                    if ((await cleanupMapping.count()) === 0) {
                        return;
                    }
                    const cleanupSave = waitForSave();
                    await cleanupMapping
                        .locator('[data-role="unlink-pair"]')
                        .click();
                    await expectSuccessfulSave(cleanupSave);
                },
            );

            const createSave = waitForSave();
            await secondSource.focus();
            await secondSource.press("Enter");
            await expectSuccessfulSave(createSave);
            await expect(mapping()).toBeVisible();
            await e2e.page.reload();
            await expect(workspace).toBeVisible();
            await waitForWorkspaceReady();
            await expect(mapping()).toBeVisible();

            const clearSave = waitForSave();
            await mapping().locator('[data-role="unlink-pair"]').click();
            await expectSuccessfulSave(clearSave);
            await expect(mapping()).toHaveCount(0);
            await e2e.page.reload();
            await expect(workspace).toBeVisible();
            await waitForWorkspaceReady();
            await expect(mapping()).toHaveCount(0);
            mappingMayExist = false;
            cancelCleanup();
        };

        await expect(
            workspace.locator("[data-template-source-options]"),
        ).toHaveCount(1);
        await completeAndRemoveMapping(
            leftSource,
            rightSource,
            '[data-drag-type="draft_template"]',
            "data-template-code",
        );
        await completeAndRemoveMapping(
            rightSource,
            leftSource,
            '[data-drag-type="draft_attribute_set"]',
            "data-attribute-set-id",
        );
    },
);
