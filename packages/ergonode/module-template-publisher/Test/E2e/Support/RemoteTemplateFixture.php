<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisher\Test\E2e\Support;

use Ergonode\Core\Api\GraphQlMutationClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use RuntimeException;

class RemoteTemplateFixture
{
    private const string QUERY = <<<'GRAPHQL'
query PublisherTemplateFixture($code: TemplateCode!) {
  template(code: $code) {
    code
  }
}
GRAPHQL;

    private const string DELETE = <<<'GRAPHQL'
mutation DeletePublisherTemplateFixture($input: TemplateDeleteInput!) {
  templateDelete(input: $input) {
    code
  }
}
GRAPHQL;

    public function __construct(
        private readonly GraphQlWriteScopeQueryClientInterface $queryClient,
        private readonly GraphQlMutationClientInterface $mutationClient
    ) {
    }

    public function uniqueCode(string $scope): string
    {
        return sprintf(
            'codex_template_publisher_%s_%s_%s',
            $scope,
            gmdate('YmdHis'),
            bin2hex(random_bytes(4))
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array
    {
        $data = $this->queryClient->queryWriteScope(self::QUERY, ['code' => $code]);
        $template = $data['template'] ?? null;

        return is_array($template) ? $template : null;
    }

    public function removeAndVerify(string $code): void
    {
        if ($this->find($code) === null) {
            return;
        }
        $response = $this->mutationClient->mutateWithResponse(self::DELETE, [
            'input' => ['code' => $code],
        ]);
        if (!empty($response['errors'])) {
            throw new RuntimeException(sprintf(
                'Unable to remove Ergonode template fixture "%s": %s',
                $code,
                json_encode($response['errors'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            ));
        }
        $deletedCode = $response['data']['templateDelete']['code'] ?? null;
        if ($deletedCode !== $code) {
            throw new RuntimeException(sprintf(
                'Ergonode confirmed an unexpected template deletion for "%s".',
                $code
            ));
        }
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if ($this->find($code) === null) {
                return;
            }
            usleep(250000);
        }

        throw new RuntimeException(sprintf(
            'Ergonode template fixture "%s" is still visible after deletion.',
            $code
        ));
    }

    /** @return array<string, mixed> */
    public function requireVisible(string $code): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $template = $this->find($code);
            if ($template !== null) {
                return $template;
            }
            usleep(250000);
        }

        throw new RuntimeException(sprintf(
            'Ergonode template fixture "%s" did not become visible.',
            $code
        ));
    }
}
