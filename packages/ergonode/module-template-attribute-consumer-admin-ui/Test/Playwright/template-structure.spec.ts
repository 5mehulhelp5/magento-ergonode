import { createHash, randomUUID } from "node:crypto";

import mysql, { type Connection, type RowDataPacket } from "mysql2/promise";
import { projectConfig } from "@vendivo/test-config";
import { expect, test } from "@packhauer/playwright-runner/fixtures";

import {
    loginToErgonodeAdmin,
    openErgonodeAdminPage,
} from "@magento-module/Ergonode_CoreAdminUi/Test/Playwright/support/admin-page";

test(
    "[ERG-TATTR-001] Admin reads structure and persists manual placement",
    {
        annotation: [
            { type: "id", description: "ERG-TATTR-001" },
            {
                type: "description",
                description:
                    "Inspect a saved template pair, pin an eligible attribute, reload, and remove the pin.",
            },
            { type: "kind", description: "e2e" },
            { type: "module", description: "Ergonode_TemplateAttributeConsumerAdminUi" },
            { type: "requires-module", description: "Ergonode_TemplateAdminUi" },
            { type: "requires-module", description: "Ergonode_TemplateAttributeAdminUi" },
            { type: "requires-module", description: "Ergonode_TemplateAttributeConsumer" },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            { type: "acl-resource", description: "Ergonode_TemplateConsumer::template_mapping" },
            { type: "acl-resource", description: "Ergonode_TemplateConsumer::template_save" },
            {
                type: "data-policy",
                description:
                    "Uses one unmapped local Sample Data attribute; creates a snapshot and mapping, then removes its pin and records in finally.",
            },
        ],
    },
    async ({ e2e }) => {
        const config = projectConfig();
        const database = config.magento.database;
        if (database?.host !== "db" || database.database !== "db") {
            throw new Error("Template structure E2E requires the local DDEV database.");
        }
        const prefix = database.tablePrefix || "";
        if (!/^[a-zA-Z0-9_]*$/.test(prefix)) {
            throw new Error("Invalid Magento table prefix.");
        }
        const table = (name: string): string => `\`${prefix}${name}\``;
        const { tablePrefix: _tablePrefix, ...connectionSettings } = database;
        const connection: Connection = await mysql.createConnection(connectionSettings);
        const fixtureCode = `pw_tattr_${randomUUID().replaceAll("-", "")}`;
        const sourceCode = `${fixtureCode}_attribute`;
        let setId = 0;
        let attributeId = 0;
        let attributeCode = "";

        try {
            const [locks] = await connection.query<RowDataPacket[]>(
                "SELECT GET_LOCK('ergonode-template-attribute-playwright', 0) AS acquired",
            );
            expect(Number(locks[0]?.acquired)).toBe(1);

            const [candidates] = await connection.query<RowDataPacket[]>(`
                SELECT s.attribute_set_id, a.attribute_id, a.attribute_code
                FROM ${table("eav_attribute_set")} s
                JOIN ${table("eav_entity_type")} t ON t.entity_type_id=s.entity_type_id
                JOIN ${table("eav_entity_attribute")} p ON p.attribute_set_id=s.attribute_set_id
                JOIN ${table("eav_attribute")} a ON a.attribute_id=p.attribute_id
                LEFT JOIN ${table("ergonode_template")} template ON template.attribute_set_id=s.attribute_set_id
                LEFT JOIN ${table("ergonode_product_attribute_mapping")} mapping
                    ON mapping.magento_attribute_code=a.attribute_code
                LEFT JOIN ${table("ergonode_template_manual_placement")} manual
                    ON manual.attribute_set_id=s.attribute_set_id AND manual.attribute_id=a.attribute_id
                WHERE t.entity_type_code='catalog_product' AND a.is_user_defined=1
                    AND a.is_required=0 AND template.entity_id IS NULL
                    AND mapping.mapping_id IS NULL AND manual.attribute_id IS NULL
                    AND a.attribute_code IN ('activity', 'color', 'material')
                ORDER BY s.attribute_set_id, a.attribute_id LIMIT 1
            `);
            expect(candidates, "An unclaimed product set and attribute are required.").toHaveLength(1);
            setId = Number(candidates[0].attribute_set_id);
            attributeId = Number(candidates[0].attribute_id);
            attributeCode = String(candidates[0].attribute_code);

            const hash = createHash("sha256").update("{}").digest("hex");
            await connection.query(
                `INSERT INTO ${table("ergonode_template")}
                    (code, attribute_set_id, raw_json, content_hash) VALUES (?, ?, '{}', ?)`,
                [fixtureCode, setId, hash],
            );
            await connection.query(
                `INSERT INTO ${table("ergonode_template_section")}
                    (template_code, section_code, content_hash, raw_json)
                    VALUES (?, 'playwright_details', ?, ?)`,
                [fixtureCode, hash, JSON.stringify({ name: [{ language: "en_GB", value: "Playwright details" }] })],
            );
            await connection.query(
                `INSERT INTO ${table("ergonode_template_attribute")}
                    (template_code, section_code, attribute_code)
                    VALUES (?, 'playwright_details', ?)`,
                [fixtureCode, sourceCode],
            );
            await connection.query(
                `INSERT INTO ${table("ergonode_product_attribute_mapping")}
                    (ergonode_attribute_code, magento_attribute_code, status, content_hash)
                    VALUES (?, ?, 'complete', ?)`,
                [sourceCode, attributeCode, hash],
            );

            await loginToErgonodeAdmin(e2e);
            const workspace = await openErgonodeAdminPage(
                e2e,
                "ergonode/template/index",
                "#ergonode-template-admin",
            );
            const open = async () => {
                const action = workspace.locator(
                    `[data-role="template-structure-action"][data-template-code="${fixtureCode}"]`,
                );
                await expect(action).toBeVisible();
                const response = e2e.page.waitForResponse((candidate) =>
                    candidate.request().method() === "GET" &&
                    /\/ergonode\/template\/structure(?:\/|$)/i.test(candidate.url()),
                );
                await action.click();
                const result = await response;
                expect(result.ok()).toBe(true);
                expect((await result.json()).success).toBe(true);
                const dialog = e2e.page.getByRole("dialog");
                await expect(dialog.getByText("Playwright details")).toBeVisible();
                await expect(dialog.locator("code").filter({ hasText: sourceCode }).first()).toBeVisible();
                return dialog;
            };

            let dialog = await open();
            let pin = dialog.locator(
                `[data-role="placement-actions"][data-attribute-id="${attributeId}"] [data-role="manual-placement"]`,
            );
            await expect(pin).toBeEnabled();
            await expect(pin).toHaveAttribute("aria-pressed", "false");
            await pin.click();
            await expect(pin).toHaveAttribute("aria-pressed", "true");
            const [saved] = await connection.query<RowDataPacket[]>(
                `SELECT COUNT(*) AS count FROM ${table("ergonode_template_manual_placement")}
                 WHERE attribute_set_id=? AND attribute_id=?`,
                [setId, attributeId],
            );
            expect(Number(saved[0].count)).toBe(1);

            await e2e.page.reload();
            await expect(workspace).toBeVisible();
            dialog = await open();
            pin = dialog.locator(
                `[data-role="placement-actions"][data-attribute-id="${attributeId}"] [data-role="manual-placement"]`,
            );
            await expect(pin).toHaveAttribute("aria-pressed", "true");
            await pin.click();
            await expect(pin).toHaveAttribute("aria-pressed", "false");
            const [removed] = await connection.query<RowDataPacket[]>(
                `SELECT COUNT(*) AS count FROM ${table("ergonode_template_manual_placement")}
                 WHERE attribute_set_id=? AND attribute_id=?`,
                [setId, attributeId],
            );
            expect(Number(removed[0].count)).toBe(0);
        } finally {
            try {
                if (setId > 0 && attributeId > 0) {
                    await connection.query(
                        `DELETE FROM ${table("ergonode_template_manual_placement")}
                         WHERE attribute_set_id=? AND attribute_id=?`,
                        [setId, attributeId],
                    );
                }
                await connection.query(
                    `DELETE FROM ${table("ergonode_product_attribute_mapping")}
                     WHERE ergonode_attribute_code=?`,
                    [sourceCode],
                );
                await connection.query(
                    `DELETE FROM ${table("ergonode_template")} WHERE code=?`,
                    [fixtureCode],
                );
                await connection.query(
                    "SELECT RELEASE_LOCK('ergonode-template-attribute-playwright')",
                );
            } finally {
                await connection.end();
            }
        }
    },
);
