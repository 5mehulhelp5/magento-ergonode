import fs from "node:fs/promises";
import path from "node:path";

export const ownedPaths = [
    "general/enabled",
    "general/environment",
    "general/mode",
    "test/url",
    "test/requests_per_minute",
    "test/consumer/api_key",
].map((value) => "ergonode_connection/" + value);
export const lockName = "ergonode-connection-playwright";

/** Exact database snapshot shared by fixture teardown and manual recovery. */
export class ConnectionSnapshot {
    constructor(database, table, file) {
        if (!/^[a-zA-Z0-9_]+$/.test(table)) {
            throw new Error("Niepoprawna nazwa tabeli konfiguracji.");
        }
        this.database = database;
        this.table = table;
        this.file = file;
        this.snapshot = null;
    }

    async readRows() {
        const [rows] = await this.database.query(
            `SELECT * FROM \`${this.table}\` WHERE scope = 'default' AND scope_id = 0 AND path IN (?) ORDER BY path`,
            [ownedPaths],
        );
        return rows;
    }

    async capture(enabled) {
        const snapshot = {
            table: this.table,
            rows: await this.readRows(),
            enabled,
        };
        await fs.mkdir(path.dirname(this.file), {
            recursive: true,
            mode: 0o700,
        });
        try {
            await fs.writeFile(this.file, JSON.stringify(snapshot), {
                flag: "wx",
                mode: 0o600,
            });
        } catch (error) {
            if (error.code === "EEXIST") {
                throw new Error(
                    "Istnieje kopia konfiguracji po przerwanym teście. Przywróć var/test-state/ergonode-connection.json zgodnie z README przed kolejnym uruchomieniem.",
                );
            }
            throw error;
        }
        this.snapshot = snapshot;
    }

    async load() {
        const snapshot = JSON.parse(await fs.readFile(this.file, "utf8"));
        if (
            snapshot.table !== this.table ||
            !["0", "1"].includes(snapshot.enabled) ||
            !Array.isArray(snapshot.rows) ||
            snapshot.rows.length > ownedPaths.length ||
            new Set(snapshot.rows.map((row) => row.path)).size !==
                snapshot.rows.length ||
            snapshot.rows.some(
                (row) =>
                    row.scope !== "default" ||
                    Number(row.scope_id) !== 0 ||
                    !ownedPaths.includes(row.path),
            )
        ) {
            throw new Error(
                "Kopia nie odpowiada sześciu ścieżkom fixture tego projektu.",
            );
        }
        this.snapshot = snapshot;
    }

    async restoreRows() {
        if (!this.snapshot) {
            throw new Error("Brak kopii konfiguracji do odtworzenia.");
        }
        await this.database.beginTransaction();
        try {
            await this.database.query(
                `DELETE FROM \`${this.table}\` WHERE scope = 'default' AND scope_id = 0 AND path IN (?)`,
                [ownedPaths],
            );
            for (const row of this.snapshot.rows) {
                await this.database.query(
                    `INSERT INTO \`${this.table}\` SET ?`,
                    row,
                );
            }
            await this.database.commit();
        } catch (error) {
            await this.database.rollback();
            throw error;
        }
    }

    async finish() {
        if (
            !this.snapshot ||
            JSON.stringify(await this.readRows()) !==
                JSON.stringify(this.snapshot.rows)
        ) {
            throw new Error(
                "Konfiguracja różni się od kopii. Kopia pozostaje na dysku.",
            );
        }
        await fs.unlink(this.file);
        this.snapshot = null;
    }

    async restore(refreshConfiguration) {
        await this.restoreRows();
        try {
            await refreshConfiguration();
        } finally {
            // HTTP can persist Status and then fail. Restore missing/inherited rows even then.
            await this.restoreRows();
        }
        // A failed refresh must leave the recovery file in place.
        await this.finish();
    }
}
