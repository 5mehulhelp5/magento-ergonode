# Magento Ergonode

Lokalny projekt Magento Open Source 2.4.9 uruchamiany przez DDEV.

| Element | Konfiguracja |
| --- | --- |
| PHP | 8.5 |
| Baza danych | MariaDB 11.8 |
| Wyszukiwarka | OpenSearch 3.1.0 |
| Serwer WWW | nginx + PHP-FPM, document root `pub` |
| Tryb Magento | developer |
| Locale / waluta | pl_PL / PLN |
| Strefa czasowa | Europe/Warsaw |

Sklep: <https://magento-ergonode.ddev.site/>. Panel administracyjny:
<https://magento-ergonode.ddev.site/admin/>. Mailpit:
<https://magento-ergonode.ddev.site:8026/>. OpenSearch Dashboards:
<https://magento-ergonode.ddev.site:5602/>.

Projekt zawiera Magento bez danych przykładowych oraz 66 modułów Ergonode
i dwa wymagane moduły PackHauer, instalowane przez Composer z lokalnego `packages`.
Ustawione `pl_PL` nie oznacza zainstalowania pełnego polskiego pakietu tłumaczeń.

## Wymagania i pierwsza instalacja

Potrzebne są Docker (np. OrbStack), DDEV 1.25.2 lub nowszy i własne klucze
do `repo.magento.com`. Klucze należy skonfigurować w globalnym Composerze
kontenera lub przez DDEV `homeadditions`; pliku `auth.json` nie zapisujemy w Git.
Na komputerze, na którym przygotowano projekt, klucze są już dostępne przez
globalne DDEV `homeadditions`.

```bash
git clone git@github.com:packhauer/magento-ergonode.git
cd magento-ergonode
ddev start
ddev composer install --no-interaction --prefer-dist
cp .env.example .env.local
chmod 600 .env.local
```

Ustaw własne `MAGENTO_ADMIN_PASSWORD` w `.env.local`, następnie uruchom:

```bash
ddev magento-install
```

Polecenie tworzy schemat bazy i administratora, ustawia HTTPS, OpenSearch,
tryb developer, wykonuje indeksowanie i konfiguruje cron. Jest przeznaczone
do świeżej instalacji: odmawia działania, gdy istnieje `app/etc/env.php`.
Domyślny login administratora to `admin`. Hasło istniejącej lokalnej instalacji
zapisano w ignorowanym pliku `.env.local`.

W lokalnym środowisku wyłączone są `Magento_TwoFactorAuth` i
`Magento_AdminAdobeImsTwoFactorAuth`, zgodnie z quickstartem DDEV. Tej konfiguracji
nie należy przenosić na środowisko produkcyjne.

## Pakiety Ergonode

Źródła skopiowano z `vendivo-1/backend/packages` do tego repozytorium:

- `packages/ergonode`: 66 aktywnych modułów, obejmujących podstawę integracji,
  mapowania, import, publikację, historię, media i panel administracyjny.
- `packages/packhauer/module-unit-attribute`: wymagany przez moduły atrybutów
  produktu po stronie importu i publikacji.
- `packages/packhauer/module-file-attribute`: wymagany przez import produktów.

Repozytorium Composer typu `path` wskazuje `packages/*/*` i ma ustawione
`symlink: true`. Linki w `vendor` prowadzą do kopii w tym projekcie.
Moduły mediów `Media`, `ProductMedia`, `ProductMediaConsumer` i `MediaAdminUi`
skopiowano dodatkowo z `vendivo-1/backend/app/code/Ergonode` i zainstalowano jako
lokalne pakiety Composer. Pozostałe pakiety Vendivo i moduły z `backlog` nie są
częścią projektu. Klucze API oraz dane bazy pozostają poza Git. W lokalnej bazie
skonfigurowano testowe połączenie Ergonode z `vendivo-1`.

Zwykłe zmiany kodu w `packages` są widoczne przez symlinki w `vendor`.
Po zmianie manifestów pakietów wykonaj:

```bash
ddev mutagen sync
ddev composer update 'ergonode/*' 'packhauer/*' --minimal-changes --no-interaction
ddev magento setup:upgrade
ddev magento setup:di:compile
ddev magento cache:flush
```

Przy odtwarzaniu projektu z istniejącego `composer.lock` wystarczy
`ddev composer install`. Połączenie z Ergonode należy skonfigurować w panelu
administracyjnym dla własnego środowiska; instalacja pakietów nie uruchamia
synchronizacji z instancją używaną w `vendivo-1`.

## Import mediów produktowych

Media działają w trybie `shared`: produkty korzystają ze wspólnych plików
rozpoznawanych według SHA-256 zawartości. Rejestr źródeł, indeks lokalnych plików,
rewizje i powiązania produktów są zapisywane w tabelach `ergonode_media_*`.
Jedna partia kolejki pamięta już rozpoznane ścieżki, dzięki czemu kolejne produkty
nie powtarzają kontroli pliku ani hashowania zweryfikowanej zawartości.

Lokalnie wybrano standardowy atrybut Ergonode `gallery`, tryb `shared` oraz
mapowanie `pl_PL` na Default Values i domyślny Store View. Pierwszy pełny skan
zakończono na pustym katalogu produktów. Konfiguracja i wynik skanu są w bazie;
przy odtwarzaniu środowiska trzeba je ustawić ponownie:

```bash
ddev magento config:set ergonode_products/media/gallery_attribute gallery
ddev magento config:set ergonode_products/media/gallery_mode shared
ddev magento cache:clean config
ddev magento ergonode:media:scan
ddev magento ergonode:media:list --limit=100
```

Przed importem skonfiguruj połączenie i aktywne mapowanie języka w panelu Ergonode.
Jeżeli źródło ma inny atrybut Gallery, użyj jego kodu. Kolejka
`ergonode.media.gallery` jest obsługiwana przez standardowy mechanizm konsumentów
Magento uruchamiany z crona; można ją również przetworzyć ręcznie:

```bash
ddev magento queue:consumers:start ergonode.media.gallery --max-messages=1
```

Testy i dokładny kontrakt ponownego użycia opisano w
[dokumencie mediów](docs/ergonode-media-single-instance.md).

```bash
ddev mutagen sync
ddev exec vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap-unit.php \
  --do-not-cache-result packages/ergonode/module-media/Test/Unit \
  packages/ergonode/module-product-media/Test/Unit \
  packages/ergonode/module-product-media-consumer/Test/Unit \
  packages/ergonode/module-media-admin-ui/Test/Unit \
  packages/ergonode/module-product-attribute/Test/Unit/Model/Mapping/MediaCapabilityTest.php
ddev exec node --test packages/ergonode/module-media-admin-ui/Test/Js/system-config.test.cjs
ddev exec --raw -- php tests/media-import-smoke.php
```

Smoke test wymaga pustej kolejki pracy mediów i ukończonego skanu w trybie
`shared`. Korzysta z lokalnego serwera zdjęć bez kluczy API, tworzy produkty
wewnątrz transakcji, wycofuje ich dane i usuwa własny katalog plików testowych.

## Codzienna praca

```bash
ddev start
ddev magento cache:flush
ddev magento indexer:reindex
ddev magento setup:upgrade
ddev stop
```

DDEV uruchamia daemon cron i przy każdym starcie odtwarza crontab Magento
dla zainstalowanego projektu. Zadania Magento są uruchamiane co minutę.
Poczta trafia do Mailpit. Do podstawowej instalacji używane są plikowe cache
i sesje; usługi RabbitMQ i Valkey nie są wymagane w tej konfiguracji.

```bash
ddev describe
ddev composer check-platform-reqs
ddev magento setup:db:status
ddev magento indexer:status
ddev exec supervisorctl status
```

## Git

Remote `origin`: `git@github.com:packhauer/magento-ergonode.git`. Główna gałąź: `main`.
Wersje zależności są utrwalone w `composer.lock`; konfiguracja modułów w `app/etc/config.php`.
Konfiguracja DDEV i jawne wersje OpenSearch są częścią repozytorium.

`.gitignore` wyklucza zależności, `app/etc/env.php`, `auth.json`, `.env.local`,
media, cache, logi, wygenerowane pliki i zrzuty bazy. Lokalna baza danych oraz
hasło administratora nie są częścią repozytorium.
Biblioteki bazowe Magento oraz jego narzędzia i testy w `lib`, `setup`, `dev`
i `phpserver` są odtwarzane przez Composer i również wykluczone z Git.

Dokumentacja: [Magento w DDEV](https://docs.ddev.com/en/stable/users/quickstart/#magento-2),
[wymagania Magento](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements),
[dodatek OpenSearch](https://github.com/ddev/ddev-opensearch).

## Bramki jakości

Analizy i testy przeniesione z Vendivo obejmują lokalne pakiety Ergonode i PackHauer.

```bash
ddev exec npm ci --ignore-scripts
./quality modules
./quality module-check Ergonode_Media --base-commit=HEAD
./quality module-done Ergonode_Media
./quality all
```

Zasady Factory, pluginów i zależności, opis bramek, testów integracyjnych oraz
opcjonalnych audytów: [Bramki jakości](docs/quality.md).
