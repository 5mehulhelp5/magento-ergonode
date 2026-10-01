import http from "node:http";
import mysql, { type Connection, type RowDataPacket } from "mysql2/promise";
import { projectConfig, magentoAccount } from "@vendivo/test-config";
import {
    test as base,
    expect,
    E2EContext,
} from "@packhauer/playwright-runner/fixtures";
import type { Page } from "@playwright/test";

export const fixtureCode = "playwright_snapshot_e2e";
export const fixtureUrl = projectConfig().e2e?.graphqlUrl || "http://playwright:18089/api/graphql/";
export type Role = "full" | "viewer" | "editor" | "denied";

export class SnapshotEnvironment {
    readonly config = projectConfig();
    readonly account = magentoAccount(this.config);
    readonly requests: Array<string | null> = [];
    failSecondPage = false;
    private database!: Connection;
    private server?: http.Server;
    private originalCodes: string[] = [];
    private originalMappings: RowDataPacket[] = [];
    private originalVisibility: RowDataPacket[] = [];
    private initialized = false;

    private table(name: string): string {
        return (
            "`" + (this.config.magento.database.tablePrefix || "") + name + "`"
        );
    }

    async codes(): Promise<string[]> {
        const [rows] = await this.database.query<RowDataPacket[]>(
            `SELECT language_code FROM ${this.table("ergonode_language")} ORDER BY language_code`,
        );
        // Compare the set deterministically, independently of MySQL collation.
        return rows.map((row) => String(row.language_code)).sort();
    }

    async start(): Promise<void> {
        const { tablePrefix = "", ...settings } = this.config.magento.database;
        if (
            !/^[a-zA-Z0-9_]*$/.test(tablePrefix) ||
            !["db", "localhost", "127.0.0.1"].includes(settings.host)
        ) {
            throw new Error(
                "Language E2E requires the configured local database.",
            );
        }
        this.database = await mysql.createConnection(settings);
        const [locks] = await this.database.query<RowDataPacket[]>(
            "SELECT GET_LOCK('ergonode-language-snapshot-playwright', 0) AS acquired",
        );
        if (Number(locks[0].acquired) !== 1)
            throw new Error("Another Language snapshot test is running.");
        const required = {
            "ergonode_connection/test/url": fixtureUrl,
            "ergonode_connection/general/environment": "test",
            "ergonode_connection/general/mode": "read",
            "ergonode_connection/general/enabled": "1",
        };
        const [configuration] = await this.database.query<RowDataPacket[]>(
            `SELECT path, value FROM ${this.table("core_config_data")} WHERE scope='default' AND scope_id=0 AND path IN (?)`,
            [Object.keys(required)],
        );
        expect(
            Object.fromEntries(
                configuration.map((row) => [row.path, row.value]),
            ),
            "Run using support/run-isolated.php; never refresh against the real API.",
        ).toEqual(required);
        if (this.config.e2e) {
            expect(settings.database).toBe("vendivo_e2e");
            expect(fixtureUrl).toBe("http://playwright:18092/api/graphql/");
            const [identity] = await this.database.query<RowDataPacket[]>(
                "SELECT value FROM core_config_data WHERE scope='default' AND scope_id=0 AND path='e2e/environment/id'",
            );
            expect(identity[0]?.value).toBe(this.config.e2e.id);
        } else {
            const [owner] = await this.database.query<RowDataPacket[]>(
                "SELECT IS_USED_LOCK('ergonode-connection-playwright') AS owner",
            );
            expect(
                owner[0].owner,
                "The isolated wrapper must hold the connection lock.",
            ).not.toBeNull();
        }
        this.originalCodes = await this.codes();
        expect(this.originalCodes).not.toContain(fixtureCode);
        [this.originalMappings] = await this.database.query<RowDataPacket[]>(
            `SELECT * FROM ${this.table("ergonode_language_store_mapping")} ORDER BY mapping_id`,
        );
        [this.originalVisibility] = await this.database.query<RowDataPacket[]>(
            `SELECT * FROM ${this.table("ergonode_mapping_visibility")} WHERE entity_type='language' ORDER BY entity_id`,
        );
        expect(
            this.originalMappings.some(
                (row) => row.language_code === fixtureCode,
            ),
        ).toBe(false);
        expect(
            this.originalVisibility.some(
                (row) =>
                    row.source === "ergo" && row.identifier === fixtureCode,
            ),
        ).toBe(false);
        this.initialized = true;
        this.server = http.createServer(async (request, response) => {
            response.setHeader("Content-Type", "application/json");
            try {
                const chunks: Buffer[] = [];
                for await (const chunk of request)
                    chunks.push(Buffer.from(chunk));
                const body = JSON.parse(Buffer.concat(chunks).toString());
                if (
                    request.method !== "POST" ||
                    request.url !== "/api/graphql/" ||
                    request.headers["x-api-key"] !== "language-e2e-fixture" ||
                    !body.query?.includes("query ErgonodeLanguageList(")
                ) {
                    response.writeHead(400).end(
                        JSON.stringify({
                            errors: [
                                { message: "Unsupported fixture request" },
                            ],
                        }),
                    );
                    return;
                }
                const cursor = body.variables?.after ?? null;
                this.requests.push(cursor);
                if (cursor !== null && cursor !== "language-page-2")
                    throw new Error("Invalid cursor");
                if (cursor && this.failSecondPage) {
                    response.end(
                        JSON.stringify({
                            errors: [
                                { message: "Controlled second-page failure" },
                            ],
                        }),
                    );
                    return;
                }
                response.end(
                    JSON.stringify({
                        data: {
                            languageList: {
                                edges: (cursor
                                    ? this.originalCodes
                                    : [fixtureCode]
                                ).map((node) => ({ node })),
                                pageInfo: {
                                    hasNextPage: cursor === null,
                                    endCursor: cursor
                                        ? null
                                        : "language-page-2",
                                },
                            },
                        },
                    }),
                );
            } catch {
                response.writeHead(400).end(
                    JSON.stringify({
                        errors: [{ message: "Invalid fixture request" }],
                    }),
                );
            }
        });
        await new Promise<void>((resolve, reject) => {
            this.server!.once("error", reject);
            this.server!.listen(Number(new URL(fixtureUrl).port), "0.0.0.0", resolve);
        });
    }

    async seed(): Promise<void> {
        await this.database.query(
            `INSERT INTO ${this.table("ergonode_language")} (language_code) VALUES (?)`,
            [fixtureCode],
        );
    }

    async login(page: Page, role: Role = "full"): Promise<void> {
        const username = "pw_lang_e2e_" + role;
        const [users] = await this.database.query<RowDataPacket[]>(
            `SELECT user_id, is_active FROM ${this.table("admin_user")} WHERE username=? AND email=?`,
            [username, username + "@example.invalid"],
        );
        expect(
            users,
            "Run through run-isolated.php to prepare the limited test roles.",
        ).toHaveLength(1);
        expect(Number(users[0].is_active)).toBe(1);
        const values = {
            MAGENTO_BASE_URL: this.config.magento.baseUrl,
            MAGENTO_ADMIN_PATH: this.config.magento.adminPath || "admin",
            MAGENTO_PLAYWRIGHT_USERNAME: username,
            MAGENTO_PLAYWRIGHT_PASSWORD: this.account.password,
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

    async close(): Promise<void> {
        try {
            if (!this.database) return;
            if (this.initialized) {
                await this.database.query(
                    `DELETE FROM ${this.table("ergonode_language")} WHERE language_code=?`,
                    [fixtureCode],
                );
                expect(
                    await this.codes(),
                    "Original snapshot must survive the test.",
                ).toEqual(this.originalCodes);
                const [mappings] = await this.database.query(
                    `SELECT * FROM ${this.table("ergonode_language_store_mapping")} ORDER BY mapping_id`,
                );
                const [visibility] = await this.database.query(
                    `SELECT * FROM ${this.table("ergonode_mapping_visibility")} WHERE entity_type='language' ORDER BY entity_id`,
                );
                expect(mappings, "Mappings must remain unchanged.").toEqual(
                    this.originalMappings,
                );
                expect(visibility, "Visibility must remain unchanged.").toEqual(
                    this.originalVisibility,
                );
            }
        } finally {
            this.server?.closeAllConnections();
            if (this.server?.listening)
                await new Promise<void>((resolve, reject) =>
                    this.server!.close((error) =>
                        error ? reject(error) : resolve(),
                    ),
                );
            await this.database?.end();
        }
    }
}

export const test = base.extend<{ languageEnv: SnapshotEnvironment }>({
    languageEnv: async ({}, use) => {
        const environment = new SnapshotEnvironment();
        try {
            await environment.start();
            await use(environment);
        } finally {
            await environment.close();
        }
    },
});
export { expect };
