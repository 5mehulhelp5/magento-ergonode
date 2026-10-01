# Ergonode_CategoryAdminUi

Moduł posiada neutralny panel drzew kategorii: konfigurację powiązań Ergonode–Magento,
widok źródła i katalogu, ręczne mapowanie, automatyczne podpowiedzi dopasowania,
zapis/walidację szkicu, odświeżanie snapshotu,
wspólną nawigację i zasoby wizualne. Nie posiada synchronizacji Ergonode → Magento,
publikacji Magento → Ergonode, tabel, historii ani poświadczeń.

## Zależności i dane

CategoryAdminUi → Category: wspólne kontrakty i dane mapowań, odczyt katalogu,
walidacja, zapis, read-only propozycje dopasowania i odświeżanie źródła.
CategoryAdminUi → CoreAdminUi: prymitywy panelu, nawigacji, formularzy i transportu.
Moduł nie zależy od CategoryConsumer ani CategoryPublisher.

Domyślna wartość i odczyt ergonode_categories/tree/initial_page_size należą do Category;
tutaj jest pole w konfiguracji i walidator wejścia. Nie zmieniono zapisanych ścieżek
konfiguracji ani identyfikatorów ACL ról. ACL panelu definiuje Category, również gdy
utrwalony identyfikator zasobu zawiera historyczny prefiks CategoryConsumer.

## Punkty rozszerzeń

- CategoryNavigationItemProviderInterface i CategoryNavigationGroupProvider przyjmują
  wkłady DI do nawigacji domenowej; CoreAdminUi renderuje wspólny komponent.
- Layout ergonode.category_tree_mapping.index udostępnia child actions oraz sidebar.
  CategoryConsumerAdminUi dostarcza przyciski importu, a rozszerzenia dodają historię
  i publikację bez przenoszenia ich orkiestracji do neutralnego panelu.
- CategoryTreeMappingUiProvider udostępnia konfigurację źródła i katalogu. Consumer
  rozszerza ją pluginem o swoje URL-e, blokady i metadane. Błąd włączenia importu nie
  stanowi domyślnego błędu neutralnego panelu.
- veaCategoryMappingApi i zdarzenie ergonode:category-mapping:ready umożliwiają dodawanie
  szkiców, mapowanie, zapis i publikację. validateLayout() sprawdza cały bieżący szkic
  przed pierwszą mutacją. getDraft(), applyConfig(), operacje modeli, stan zajętości,
  cleanup obsługują rzeczywistych konsumentów.
  Zdarzenie ergonode:category-mapping:config przekazuje zmianę wybranego drzewa.
- mapping_actions dostarcza opcjonalne akcje menu; panel sam nie wybiera importu.
  Panel udostępnia Auto Connect dla drzewa i potomków przez `Mapping/AutoMap`, które
  wywołuje bazowy `CategoryAutoMapperInterface` i zmienia tylko szkic w przeglądarce.
  CategoryConsumerAdminUi dopina synchronizację przez RequireJS mixin.

## Walidacja i cykl życia

Wspólne operacje używają CategoryLayoutValidatorInterface. Walidacja nie publikuje
ani nie zapisuje mapowań. Stan modeli i indeksów jest wymieniany po zmianie drzewa;
rejestracje korzystają z CoreAdminUi workspace scope. Consumer posiada cykl życia
swojego okna postępu i procesu synchronizacji, Publisher swoich dialogów.

Test/Unit i Test/Js chronią neutralny zapis, uprawnienia, modele, wydajność mapowania,
spójność interakcji i brak zależności od modułów kierunkowych. Kompozycję z importem
przedstawia Storybook w CategoryConsumerAdminUi. Pełne wdrożenie wymaga włączenia nowego
modułu razem z istniejącymi rozszerzeniami oraz sprawdzenia DI i layoutów Magento.
