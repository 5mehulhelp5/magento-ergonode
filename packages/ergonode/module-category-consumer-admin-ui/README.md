# Ergonode_CategoryConsumerAdminUi

Moduł posiada kontrolki synchronizacji Ergonode → Magento,
postęp/pauzę/wznowienie oraz ustawienia importu. Wspólny panel, kontrolery zapisu i
odświeżania, style, model konfiguracji i nawigacja należą do CategoryAdminUi.

CategoryConsumerAdminUi → CategoryConsumer dostarcza wykonanie importu.
CategoryConsumerAdminUi → CategoryAdminUi dostarcza panel i kontrakty rozszerzeń.
`CategoryMappingConfiguration` dodaje metadane i URL-e synchronizacji; mixin
`category-mapping-consumer` dopina kontrolki synchronizacji do neutralnego API.
Panel działa bez tego mixina i bez Consumer. Konsumenci starszego panelu korzystają
z neutralnych kontraktów; Publisher nie zależy od Consumer ani od tego modułu.

## Zachowane adaptery dla zatwierdzonego grafu zależności

Rozszerzenia CategoryAttributeConsumerAdminUi i CategoryAttributeHistoryAdminUi
nie mają jeszcze zgody na bezpośrednią zależność od CategoryAdminUi. Dla ich
wkładów nawigacyjnych oraz CategoryConsumerHistoryAdminUi zachowano forwardery
CategoryNavigationItemProviderInterface i CategoryNavigationGroupProvider.
Forwardery używają jednej implementacji neutralnej;
nie kopiują logiki ani stylów. Consumer zastępuje rejestrację grupy w CoreAdminUi swoim
forwarderem, aby dotychczasowe wkłady DI atrybutów nadal trafiały do nawigacji.

Usunąć te forwardery po zatwierdzeniu i wdrożeniu odpowiednich krawędzi oraz aktualizacji
wskazanych konsumentów. Chronią je testy istniejącej nawigacji/historii oraz kontrakt
forwardowania nawigacji. Nie są wymagane przez Publisher ani neutralny panel.

## Kontrakty i weryfikacja

Kontrolery Sync/SyncStatus/PauseSync/ResetSyncCursor wywołują kontrakty
CategoryConsumer. Akcja Mapping/AutoMap i Auto Connect należą do CategoryAdminUi,
a algorytm podpowiedzi do Category. Consumer nie implementuje drugiego dopasowania.
Konfiguracja posiada pola importu w ergonode_categories/synchronization, cron i
data_cron; wspólny initial_page_size jest deklarowany przez CategoryAdminUi.
Istniejące zapisane ścieżki konfiguracji pozostają bez zmian.

Test/Js obejmuje kompozycję dopasowania, postęp, pauzę, odzyskiwanie odpowiedzi
oraz kompozycję panelu z neutralnymi zasobami. Test/Storybook/CategoryTreeMapping
przedstawia wspólny panel z kontrolkami Consumer. Testy neutralnych kontraktów,
mapowania i konfiguracji znajdują się w CategoryAdminUi/Test.

Style postępu i kontrolek synchronizacji są w category-synchronization.css, ładowanym
przez layout rozszerzenia i jego stories. Wspólny CSS nie zawiera selektorów vec-sync.
CSS i jego względne ikony są ładowane bezpośrednio z CategoryAdminUi. Dawne
symlinki wychodzące poza moduł zostały usunięte, aby moduł dało się spakować.
