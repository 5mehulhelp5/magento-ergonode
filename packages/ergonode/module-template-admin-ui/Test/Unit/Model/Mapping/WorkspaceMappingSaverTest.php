<?php

declare(strict_types=1);

namespace Ergonode\TemplateAdminUi\Test\Unit\Model\Mapping;

use Ergonode\Template\Api\TemplateMappingSaverInterface;
use Ergonode\TemplateAdminUi\Model\Mapping\WorkspaceMappingSaver;
use Ergonode\TemplateAdminUi\Model\MappingVisibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WorkspaceMappingSaverTest extends TestCase
{
    public function testMappingFailureRollsBackBeforeVisibilityWrite(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $mappingSaver = $this->createStub(TemplateMappingSaverInterface::class);
        $mappingSaver->method('save')->willThrowException(new RuntimeException('Invalid mapping.'));
        $visibility = $this->createMock(MappingVisibility::class);
        $visibility->expects(self::never())->method('save');

        $this->expectException(RuntimeException::class);
        (new WorkspaceMappingSaver($resource, $mappingSaver, $visibility))->save(['template' => 4], []);
    }
}
