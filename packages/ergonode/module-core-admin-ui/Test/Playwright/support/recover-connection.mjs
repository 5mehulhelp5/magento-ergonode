/**
 * Odzyskanie po twardym przerwaniu testu. Zatrzymaj najpierw aktywny test w runnerze.
 * W DDEV:
 * ddev exec -s playwright --raw -- node app/code/Ergonode/CoreAdminUi/Test/Playwright/support/recover-connection.mjs
 * ddev exec --raw -- php bin/magento cache:clean config
 * Dopiero po pomyślnym odświeżeniu cache:
 * ddev exec -s playwright --raw -- node app/code/Ergonode/CoreAdminUi/Test/Playwright/support/recover-connection.mjs --finish
 * Kopia jest zachowywana, dopóki operator nie potwierdzi odświeżenia cache przez --finish.
 */
import path from "node:path";
import mysql from "mysql2/promise";
import { projectConfig } from "@vendivo/test-config";
import { ConnectionSnapshot, lockName } from "./connection-snapshot.mjs";

const file = path.join(
    process.env.MAGENTO_ROOT || process.cwd(),
    "var/test-state/ergonode-connection.json",
);
const { tablePrefix = "", ...settings } = projectConfig().magento.database;
const table = tablePrefix + "core_config_data";
const database = await mysql.createConnection(settings);
try {
    const [lock] = await database.query("SELECT GET_LOCK(?, 0) AS acquired", [
        lockName,
    ]);
    if (Number(lock[0].acquired) !== 1) {
        throw new Error("Test nadal trzyma blokadę konfiguracji.");
    }
    const snapshot = new ConnectionSnapshot(database, table, file);
    await snapshot.load();
    if (process.argv.includes("--finish")) {
        await snapshot.finish();
        process.stdout.write("Zakończono odzyskiwanie i usunięto kopię.\n");
    } else {
        await snapshot.restoreRows();
        process.stdout.write(
            "Odtworzono konfigurację. Odśwież cache Magento: cache:clean config. Następnie uruchom ten skrypt z --finish.\n",
        );
    }
} finally {
    await database.end();
}
