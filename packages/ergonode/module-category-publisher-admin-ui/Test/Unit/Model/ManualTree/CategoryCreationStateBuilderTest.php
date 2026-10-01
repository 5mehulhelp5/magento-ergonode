<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualTree;

use Ergonode\CategoryPublisher\Api\CategoryDesiredStateFactoryInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryCreationStateBuilder;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryCreationStateBuilderTest extends TestCase
{
    public function testBuildsValidStatesAndResolvesLanguageOnlyOnce(): void
    {
        $firstState = $this->createStub(CategoryStateInterface::class);
        $secondState = $this->createStub(CategoryStateInterface::class);
        $stateFactory = $this->createMock(CategoryDesiredStateFactoryInterface::class);
        $stateFactory->expects(self::exactly(2))->method('createCategory')->willReturnMap([
            ['chairs', ['pl_PL' => 'Krzesła'], $firstState],
            ['tables', ['pl_PL' => 'Stoły'], $secondState],
        ]);
        $language = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $language->expects(self::once())->method('requireAdminLanguageCode')->willReturn('pl_PL');
        $builder = new CategoryCreationStateBuilder($stateFactory, $language);

        self::assertSame($firstState, $builder->build('chairs', 'Krzesła'));
        self::assertSame($secondState, $builder->build('tables', 'Stoły'));
    }

    public function testReturnsValidationErrorsWithoutResolvingLanguage(): void
    {
        $language = $this->createMock(LanguageStoreMappingProviderInterface::class);
        $language->expects(self::never())->method('requireAdminLanguageCode');
        $builder = new CategoryCreationStateBuilder(
            $this->createStub(CategoryDesiredStateFactoryInterface::class),
            $language
        );

        self::assertStringContainsString(
            'Invalid Ergonode category code',
            $builder->validationError('Bad-Code', 'Bad')
        );
        self::assertStringContainsString('label is required', $builder->validationError('valid_code', ''));
    }
}
