import { projectConfig, magentoAccount } from "@vendivo/test-config";
import {
    E2EContext,
    expect,
    test as base,
} from "../../../../../../dev/tests/playwright/fixtures/e2e";

export const test = base.extend({
    e2e: async ({ page }, use, testInfo) => {
        const config = projectConfig();
        const account = magentoAccount(config);
        const environment: Record<string, string> = {
            MAGENTO_BASE_URL: config.magento.baseUrl,
            MAGENTO_ADMIN_PATH: config.magento.adminPath || "admin",
            MAGENTO_PLAYWRIGHT_USERNAME: account.username,
            MAGENTO_PLAYWRIGHT_PASSWORD: account.password,
        };
        const previous = new Map(
            Object.keys(environment).map((key) => [key, process.env[key]]),
        );
        Object.assign(process.env, environment);
        const context = new E2EContext(page, testInfo);
        try {
            await context.login();
            await use(context);
        } finally {
            try {
                await context.finalize();
            } finally {
                for (const [key, value] of previous) {
                    if (value === undefined) {
                        delete process.env[key];
                    } else {
                        process.env[key] = value;
                    }
                }
            }
        }
    },
});

export { expect };
