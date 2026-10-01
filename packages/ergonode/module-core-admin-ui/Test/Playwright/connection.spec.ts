import { test, expect } from "./support/connection";

const metadata = (id: string, description: string, live = false) => ({
    annotation: [
        { type: "id", description: id },
        { type: "description", description },
        { type: "kind", description: "e2e" },
        { type: "requires-module", description: "Ergonode_Core" },
        { type: "requires-module", description: "Ergonode_Consumer" },
        { type: "requires-module", description: "Ergonode_ConsumerAdminUi" },
        { type: "acl-resource", description: "Ergonode_Core::main" },
        { type: "acl-resource", description: "Ergonode_Core::config" },
        {
            type: "data-policy",
            description:
                "Bez resetu bazy. Fixture kopiuje i odtwarza sześć ścieżek konfiguracji po każdym teście, także po błędzie. " +
                (live
                    ? "Wysyła zapytanie GraphQL __typename do stagingu Ergonode; bez importu, publikacji ani logowania REST."
                    : "Bez połączeń z zewnętrznym Ergonode."),
        },
    ],
});

test(
    "[ERG-CONN-001] Wyłączona integracja ukrywa pola i zachowuje wybór po ponownym włączeniu",
    metadata(
        "ERG-CONN-001",
        "Rozpoczyna od przygotowanego Disable/Test/Read only. Sprawdza ukrycie pól, włącza integrację, wybiera Production, wyłącza i ponownie włącza formularz. Oczekuje zachowania wyboru Production bez zapisywania go w bazie.",
    ),
    async ({ connection: c }) => {
        await test.step("Disable ukrywa wybór środowiska, trybu i oba profile", async () => {
            await expect(c.field("general_enabled")).toHaveValue("0");
            for (const field of [
                "general_environment",
                "general_mode",
                "test_url",
                "production_url",
            ]) {
                await expect(c.field(field)).toBeHidden();
            }
        });
        await test.step("Po włączeniu widoczny jest przygotowany profil Test i tryb odczytu", async () => {
            await c.selectRead();
            await expect(c.field("production_url")).toBeHidden();
        });
        await test.step("Wyłączenie i włączenie nie kasuje wyboru środowiska", async () => {
            await c.field("general_environment").selectOption("production");
            await c.field("general_enabled").selectOption("0");
            await expect(c.field("production_url")).toBeHidden();
            await c.field("general_enabled").selectOption("1");
            await expect(c.field("general_environment")).toHaveValue(
                "production",
            );
            await expect(c.field("production_url")).toBeVisible();
            await expect(c.field("test_url")).toBeHidden();
        });
    },
);

test(
    "[ERG-CONN-002] Przełączanie Test i Production pokazuje tylko pola wybranego profilu",
    metadata(
        "ERG-CONN-002",
        "Włącza formularz w trybie odczytu. Przechodzi Test → Production → Test i w każdym kroku sprawdza URL, limit, klucz oraz przycisk Test connection. Wpisany tymczasowo URL profilu Test ma pozostać w formularzu po powrocie. Niczego nie zapisuje.",
    ),
    async ({ connection: c }) => {
        await c.selectRead();
        await c.field("test_url").fill("https://fixture.ergonode.cloud");
        for (const environment of ["test", "production", "test"]) {
            await test.step(`Profil ${environment}: własne pola widoczne, drugi profil ukryty`, async () => {
                await c.field("general_environment").selectOption(environment);
                const inactive = environment === "test" ? "production" : "test";
                for (const suffix of [
                    "url",
                    "requests_per_minute",
                    "consumer_api_key",
                    "consumer_connection",
                ]) {
                    await expect(
                        c.field(`${environment}_${suffix}`),
                    ).toBeVisible();
                    await expect(c.field(`${inactive}_${suffix}`)).toBeHidden();
                }
            });
        }
        await expect(c.field("test_url")).toHaveValue(
            "https://fixture.ergonode.cloud",
        );
    },
);

test(
    "[ERG-CONN-003] Tryb odczytu i zapisu przełącza klucze i sekcję REST",
    {
        annotation: [
            ...metadata(
                "ERG-CONN-003",
                "Dla obu środowisk przełącza Read only → Read and write → Read only. Sprawdza widoczność klucza odczytu albo publikacji i odpowiadającego przycisku oraz brak osobnego formularza logowania REST. Nie zapisuje trybu i nie łączy się przez REST.",
            ).annotation,
            { type: "requires-module", description: "Ergonode_Publisher" },
            {
                type: "requires-module",
                description: "Ergonode_PublisherAdminUi",
            },
        ],
    },
    async ({ connection: c }) => {
        await c.selectRead();
        for (const environment of ["test", "production"]) {
            await c.field("general_environment").selectOption(environment);
            for (const mode of ["read", "write", "read"]) {
                await test.step(`${environment}, ${mode}: właściwy klucz i przycisk`, async () => {
                    await c.field("general_mode").selectOption(mode);
                    const active = mode === "read" ? "consumer" : "publisher";
                    const hidden = mode === "read" ? "publisher" : "consumer";
                    for (const suffix of ["api_key", "connection"]) {
                        await expect(
                            c.field(`${environment}_${active}_${suffix}`),
                        ).toBeVisible();
                        await expect(
                            c.field(`${environment}_${hidden}_${suffix}`),
                        ).toBeHidden();
                    }
                    if (mode === "write") {
                        await expect(c.page.locator(
                            "#row_ergonode_connection_general_rest_connection",
                        )).toHaveCount(0);
                    }
                });
            }
        }
    },
);

test(
    "[ERG-CONN-004] Formularz blokuje brak wymaganych danych oraz błędny URL i limit",
    metadata(
        "ERG-CONN-004",
        "Próbuje zapisać aktywny profil bez URL i klucza, następnie z niepoprawnym URL, ujemnym limitem i limitem ułamkowym. Każdy wariant musi wyświetlić błąd przy właściwym polu. Żaden nie może wysłać POST zapisującego konfigurację.",
    ),
    async ({ connection: c }) => {
        let saves = 0;
        c.page.on("request", (request) => {
            if (
                request.method() === "POST" &&
                request.url().includes("/system_config/save/")
            ) {
                saves++;
            }
        });
        await c.selectRead();
        const save = c.page.getByRole("button", {
            name: "Save Config",
            exact: true,
        });
        const error = (name: string) =>
            c.page.locator("#ergonode_connection_" + name + "-error");
        await test.step("Pusty URL i klucz są wymagane", async () => {
            await save.click();
            await expect(error("test_url")).toBeVisible();
            await expect(error("test_consumer_api_key")).toBeVisible();
        });
        await test.step("Błędny URL nie jest akceptowany", async () => {
            await c.fillSecret("fixture-key-never-sent");
            await c.field("test_url").fill("not-a-url");
            await save.click();
            await expect(error("test_url")).toBeVisible();
        });
        for (const value of ["-1", "1.5"]) {
            await test.step(`Limit ${value} nie jest nieujemną liczbą całkowitą`, async () => {
                await c
                    .field("test_url")
                    .fill("https://fixture.ergonode.cloud");
                await c.field("test_requests_per_minute").fill(value);
                await save.click();
                await expect(error("test_requests_per_minute")).toBeVisible();
            });
        }
        expect(
            saves,
            "Walidacja przeglądarki zablokowała wszystkie próby zapisu.",
        ).toBe(0);
    },
);

test(
    "[ERG-CONN-005] Zapisany profil Test i klucz odczytu pozostają po odświeżeniu",
    metadata(
        "ERG-CONN-005",
        "Wpisuje przez formularz URL i klucz odczytu ze wspólnej konfiguracji projektu, wybiera Test/Read only i limit 0. Zapisuje i odświeża stronę. Oczekuje zachowania profilu, URL i limitu oraz maski zamiast jawnego klucza. Następnie odtwarza poprzednią konfigurację.",
    ),
    async ({ connection: c }) => {
        await test.step("Uzupełnienie profilu Test/Read only danymi projektu", async () => {
            await c.selectRead();
            const credentials = c.credentials();
            await c.field("test_url").fill(credentials.url);
            await c.fillSecret(credentials.readApiKey);
            await c.field("test_requests_per_minute").fill("0");
        });
        await test.step("Zapis i ponowne odczytanie formularza", async () => {
            await c.submit();
            await expect(c.field("general_enabled")).toHaveValue("1");
            await expect(c.field("general_environment")).toHaveValue("test");
            await expect(c.field("general_mode")).toHaveValue("read");
            await expect(c.field("test_url")).toHaveValue(c.credentials().url);
            await expect(c.field("test_requests_per_minute")).toHaveValue("0");
            const masked = await c.field("test_consumer_api_key").inputValue();
            expect(
                /^\*+$/.test(masked),
                "Po odświeżeniu klucz jest zamaskowany.",
            ).toBe(true);
        });
    },
);

test(
    "[ERG-CONN-006] Test connection sprawdza klucz wpisany przed zapisaniem formularza",
    metadata(
        "ERG-CONN-006",
        "Włącza formularz, wybiera Test/Read only i wpisuje dane stagingu z konfiguracji projektu. Bez zapisywania klika Test connection. Sprawdza rzeczywistą odpowiedź backendu, profil test/read, komunikat sukcesu i ponowne odblokowanie przycisku.",
        true,
    ),
    async ({ connection: c }) => {
        await c.selectRead();
        await c.field("test_url").fill(c.credentials().url);
        await c.fillSecret(c.credentials().readApiKey);
        await test.step("Zapytanie przez Magento do stagingu Ergonode kończy się sukcesem", async () => {
            const responsePromise = c.page.waitForResponse(
                (response) =>
                    response.request().method() === "POST" &&
                    response
                        .url()
                        .toLowerCase()
                        .includes("/connection/testconnection/"),
                { timeout: 45000 },
            );
            await c.field("test_consumer_connection").click();
            const response = await responsePromise;
            const payload = new URLSearchParams(
                response.request().postData() || "",
            );
            expect(payload.get("environment")).toBe("test");
            expect(payload.get("mode")).toBe("read");
            expect(response.status()).toBe(200);
            expect(
                (await response.json()).success,
                "Backend potwierdził połączenie z Ergonode.",
            ).toBe(true);
            await expect(c.field("test_consumer_connection_result")).toHaveText(
                "Connection successful.",
            );
            await expect(c.field("test_consumer_connection")).toBeEnabled();
        });
    },
);

test(
    "[ERG-CONN-007] Test connection używa zapisanego klucza ukrytego pod maską",
    metadata(
        "ERG-CONN-007",
        "Samodzielna fixture zapisuje Test/Read only przez Magento, korzystając z danych projektu. Po odświeżeniu klucz jest maską. Kliknięcie Test connection ma wysłać maskę i zakończyć się sukcesem dzięki odczytaniu i odszyfrowaniu właściwego zapisanego klucza przez backend. Nie wymaga wcześniejszego testu zapisu.",
        true,
    ),
    async ({ connection: c }) => {
        await test.step("Niezależne przygotowanie konfiguracji dla tego scenariusza", async () => {
            await c.prepareReadConnection();
        });
        await test.step("Połączenie działa z maską zamiast ponownego podawania klucza", async () => {
            const responsePromise = c.page.waitForResponse(
                (response) =>
                    response.request().method() === "POST" &&
                    response
                        .url()
                        .toLowerCase()
                        .includes("/connection/testconnection/"),
                { timeout: 45000 },
            );
            await c.field("test_consumer_connection").click();
            const response = await responsePromise;
            const payload = new URLSearchParams(
                response.request().postData() || "",
            );
            expect(
                /^\*+$/.test(payload.get("api_key") || ""),
                "Przeglądarka wysłała maskę zapisanego klucza.",
            ).toBe(true);
            expect(
                (await response.json()).success,
                "Backend użył właściwego zapisanego klucza.",
            ).toBe(true);
            await expect(c.field("test_consumer_connection_result")).toHaveText(
                "Connection successful.",
            );
            await expect(c.field("test_consumer_connection")).toBeEnabled();
        });
    },
);
