import { expect, test } from "./support/language-test";
import { openErgonodeAdminPage } from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";
import {
    expectConflict,
    expectSaveError,
    languageCard,
    ready,
    saveThroughUi,
    saveUrl,
    snapshot,
    storeCard,
    storeRows,
    withExistingPairs,
    workspaceSelector,
} from "./support/language-workspace";

test(
    "[ERG-LANG-004] Stale editor and retry cannot overwrite the saved mapping",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-004" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Two authenticated tabs edit the same revision. Real Magento rejects the stale save and retry with HTTP 409; reload preserves the first editor's mapping.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Uses existing default/en_GB and base_pl/pl_PL pairs. Finally restores each Store View mapping through the real UI and verifies original mappings and visibility.",
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
            async (
                workspace,
                storeId,
                before,
                { storeCode, languageCode, alternateLanguageCode },
            ) => {
                const stale = await page.context().newPage();
                try {
                    await stale.goto(page.url(), {
                        waitUntil: "domcontentloaded",
                    });
                    const staleWorkspace = await ready(stale);
                    expect(await snapshot(staleWorkspace)).toEqual(before);
                    await saveThroughUi(page, () =>
                        languageCard(workspace, languageCode).dragTo(
                            storeCard(workspace, storeCode),
                        ),
                    );
                    await expectConflict(stale, () =>
                        languageCard(
                            staleWorkspace,
                            alternateLanguageCode,
                        ).dragTo(storeCard(staleWorkspace, storeCode)),
                    );
                    await expect(
                        storeRows(stale, storeId).locator('[data-side="ergo"]'),
                    ).toHaveAttribute("data-code", alternateLanguageCode);
                    await expectConflict(stale, () =>
                        staleWorkspace
                            .locator('[data-role="retry-autosave"]')
                            .click(),
                    );

                    await page.reload({ waitUntil: "domcontentloaded" });
                    await ready(page);
                    const authoritative = await snapshot(workspace);
                    expect(authoritative).toEqual({
                        mappings: [...before.mappings, [languageCode, storeId]],
                        visibility: before.visibility,
                    });
                    await stale.reload({ waitUntil: "domcontentloaded" });
                    await ready(stale);
                    expect(await snapshot(staleWorkspace)).toEqual(
                        authoritative,
                    );
                } finally {
                    await stale.close();
                }
            },
        );
    },
);

test(
    "[ERG-LANG-005] Failed request keeps the edit unsaved until a real retry succeeds",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-005" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Abort the first save before Magento receives it. The UI exposes retry, another tab confirms unchanged persistence, and a real retry persists the edit across reload.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Existing default/en_GB and base_pl/pl_PL pairs. Injects one transport failure; successful save and cleanup use real Magento. Finally verifies original mappings and visibility.",
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
                const observer = await page.context().newPage();
                await page.route(saveUrl, async (route) => {
                    if (route.request().method() !== "POST")
                        return route.continue();
                    await route.abort("failed");
                });
                try {
                    await Promise.all([
                        page.waitForEvent(
                            "requestfailed",
                            (request) =>
                                request.method() === "POST" &&
                                saveUrl.test(request.url()),
                        ),
                        languageCard(workspace, languageCode).dragTo(
                            storeCard(workspace, storeCode),
                        ),
                    ]);
                    await page.unroute(saveUrl);
                    await expectSaveError(page);
                    await expect(
                        storeRows(page, storeId).locator('[data-side="ergo"]'),
                    ).toHaveAttribute("data-code", languageCode);
                    await observer.goto(page.url(), {
                        waitUntil: "domcontentloaded",
                    });
                    expect(await snapshot(await ready(observer))).toEqual(
                        before,
                    );
                    await saveThroughUi(page, () =>
                        workspace
                            .locator('[data-role="retry-autosave"]')
                            .click(),
                    );
                    await page.reload({ waitUntil: "domcontentloaded" });
                    expect(await snapshot(await ready(page))).toEqual({
                        mappings: [...before.mappings, [languageCode, storeId]],
                        visibility: before.visibility,
                    });
                } finally {
                    await page.unroute(saveUrl);
                    await observer.close();
                }
            },
        );
    },
);

test(
    "[ERG-LANG-006] Lost response after commit requires reload instead of overwriting newer state",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-006" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Forward the save to Magento, discard its successful response, verify persistence in another tab, then confirm retry is rejected with HTTP 409 until reload.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Existing default/en_GB and base_pl/pl_PL pairs. A real request is committed before response loss is injected. Finally restores the original Store View pair and verifies original mappings and visibility.",
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
                const observer = await page.context().newPage();
                let committedStatus: number | undefined;
                let committedResult:
                    { success?: boolean; revision?: string } | undefined;
                await page.route(saveUrl, async (route) => {
                    if (route.request().method() !== "POST")
                        return route.continue();
                    const response = await route.fetch({ maxRetries: 0 });
                    committedStatus = response.status();
                    committedResult = await response.json();
                    await route.abort("failed");
                });
                try {
                    await Promise.all([
                        page.waitForEvent(
                            "requestfailed",
                            (request) =>
                                request.method() === "POST" &&
                                saveUrl.test(request.url()),
                        ),
                        languageCard(workspace, languageCode).dragTo(
                            storeCard(workspace, storeCode),
                        ),
                    ]);
                    await page.unroute(saveUrl);
                    expect(committedStatus).toBe(200);
                    expect(committedResult?.success).toBe(true);
                    expect(committedResult?.revision).toMatch(/^[a-f0-9]{64}$/);
                    await expectSaveError(page);
                    await observer.goto(page.url(), {
                        waitUntil: "domcontentloaded",
                    });
                    const persisted = {
                        mappings: [...before.mappings, [languageCode, storeId]],
                        visibility: before.visibility,
                    };
                    expect(await snapshot(await ready(observer))).toEqual(
                        persisted,
                    );
                    await expectConflict(page, () =>
                        workspace
                            .locator('[data-role="retry-autosave"]')
                            .click(),
                    );
                    await page.reload({ waitUntil: "domcontentloaded" });
                    expect(await snapshot(await ready(page))).toEqual(
                        persisted,
                    );
                    await expect(
                        workspace.locator('[data-role="autosave-error"]'),
                    ).not.toBeVisible();
                } finally {
                    await page.unroute(saveUrl);
                    await observer.close();
                }
            },
        );
    },
);
