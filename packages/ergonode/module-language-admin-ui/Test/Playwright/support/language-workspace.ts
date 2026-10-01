import type { Locator, Page } from "@playwright/test";
import { expect, test } from "./language-test";

// Existing local Store Views and snapshot languages selected by the user.
const pairs = [
    {
        storeCode: "default",
        languageCode: "en_GB",
        alternateLanguageCode: "pl_PL",
    },
    {
        storeCode: "base_pl",
        languageCode: "pl_PL",
        alternateLanguageCode: "en_GB",
    },
];
export const workspaceSelector = "#ergonode-language-mapping";

export const saveUrl = /\/ergonode\/language\/save\//;

export function languageCard(workspace: Locator, code: string): Locator {
    return workspace.locator(
        '[data-role="entity-card"][data-source="ergo"][data-code="' +
            code +
            '"]',
    );
}

export function storeCard(workspace: Locator, storeCode: string): Locator {
    return workspace.locator(
        '[data-role="entity-card"][data-store-code="' + storeCode + '"]',
    );
}

export function storeRows(page: Page, storeId: string): Locator {
    return page
        .locator(workspaceSelector + ' [data-role="mapping-row"]')
        .filter({
            has: page.locator(
                '[data-side="magento"][data-code="' + storeId + '"]',
            ),
        });
}

export async function expectSaveError(page: Page): Promise<void> {
    const workspace = page.locator(workspaceSelector);
    await expect(
        workspace.locator('[data-role="autosave-region"]'),
    ).toHaveAttribute("data-autosave-state", "error");
    await expect(
        workspace.locator('[data-role="autosave-error"]'),
    ).toBeVisible();
    await expect(
        workspace.locator('[data-role="retry-autosave"]'),
    ).toBeEnabled();
    await expect(workspace).not.toHaveAttribute("data-language-busy", "true");
}

export async function expectConflict(
    page: Page,
    action: () => Promise<void>,
): Promise<void> {
    const [response] = await Promise.all([
        page.waitForResponse(
            (candidate) =>
                candidate.request().method() === "POST" &&
                saveUrl.test(candidate.url()),
        ),
        action(),
    ]);
    expect(response.status()).toBe(409);
    const result = await response.json();
    expect(result.success).toBe(false);
    expect(result.revision).toBeUndefined();
    await expectSaveError(page);
}

/** Exercise both existing Store Views and restore each one's original pair. */
export async function withExistingPairs(
    page: Page,
    run: (
        workspace: Locator,
        storeId: string,
        before: Awaited<ReturnType<typeof snapshot>>,
        pair: (typeof pairs)[number],
    ) => Promise<void>,
): Promise<void> {
    const hostname = new URL(page.url()).hostname;
    expect(
        hostname.endsWith(".ddev.site") ||
            ["localhost", "127.0.0.1", "[::1]"].includes(hostname),
    ).toBe(true);
    for (const pair of pairs) {
        await test.step(`${pair.storeCode} ↔ ${pair.languageCode}`, async () => {
            const workspace = await ready(page);
            const store = storeCard(workspace, pair.storeCode);
            await expect(
                store,
                `The existing Store View ${pair.storeCode} is required.`,
            ).toHaveCount(1);
            const storeId = await store.getAttribute("data-code");
            expect(storeId).toMatch(/^[1-9][0-9]*$/);
            if (storeId === null)
                throw new Error("Existing Store View has no ID.");
            for (const code of [
                pair.languageCode,
                pair.alternateLanguageCode,
            ]) {
                await expect(languageCard(workspace, code)).toBeVisible();
            }
            const original = await snapshot(workspace);
            const originalPairs = original.mappings.filter(
                (row) => row[1] === storeId,
            );
            expect(originalPairs.length).toBeLessThanOrEqual(1);
            const originalLanguage = originalPairs[0]?.[0];
            if (originalLanguage) {
                await expect(
                    languageCard(workspace, originalLanguage),
                ).toBeVisible();
            }
            for (const code of [
                pair.languageCode,
                pair.alternateLanguageCode,
            ]) {
                expect(
                    original.mappings.some(
                        (row) => row[0] === code && row[1] === "",
                    ),
                    "Resolve existing language-only drafts before running mapping tests.",
                ).toBe(false);
            }
            const rows = storeRows(page, storeId);
            try {
                if (originalPairs.length) {
                    await saveThroughUi(page, () =>
                        rows
                            .locator(':scope > [data-role="unlink-mapping"]')
                            .click(),
                    );
                }
                await expect(store).toBeVisible();
                await run(workspace, storeId, await snapshot(workspace), pair);
            } finally {
                // Discard unsaved DOM, fetch the current revision and never restore a stale full snapshot.
                await page.reload({ waitUntil: "domcontentloaded" });
                await ready(page);
                if (await rows.count()) {
                    await expect(rows).toHaveCount(1);
                    const currentLanguage = await rows
                        .locator('[data-side="ergo"]')
                        .getAttribute("data-code");
                    expect(
                        [
                            pair.languageCode,
                            pair.alternateLanguageCode,
                            originalLanguage,
                        ].includes(currentLanguage ?? undefined),
                        "Do not remove a pair replaced by an unrelated editor.",
                    ).toBe(true);
                    await saveThroughUi(page, () =>
                        rows
                            .locator(':scope > [data-role="unlink-mapping"]')
                            .click(),
                    );
                }
                if (originalPairs.length) {
                    await saveThroughUi(page, () =>
                        originalLanguage
                            ? languageCard(workspace, originalLanguage).dragTo(
                                  storeCard(workspace, pair.storeCode),
                              )
                            : storeCard(
                                  workspace,
                                  pair.storeCode,
                              ).dispatchEvent("dblclick"),
                    );
                }
                await page.reload({ waitUntil: "domcontentloaded" });
                await ready(page);
                const restored = await snapshot(workspace);
                expect(
                    { ...restored, mappings: restored.mappings.slice().sort() },
                    "Original mappings and visibility must remain unchanged.",
                ).toEqual({
                    ...original,
                    mappings: original.mappings.slice().sort(),
                });
            }
        });
    }
}

export async function ready(page: Page): Promise<Locator> {
    const workspace = page.locator(workspaceSelector);
    await expect(workspace).toBeVisible();
    await expect(
        workspace.locator('[data-role="entity-options-placeholder"]:visible'),
    ).toHaveCount(0);
    await expect(
        workspace.locator('[data-role="autosave-region"]'),
    ).toHaveAttribute("aria-busy", "false");
    return workspace;
}

export async function snapshot(workspace: Locator) {
    return workspace.evaluate((root) => ({
        mappings: Array.from(
            root.querySelectorAll('[data-role="mapping-row"]'),
            (row) => [
                row
                    .querySelector('[data-side="ergo"]')
                    ?.getAttribute("data-code") || "",
                row
                    .querySelector('[data-side="magento"]')
                    ?.getAttribute("data-code") || "",
            ],
        ),
        visibility: Array.from(
            root.querySelectorAll('[data-role="entity-card"]'),
            (card) => [
                card.getAttribute("data-source"),
                card.getAttribute("data-code"),
                card
                    .querySelector('[data-role="source-active-toggle"]')
                    ?.getAttribute("aria-pressed") || "true",
            ],
        ).sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))),
    }));
}

export async function saveThroughUi(
    page: Page,
    action: () => Promise<void>,
): Promise<void> {
    const [response] = await Promise.all([
        page.waitForResponse(
            (candidate) =>
                candidate.request().method() === "POST" &&
                new URL(candidate.url()).pathname.includes(
                    "/ergonode/language/save/",
                ),
        ),
        action(),
    ]);
    expect(
        response.status(),
        "The actual Magento save controller must succeed.",
    ).toBe(200);
    const result = await response.json();
    expect(result.success, "Magento rejected the language mapping save.").toBe(
        true,
    );
    expect(result.revision).toMatch(/^[a-f0-9]{64}$/);
    await expect(page.locator(workspaceSelector)).not.toHaveAttribute(
        "data-language-busy",
        "true",
    );
    await expect(
        page.locator(workspaceSelector + ' [data-role="autosave-region"]'),
    ).toHaveAttribute("data-autosave-state", "saved");
}
