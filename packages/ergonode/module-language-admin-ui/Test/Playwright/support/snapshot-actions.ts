import type { Page, Locator } from "@playwright/test";
import { expect, fixtureCode } from "./snapshot-environment";
import { languageCard, ready, workspaceSelector } from "./language-workspace";

export async function sourceMenu(page: Page): Promise<Locator> {
    const menu = page.locator(
        workspaceSelector +
            ' [data-role="source-panel"][data-source-panel="ergo"] [data-language-source-options]',
    );
    if ((await menu.getAttribute("open")) === null)
        await menu.locator(":scope > summary").click();
    return menu;
}

export async function refresh(page: Page, success: boolean): Promise<void> {
    const menu = await sourceMenu(page);
    const button = menu.locator('[data-role="refresh-ergonode"]');
    const responsePromise = page
        .waitForResponse(
            (response) =>
                response.request().method() === "POST" &&
                response.url().includes("/ergonode/language/refresh/"),
        )
        .then(async (response) => ({
            status: response.status(),
            body: success ? undefined : await response.json(),
        }));
    const navigation = success ? page.waitForEvent("domcontentloaded") : null;
    await button.click();
    const response = await responsePromise;
    expect(response.status).toBe(200);
    if (!success) expect(response.body.success).toBe(false);
    if (navigation) {
        await navigation;
        await ready(page);
    } else {
        await expect(button).toBeEnabled();
        await expect(
            page.locator(
                workspaceSelector +
                    ' [data-role="source-panel"][data-source-panel="ergo"]',
            ),
        ).not.toHaveClass(/is-refreshing/);
    }
}

export async function removeButton(page: Page): Promise<Locator> {
    const card = languageCard(page.locator(workspaceSelector), fixtureCode);
    const menu = card.locator('[data-role="entity-options"]');
    if ((await menu.getAttribute("open")) === null)
        await menu.locator(":scope > summary").click();
    return menu.locator('[data-role="entity-delete-snapshot"]');
}

export async function removeSnapshot(
    page: Page,
    success = true,
): Promise<void> {
    const button = await removeButton(page);
    page.once("dialog", (dialog) => dialog.accept());
    const responsePromise = page
        .waitForResponse(
            (response) =>
                response.request().method() === "POST" &&
                response.url().includes("/ergonode/language/deleteSnapshot/"),
        )
        .then(async (response) => ({
            status: response.status(),
            body: success ? undefined : await response.json(),
        }));
    const navigation = success ? page.waitForEvent("domcontentloaded") : null;
    await button.click();
    const response = await responsePromise;
    expect(response.status).toBe(200);
    if (!success) expect(response.body.success).toBe(false);
    if (navigation) {
        await navigation;
        await ready(page);
    } else {
        await expect(button).toBeEnabled();
    }
}
