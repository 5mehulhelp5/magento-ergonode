define([
    'mage/translate',
    'Ergonode_CoreAdminUi/js/bulk-publish-progress'
], function ($t, createBulkPublishProgress) {
    'use strict';

    return function createCategoryPublishProgress() {
        return createBulkPublishProgress({
            title: $t('Tworzenie kategorii w Ergonode'),
            progressLabel: $t('Postęp tworzenia kategorii'),
            unit: $t('kategorii'),
            processingBatch: $t('Przetwarzam paczkę %1 z %2 (%3 kategorii).'),
            finalizing: $t('Aktualizuję drzewo Ergonode i zapisuję mapowania w Magento…'),
            complete: $t('Wszystkie kategorie zostały utworzone i zapisane.'),
            partial: $t(
                'Proces zakończony. Poprawne kategorie zapisano, a błędy pozostawiono do ponowienia.'
            )
        });
    };
});
