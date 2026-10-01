import type { BrowserContext, Page } from "@playwright/test";
import { createHash } from "node:crypto";

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

const username = "pw_template_publisher_acl";
const workspaceSelector = "#ergonode-template-admin";

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

async function addAttributeSetDraft(page: Page): Promise<void> {
    await expect
        .poll(() =>
            page.locator(workspaceSelector).evaluate((element) =>
                Boolean(
                    (element as HTMLElement & { veaWorkspace?: unknown })
                        .veaWorkspace,
                ),
            ),
        )
        .toBe(true);
    const source = page
        .locator(
            `${workspaceSelector} [data-role="unmapped-set-list"] ` +
                '[data-drag-type="attribute_set"][draggable="true"]',
        )
        .first();
    await expect(source).toBeVisible();
    await source.focus();
    await source.press("Enter");
    await expect(
        page.locator(`${workspaceSelector} .vet-draft-card`),
    ).toBeVisible();
}

async function postCreateWithoutSavePermission(page: Page): Promise<number> {
    const config = projectConfig();
    const formKey = await page.evaluate(
        () => (window as Window & { FORM_KEY?: string }).FORM_KEY || "",
    );
    expect(formKey).not.toBe("");
    const secretKey = createHash("sha256")
        .update(`ergonode_template_publicationtemplatecreate${formKey}`)
        .digest("hex");
    const adminPath = config.magento.adminPath || "admin";
    const url =
        `${config.magento.baseUrl.replace(/\/$/, "")}/${adminPath}/` +
        "ergonode_template_publication/template/create/" +
        `key/${secretKey}/`;
    const response = await page.request.post(url, {
        form: {
            form_key: formKey,
            template_code: "pw_acl_must_not_execute",
            attribute_set_id: "0",
        },
        headers: { "X-Requested-With": "XMLHttpRequest" },
        maxRedirects: 0,
    });

    return response.status();
}

test(
    "[ERG-TPL-PUB-001] Template creation action follows the endpoint save permission",
    {
        annotation: [
            { type: "id", description: "ERG-TPL-PUB-001" },
            {
                type: "description",
                description:
                    "A real mapping-only Magento role receives neither the template publication initializer nor action, and its direct POST is denied before the service; the configured control account receives the action.",
            },
            { type: "kind", description: "e2e" },
            {
                type: "module",
                description: "Ergonode_TemplatePublisherAdminUi",
            },
            {
                type: "requires-module",
                description: "Ergonode_TemplateAdminUi",
            },
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
                    "Local only. support/run-isolated.php creates and removes one temporary mapping-only role/account. Incomplete browser drafts do not change mappings and no Ergonode write is invoked.",
            },
        ],
    },
    async ({ e2e }) => {
        let limitedContext: BrowserContext | undefined;
        try {
            await loginToErgonodeAdmin(e2e);
            await openErgonodeAdminPage(
                e2e,
                "ergonode/template/index",
                workspaceSelector,
            );
            await addAttributeSetDraft(e2e.page);
            await expect(
                e2e.page.locator('[data-role="create-ergonode-template"]'),
            ).toHaveCount(1);

            limitedContext = await e2e.page
                .context()
                .browser()!
                .newContext({ ignoreHTTPSErrors: true });
            const limitedPage = await limitedContext.newPage();
            await loginLimited(limitedPage);
            const documentPromise = limitedPage.waitForResponse(
                (response) =>
                    response.request().isNavigationRequest() &&
                    response.url().includes("/ergonode/template/index/"),
            );
            const [, documentResponse] = await Promise.all([
                openErgonodeAdminPage(
                    { page: limitedPage },
                    "ergonode/template/index",
                    workspaceSelector,
                ),
                documentPromise,
            ]);
            expect(await documentResponse.text()).not.toContain(
                "Ergonode_TemplatePublisherAdminUi/js/template-publisher",
            );
            expect(await postCreateWithoutSavePermission(limitedPage)).toBe(403);
            await addAttributeSetDraft(limitedPage);
            await expect(
                limitedPage.locator('[data-role="create-ergonode-template"]'),
            ).toHaveCount(0);
        } finally {
            await limitedContext?.close();
        }
    },
);
