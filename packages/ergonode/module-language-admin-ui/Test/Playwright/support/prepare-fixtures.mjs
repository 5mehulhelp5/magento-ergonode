import mysql from "mysql2/promise";
import { projectConfig, magentoAccount } from "@vendivo/test-config";

const config = projectConfig(); // Rejects non-local Magento URLs before any connection.
const { tablePrefix = "", ...database } = config.magento.database || {};
if (
    !["db", "localhost", "127.0.0.1"].includes(database.host) ||
    !database.database ||
    !/^[a-zA-Z0-9_]*$/.test(tablePrefix)
) {
    throw new Error(
        "Language fixtures require the configured local Magento database and a valid table prefix.",
    );
}
// Kept as a read-only preflight; even the legacy --prepare flag creates no data.
if (
    process.argv
        .slice(2)
        .some((argument) => !["--check", "--prepare"].includes(argument))
) {
    throw new Error("Use --check (default) or --prepare.");
}
const storeCodes = ["default", "base_pl"];
const codes = ["en_GB", "pl_PL"];
const table = (name) => "`" + tablePrefix + name + "`";
const connection = await mysql.createConnection(database);
try {
    const [users] = await connection.query(
        `SELECT is_active FROM ${table("admin_user")} WHERE username = ?`,
        [magentoAccount(config).username],
    );
    if (users.length !== 1 || Number(users[0].is_active) !== 1) {
        throw new Error(
            "The configured test account must already exist and be active; no accounts are provisioned here.",
        );
    }
    const [stores] = await connection.query(
        `SELECT store_id, code FROM ${table("store")} WHERE code IN (?)`,
        [storeCodes],
    );
    const missingStores = storeCodes.filter(
        (code) => !stores.some((row) => row.code === code),
    );
    const [languages] = await connection.query(
        `SELECT language_code FROM ${table("ergonode_language")} WHERE language_code IN (?)`,
        [codes],
    );
    const missing = codes.filter(
        (code) => !languages.some((row) => row.language_code === code),
    );
    console.log(
        JSON.stringify(
            {
                mode: "check",
                accountActive: true,
                stores: stores.map(({ code }) => code),
                missingStores,
                missingLanguages: missing,
                changes: [],
            },
            null,
            2,
        ),
    );
    if (missingStores.length || missing.length) process.exitCode = 1;
} finally {
    await connection.end();
}
