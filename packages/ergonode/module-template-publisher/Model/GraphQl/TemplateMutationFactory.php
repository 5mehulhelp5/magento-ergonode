<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Model\GraphQl;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Model\Data\MutationOperation;
use Ergonode\Publisher\Model\Data\MutationVariable;

class TemplateMutationFactory
{
    /**
     * @param array<string, string> $names
     */
    public function create(string $code, array $names): MutationOperationInterface
    {
        $translations = [];
        foreach ($names as $language => $value) {
            $translations[] = ['language' => $language, 'value' => $value];
        }

        return new MutationOperation(
            'templateCreate',
            ['input' => new MutationVariable('TemplateCreateInput!', [
                'code' => $code,
                'name' => $translations,
            ])],
            ['template.code'],
            ['operation_key' => 'create']
        );
    }
}
