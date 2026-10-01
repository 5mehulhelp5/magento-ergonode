import {
    test,
    expect,
    fixtureCode,
    type Role,
} from "./support/snapshot-environment";
import { openErgonodeAdminPage } from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";
import { ready, workspaceSelector } from "./support/language-workspace";
import { sourceMenu } from "./support/snapshot-actions";

test(
    "[ERG-LANG-011] Browser roles enforce separate save and refresh permissions",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-011" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "Temporary viewer and editor roles log in through real Magento. Refresh is hidden and forbidden; viewer save/delete are forbidden while editor save is allowed.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Temporary limited users/roles and one snapshot language are removed in teardown. Existing users and roles never change. Approved isolated connection is restored by support/run-isolated.php.",
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
        const before = await languageEnv.codes();
        for (const role of ["viewer", "editor"] as Role[]) {
            const context = await e2e.page
                .context()
                .browser()!
                .newContext({ ignoreHTTPSErrors: true });
            try {
                const page = await context.newPage();
                await languageEnv.login(page, role);
                const documentPromise = page
                    .waitForResponse(
                        (response) =>
                            response.request().isNavigationRequest() &&
                            response
                                .url()
                                .includes("/ergonode/language/index/"),
                    )
                    .then((response) => response.text());
                await openErgonodeAdminPage(
                    { page },
                    "ergonode/language/index",
                    workspaceSelector,
                );
                await ready(page);
                const menu = await sourceMenu(page);
                await expect(
                    menu.locator('[data-role="refresh-ergonode"]'),
                ).toHaveCount(0);
                const html = await documentPromise;
                const initializers = [
                    ...html.matchAll(
                        /<script type="text\/x-magento-init">([\s\S]*?)<\/script>/g,
                    ),
                ].map((match) => JSON.parse(match[1]));
                const config = initializers.find(
                    (value) => value[workspaceSelector],
                )[workspaceSelector][
                    "Ergonode_LanguageAdminUi/js/language-mapping"
                ];
                const results = await page.evaluate(
                    async ({ role, fixtureCode, config }) => {
                        const mappings = Array.from(
                            document.querySelectorAll(
                                '#ergonode-language-mapping [data-role="mapping-row"]',
                            ),
                            (row) => {
                                const left = row
                                    .querySelector('[data-side="ergo"]')
                                    ?.getAttribute("data-code");
                                const right = row
                                    .querySelector('[data-side="magento"]')
                                    ?.getAttribute("data-code");
                                return {
                                    left: left ? { code: left } : null,
                                    right: right ? { code: right } : null,
                                };
                            },
                        );
                        const results: Array<{
                            action: string;
                            status: number;
                            success?: boolean;
                        }> = [];
                        for (const action of role === "viewer"
                            ? ["save", "refresh", "delete_snapshot"]
                            : ["save", "refresh"]) {
                            const response = await fetch(config.urls[action], {
                                method: "POST",
                                headers: {
                                    "X-Requested-With": "XMLHttpRequest",
                                },
                                body: new URLSearchParams({
                                    form_key: config.form_key,
                                    code: fixtureCode,
                                    payload: JSON.stringify({
                                        mappings,
                                        visibility: [],
                                        revision: config.revision,
                                    }),
                                }),
                            });
                            const result =
                                response.status === 200
                                    ? await response.json()
                                    : {};
                            results.push({
                                action,
                                status: response.status,
                                success: result.success,
                            });
                        }
                        return results;
                    },
                    { role, fixtureCode, config },
                );
                for (const result of results) {
                    if (role === "editor" && result.action === "save") {
                        expect(result.status).toBe(200);
                        expect(result.success).toBe(true);
                    } else {
                        expect(
                            result.status,
                            role + " must be denied " + result.action,
                        ).toBe(403);
                    }
                }
                expect(await languageEnv.codes()).toEqual(before);
                expect(languageEnv.requests).toHaveLength(0);
                await expect(page.locator(".admin-user")).toBeVisible();
            } finally {
                await context.close();
            }
        }
    },
);

test(
    "[ERG-LANG-012] An authenticated user without mapping access cannot open its URL",
    {
        annotation: [
            { type: "id", description: "ERG-LANG-012" },
            { type: "module", description: "Ergonode_LanguageAdminUi" },
            { type: "requires-module", description: "Ergonode_Language" },
            { type: "kind", description: "e2e" },
            {
                type: "description",
                description:
                    "An authenticated limited role has no Languages menu entry and receives real HTTP 403 for a direct URL with its own valid Magento secret key.",
            },
            {
                type: "data-policy",
                description:
                    "Local only. Creates and removes one temporary denied role/account. Existing accounts, permissions, snapshot, mappings and visibility remain unchanged.",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
        ],
    },
    async ({ e2e, languageEnv }) => {
        const page = e2e.page;
        await languageEnv.login(page, "denied");
        await expect(
            page.locator(
                'li[data-ui-id^="menu-ergonode-"] > a[href*="/ergonode/language/index/"]',
            ),
        ).toHaveCount(0);
        const formKey = await page.evaluate(
            () => (window as typeof window & { FORM_KEY: string }).FORM_KEY,
        );
        expect(formKey).toMatch(/^[a-zA-Z0-9]+$/);
        const signed = await fetch(languageEnv.config.e2e?.signerUrl || "http://web:18090/language-index-key", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Language-Fixture-Key": "language-e2e-fixture",
            },
            body: JSON.stringify({ formKey }),
        });
        expect(signed.status).toBe(200);
        const { key } = await signed.json();
        expect(key).toMatch(/^[a-f0-9]{64}$/);
        const base = languageEnv.config.magento.baseUrl.replace(/\/$/, "");
        const admin = (languageEnv.config.magento.adminPath || "admin").replace(
            /^\/+|\/+$/g,
            "",
        );
        const response = await page.goto(
            `${base}/${admin}/ergonode/language/index/key/${key}/`,
            { waitUntil: "domcontentloaded" },
        );
        expect(response?.status()).toBe(403);
        await expect(page.locator(".admin-user")).toBeVisible();
        await expect(page.locator(workspaceSelector)).toHaveCount(0);
        expect(languageEnv.requests).toHaveLength(0);
    },
);
