# Module Composer — lokalna wersja walidatora Vendivo

Kod przeniesiono z `vendivo-1/backend/packages/vendivo-module-composer`.
Reguły projektu i komendy opisuje [dokumentacja jakości](../../../docs/quality.md).

Walidator wykrywa moduły w `packages/*/*` oraz zachowuje obsługę `app/code`
dla testów narzędzia. Nazwę modułu odczytuje z `etc/module.xml`.

Sprawdza schemat JSON Composera, metadane, autoload oraz użycia klas w
PHP/PHTML/XML/GraphQL. Używane pakiety muszą być deklarowane bezpośrednio.
Zależność osiągalna przechodnio nie jest błędem ani kandydatem do automatycznego
usunięcia. Generator uzupełnia wymagania, zachowując istniejące deklaracje.
Źródła testowe nie wpływają na wymagania produkcyjne.

Walidacja `module.xml/sequence` jest osobnym zagadnieniem.

```bash
./quality composer
./quality composer Ergonode_Media
./quality tooling-tests
```

Kody wyjścia CLI: 0 — poprawnie, 1 — znalezione problemy, 2 — błąd wykonania.
