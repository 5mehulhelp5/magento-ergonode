<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Contract;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValue;
use Ergonode\ProductPublisher\Model\Data\ProductState;
use Ergonode\ProductPublisher\Model\GraphQl\ProductMutationFactory;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use PHPUnit\Framework\TestCase;

class SchemaContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $schema;

    protected function setUp(): void
    {
        $this->schema = json_decode((string)file_get_contents(
            dirname(__DIR__, 6) . '/vendor/ergonode/module-publisher/Test/Contract/Fixture/ergonode-schema.json'
        ), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSupportedProductMutationsUseTheExactInputAndPayloadContract(): void
    {
        $inputs = [
            'productCreateGrouping' => $this->createInputFields(),
            'productCreateSimple' => $this->createInputFields(),
            'productCreateVariable' => $this->createInputFields(),
            'productDelete' => ['sku' => 'Sku!'],
            'productDeleteAttributeValueTranslations' => [
                'sku' => 'Sku!', 'attributeCode' => 'AttributeCode!', 'languages' => '[Language!]',
            ],
            'productGroupingAddChild' => ['sku' => 'Sku!', 'childSku' => 'Sku!', 'quantity' => 'Int'],
            'productGroupingRemoveChild' => ['sku' => 'Sku!', 'childSku' => 'Sku!'],
            'productGroupingSetChildQuantity' => ['sku' => 'Sku!', 'childSku' => 'Sku!', 'quantity' => 'Int!'],
            'productSetStatus' => ['sku' => 'Sku!', 'language' => 'Language!', 'statusCode' => 'StatusCode!'],
            'productSetTemplate' => ['sku' => 'Sku!', 'template' => 'TemplateCode!'],
            'productVariableAddVariant' => ['sku' => 'Sku!', 'variantSku' => 'Sku!'],
            'productVariableRemoveVariant' => ['sku' => 'Sku!', 'variantSku' => 'Sku!'],
            'productVariableSetBindings' => ['sku' => 'Sku!', 'bindingCodes' => '[AttributeCode!]!'],
        ];
        foreach ($this->valueSuffixes() as $suffix => $translationType) {
            $inputs['productAddAttributeValueTranslations' . $suffix] = [
                'sku' => 'Sku!',
                'attributeCode' => 'AttributeCode!',
                'translations' => '[' . $translationType . '!]!',
            ];
        }
        $inputs['productAddAttributeValueTranslationsProductRelation']['twoWayRelation'] = 'TwoWayRelation';
        foreach ($inputs as $operation => $expectedFields) {
            $signature = $this->schema['domains']['products']['mutations'][$operation];
            $inputType = rtrim($signature['arguments']['input']['type'], '!');
            self::assertSame('INPUT_OBJECT', $this->schema['types'][$inputType]['kind'], $operation);
            $actualFields = array_map(
                static fn (array $field): string => (string)$field['type'],
                $this->schema['types'][$inputType]['inputFields']
            );
            foreach ($expectedFields as $field => $type) {
                self::assertSame($type, $actualFields[$field] ?? null, $operation . '.' . $field);
            }

            $payloadType = $signature['returns'];
            $payloadFields = array_map(
                static fn (array $field): string => (string)$field['type'],
                $this->schema['types'][$payloadType]['fields']
            );
            self::assertSame($this->payloadFields($operation), $payloadFields, $operation . ' payload fields');
        }
    }

    public function testFactoryOperationsConformToPinnedMutationContracts(): void
    {
        foreach ($this->factoryOperations() as $operation) {
            $field = $operation->getField();
            $signature = $this->schema['domains']['products']['mutations'][$field];
            $inputVariable = $operation->getVariables()['input'];
            self::assertSame($signature['arguments']['input']['type'], $inputVariable->getType(), $field);
            $inputType = rtrim($inputVariable->getType(), '!');
            $schemaFields = $this->schema['types'][$inputType]['inputFields'];
            $input = $inputVariable->getValue();
            self::assertSame([], array_diff(array_keys($input), array_keys($schemaFields)), $field);
            foreach ($schemaFields as $name => $definition) {
                if (str_ends_with((string)$definition['type'], '!')) {
                    self::assertArrayHasKey($name, $input, $field . '.' . $name);
                }
            }
            self::assertSame(
                $field === 'productDelete' ? ['sku'] : ['product.sku'],
                $operation->getResponseFields(),
                $field . ' response selection'
            );
        }
    }

    public function testEveryValueTranslationTypeAndLoaderResponsePathIsPinned(): void
    {
        foreach ($this->valueSuffixes() as $suffix => $translationType) {
            $inputFields = $this->schema['types'][$translationType]['inputFields'];
            self::assertArrayHasKey('language', $inputFields, $translationType);
            self::assertArrayHasKey('value', $inputFields, $translationType);
            $valueType = $suffix === 'Numeric' ? 'NumberAttributeValue' : $suffix . 'AttributeValue';
            self::assertContains($valueType, $this->schema['types']['AttributeValue']['possibleTypes']);
            self::assertArrayHasKey('translations', $this->schema['types'][$valueType]['fields']);
            self::assertArrayHasKey('attribute', $this->schema['types'][$valueType]['fields']);
        }
    }

    public function testProductAndVerifiedReferenceQueriesMatchSnapshot(): void
    {
        self::assertSame(
            'Sku!',
            $this->schema['domains']['products']['queries']['product']['arguments']['sku']['type']
        );
        self::assertSame('Product', $this->schema['domains']['products']['queries']['product']['returns']);
        self::assertSame(
            ['GroupingProduct', 'SimpleProduct', 'VariableProduct'],
            $this->schema['types']['Product']['possibleTypes']
        );
        foreach (['attributeList', 'sku', 'status', 'template'] as $field) {
            self::assertArrayHasKey($field, $this->schema['types']['Product']['fields']);
        }
        self::assertSame(
            'TemplateCode!',
            $this->schema['domains']['templates']['queries']['template']['arguments']['code']['type']
        );
        self::assertArrayHasKey('attributeList', $this->schema['types']['Template']['fields']);
        self::assertSame(
            'MultimediaPath!',
            $this->schema['domains']['multimedia']['queries']['multimedia']['arguments']['path']['type']
        );
        self::assertArrayHasKey('path', $this->schema['types']['Multimedia']['fields']);
    }

    public function testComparisonCanReadTranslationLanguagesWithoutTypeSpecificValues(): void
    {
        self::assertSame(
            '[AttributeValueTranslation!]!',
            $this->schema['types']['AttributeValue']['fields']['translations']['type']
        );
        self::assertSame(
            'Language!',
            $this->schema['types']['AttributeValueTranslation']['fields']['language']['type']
        );
        self::assertSame(
            ['after' => 'String', 'codes' => '[AttributeCode!]', 'first' => 'Int!'],
            $this->schema['types']['Product']['fields']['attributeList']['arguments']
        );
    }

    public function testModuleDoesNotImportDeferredPublishersOrLegacyGraphQlClient(): void
    {
        $composer = (string)file_get_contents(dirname(__DIR__, 3) . '/composer.json');
        self::assertStringNotContainsString('gmostafa/php-graphql-client', $composer);
        self::assertStringNotContainsString('template-publisher', $composer);
        self::assertStringNotContainsString('multimedia-publisher', $composer);
        $source = implode("\n", array_map(
            static fn (string $path): string => (string)file_get_contents($path),
            glob(dirname(__DIR__, 3) . '/Model/{GraphQl,Sync}/*.php', GLOB_BRACE) ?: []
        ));
        self::assertStringNotContainsString('Ergonode\\TemplatePublisher', $source);
        self::assertStringNotContainsString('Ergonode\\MultimediaPublisher', $source);
        self::assertStringNotContainsString('convertArrayRawObject', $source);
    }

    /** @return array<string, string> */
    private function valueSuffixes(): array
    {
        $types = [];
        foreach (ErgonodeAttributeTypeInterface::MUTATION_SUFFIXES as $suffix) {
            $types[$suffix] = $suffix . 'ValueTranslationInput';
        }

        return $types;
    }

    /** @return array<string, string> */
    private function createInputFields(): array
    {
        return [
            'sku' => 'Sku',
            'templateCode' => 'TemplateCode!',
        ];
    }

    /** @return array<string, string> */
    private function payloadFields(string $operation): array
    {
        $productType = match (true) {
            $operation === 'productCreateSimple' => 'SimpleProduct!',
            $operation === 'productCreateVariable', str_starts_with($operation, 'productVariable') =>
                'VariableProduct!',
            $operation === 'productCreateGrouping', str_starts_with($operation, 'productGrouping') =>
                'GroupingProduct!',
            default => 'Product!',
        };

        return $operation === 'productDelete' ? ['sku' => 'Sku!'] : ['product' => $productType];
    }

    /** @return MutationOperationInterface[] */
    private function factoryOperations(): array
    {
        $factory = new ProductMutationFactory();
        $state = new ProductState('SKU-1', 'simple', 'default');
        $operations = [
            $factory->create($state),
            $factory->create(new ProductState('SKU-V', 'variable', 'default')),
            $factory->create(new ProductState('SKU-G', 'grouping', 'default')),
            $factory->delete('SKU-1'),
            $factory->setTemplate('SKU-1', 'other'),
            $factory->setStatus('SKU-1', 'pl_PL', 'active'),
            $factory->deleteValueTranslation('SKU-1', 'name', 'pl_PL'),
            $factory->setBindings('SKU-V', ['size']),
            $factory->addVariant('SKU-V', 'SKU-2'),
            $factory->removeVariant('SKU-V', 'SKU-2'),
            $factory->addGroupedChild('SKU-G', 'SKU-2', 1),
            $factory->removeGroupedChild('SKU-G', 'SKU-2'),
            $factory->setGroupedChildQuantity('SKU-G', 'SKU-2', 2),
        ];
        foreach ($this->valueExamples() as $type => $value) {
            $operations[] = $factory->setValue(
                'SKU-1',
                new ProductAttributeValue('attribute_' . $type, $type, ['pl_PL' => $value]),
                'pl_PL'
            );
        }

        return $operations;
    }

    /** @return array<string, float|string|string[]> */
    private function valueExamples(): array
    {
        return [
            ErgonodeAttributeTypeInterface::TYPE_DATE => '2026-08-08',
            ErgonodeAttributeTypeInterface::TYPE_FILE => ['/file.pdf'],
            ErgonodeAttributeTypeInterface::TYPE_GALLERY => ['/one.jpg'],
            ErgonodeAttributeTypeInterface::TYPE_IMAGE => '/image.jpg',
            ErgonodeAttributeTypeInterface::TYPE_MULTI_SELECT => ['red'],
            ErgonodeAttributeTypeInterface::TYPE_NUMERIC => 1.0,
            ErgonodeAttributeTypeInterface::TYPE_PRICE => 1.0,
            ErgonodeAttributeTypeInterface::TYPE_PRODUCT_RELATION => ['SKU-2'],
            ErgonodeAttributeTypeInterface::TYPE_SELECT => 'red',
            ErgonodeAttributeTypeInterface::TYPE_TEXT => 'Text',
            ErgonodeAttributeTypeInterface::TYPE_TEXTAREA => 'Long text',
            ErgonodeAttributeTypeInterface::TYPE_UNIT => 1.0,
        ];
    }
}
