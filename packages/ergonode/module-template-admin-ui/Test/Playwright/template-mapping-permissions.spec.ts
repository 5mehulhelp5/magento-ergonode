import type { BrowserContext, Page } from "@playwright/test";

import { projectConfig, magentoAccount } from "@vendivo/test-config";
import {
    E2EContext,
    expect,
    test,
} from "@packhauer/playwright-runner/fixtures";
import {
    loginToErgonodeAdmin,
    openErgonodeAdminPage,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

const username = "pw_template_mapping_acl";
const workspaceSelector = "#ergonode-template-admin";

type TemplateConfig = {
    templates: Array<{
        code: string;
        attribute_set_id?: number | null;
        active?: boolean;
    }>;
    attribute_sets: Array<{
        id: number;
        active?: boolean;
    }>;
    urls: { save_mapping: string };
    form_key: string;
};

type PostResult = {
    status: number;
    redirected: boolean;
    contentType: string;
    success?: boolean;
};

async function loginLimited(page: Page): Promise<void> {
    const config = projectConfig();
    const password = magentoAccount(config).password;
    const values = {
        MAGENTO_BASE_URL: config.magento.baseUrl,
        MAGENTO_ADMIN_PATH: config.magento.adminPath || "admin",
        MAGENTO_PLAYWRIGHT_USERNAME: username,
        MAGENTO_PLAYWRIGHT_PASSWORD: password,
    };
    const previous = Object.fromEntries(
        Object.keys(values).map((key) => [key, process.env[key]]),
    );
    Object.assign(process.env, values);
    try {
        await new E2EContext(page).login();
    } finally {
        for (const [key, value] of Object.entries(previous)) {
            if (value === undefined) delete process.env[key];
            else process.env[key] = value;
        }
    }
}

async function openWorkspace(page: Page): Promise<TemplateConfig> {
    await openErgonodeAdminPage(
        { page },
        "ergonode/template/index",
        workspaceSelector,
    );
    const initializers = await page
        .locator('script[type="text/x-magento-init"]')
        .allTextContents();
    for (const initializer of initializers) {
        const value = JSON.parse(initializer);
        const config = value[workspaceSelector]?.[
            "Ergonode_TemplateAdminUi/js/template-admin"
        ];
        if (config) return config as TemplateConfig;
    }
    throw new Error("Template workspace initializer was not found.");
}

async function postCurrentSnapshot(
    page: Page,
    config: TemplateConfig,
    formKey: string,
): Promise<PostResult> {
    return page.evaluate(
        async ({ config, formKey }) => {
            const mappings = Object.fromEntries(
                config.templates
                    .filter(
                        (template) =>
                            template.attribute_set_id !== null &&
                            template.attribute_set_id !== undefined,
                    )
                    .map((template) => [
                        String(template.code),
                        Number(template.attribute_set_id),
                    ]),
            );
            const visibility = config.templates
                .map((template) => ({
                    source: "ergo",
                    code: String(template.code),
                    active: template.active !== false,
                }))
                .concat(
                    config.attribute_sets.map((attributeSet) => ({
                        source: "magento",
                        code: String(attributeSet.id),
                        active: attributeSet.active !== false,
                    })),
                );
            const response = await fetch(config.urls.save_mapping, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded; charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: new URLSearchParams({
                    form_key: formKey,
                    mappings: JSON.stringify(mappings),
                    visibility: JSON.stringify(visibility),
                }),
            });
            const contentType = response.headers.get("content-type") || "";
            let success: boolean | undefined;
            if (contentType.toLowerCase().includes("application/json")) {
                try {
                    success = Boolean((await response.json()).success);
                } catch {
                    success = undefined;
                }
            }
            return {
                status: response.status,
                redirected: response.redirected,
                contentType,
                success,
            };
        },
        { config, formKey },
    );
}

test(
    "[ERG-TPL-003] ACL and form-key guards reject mapping writes",
    {
        annotation: [
            { type: "id", description: "ERG-TPL-003" },
            {
                type: "description",
                description:
                    "A real mapping-only Magento role receives HTTP 403 from the save endpoint, while empty and invalid form keys are rejected for the configured control account.",
            },
            { type: "kind", description: "e2e" },
            { type: "module", description: "Ergonode_TemplateAdminUi" },
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
                    "Local only. support/run-isolated.php creates and removes one temporary mapping-only role/account and verifies that mappings and visibility are unchanged.",
            },
        ],
    },
    async ({ e2e }) => {
        await loginToErgonodeAdmin(e2e);
        const controlConfig = await openWorkspace(e2e.page);

        for (const formKey of ["", "invalid-form-key"]) {
            const result = await postCurrentSnapshot(
                e2e.page,
                controlConfig,
                formKey,
            );
            expect(result.success).not.toBe(true);
            expect(
                result.status === 400 ||
                    result.status === 403 ||
                    result.redirected ||
                    result.success === false,
            ).toBe(true);
        }

        let limitedContext: BrowserContext | undefined;
        try {
            limitedContext = await e2e.page
                .context()
                .browser()!
                .newContext({ ignoreHTTPSErrors: true });
            const limitedPage = await limitedContext.newPage();
            await loginLimited(limitedPage);
            const limitedConfig = await openWorkspace(limitedPage);
            const result = await postCurrentSnapshot(
                limitedPage,
                limitedConfig,
                limitedConfig.form_key,
            );

            expect(result.status).toBe(403);
            expect(result.success).not.toBe(true);
            await expect(limitedPage.locator(".admin-user")).toBeVisible();
        } finally {
            await limitedContext?.close();
        }
    },
);
