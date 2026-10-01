<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryOption\Test\Unit;

use Ergonode\CategoryAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Option\AutoMatch;
use Ergonode\CoreAdminUi\Model\Request\MappingPayloadDecoder;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AutoMatchTest extends TestCase
{
    public function testReturnsSuggestionsFromNeutralDomainWithoutSaving(): void
    {
        $left = [['code' => 'navy', 'label' => 'Granatowy']];
        $right = [['code' => 'option_42', 'label' => 'Navy']];
        $matches = [['left' => $left[0], 'right' => $right[0]]];
        $matcher = $this->createMock(OptionAutoMatcherInterface::class);
        $matcher->expects(self::once())->method('suggest')->with(7, $left, $right)
            ->willReturn(['matches' => $matches]);
        $this->executePayload((new Json())->serialize([
            'attribute_mapping_id' => 7, 'ergonode_options' => $left, 'magento_options' => $right,
        ]), $matcher, ['success' => true, 'matches' => $matches]);
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidRequestBeforeCallingMatcher(string $payload, string $message): void
    {
        $matcher = $this->createMock(OptionAutoMatcherInterface::class);
        $matcher->expects(self::never())->method('suggest');
        $this->executePayload($payload, $matcher, ['success' => false, 'message' => $message]);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'invalid JSON' => ['{', 'Invalid save payload.'],
            'missing context' => ['{}', 'Missing category attribute mapping context.'],
            'missing source' => ['{"attribute_mapping_id":7}', 'Invalid Ergonode option snapshot.'],
            'invalid target' => [
                '{"attribute_mapping_id":7,"ergonode_options":[],"magento_options":"bad"}',
                'Invalid Magento option snapshot.',
            ],
        ];
    }

    /** @param array<string, mixed> $expected */
    private function executePayload(
        string $payload,
        OptionAutoMatcherInterface $matcher,
        array $expected
    ): void {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($payload);
        $result = $this->createMock(JsonResult::class);
        $result->expects(self::once())->method('setData')->with($expected)->willReturnSelf();
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::once())->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($result);
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($factory);
        $controller = new AutoMatch($context, new MappingPayloadDecoder(new Json()), $matcher);

        self::assertSame($result, $controller->execute());
        self::assertSame('Ergonode_CategoryConsumer::category_attribute_save', AutoMatch::ADMIN_RESOURCE);
    }
}
