import path from "node:path";
import mysql, {
    type Connection as Database,
    type RowDataPacket,
} from "mysql2/promise";
import { projectConfig } from "@vendivo/test-config";
import { loginToErgonodeAdmin } from "./admin-page";
import {
    test as base,
    expect,
    type E2EContext,
} from "@packhauer/playwright-runner/fixtures";
import type { Locator, Page } from "@playwright/test";

import {
    ConnectionSnapshot,
    ownedPaths,
    lockName,
} from "./connection-snapshot.mjs";

const prefix = "ergonode_connection/";

export class ConnectionForm {
    public readonly page: Page;
    private editUrl = "";
    private saveUrl = "";
    private originalEnabled = "0";
    private database!: Database;
    private table = "";
    private snapshot!: ConnectionSnapshot;
    public readonly settings = projectConfig();

    public constructor(private readonly e2e: E2EContext) {
        this.page = e2e.page;
    }

    public field(suffix: string): Locator {
        return this.page.locator("#ergonode_connection_" + suffix);
    }

    private async connectSnapshot(): Promise<void> {
        const { magento } = this.settings;
        const { tablePrefix = "", ...database } = magento.database || {};
        if (
            !database.host ||
            !database.database ||
            !/^[a-zA-Z0-9_]*$/.test(tablePrefix)
        ) {
            throw new Error(
                "Uzupełnij magento.database w konfiguracji testów projektu.",
            );
        }
        this.table = tablePrefix + "core_config_data";
        this.database = await mysql.createConnection(database);
        this.e2e.registerCleanup("Zamknięcie połączenia z bazą", async () => {
            await this.database.end();
        });
        const [lock] = await this.database.query<RowDataPacket[]>(
            "SELECT GET_LOCK(?, 0) AS acquired",
            [lockName],
        );
        if (Number(lock[0].acquired) !== 1) {
            throw new Error(
                "Inny test konfiguracji Ergonode jest już uruchomiony.",
            );
        }
        this.snapshot = new ConnectionSnapshot(
            this.database,
            this.table,
            path.join(
                process.env.MAGENTO_ROOT || process.cwd(),
                "var/test-state/ergonode-connection.json",
            ),
        );
    }

    public async recover(): Promise<void> {
        await this.connectSnapshot();
        await this.snapshot.load();
        this.originalEnabled = this.snapshot.snapshot.enabled;
        await this.restore();
    }

    public async start(): Promise<void> {
        await this.openAuthenticatedForm();
        this.originalEnabled = await this.field("general_enabled").inputValue();
        await this.connectSnapshot();
        await this.snapshot.capture(this.originalEnabled);
        this.e2e.registerCleanup(
            "Przywrócenie konfiguracji sprzed testu",
            async () => {
                await this.restore();
            },
        );
        // Wyłącznie sześć ścieżek fixture; pozostałe profile i dane sklepu pozostają nietknięte.
        await this.database.query(
            `DELETE FROM \`${this.table}\` WHERE scope = 'default' AND scope_id = 0 AND path IN (?)`,
            [ownedPaths],
        );
        for (const [name, value] of Object.entries({
            "general/enabled": "0",
            "general/environment": "test",
            "general/mode": "read",
        })) {
            await this.database.query(
                `INSERT INTO \`${this.table}\` (scope, scope_id, path, value) VALUES ('default', 0, ?, ?)`,
                [prefix + name, value],
            );
        }
        await this.saveFields({ "general/enabled": "0" });
        await expect(this.field("general_enabled")).toHaveValue("0");
    }

    private async openAuthenticatedForm(): Promise<void> {
        await loginToErgonodeAdmin(this.e2e, this.settings);
        const menu = this.page.locator(
            'li[data-ui-id="menu-ergonode-core-main"] > a',
        );
        await menu.click();
        const link = this.page
            .locator(
                'a[href*="/system_config/edit/section/ergonode_connection/"]',
            )
            .first();
        this.editUrl = (await link.getAttribute("href"))!;
        await this.open();
        this.saveUrl = (await this.page
            .locator("#config-edit-form")
            .getAttribute("action"))!;
    }

    public async open(): Promise<void> {
        await this.page.goto(this.editUrl, { waitUntil: "domcontentloaded" });
        await expect(this.page.locator("#config-edit-form")).toBeVisible();
        // Poczekaj na inicjalizację natywnych zależności i widgetu, nie na arbitralny timeout.
        await this.page.waitForFunction(() => {
            const w = window as typeof window & { jQuery?: any };
            return Boolean(
                w
                    .jQuery?.("#ergonode_connection_test_consumer_connection")
                    .data("ergonode-testConnection"),
            );
        });
    }

    public async selectRead(): Promise<void> {
        await this.field("general_enabled").selectOption("1");
        await this.field("general_environment").selectOption("test");
        await this.field("general_mode").selectOption("read");
        await expect(this.field("test_url")).toBeVisible();
        await expect(this.field("test_consumer_api_key")).toBeVisible();
    }

    public credentials(): { url: string; readApiKey: string } {
        const { url, readApiKey } = this.settings.ergonode || {};
        if (!url || !readApiKey || readApiKey === "replace-me") {
            throw new Error(
                "Uzupełnij ergonode.url i ergonode.readApiKey w app/etc/playwright.yaml.",
            );
        }
        if (new URL(url).protocol !== "https:") {
            throw new Error("Ergonode wymaga adresu HTTPS.");
        }
        return { url, readApiKey };
    }

    public async fillSecret(value: string): Promise<void> {
        // evaluate nie dodaje wartości klucza do opisu kroku fill ani do raportu.
        await this.field("test_consumer_api_key").evaluate(
            (element, secret) => {
                const input = element as HTMLInputElement;
                input.value = secret;
                input.dispatchEvent(new Event("input", { bubbles: true }));
                input.dispatchEvent(new Event("change", { bubbles: true }));
            },
            value,
        );
    }

    public async prepareReadConnection(): Promise<void> {
        const credentials = this.credentials();
        await this.saveFields({
            "general/enabled": "1",
            "general/environment": "test",
            "general/mode": "read",
            "test/url": credentials.url,
            "test/requests_per_minute": "0",
            "test/consumer/api_key": credentials.readApiKey,
        });
        await expect(this.field("general_environment")).toHaveValue("test");
        await expect(this.field("general_mode")).toHaveValue("read");
    }

    public async saveFields(values: Record<string, string>): Promise<void> {
        const form: Record<string, string> = {
            form_key: await this.page
                .locator('#config-edit-form input[name="form_key"]')
                .inputValue(),
        };
        for (const [name, value] of Object.entries(values)) {
            const parts = name.split("/");
            const field = parts.pop();
            const group =
                parts.length === 1
                    ? `groups[${parts[0]}]`
                    : `groups[${parts[0]}][groups][${parts[1]}]`;
            form[`${group}[fields][${field}][value]`] = value;
        }
        const response = await this.page.request.post(this.saveUrl, {
            form,
            maxRedirects: 0,
        });
        expect(
            response.status(),
            "Magento powinno przekierować po zapisie konfiguracji.",
        ).toBe(302);
        await this.open();
        await expect(this.page.locator(".message-success")).toContainText(
            "You saved the configuration.",
        );
    }

    public async submit(): Promise<void> {
        await Promise.all([
            this.page.waitForNavigation({ waitUntil: "domcontentloaded" }),
            this.page
                .getByRole("button", { name: "Save Config", exact: true })
                .click(),
        ]);
        await expect(this.page.locator(".message-success")).toContainText(
            "You saved the configuration.",
        );
        await this.open();
    }

    private async restore(): Promise<void> {
        await this.snapshot.restore(async () => {
            // Re-authenticate and obtain fresh secret/form keys after session expiry.
            await this.openAuthenticatedForm();
            await this.saveFields({ "general/enabled": this.originalEnabled });
        });
    }
}

export const test = base.extend<{ connection: ConnectionForm }>({
    connection: async ({ e2e }, use) => {
        const connection = new ConnectionForm(e2e);
        await connection.start();
        await use(connection);
    },
});
export { expect };
