import { expect, test } from "@packhauer/playwright-runner/fixtures";

import {
    loginToErgonodeAdmin,
    openErgonodeAdminPage,
} from "./support/admin-page";

test(
    "[ERG-CORE-001] Admin can navigate through the Ergonode workspace",
    {
        annotation: [
            { type: "id", description: "ERG-CORE-001" },
            {
                type: "description",
                description:
                    "Open readiness, follow Languages and return through the Ergonode menu. Connection availability is tested separately; this scenario never configures the integration.",
            },
            { type: "kind", description: "e2e" },
            { type: "module", description: "Ergonode_CoreAdminUi" },
            { type: "requires-module", description: "Ergonode_Core" },
            {
                type: "requires-module",
                description: "Ergonode_LanguageAdminUi",
            },
            { type: "acl-resource", description: "Ergonode_Core::main" },
            {
                type: "acl-resource",
                description: "Ergonode_Core::readiness",
            },
            {
                type: "acl-resource",
                description: "Ergonode_Language::language_mapping",
            },
            { type: "data-policy", description: "read-only" },
        ],
    },
    async ({ e2e }) => {
        await loginToErgonodeAdmin(e2e);
        const readiness = await openErgonodeAdminPage(
            e2e,
            "ergonode/readiness/index",
            ".ver-readiness",
        );
        const navigation = readiness.getByRole("navigation", {
            name: "Ergonode sections",
        });
        await expect(navigation).toBeVisible();
        // Optional modules contribute additional destinations to this navigation.
        await expect(
            navigation.getByRole("link", { name: "Languages", exact: true }),
        ).toHaveCount(1);

        await navigation
            .getByRole("link", { name: "Languages", exact: true })
            .click();
        await expect(
            e2e.page.getByRole("heading", { name: "Languages", exact: true }),
        ).toBeVisible();
        // A disabled connection intentionally replaces the mapping workspace with
        // ConnectionNotice. The shared admin menu still provides the return route.
        await openErgonodeAdminPage(
            e2e,
            "ergonode/readiness/index",
            ".ver-readiness",
        );
    },
);
