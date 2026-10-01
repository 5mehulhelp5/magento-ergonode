import { expect, type Locator } from "@playwright/test";

import type { Page } from "@playwright/test";
import { projectConfig, magentoAccount } from "@vendivo/test-config";

export async function loginToErgonodeAdmin(
    e2e: { login(): Promise<void> },
    settings = projectConfig(),
): Promise<void> {
    const account = magentoAccount(settings);
    process.env.MAGENTO_BASE_URL = settings.magento.baseUrl;
    process.env.MAGENTO_ADMIN_PATH = settings.magento.adminPath || "admin";
    process.env.MAGENTO_PLAYWRIGHT_USERNAME = account.username;
    process.env.MAGENTO_PLAYWRIGHT_PASSWORD = account.password;
    await e2e.login();
}

type WorkspaceModule = {
    get: (root: Element) => unknown;
};

type AmdRequire = (
    modules: string[],
    onLoad: (workspace: WorkspaceModule) => void,
    onError: (error: unknown) => void,
) => void;

export async function openErgonodeAdminPage(
    e2e: { page: Page },
    route: string,
    workspaceSelector: string,
): Promise<Locator> {
    const ergonodeMenu = e2e.page.locator(
        "li[data-ui-id='menu-ergonode-core-main'] > a",
    );
    await expect(ergonodeMenu).toBeVisible();
    await ergonodeMenu.click();

    const menuPath = route.replace(/^adminhtml\//, "admin/");
    const menuLink = e2e.page
        .locator(`li[data-ui-id^="menu-ergonode-"] > a[href*="/${menuPath}/"]`)
        .first();
    const menuUrl = await menuLink.getAttribute("href");
    if (menuUrl === null) {
        throw new Error(
            `Ergonode admin menu link for route ${route} is missing.`,
        );
    }

    const response = await e2e.page.goto(menuUrl, {
        waitUntil: "domcontentloaded",
    });
    if (response === null) {
        throw new Error(
            `Magento did not return a document response for admin route ${route}.`,
        );
    }

    expect(
        response.status(),
        `Admin route ${route} returned HTTP ${response.status()}.`,
    ).toBeLessThan(400);
    await expect(e2e.page.locator("body.adminhtml-auth-login")).toHaveCount(0);

    const workspace = e2e.page.locator(workspaceSelector);
    await expect(workspace).toBeVisible();

    return workspace;
}

export async function toggleAndRestore(
    workspace: Locator,
    role: string,
): Promise<void> {
    const toggle = workspace.locator(`[data-role="${role}"]`).first();
    const optionsMenu = toggle.locator("xpath=ancestor::details[1]");

    if (
        (await optionsMenu.count()) > 0 &&
        (await optionsMenu.getAttribute("open")) === null
    ) {
        await optionsMenu.locator(":scope > summary").click();
    }
    await expect(toggle).toBeVisible();
    await toggle.evaluate(
        (button) =>
            new Promise<void>((resolve, reject) => {
                const root = button.closest(".veui-workspace");
                const amdRequire = (
                    window as typeof window & { require?: AmdRequire }
                ).require;
                if (root === null || amdRequire === undefined) {
                    reject(
                        new Error(
                            "Ergonode workspace or Magento AMD loader is unavailable.",
                        ),
                    );
                    return;
                }

                amdRequire(
                    ["Ergonode_CoreAdminUi/js/workspace"],
                    (workspaceModule) => {
                        const deadline = Date.now() + 15_000;
                        const waitForMount = (): void => {
                            if (workspaceModule.get(root)) {
                                resolve();
                                return;
                            }
                            if (Date.now() >= deadline) {
                                reject(
                                    new Error(
                                        "Ergonode workspace did not finish mounting.",
                                    ),
                                );
                                return;
                            }
                            window.setTimeout(waitForMount, 25);
                        };

                        waitForMount();
                    },
                    reject,
                );
            }),
    );
    await expect(toggle).toHaveAttribute("aria-pressed", "false");

    await toggle.click();
    await expect(toggle).toHaveAttribute("aria-pressed", "true");

    if (
        (await optionsMenu.count()) > 0 &&
        (await optionsMenu.getAttribute("open")) === null
    ) {
        await optionsMenu.locator(":scope > summary").click();
    }
    await toggle.click();
    await expect(toggle).toHaveAttribute("aria-pressed", "false");
}
