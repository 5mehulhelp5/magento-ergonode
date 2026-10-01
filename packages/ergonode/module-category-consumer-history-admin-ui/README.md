# Ergonode Category Consumer History Admin UI

## Granica modułu i zasady rozbudowy

Opcjonalna prezentacja historii kategorii: przeglądarka operacji i historycznych
drzew oraz panel operacji przy mapowaniu. Jest adapterem odczytu historii;
przekazuje również kontekst administratora do jej rejestratora.
[Mapa rodziny i zasady zmiany granic](../Category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane i punkty integracji

- Nie posiada tabel ani konfiguracji synchronizacji. Odczytuje operacje i stan
  wyłącznie przez `CategoryConsumerHistory/Api/CategoryTreeHistoryQueryInterface`.
  Lokalnie utrzymuje wybór operacji, filtry, paginację i stan prezentacji drzewa.
- [adminhtml/di.xml](etc/adminhtml/di.xml) dostarcza implementację
  `HistoryActorProviderInterface` korzystającą z sesji administratora.
  Ten adapter nie zapisuje sam rekordów historii.
- Layout dopina panel do kontenera
  `ergonode.category_tree_mapping.sidebar` należącego do CategoryConsumerAdminUi.
- Panel mapowania pobiera tylko podsumowania operacji aktywnego `category_tree_id`;
  pełny stan i delty są potrzebne w przeglądarce historii. Współdzielone renderowanie
  kart operacji i stronicowania pozostaje wewnątrz tego modułu.

### Zależności i wpływ zmian

[Composer](composer.json): `CategoryConsumerHistoryAdminUi → CategoryConsumerHistory`
oraz `CategoryConsumerHistoryAdminUi → CategoryConsumerAdminUi` (host panelu).
Ten drugi pakiet wymaga CategoryAdminUi; lokalna bramka odrzuca redundantny
bezpośredni `require`. [module.xml](etc/module.xml) deklaruje bezpośrednią
sekwencję do CategoryAdminUi, którego kanoniczny CSS i ikony są używane.
[module.xml](etc/module.xml) uwzględnia też Magento_Backend i CoreAdminUi.
Ekran korzysta z wyglądu drzew i zdarzeń mapowania gospodarza oraz wspólnych elementów UI.

Zmiana kontraktu historii wymaga dostosowania kontrolerów, parsera stanu i obu
widoków. Zmiana zdarzeń `ready`, `saved`, `refreshed`, `snapshot-removed` lub kontekstu
aktywnego drzewa w panelu mapowania wpływa na odświeżanie historii. Zmiana bazowych
kart/CSS wymaga sprawdzenia historycznych rodziców, pozycji i odmapowanych kategorii.
Oznaczenia kontekstowe w widoku nie tworzą nowych delt ani nie zwiększają liczników
operacji zwracanych przez runtime.

### Poza zakresem i wskazówki dla agenta

Nie przechwytuj tutaj operacji, nie zapisuj historii, nie odtwarzaj jej algorytmu
w JS i nie dodawaj mutacji kategorii ani przywracania drzewa. Rozbudowę zapytań,
rekonstrukcji i liczenia wyników kieruj do CategoryConsumerHistory. Nowy element
ogólnego ekranu mapowania należy do CategoryConsumerAdminUi.

Przed zmianą UI przeczytaj [kontrakt Storybook](../../../../dev/tools/ergonode-storybook/README.md).
Sprawdź [testy JS](Test/Js), [testy bloków](Test/Unit) i [stories](Test/Storybook),
w tym nawigację między mapowaniem i historią oraz wybór operacji spoza pierwszej strony.

## Szczegóły działania

This module owns the Magento Admin presentation of grouped category-tree history: the
three-column history workspace, its JSON endpoint, styling, and browser behavior.

The workspace shows the Ergonode tree on the left, the Magento tree in the middle and grouped
operations on the right. Both tree panels remain visible and have independent search fields.
Each tree header combines its title and search using the shared
CoreAdminUi header and expandable-search variants. The bordered search icon expands on hover,
focus or a non-empty value, leaving the tree directly below the header. Headers omit redundant
state dates and operation-selection instructions; the selected operation card supplies that context.
The Ergonode header reuses the shared options menu with a `Connected` visibility control.
Connected categories are hidden initially according to each historical row's mapping snapshot.
Mapped ancestors remain when needed to locate visible unconnected descendants. Search respects
this filter; `Show on tree` reveals a connected source category and updates the control when needed.
The Magento tree projects both change streams
onto the Magento hierarchy; the Ergonode tree retains the source hierarchy, including unmapped
categories and historical locations. It uses the source parent and source position snapshots so
source-only moves show both their previous and destination locations even when the effective manual
layout is unchanged. Both trees show the same grouped operation without requesting additional
data or changing history storage.
The Ergonode column includes the configured tree root from the selected historical
tree metadata. It follows the same connected-category filter as the mapping root,
remaining visible as an ancestor of unconnected descendants. Unmapped source rows
show their code without a redundant `Not mapped` suffix.
Unchanged descendants of a created source category carry a new-branch icon with
the `Child of a created category` tooltip naming that ancestor. Their own recorded
changes take precedence; historical occurrences do not inherit this context.
These contextual icons neither add history deltas nor increase operation counts.
The created icon uses a 24 × 20 px mask in a 28 px wide indicator.
Tree rows reuse the mapping module's card, typography, hierarchy lines, expand/collapse controls
and icon styles. A mapped Magento row presents only the Magento category name, including at historical
locations. The Magento path includes the Ergonode code in parentheses.
The code is bold only when
the selected operation created or replaced that mapping. An unmapped row keeps
its former Ergonode code next to the Magento path and strikes that code through. The Ergonode
column uses the mapping screen's neutral card colors and typography. Connection and remapping
changes retain their Connected icon and full change tooltip; disconnection icons remain hidden there.
Other source change icons remain available. History adds change
accents to Magento rows and one contextual icon per changed Magento row; each retained icon owns
the complete change tooltip. Move destinations use bicycle-journey, previous locations use bike-path,
and position-only changes use ranking-podium. They render their previous
location without striking through the name, path or Ergonode code. Previous Magento locations have
a striped background; the Ergonode column retains its neutral colors. When movement also changes
the mapping, the Magento tree projects the previous mapped category into the effective source layout
recorded before the operation. It restores the full chain of former parents, including moved, deleted
and unmapped ancestors, sharing historical branches where possible. The saved Magento path remains
mapping metadata; it is not rewritten to pretend that the manual layout was already applied in Magento.
The former mapped category's current row also describes its transition from that projected location,
so a changed parent takes precedence over a position-only icon. A separate Ergonode move retains
the current mapped category at the source's former parent as well. For example, simultaneous movement
and remapping can show former Watches under Women / Tops and former Jackets under Gear, with each
category retaining its own current row. Previous-parent tooltips follow the displayed historical row.
Both trees attach historical descendants to their parents' previous occurrences, preserving old labels
and order even through unchanged intermediate ancestors. Current descendants stay in the current branch.
Excluded and disconnected Magento rows also use striped backgrounds in their existing action colors.
Excluded rows strike the same
three values, while visibility, deletion, mapping and other actions
reuse the existing Admin UI icon vocabulary. Disconnected rows use the mapping module’s crossed-chain
icon and the `Disconnected` label; the old puzzle asset is no longer used. Unchanged rows use the base tree styles without
history icons. Branches start collapsed unless they contain a changed descendant, including
historical locations; choosing another operation recalculates those paths. Manual expansion and
search remain available in both panels.

The operations column separates each card's title and status, completion time and event
origin (`admin`, `cli`, or `cron`), and change/category counts into readable rows. Technical
processing modes are omitted from this navigation. The
first ten operations are loaded initially; older records are fetched with keyset pagination in
batches of ten, with loaded, total and remaining counts shown below the list. Each operation has a
secondary Change list button inside its card that opens its summary, selected change and change list in a native modal
dialog. Selecting a change updates its details; Show on tree closes the dialog and focuses that
category in its tree panel. Close and Escape return focus to the operation's Change list button.
The dialog provides an index of all changes and actor context; individual before/after descriptions
also appear in tree tooltips, so this is an alternative way to inspect the same operation.

It reads history exclusively through `Ergonode_CategoryConsumerHistory` contracts and contributes a
history sidebar through `Ergonode_CategoryConsumerAdminUi`. It does not capture changes,
write history records, mutate category trees, or own category synchronization.

The module also contributes an ACL-protected operations panel below the mapping
configurations through the mapping sidebar layout container. It loads only the
selected mapping’s operation summaries from `category_tree_history/operations`,
in pages of ten; it never requests tree states or entity deltas on the mapping page.
Both screens share the operation-card and pagination renderer. Current state stays
selected in mapping; each historical card has one link to the history viewer
with `category_tree_id` and `operation_id`. The mapping sidebar has no duplicate Change list
shortcut. Existing history URLs with `details=1` still open the dialog.
Current state in history links back to that mapping and preserves the mapping
workspace’s existing guard for unsaved changes when leaving it.

`remove_snapshot` is presented as `Removed from list`. The mapping history panel
reloads its first page on `ergonode:category-mapping:snapshot-removed`, just as it
does after a successful save or refresh.

A deep link to an operation outside the first page reads just that operation’s
summary in addition to the first page and shows its selected card above the list.
It does not preload intervening pages. Once pagination reaches that operation,
only its regular list card remains; pagination counts refer to the regular list.

The Operations header owns the checked-by-default “Only changes” filter. The initial
state payload contains changed entities and the parents/mapping counterparts needed
to present them. Switching the filter requests the selected state through the existing
authenticated GET endpoint (`changes_only=0` restores the full viewer). The read
contracts and stored history remain unchanged: filtering reduces browser transfer
and rendering, while the server still reads/reconstructs the historical state.
Failed requests retain the displayed state and restore its checkbox value; newer
requests take precedence over late responses.

## History configuration form

Presents the `Tree history` group in Ergonode's Categories configuration:
recording, independent automatic cleanup, positive retention days and cron schedule.
`CategoryConsumerHistory` owns defaults, config reads, scheduling and deletion; this module owns
only the fields and validation. The form uses the existing CoreAdminUi cron validator.
The cleanup comment explicitly describes permanent deletion of existing old records.

History is opened from the mapping screen's right sidebar. This module does not
register a main-menu or section-navigation destination; direct history URLs remain valid.
