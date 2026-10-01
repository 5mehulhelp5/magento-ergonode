import { test, expect, fixtureCode } from "./support/snapshot-environment";
import { openErgonodeAdminPage } from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";
import {
    languageCard,
    ready,
    snapshot,
    workspaceSelector,
} from "./support/language-workspace";
import {
    refresh,
    removeButton,
    removeSnapshot,
    sourceMenu,
} from "./support/snapshot-actions";

test(
    "[ERG-LANG-007] Paginated refresh persists the new snapshot after reload",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-007" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Real browser, Magento controllers and database; a controlled upstream GraphQL server returns two pages.",
            },
            {
                type: "data-policy",
                description:
                    "Local isolated API only. Creates and removes temporary limited users/roles and one reserved snapshot language. Preserves original snapshot, mappings and visibility. Run through support/run-isolated.php to restore connection configuration.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
        ],
    },
    async ({ e2e, languageEnv }) => {
        const page = e2e.page;
        await languageEnv.login(page);
        await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            workspaceSelector,
        );
        const before = await languageEnv.codes();
        const mappingBefore = (await snapshot(await ready(page))).mappings;
        await refresh(page, true);
        expect(languageEnv.requests).toEqual([null, "language-page-2"]);
        expect(await languageEnv.codes()).toEqual(
            [...before, fixtureCode].sort(),
        );
        await expect(
            languageCard(page.locator(workspaceSelector), fixtureCode),
        ).toBeVisible();
        expect((await snapshot(await ready(page))).mappings).toEqual(
            mappingBefore,
        );
        await page.reload({ waitUntil: "domcontentloaded" });
        await ready(page);
        await expect(
            languageCard(page.locator(workspaceSelector), fixtureCode),
        ).toBeVisible();
        await removeSnapshot(page);
        expect(await languageEnv.codes()).toEqual(before);
    },
);

test(
    "[ERG-LANG-008] Failed second refresh page preserves the snapshot and allows retry",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-008" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "A real upstream GraphQL error on page two cannot persist page one or reload the page; explicit retry succeeds.",
            },
            {
                type: "data-policy",
                description:
                    "Local isolated API only. Creates and removes temporary limited users/roles and one reserved snapshot language. Preserves original snapshot, mappings and visibility. Run through support/run-isolated.php to restore connection configuration.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
        ],
    },
    async ({ e2e, languageEnv }) => {
        const page = e2e.page;
        await languageEnv.login(page);
        await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            workspaceSelector,
        );
        const beforeCodes = await languageEnv.codes();
        const before = await snapshot(await ready(page));
        let navigations = 0;
        page.on("domcontentloaded", () => {
            navigations++;
        });
        languageEnv.failSecondPage = true;
        await refresh(page, false);
        expect(languageEnv.requests).toEqual([null, "language-page-2"]);
        expect(navigations).toBe(0);
        expect(await languageEnv.codes()).toEqual(beforeCodes);
        expect(await snapshot(await ready(page))).toEqual(before);
        await expect(page.locator(workspaceSelector)).toContainText(
            "Controlled second-page failure",
        );
        languageEnv.failSecondPage = false;
        await refresh(page, true);
        expect(languageEnv.requests).toEqual([
            null,
            "language-page-2",
            null,
            "language-page-2",
        ]);
        expect(await languageEnv.codes()).toEqual(
            [...beforeCodes, fixtureCode].sort(),
        );
        await removeSnapshot(page);
    },
);

test(
    "[ERG-LANG-009] Snapshot removal can be cancelled and persists only after confirmation",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-009" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Cancelling confirmation sends no delete; accepting uses real Magento and the language stays removed after reload.",
            },
            {
                type: "data-policy",
                description:
                    "Local isolated API only. Creates and removes temporary limited users/roles and one reserved snapshot language. Preserves original snapshot, mappings and visibility. Run through support/run-isolated.php to restore connection configuration.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
        ],
    },
    async ({ e2e, languageEnv }) => {
        await languageEnv.seed();
        const page = e2e.page;
        await languageEnv.login(page);
        await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            workspaceSelector,
        );
        await ready(page);
        let deletions = 0;
        page.on("request", (request) => {
            if (
                request.method() === "POST" &&
                request.url().includes("/ergonode/language/deleteSnapshot/")
            )
                deletions++;
        });
        const button = await removeButton(page);
        page.once("dialog", (dialog) => dialog.dismiss());
        await button.click();
        expect(deletions).toBe(0);
        expect(await languageEnv.codes()).toContain(fixtureCode);
        await expect(
            languageCard(page.locator(workspaceSelector), fixtureCode),
        ).toBeVisible();
        await removeSnapshot(page);
        expect(deletions).toBe(1);
        expect(await languageEnv.codes()).not.toContain(fixtureCode);
        await page.reload({ waitUntil: "domcontentloaded" });
        await ready(page);
        await expect(
            languageCard(page.locator(workspaceSelector), fixtureCode),
        ).toHaveCount(0);
    },
);

test(
    "[ERG-LANG-010] Deleting an already removed snapshot shows an error without changing other data",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-010" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Two real tabs delete the same language. The stale tab receives success=false, retains its view and can reload authoritative state.",
            },
            {
                type: "data-policy",
                description:
                    "Local isolated API only. Creates and removes temporary limited users/roles and one reserved snapshot language. Preserves original snapshot, mappings and visibility. Run through support/run-isolated.php to restore connection configuration.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
        ],
    },
    async ({ e2e, languageEnv }) => {
        await languageEnv.seed();
        const page = e2e.page;
        await languageEnv.login(page);
        await openErgonodeAdminPage(
            e2e,
            "ergonode/language/index",
            workspaceSelector,
        );
        await ready(page);
        const stale = await page.context().newPage();
        try {
            await stale.goto(page.url(), { waitUntil: "domcontentloaded" });
            await ready(stale);
            await removeSnapshot(page);
            const beforeCodes = await languageEnv.codes();
            let navigations = 0;
            stale.on("domcontentloaded", () => {
                navigations++;
            });
            await removeSnapshot(stale, false);
            expect(navigations).toBe(0);
            expect(await languageEnv.codes()).toEqual(beforeCodes);
            await expect(stale.locator(workspaceSelector)).toContainText(
                "is not available in the local snapshot",
            );
            await expect(
                languageCard(stale.locator(workspaceSelector), fixtureCode),
            ).toBeVisible();
            await stale.reload({ waitUntil: "domcontentloaded" });
            await ready(stale);
            await expect(
                languageCard(stale.locator(workspaceSelector), fixtureCode),
            ).toHaveCount(0);
        } finally {
            await stale.close();
        }
    },
);
