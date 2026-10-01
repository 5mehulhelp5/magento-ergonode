<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Test\Unit\Model\GraphQl;

use Ergonode\TemplateConsumer\Model\GraphQl\TemplateQueries;
use PHPUnit\Framework\TestCase;

class TemplateQueriesTest extends TestCase
{
    public function testSynchronizationQueryUsesAvailableTemplateList(): void
    {
        self::assertStringContainsString(
            'templateList(first: $first, after: $after)',
            TemplateQueries::TEMPLATE_LIST
        );
        self::assertStringContainsString('node {', TemplateQueries::TEMPLATE_LIST);
        self::assertStringNotContainsString('templateStream', TemplateQueries::TEMPLATE_LIST);
    }

    public function testTemplateDetailsContainsOnlyBaseTemplateIdentity(): void
    {
        $expected = <<<'GRAPHQL'
query ErgonodeTemplateDetails($code: TemplateCode!) {
  template(code: $code) {
    code
    name {
      language
      value
    }
  }
}
GRAPHQL;

        self::assertSame($expected, TemplateQueries::TEMPLATE_DETAILS);
        self::assertStringNotContainsString('section', TemplateQueries::TEMPLATE_DETAILS);
        self::assertStringNotContainsString('attribute', TemplateQueries::TEMPLATE_DETAILS);
    }
}
