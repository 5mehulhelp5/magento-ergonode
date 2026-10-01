<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Test\Unit\Observer;

use Ergonode\CategoryConsumerAdminUi\Observer\CategorySettingsSaved;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

class CategorySettingsSavedTest extends TestCase
{
    public function testChangedImportSettingsShowOneManualRereadNotice(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects(self::once())->method('addNoticeMessage');
        (new CategorySettingsSaved($messages))->execute(new Observer(['event' => new Event([
            'changed_paths' => [
                'ergonode_categories/synchronization/name_mode',
                'ergonode_categories/synchronization/name_attribute',
            ],
        ])]));
    }

    public function testScheduleChangesDoNotSuggestReimportingData(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects(self::never())->method('addNoticeMessage');
        (new CategorySettingsSaved($messages))->execute(new Observer(['event' => new Event([
            'changed_paths' => ['ergonode_category_attributes/cron/schedule'],
        ])]));
    }
}
