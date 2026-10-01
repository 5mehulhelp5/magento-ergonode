import { expect, test } from "./support/language-test";
import { openErgonodeAdminPage } from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

import {
    languageCard,
    ready,
    saveThroughUi,
    snapshot,
    storeCard,
    storeRows,
    withExistingPairs,
    workspaceSelector,
} from "./support/language-workspace";

test(
    "[ERG-LANG-003] Language mapping survives reload and is cleaned up",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-003" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Create a mapping through the real admin UI, verify Magento persisted it after reload, then unlink it and verify cleanup after another reload.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Uses existing default/en_GB and base_pl/pl_PL pairs. Saves through Magento and restores each Store View mapping in finally; never creates stores or languages.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping_save",
            },
        ],
    },
    async ({ e2e }) => {
        const page = e2e.page;
        await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            workspaceSelector,
        );
        await withExistingPairs(
            page,
            async (workspace, storeId, before, { storeCode, languageCode }) => {
                await test.step("Create the mapping and wait for the real Magento response", async () => {
                    await saveThroughUi(page, () =>
                        languageCard(workspace, languageCode).dragTo(
                            storeCard(workspace, storeCode),
                        ),
                    );
                    await expect(storeRows(page, storeId)).toHaveCount(1);
                });
                await test.step("Reload and confirm the saved pair", async () => {
                    await page.reload({ waitUntil: "domcontentloaded" });
                    expect(await snapshot(await ready(page))).toEqual({
                        mappings: [...before.mappings, [languageCode, storeId]],
                        visibility: before.visibility,
                    });
                });
                if (storeCode === "default") {
                    await test.step("Restore an existing mapping when a scenario fails", async () => {
                        const persisted = await snapshot(workspace);
                        const failure = new Error(
                            "Controlled scenario failure after preparation",
                        );
                        await expect(
                            withExistingPairs(page, async () => {
                                throw failure;
                            }),
                        ).rejects.toBe(failure);
                        expect(await snapshot(await ready(page))).toEqual(
                            persisted,
                        );
                    });
                }
            },
        );
    },
);
