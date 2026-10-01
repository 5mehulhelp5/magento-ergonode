<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Model;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Ergonode\TemplatePublisher\Model\GraphQl\TemplateMutationFactory;
use Magento\Framework\Exception\LocalizedException;

class TemplateCreator implements TemplateCreatorInterface
{
    private const string QUERY = <<<'GRAPHQL'
query PublisherTemplate($code: TemplateCode!) {
  template(code: $code) {
    code
  }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $queryClient,
        private readonly TemplateMutationFactory $mutationFactory,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function create(string $code, array $names): void
    {
        $code = trim($code);
        $names = $this->normalizeNames($names);
        if (!preg_match('/^[a-z0-9_]{1,128}$/', $code)) {
            throw new LocalizedException(__(
                'Template code may contain lowercase letters, numbers and underscores (max. 128).'
            ));
        }
        if ($names === []) {
            throw new LocalizedException(__('At least one template name is required.'));
        }

        $data = $this->queryClient->queryWriteScope(self::QUERY, ['code' => $code]);
        if (isset($data['template']) && is_array($data['template'])) {
            return;
        }

        $operation = $this->mutationFactory->create($code, $names);
        foreach ($this->batchPlanner->plan([$operation]) as $batch) {
            if (!$this->executor->execute($batch)->isSuccessful()) {
                throw new LocalizedException(__('Unable to create Ergonode template "%1".', $code));
            }
        }
    }

    /**
     * @param array<string, string> $names
     * @return array<string, string>
     */
    private function normalizeNames(array $names): array
    {
        $normalized = [];
        foreach ($names as $language => $name) {
            $language = trim((string)$language);
            $name = trim((string)$name);
            if ($language !== '' && $name !== '') {
                $normalized[$language] = $name;
            }
        }

        return $normalized;
    }
}
