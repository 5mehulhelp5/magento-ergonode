<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\ResourceModel;

use Ergonode\AttributeConsumer\Model\ResourceModel\AttributeDefinitionCheck;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AttributeDefinitionCheckTest extends TestCase
{
    public function testFailureToRecordDoesNotInterruptSynchronization(): void
    {
        $flags = $this->createMock(FlagManager::class);
        $flags->expects(self::once())->method('saveFlag')
            ->willThrowException(new RuntimeException('storage unavailable'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        (new AttributeDefinitionCheck($flags, $logger))->record('running');
    }
}
