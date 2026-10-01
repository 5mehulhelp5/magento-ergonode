import {
    expect,
    test,
} from "./support/language-test";

import {
    openErgonodeAdminPage,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

test(
    "[ERG-LANG-001] Admin can inspect language mappings",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-001" },
            {
                type: "description",
                description:
                    "Inspect language and Store View panels, manual pairing, unlinking, autosave states and visibility controls. Save responses are mocked.",
            },
            { type: "kind", description: "browser-mocked" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
            {
                type: "data-policy",
                description:
                    "read-only; mocks manual mapping and unlink autosaves and restores visibility toggles",
            },
        ],
    },
    async ({ e2e }) => {
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            "#ergonode-language-mapping",
        );

        await expect(
            workspace.locator('[data-role="source-panel"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="source-search"]'),
        ).toHaveCount(2);
        await expect(
            workspace.locator('[data-role="save-mapping"]'),
        ).toHaveCount(0);
        const autosaveRegion = workspace.locator(
            '[data-role="autosave-region"]',
        );

        await expect(
            workspace.locator('[data-role="autosave-status"]'),
        ).toHaveCount(0);
        await expect(autosaveRegion).toHaveAttribute(
            "data-autosave-state",
            "saved",
        );
        await expect(autosaveRegion).toHaveAttribute("aria-busy", "false");
        await expect(autosaveRegion).not.toHaveAttribute("inert");
        await expect(
            workspace.locator('[data-role="entity-options-placeholder"]'),
        ).toHaveCount(0);

        const sourcePanel = workspace.locator(
            '[data-role="source-panel"][data-source-panel="ergo"]',
        );
        const sourceOptions = sourcePanel.locator(
            "[data-language-source-options]",
        );
        const removalZone = sourcePanel.locator(
            '[data-role="disable-drop-zone"]',
        );
        const sourceOptionsTrigger = sourceOptions.locator("summary");
        const refresh = sourceOptions.locator('[data-role="refresh-ergonode"]');
        const mappingOptions = workspace.locator(
            '[data-role="mapping-panel"] [data-language-mapping-options]',
        );
        const autoConnect = mappingOptions.locator('[data-role="auto-match"]');
        const excluded = sourceOptions.locator(
            '[data-role="visibility-toggle"]',
        );
        const excludedItems = sourcePanel.locator(
            '[data-role="entity-card"] [data-role="source-active-toggle"]' +
                '[aria-pressed="false"]',
        );
        const magentoPanel = workspace.locator(
            '[data-role="source-panel"][data-source-panel="magento"]',
        );
        const magentoCards = magentoPanel.locator(
            '[data-role="entity-card"]',
        );
        const magentoSourceOptions = magentoPanel.locator(
            '[data-source-options="magento"]',
        );
        const magentoSourceOptionsTrigger =
            magentoSourceOptions.locator("summary");
        const draggableMagentoCards = magentoPanel.locator(
            '[data-role="entity-card"]:visible:not(.is-mapped)',
        );
        const magentoExcluded = magentoPanel.locator(
            '[data-role="visibility-toggle"]',
        );
        const magentoExcludedItems = magentoPanel.locator(
            '[data-role="entity-card"] [data-role="source-active-toggle"]' +
                '[aria-pressed="false"]',
        );

        await expect(removalZone).toHaveAttribute(
            "data-drop-action",
            "remove-from-list",
        );
        await expect(removalZone).toContainText("Remove from list");
        await expect(
            magentoPanel.locator('[data-role="disable-drop-zone"]'),
        ).toHaveCount(0);
        await expect(magentoPanel).not.toContainText(
            "Upuść tutaj Magento Store View",
        );
        if ((await draggableMagentoCards.count()) > 0) {
            await draggableMagentoCards.first().evaluate((card) => {
                card.dispatchEvent(
                    new DragEvent("dragstart", {
                        bubbles: true,
                        dataTransfer: new DataTransfer(),
                    }),
                );
            });
            await expect(workspace).toHaveClass(/is-dragging-magento/);
            await expect(
                magentoPanel.locator('[data-role="disable-drop-zone"]'),
            ).toHaveCount(0);
            await expect(magentoPanel).not.toContainText(
                "Upuść tutaj Magento Store View",
            );
            await draggableMagentoCards.first().dispatchEvent("dragend");
        }

        const firstLanguage = sourcePanel.locator(
            '[data-role="entity-card"]:visible',
        ).first();
        if ((await firstLanguage.count()) > 0) {
            await expect(
                firstLanguage.locator(
                    ':scope > [data-role="source-active-toggle"]',
                ),
            ).toHaveCount(0);
            await expect(
                firstLanguage.locator(
                    '[data-role="entity-options"] > summary',
                ),
            ).toBeVisible();
            await firstLanguage.locator('[data-role="entity-options"] > summary').click();
            await expect(
                firstLanguage.locator('[data-role="entity-delete-snapshot"]'),
            ).toHaveAccessibleName("Remove from list");
        }

        const manualLanguage = sourcePanel
            .locator('[data-role="entity-card"]:visible')
            .first();
        const manualStore = magentoPanel
            .locator(
                '[data-role="entity-card"]:visible:not(.is-mapped)' +
                    ':not([data-store-code="admin"])',
            )
            .first();
        if (
            (await manualLanguage.count()) > 0 &&
            (await manualStore.count()) > 0
        ) {
            const manualLanguageCode =
                await manualLanguage.getAttribute("data-code");
            const manualStoreCode = await manualStore.getAttribute("data-code");
            const manualStoreLabel =
                await manualStore.getAttribute("data-label");
            if (
                manualLanguageCode === null ||
                manualStoreCode === null ||
                manualStoreLabel === null
            ) {
                throw new Error("Manual mapping source data is incomplete.");
            }
            const exactManualStore = magentoPanel.locator(
                `[data-role="entity-card"][data-code="${manualStoreCode}"]`,
            );
            const mappingRows = workspace.locator('[data-role="mapping-row"]');
            const languageDraft = workspace.locator(
                '[data-role="mapping-row"]:has(' +
                    `[data-side="ergo"][data-code="${manualLanguageCode}"]` +
                    '):has([data-side="magento"][data-code=""])',
            );
            const adminMappings = workspace.locator(
                '[data-role="mapping-row"]:has(' +
                    '[data-side="magento"][data-store-code="admin"]' +
                    ')',
            );
            const rowCountBeforeMapping = await mappingRows.count();
            const draftCountBeforeMapping = await languageDraft.count();
            const adminCountBeforeMapping = await adminMappings.count();
            let signalSaveStarted: (() => void) | null = null;
            const nextSave = () =>
                new Promise<void>((resolve) => {
                    signalSaveStarted = resolve;
                });

            await e2e.page.route("**/*", async (route) => {
                const request = route.request();

                if (
                    request.method() === "POST" &&
                    request.postData()?.includes("payload=")
                ) {
                    signalSaveStarted?.();
                    signalSaveStarted = null;
                    await route.fulfill({
                        status: 200,
                        contentType: "application/json",
                        body: JSON.stringify({ success: true, revision: "b".repeat(64) }),
                    });
                    return;
                }
                await route.continue();
            });
            const mappingSaveStarted = nextSave();
            const dataTransfer = await e2e.page.evaluateHandle(
                () => new DataTransfer(),
            );

            await manualLanguage.dispatchEvent("dragstart", { dataTransfer });
            await exactManualStore.dispatchEvent("dragover", { dataTransfer });
            await expect(exactManualStore).toHaveClass(/is-drop-ready/);
            await exactManualStore.dispatchEvent("drop", { dataTransfer });
            await manualLanguage.dispatchEvent("dragend", { dataTransfer });
            await mappingSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            await expect(mappingRows).toHaveCount(
                rowCountBeforeMapping + (draftCountBeforeMapping > 0 ? 0 : 1),
            );
            const completedRow = workspace.locator(
                '[data-role="mapping-row"]:has(' +
                    `[data-side="ergo"][data-code="${manualLanguageCode}"]` +
                    `):has([data-side="magento"][data-code="${manualStoreCode}"])`,
            );

            await expect(completedRow).toHaveCount(1);
            await expect(adminMappings).toHaveCount(adminCountBeforeMapping);
            const cleanupSaveStarted = nextSave();

            await completedRow
                .locator(':scope > [data-role="unlink-mapping"]')
                .click();
            await cleanupSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );

            const languageFirstDraftSaveStarted = nextSave();

            await manualLanguage.dispatchEvent("dblclick");
            await languageFirstDraftSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            const languageFirstTargetRow = workspace
                .locator(
                    '[data-role="mapping-row"]:has(' +
                        `[data-side="ergo"][data-code="${manualLanguageCode}"]` +
                        ')',
                )
                .first();

            await expect(languageFirstTargetRow).toHaveCount(1);
            const languageFirstCompletionSaveStarted = nextSave();
            const languageFirstTransfer = await e2e.page.evaluateHandle(
                () => new DataTransfer(),
            );

            await exactManualStore.dispatchEvent("dragstart", {
                dataTransfer: languageFirstTransfer,
            });
            await languageFirstTargetRow
                .locator('[data-side="magento"]')
                .dispatchEvent("dragover", {
                    dataTransfer: languageFirstTransfer,
                });
            await languageFirstTargetRow
                .locator('[data-side="magento"]')
                .dispatchEvent("drop", {
                    dataTransfer: languageFirstTransfer,
                });
            await exactManualStore.dispatchEvent("dragend", {
                dataTransfer: languageFirstTransfer,
            });
            await languageFirstCompletionSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            await expect(completedRow).toHaveCount(1);
            await expect(completedRow).toContainText(manualStoreLabel);

            const languageFirstCleanupSaveStarted = nextSave();

            await completedRow
                .locator(':scope > [data-role="unlink-mapping"]')
                .click();
            await languageFirstCleanupSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );

            const storeFirstDraftSaveStarted = nextSave();

            await exactManualStore.dispatchEvent("dblclick");
            await storeFirstDraftSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            const storeFirstDraft = workspace
                .locator(
                    '[data-role="mapping-row"]:has(' +
                        '[data-side="ergo"][data-code=""]' +
                        `):has([data-side="magento"][data-code="${manualStoreCode}"])`,
                )
                .first();

            await expect(storeFirstDraft).toHaveCount(1);
            const storeFirstCompletionSaveStarted = nextSave();
            const storeFirstTransfer = await e2e.page.evaluateHandle(
                () => new DataTransfer(),
            );

            await manualLanguage.dispatchEvent("dragstart", {
                dataTransfer: storeFirstTransfer,
            });
            await storeFirstDraft
                .locator('[data-side="ergo"]')
                .dispatchEvent("dragover", {
                    dataTransfer: storeFirstTransfer,
                });
            await storeFirstDraft
                .locator('[data-side="ergo"]')
                .dispatchEvent("drop", {
                    dataTransfer: storeFirstTransfer,
                });
            await manualLanguage.dispatchEvent("dragend", {
                dataTransfer: storeFirstTransfer,
            });
            await storeFirstCompletionSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            await expect(completedRow).toHaveCount(1);
            await expect(completedRow).toContainText(manualLanguageCode);

            const storeFirstCleanupSaveStarted = nextSave();

            await completedRow
                .locator(':scope > [data-role="unlink-mapping"]')
                .click();
            await storeFirstCleanupSaveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            await e2e.page.unroute("**/*");
        }

        const firstMapping = workspace.locator(
            '[data-role="mapping-row"]',
        ).first();
        if ((await firstMapping.count()) > 0) {
            const mappingRows = workspace.locator('[data-role="mapping-row"]');
            const mappingCount = await mappingRows.count();
            let saveCalls = 0;
            let signalSaveStarted!: () => void;
            let finishSave!: () => void;
            const saveStarted = new Promise<void>((resolve) => {
                signalSaveStarted = resolve;
            });
            const saveHeld = new Promise<void>((resolve) => {
                finishSave = resolve;
            });

            await expect(
                firstMapping.locator(
                    '[data-role="pair-slot"] [data-role="unlink-mapping"]',
                ),
            ).toHaveCount(0);
            await expect(
                firstMapping.locator(
                    ':scope > .vea-link-indicator[data-role="unlink-mapping"]',
                ),
            ).toHaveAccessibleName("Unlink mapping");
            await e2e.page.route("**/*", async (route) => {
                const request = route.request();

                if (
                    request.method() === "POST" &&
                    request.postData()?.includes("payload=")
                ) {
                    saveCalls += 1;
                    signalSaveStarted();
                    await saveHeld;
                    await route.fulfill({
                        status: 200,
                        contentType: "application/json",
                        body: JSON.stringify({ success: true, revision: "b".repeat(64) }),
                    });
                    return;
                }
                await route.continue();
            });
            await firstMapping
                .locator(':scope > [data-role="unlink-mapping"]')
                .dispatchEvent("click");
            await saveStarted;
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saving",
            );
            await expect(autosaveRegion).toHaveAttribute("aria-busy", "true");
            await expect(autosaveRegion).toHaveAttribute("inert", "");
            await expect(sourcePanel).toHaveCSS("opacity", "0.52");
            finishSave();
            await expect(mappingRows).toHaveCount(mappingCount - 1);
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
            await expect(autosaveRegion).toHaveAttribute("aria-busy", "false");
            await expect(autosaveRegion).not.toHaveAttribute("inert");
            await expect(sourcePanel).toHaveCSS("opacity", "1");
            expect(saveCalls).toBe(1);
            await e2e.page.unroute("**/*");
        }

        await expect(sourceOptionsTrigger).toBeVisible();
        await expect(magentoSourceOptionsTrigger).toBeVisible();
        await magentoSourceOptionsTrigger.click();
        await expect(magentoExcluded).toBeVisible();
        await expect(magentoExcluded).toHaveAttribute(
            "aria-label",
            /Store View/,
        );
        await expect(
            magentoExcluded.locator(".veui-visibility-icon"),
        ).toBeVisible();
        await expect(
            magentoExcluded.locator(".veui-entity-options-action-label"),
        ).toHaveText("Excluded");
        const visibilityIconAlignment = await magentoExcluded.evaluate(
            (button) => {
                const icon = button.querySelector(".veui-visibility-icon");
                const label = button.querySelector(".veui-entity-options-action-label");
                const iconBox = icon?.getBoundingClientRect();
                const labelBox = label?.getBoundingClientRect();

                if (!iconBox || !labelBox) {
                    return null;
                }

                return {
                    gap: labelBox.left - iconBox.right,
                    vertical: Math.abs(
                        labelBox.top + labelBox.height / 2 -
                            (iconBox.top + iconBox.height / 2),
                    ),
                };
            },
        );

        expect(visibilityIconAlignment).not.toBeNull();
        expect(visibilityIconAlignment?.gap).toBeGreaterThan(0);
        expect(visibilityIconAlignment?.vertical).toBeLessThanOrEqual(0.5);
        await expect(sourceOptions.locator('[data-role="auto-match"]')).toHaveCount(0);
        await mappingOptions.locator("summary").click();
        await expect(autoConnect).toBeVisible();
        const expectedAutoConnections = await workspace.evaluate((root) => {
            const active = (card: Element) =>
                card.querySelector('[data-role="source-active-toggle"]')
                    ?.getAttribute("aria-pressed") !== "false";
            const languageCodes = Array.from(
                root.querySelectorAll(
                    '[data-role="entity-card"][data-source="ergo"]',
                ),
            )
                .filter(active)
                .map((card) =>
                    (card.getAttribute("data-code") || "")
                        .toLocaleLowerCase()
                        .replace("-", "_"),
                );

            return Array.from(
                root.querySelectorAll(
                    '[data-role="entity-card"][data-source="magento"]:not(.is-mapped)',
                ),
            ).filter((card) => {
                const locale = (card.getAttribute("data-locale") || "")
                    .toLocaleLowerCase()
                    .replace("-", "_");
                const prefix = locale.split("_")[0];

                return (
                    active(card) &&
                    languageCodes.some(
                        (code) =>
                            code === locale || code.split("_")[0] === prefix,
                    )
                );
            }).length;
        });

        await expect(autoConnect).toHaveAttribute(
            "data-available-count",
            String(expectedAutoConnections),
        );
        if (expectedAutoConnections === 0) {
            await expect(autoConnect).toBeDisabled();
            await expect(
                autoConnect.locator('[data-role="auto-match-count"]'),
            ).toBeHidden();
        } else {
            await expect(autoConnect).toBeEnabled();
            await expect(
                autoConnect.locator('[data-role="auto-match-count"]'),
            ).toHaveText(String(expectedAutoConnections));
        }
        await sourceOptionsTrigger.click();
        await expect(excluded).toBeVisible();
        if ((await sourcePanel.getAttribute("data-can-refresh")) === "1") {
            await expect(refresh).toBeVisible();
        } else {
            await expect(refresh).toHaveCount(0);
        }

        if ((await excludedItems.count()) === 0) {
            await expect(excluded).toBeDisabled();
        } else {
            const magentoHiddenBefore = await magentoCards.evaluateAll((cards) =>
                cards.map((card) => (card as HTMLElement).hidden),
            );

            await expect(excluded).toBeEnabled();
            await excluded.click();
            await expect(excluded).toHaveAttribute("aria-pressed", "true");
            expect(
                await magentoCards.evaluateAll((cards) =>
                    cards.map((card) => (card as HTMLElement).hidden),
                ),
            ).toEqual(magentoHiddenBefore);
            await sourceOptionsTrigger.click();
            await excluded.click();
            await expect(excluded).toHaveAttribute("aria-pressed", "false");
            await expect(autosaveRegion).toHaveAttribute(
                "data-autosave-state",
                "saved",
            );
        }

        await magentoSourceOptionsTrigger.click();
        if ((await magentoExcludedItems.count()) === 0) {
            await expect(magentoExcluded).toBeDisabled();
        } else {
            const languageHiddenBefore = await sourcePanel
                .locator('[data-role="entity-card"]')
                .evaluateAll((cards) =>
                    cards.map((card) => (card as HTMLElement).hidden),
                );

            await expect(magentoExcluded).toBeEnabled();
            await magentoExcluded.click();
            await expect(magentoExcluded).toHaveAttribute(
                "aria-pressed",
                "true",
            );
            expect(
                await magentoExcludedItems.evaluateAll((toggles) =>
                    toggles.map(
                        (toggle) =>
                            (toggle.closest(
                                '[data-role="entity-card"]',
                            ) as HTMLElement).hidden,
                    ),
                ),
            ).toEqual(
                Array(await magentoExcludedItems.count()).fill(false),
            );
            expect(
                await sourcePanel
                    .locator('[data-role="entity-card"]')
                    .evaluateAll((cards) =>
                        cards.map((card) => (card as HTMLElement).hidden),
                    ),
            ).toEqual(languageHiddenBefore);
            await magentoSourceOptionsTrigger.click();
            await magentoExcluded.click();
            await expect(magentoExcluded).toHaveAttribute(
                "aria-pressed",
                "false",
            );
            expect(
                await magentoExcludedItems.evaluateAll((toggles) =>
                    toggles.map(
                        (toggle) =>
                            (toggle.closest(
                                '[data-role="entity-card"]',
                            ) as HTMLElement).hidden,
                    ),
                ),
            ).toEqual(
                Array(await magentoExcludedItems.count()).fill(true),
            );
        }
    },
);

test(
    "[ERG-LANG-002] Admin can drag language mappings and remove a language",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-002" },
            {
                type: "description",
                description:
                    "Drag language mappings in both directions and verify snapshot-removal requests. Save and removal responses are mocked.",
            },
            { type: "kind", description: "browser-mocked" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
            {
                type: "data-policy",
                description:
                    "read-only; mocks mapping autosaves and language snapshot removal",
            },
        ],
    },
    async ({ e2e }) => {
        const workspace = await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            "#ergonode-language-mapping",
        );
        const languagePanel = workspace.locator(
            '[data-role="source-panel"][data-source-panel="ergo"]',
        );
        const storePanel = workspace.locator(
            '[data-role="source-panel"][data-source-panel="magento"]',
        );
        const languageCards = languagePanel.locator(
            '[data-role="entity-card"]:visible',
        );
        const mappedLanguageCodes = await workspace
            .locator(
                '[data-role="mapping-row"] ' +
                    '[data-role="pair-slot"][data-side="ergo"][data-code]:not([data-code=""])',
            )
            .evaluateAll((slots) =>
                slots.map((slot) => slot.getAttribute("data-code") || ""),
            );
        const languageIndex = await languageCards.evaluateAll(
            (cards, mappedCodes) =>
                cards.findIndex(
                    (card) =>
                        !mappedCodes.includes(
                            card.getAttribute("data-code") || "",
                        ),
                ),
            mappedLanguageCodes,
        );
        const language = languageCards.nth(
            languageIndex >= 0 ? languageIndex : 0,
        );
        const store = storePanel
            .locator(
                '[data-role="entity-card"]:visible:not(.is-mapped)' +
                    ':not([data-store-code="admin"])',
            )
            .first();

        test.skip(
            languageIndex < 0 || (await store.count()) === 0,
            "The environment needs an unmapped active language and Store View.",
        );

        const languageCode = await language.getAttribute("data-code");
        const storeCode = await store.getAttribute("data-code");

        if (languageCode === null || storeCode === null) {
            throw new Error("Drag-and-drop source data is incomplete.");
        }

        let saveCalls = 0;
        let removalCalls = 0;

        await e2e.page.route("**/*", async (route) => {
            const request = route.request();

            if (request.method() !== "POST") {
                await route.continue();
                return;
            }
            if (request.postData()?.includes("payload=")) {
                saveCalls += 1;
                await route.fulfill({
                    status: 200,
                    contentType: "application/json",
                    body: JSON.stringify({ success: true, revision: "b".repeat(64) }),
                });
                return;
            }
            if (
                request
                    .postData()
                    ?.includes(`code=${encodeURIComponent(languageCode)}`)
            ) {
                removalCalls += 1;
                await route.fulfill({
                    status: 200,
                    contentType: "application/json",
                    body: JSON.stringify({
                        success: false,
                        message: "Snapshot removal mocked by Playwright.",
                    }),
                });
                return;
            }
            await route.continue();
        });

        const adminStore = storePanel.locator(
            '[data-role="entity-card"][data-store-code="admin"]',
        );

        await expect(adminStore).toHaveCount(1);
        await expect(
            language.locator('[data-role="entity-options"]'),
        ).toHaveCount(1);
        const adminStoreState = await adminStore.evaluate((card) => {
            const wasMapped = card.classList.contains("is-mapped");
            const ariaDisabled = card.getAttribute("aria-disabled");

            card.classList.remove("is-mapped");
            card.removeAttribute("aria-disabled");

            return { wasMapped, ariaDisabled };
        });
        const adminDragTransfer = await e2e.page.evaluateHandle(
            () => new DataTransfer(),
        );

        await adminStore.dispatchEvent("dragstart", {
            dataTransfer: adminDragTransfer,
        });
        await expect(adminStore).toHaveClass(/is-dragging/);
        await expect
            .poll(() =>
                adminStore.evaluate((card) =>
                    window.getComputedStyle(card).opacity,
                ),
            )
            .toBe("0");
        const adminDragPreview = e2e.page.locator(
            "body > .vel-source-drag-preview",
        );

        await expect(adminDragPreview).toHaveCount(1);
        await expect(adminDragPreview).toHaveClass(/is-captured/);
        await expect(
            adminDragPreview.locator('button, details, [role="tooltip"]'),
        ).toHaveCount(0);
        await adminStore.dispatchEvent("dragend");
        await expect(adminDragPreview).toHaveCount(0);
        await expect(adminStore).not.toHaveClass(/is-dragging/);
        await adminStore.evaluate((card, state) => {
            card.classList.toggle("is-mapped", state.wasMapped);
            if (state.ariaDisabled === null) {
                card.removeAttribute("aria-disabled");
            } else {
                card.setAttribute("aria-disabled", state.ariaDisabled);
            }
        }, adminStoreState);
        await adminDragTransfer.dispose();

        const exactStore = storePanel.locator(
            `[data-role="entity-card"][data-code="${storeCode}"]`,
        );
        const exactStoreBox = await exactStore.boundingBox();

        if (exactStoreBox === null) {
            throw new Error("Store View card is not visible for pointer drag.");
        }

        const storeDragOrigin = {
            x: 12,
            y: exactStoreBox.height / 2,
        };
        const completed = workspace.locator(
            '[data-role="mapping-row"]:has(' +
                `[data-side="ergo"][data-code="${languageCode}"]` +
                `):has([data-side="magento"][data-code="${storeCode}"])`,
        );

        const options = language.locator('[data-role="entity-options"]');

        await options.locator("summary").click();
        await options.locator('[data-role="entity-add-to-mapping"]').click();
        const languageTargetRow = workspace.locator(
            '[data-role="mapping-row"]:has(' +
                `[data-side="ergo"][data-code="${languageCode}"]` +
                ')',
        );

        await expect(languageTargetRow).toHaveCount(1);
        await exactStore.dragTo(
            languageTargetRow.first().locator('[data-side="magento"]'),
            { sourcePosition: storeDragOrigin },
        );

        await expect(completed).toHaveCount(1);
        await expect.poll(() => saveCalls).toBeGreaterThan(0);
        await completed
            .locator(':scope > [data-role="unlink-mapping"]')
            .click();

        await exactStore.dispatchEvent("dblclick");
        const storeDraft = workspace.locator(
            '[data-role="mapping-row"]:has(' +
                '[data-side="ergo"][data-code=""]' +
                `):has([data-side="magento"][data-code="${storeCode}"])`,
        );

        await expect(storeDraft).toHaveCount(1);
        await language.dragTo(storeDraft.first().locator('[data-side="ergo"]'));
        await expect(completed).toHaveCount(1);
        await completed
            .locator(':scope > [data-role="unlink-mapping"]')
            .click();

        await options.locator("summary").click();
        e2e.page.once("dialog", (dialog) => dialog.accept());
        await options.locator('[data-role="entity-delete-snapshot"]').click();
        await expect.poll(() => removalCalls).toBe(1);

        const removalZone = languagePanel.locator(
            '[data-role="disable-drop-zone"]',
        );

        await workspace.evaluate((root) =>
            root.classList.add("is-dragging-ergo"),
        );
        await expect(removalZone).toBeVisible();
        e2e.page.once("dialog", (dialog) => dialog.accept());
        await language.dragTo(removalZone);
        await expect.poll(() => removalCalls).toBe(2);
        await e2e.page.unroute("**/*");
    },
);
