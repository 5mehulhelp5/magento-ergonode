import { test } from '@packhauer/playwright-runner/fixtures';
import { ConnectionForm } from './support/connection';

test('[ERG-CONNECTION-RECOVERY] Restore Ergonode connection configuration', async ({ e2e }) => {
    await test.step('Przywróć konfigurację i odśwież cache Magento', async () => {
        await new ConnectionForm(e2e).recover();
    });
});
