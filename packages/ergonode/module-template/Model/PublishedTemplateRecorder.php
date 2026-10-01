<?php

declare(strict_types=1);

namespace Ergonode\Template\Model;

use Ergonode\Template\Api\PublishedTemplateRecorderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class PublishedTemplateRecorder implements PublishedTemplateRecorderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    public function record(string $code, array $names): void
    {
        $localizedNames = [];
        foreach ($names as $language => $value) {
            $localizedNames[] = ['language' => $language, 'value' => $value];
        }
        $rawJson = $this->json->serialize(['code' => trim($code), 'name' => $localizedNames]);
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName('ergonode_template'),
            [
                'code' => trim($code),
                'raw_json' => $rawJson,
                'content_hash' => hash('sha256', $rawJson),
                'is_deleted' => 0,
            ],
            ['is_deleted']
        );
    }
}
