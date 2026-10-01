const { test } = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs/promises");
const path = require("node:path");
const os = require("node:os");
const { spawn } = require("node:child_process");
const { once } = require("node:events");

const support = import("../Playwright/support/connection-snapshot.mjs");
const original = [
    {
        config_id: 9,
        scope: "default",
        scope_id: 0,
        path: "ergonode_connection/test/consumer/api_key",
        value: "encrypted-fixture-value",
    },
    {
        config_id: 10,
        scope: "default",
        scope_id: 0,
        path: "ergonode_connection/test/url",
        value: "https://original.example",
    },
];
const unowned = {
    config_id: 11,
    scope: "default",
    scope_id: 0,
    path: "ergonode_connection/production/url",
    value: "https://untouched.example",
};

async function fixture(t) {
    const { ConnectionSnapshot, ownedPaths } = await support;
    const directory = await fs.mkdtemp(
        path.join(os.tmpdir(), "connection-snapshot-"),
    );
    t.after(() => fs.rm(directory, { recursive: true, force: true }));
    const database = {
        rows: structuredClone([...original, unowned]),
        async beginTransaction() {
            this.before = structuredClone(this.rows);
        },
        async commit() {
            this.before = null;
        },
        async rollback() {
            this.rows = this.before;
        },
        async query(sql, parameters) {
            const owned = (row) =>
                row.scope === "default" &&
                row.scope_id === 0 &&
                ownedPaths.includes(row.path);
            if (sql.startsWith("SELECT")) {
                return [
                    structuredClone(
                        this.rows
                            .filter(owned)
                            .sort((a, b) => a.path.localeCompare(b.path)),
                    ),
                ];
            }
            if (sql.startsWith("DELETE")) {
                this.rows = this.rows.filter((row) => !owned(row));
                return;
            }
            if (sql.startsWith("INSERT")) {
                if (this.failInsert) {
                    throw new Error("insert failed");
                }
                this.rows.push(structuredClone(parameters));
                return;
            }
            throw new Error("Unexpected SQL in test");
        },
    };
    const file = path.join(directory, "snapshot.json");
    const snapshot = new ConnectionSnapshot(database, "core_config_data", file);
    return { database, snapshot, file, ConnectionSnapshot };
}

test("partial setup is restored exactly, including absent rows and encrypted values", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    await snapshot.capture("0");
    assert.equal((await fs.stat(file)).mode & 0o777, 0o600);
    database.rows = [unowned]; // setup failed just after deleting owned settings
    await snapshot.restore(async () => {
        assert.deepEqual(await snapshot.readRows(), original);
        // Native HTTP save materializes a previously inherited Status.
        database.rows.push({
            config_id: 12,
            scope: "default",
            scope_id: 0,
            path: "ergonode_connection/general/enabled",
            value: "0",
        });
    });
    assert.deepEqual(await snapshot.readRows(), original);
    assert.deepEqual(
        database.rows.find((row) => row.config_id === 11),
        unowned,
    );
    await assert.rejects(fs.stat(file), { code: "ENOENT" });
});

test("failed HTTP refresh restores its partial write and retains recovery data", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    await snapshot.capture("0");
    await assert.rejects(
        snapshot.restore(async () => {
            database.rows.push({
                config_id: 12,
                scope: "default",
                scope_id: 0,
                path: "ergonode_connection/general/enabled",
                value: "0",
            });
            throw new Error("session expired");
        }),
        /session expired/,
    );
    assert.deepEqual(await snapshot.readRows(), original);
    assert.ok(await fs.stat(file));
});

test("database failure rolls back the restore and retains its backup for another attempt", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    await snapshot.capture("0");
    database.rows = [unowned];
    database.failInsert = true;
    await assert.rejects(snapshot.restoreRows(), /insert failed/);
    assert.deepEqual(database.rows, [unowned]);
    assert.ok(await fs.stat(file));
    database.failInsert = false;
    await snapshot.restore(async () => {});
    assert.deepEqual(await snapshot.readRows(), original);
});

test("stale backup blocks recapture and mismatched database state blocks finish", async (t) => {
    const { database, snapshot, file, ConnectionSnapshot } = await fixture(t);
    await snapshot.capture("0");
    const backup = await fs.readFile(file, "utf8");
    const next = new ConnectionSnapshot(database, "core_config_data", file);
    await assert.rejects(next.capture("1"), /Istnieje kopia/);
    assert.equal(await fs.readFile(file, "utf8"), backup);
    await next.load();
    database.rows = [unowned];
    await assert.rejects(next.finish(), /różni się od kopii/);
    assert.equal(await fs.readFile(file, "utf8"), backup);
    await next.restoreRows();
    assert.ok(
        await fs.stat(file),
        "manual restore waits for explicit cache refresh/finish",
    );
    await next.finish();
});

test("a killed process leaves a snapshot usable by a fresh recovery process", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    const modulePath = path.resolve(
        __dirname,
        "../Playwright/support/connection-snapshot.mjs",
    );
    const child = spawn(
        process.execPath,
        [
            "--input-type=module",
            "-e",
            `
        import { ConnectionSnapshot } from ${JSON.stringify("file://" + modulePath)};
        const snapshot = new ConnectionSnapshot({query: async () => [${JSON.stringify(original)}]}, 'core_config_data', ${JSON.stringify(file)});
        await snapshot.capture('0');
        process.stdout.write('captured');
        setInterval(() => {}, 1000);
    `,
        ],
        { stdio: ["ignore", "pipe", "pipe"] },
    );
    t.after(() => child.kill("SIGKILL"));
    const exited = once(child, "exit");
    await once(child.stdout, "data");
    child.kill("SIGKILL");
    await exited;
    database.rows = [unowned];
    await snapshot.load();
    await snapshot.restore(async () => {});
    assert.deepEqual(await snapshot.readRows(), original);
});

test("invalid saved status is rejected before recovery changes database rows", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    await fs.writeFile(file, JSON.stringify({ table: "core_config_data", rows: original, enabled: "invalid" }));
    await assert.rejects(snapshot.load(), /nie odpowiada/);
    assert.deepEqual(database.rows, [...original, unowned]);
});

test("invalid backup scope is rejected before any database mutation", async (t) => {
    const { database, snapshot, file } = await fixture(t);
    await fs.writeFile(
        file,
        JSON.stringify({ table: "core_config_data", rows: [unowned] }),
    );
    await assert.rejects(snapshot.load(), /nie odpowiada/);
    assert.deepEqual(database.rows, [...original, unowned]);
});

test(
    "MariaDB restores exact rows and leaves other profiles and store scopes intact",
    {
        skip: process.env.PACKHAUER_TEST_DB !== "1",
        timeout: 15_000,
    },
    async (t) => {
        const { ConnectionSnapshot } = await support;
        const { projectConfig } = await import("@vendivo/test-config");
        const mysql = require("mysql2/promise");
        const { tablePrefix, ...settings } = projectConfig().magento.database;
        const database = await mysql.createConnection(settings);
        t.after(() => database.end());
        // Session-local table: no write targets a Magento table or existing configuration.
        const table = "playwright_snapshot_verification";
        await database.query(`CREATE TEMPORARY TABLE ${table} (
        config_id INT PRIMARY KEY, scope VARCHAR(8), scope_id INT,
        path VARCHAR(255), value TEXT,
        UNIQUE KEY scope_path (scope, scope_id, path)
    ) ENGINE=InnoDB`);
        const storeRow = {
            ...original[1],
            config_id: 20,
            scope: "stores",
            scope_id: 1,
        };
        for (const row of [...original, unowned, storeRow]) {
            await database.query(`INSERT INTO ${table} SET ?`, row);
        }
        const directory = await fs.mkdtemp(
            path.join(os.tmpdir(), "connection-db-"),
        );
        t.after(() => fs.rm(directory, { recursive: true, force: true }));
        const snapshot = new ConnectionSnapshot(
            database,
            table,
            path.join(directory, "snapshot.json"),
        );
        await snapshot.capture("0");
        await database.query(
            `UPDATE ${table} SET value = 'changed' WHERE config_id = 9`,
        );
        await snapshot.restore(async () => {
            await database.query(`INSERT INTO ${table} SET ?`, {
                config_id: 12,
                scope: "default",
                scope_id: 0,
                path: "ergonode_connection/general/enabled",
                value: "0",
            });
        });
        const [rows] = await database.query(
            `SELECT * FROM ${table} ORDER BY config_id`,
        );
        assert.deepEqual(rows, [...original, unowned, storeRow]);
    },
);
