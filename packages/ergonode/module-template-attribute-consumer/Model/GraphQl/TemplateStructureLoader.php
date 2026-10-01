<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\GraphQl;

use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\TemplateAttributeConsumer\Api\TemplateStructureLoaderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class TemplateStructureLoader implements TemplateStructureLoaderInterface
{
    private const array PAGE_SIZES = [50, 25, 10, 5];

    public function __construct(
        private readonly Client $client,
        private readonly PageQueryRetrierInterface $pageQueryRetrier,
        private readonly LoggerInterface $logger
    ) {
    }

    public function load(string $templateCode): array
    {
        $template = $this->loadTemplate($templateCode);
        $sectionEdges = $template['sectionList']['edges'];

        foreach ($sectionEdges as &$sectionEdge) {
            $sectionCode = (string)$sectionEdge['node']['code'];
            $sectionEdge['node']['attributeList'] = [
                'edges' => $this->loadSectionAttributes($templateCode, $sectionCode),
            ];
        }
        unset($sectionEdge);

        $template['sectionList']['edges'] = $sectionEdges;

        return $template;
    }

    /**
     * @return array{
     *     code: string,
     *     name: array<int, array<string, mixed>>,
     *     attributeList: array{edges: array<int, array<string, mixed>>},
     *     sectionList: array{edges: array<int, array{node: array<string, mixed>}>}
     * }
     * @throws LocalizedException
     */
    private function loadTemplate(string $templateCode): array
    {
        $attributeEdges = [];
        $sectionEdges = [];
        $seenAttributes = [];
        $seenSections = [];
        $attributeCursor = null;
        $sectionCursor = null;
        $attributesComplete = false;
        $sectionsComplete = false;
        $resolvedCode = $templateCode;
        $resolvedName = [];

        while (!$attributesComplete || !$sectionsComplete) {
            $data = $this->queryTemplatePage(
                $templateCode,
                $attributesComplete,
                $attributeCursor,
                $sectionsComplete,
                $sectionCursor
            );
            $template = isset($data['template']) && is_array($data['template'])
                ? $data['template']
                : null;
            if ($template === null) {
                throw new LocalizedException(__('Ergonode template "%1" was not found.', $templateCode));
            }

            if (($template['code'] ?? null) !== $templateCode || !is_array($template['name'] ?? null)) {
                throw new LocalizedException(__('Ergonode returned incomplete template data for "%1".', $templateCode));
            }
            $resolvedCode = $templateCode;
            if (isset($template['name']) && is_array($template['name'])) {
                $resolvedName = array_values($template['name']);
            }
            if (!$attributesComplete) {
                $attributeConnection = $this->connection($template, 'attributeList');
                $added = $this->mergeEdges(
                    $attributeConnection['edges'],
                    $attributeEdges,
                    $seenAttributes,
                    'template attributes',
                    $templateCode
                );
                [$attributesComplete, $attributeCursor] = $this->advance(
                    $attributeConnection['pageInfo'],
                    $attributeCursor,
                    $added,
                    'template attributes',
                    $templateCode
                );
            }

            if (!$sectionsComplete) {
                $sectionConnection = $this->connection($template, 'sectionList');
                $added = $this->mergeEdges(
                    $sectionConnection['edges'],
                    $sectionEdges,
                    $seenSections,
                    'template sections',
                    $templateCode
                );
                [$sectionsComplete, $sectionCursor] = $this->advance(
                    $sectionConnection['pageInfo'],
                    $sectionCursor,
                    $added,
                    'template sections',
                    $templateCode
                );
            }
        }

        return [
            'code' => $resolvedCode,
            'name' => $resolvedName,
            'attributeList' => ['edges' => $attributeEdges],
            'sectionList' => ['edges' => $sectionEdges],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function loadSectionAttributes(string $templateCode, string $sectionCode): array
    {
        $edges = [];
        $seen = [];
        $cursor = null;
        $complete = false;

        while (!$complete) {
            $data = $this->querySectionPage($sectionCode, $cursor);
            $section = isset($data['section']) && is_array($data['section'])
                ? $data['section']
                : null;
            if ($section === null) {
                throw new LocalizedException(__('Ergonode section "%1" was not found.', $sectionCode));
            }
            if (($section['code'] ?? null) !== $sectionCode) {
                throw new LocalizedException(__('Ergonode returned incomplete section data for "%1".', $sectionCode));
            }

            $connection = $this->connection($section, 'attributeList');
            $added = $this->mergeEdges(
                $connection['edges'],
                $edges,
                $seen,
                'section attributes',
                $sectionCode,
                $templateCode
            );
            [$complete, $cursor] = $this->advance(
                $connection['pageInfo'],
                $cursor,
                $added,
                'section attributes',
                $sectionCode
            );
        }

        return $edges;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function queryTemplatePage(
        string $templateCode,
        bool $attributesComplete,
        ?string $attributeCursor,
        bool $sectionsComplete,
        ?string $sectionCursor
    ): array {
        return $this->pageQueryRetrier->query(
            self::PAGE_SIZES,
            self::PAGE_SIZES[0],
            fn (int $pageSize): array => $this->client->query(
                TemplateStructureQueries::TEMPLATE_DETAILS,
                [
                    'code' => $templateCode,
                    'attributeFirst' => $attributesComplete ? 0 : $pageSize,
                    'attributeAfter' => $attributeCursor,
                    'sectionFirst' => $sectionsComplete ? 0 : $pageSize,
                    'sectionAfter' => $sectionCursor,
                ]
            )
        );
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function querySectionPage(string $sectionCode, ?string $cursor): array
    {
        return $this->pageQueryRetrier->query(
            self::PAGE_SIZES,
            self::PAGE_SIZES[0],
            fn (int $pageSize): array => $this->client->query(
                TemplateStructureQueries::SECTION_ATTRIBUTES,
                [
                    'code' => $sectionCode,
                    'first' => $pageSize,
                    'after' => $cursor,
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $node
     * @return array{edges: array<int, mixed>, pageInfo: array<string, mixed>|null}
     */
    private function connection(array $node, string $key): array
    {
        $connection = isset($node[$key]) && is_array($node[$key]) ? $node[$key] : [];
        if (!isset($connection['edges']) || !is_array($connection['edges'])) {
            throw new LocalizedException(__('Ergonode returned an incomplete %1 connection.', $key));
        }

        return [
            'edges' => array_values($connection['edges']),
            'pageInfo' => isset($connection['pageInfo']) && is_array($connection['pageInfo'])
                ? $connection['pageInfo']
                : null,
        ];
    }

    /**
     * @param array<int, mixed> $source
     * @param array<int, array<string, mixed>> $target
     * @param array<string, true> $seen
     */
    private function mergeEdges(
        array $source,
        array &$target,
        array &$seen,
        string $connection,
        string $ownerCode,
        ?string $templateCode = null
    ): int {
        $added = 0;

        foreach ($source as $edge) {
            if (!is_array($edge) || !isset($edge['node']) || !is_array($edge['node'])) {
                throw new LocalizedException(__('Ergonode returned an incomplete %1 connection.', $connection));
            }

            $code = $edge['node']['code'] ?? null;
            if (!is_string($code) || trim($code) === '') {
                throw new LocalizedException(__('Ergonode returned an incomplete %1 connection.', $connection));
            }
            if ($connection === 'template sections' && !is_array($edge['node']['name'] ?? null)) {
                throw new LocalizedException(__('Ergonode returned incomplete section data for "%1".', $code));
            }

            if (isset($seen[$code])) {
                $this->logger->warning('Duplicate Ergonode template structure code ignored.', [
                    'connection' => $connection,
                    'owner_code' => $ownerCode,
                    'template_code' => $templateCode ?? $ownerCode,
                    'duplicate_code' => $code,
                ]);
                continue;
            }

            $seen[$code] = true;
            $target[] = $edge;
            $added++;
        }

        return $added;
    }

    /**
     * @param array<string, mixed>|null $pageInfo
     * @return array{bool, string|null}
     * @throws LocalizedException
     */
    private function advance(
        ?array $pageInfo,
        ?string $cursor,
        int $added,
        string $connection,
        string $ownerCode
    ): array {
        if ($pageInfo === null || !isset($pageInfo['hasNextPage']) || !is_bool($pageInfo['hasNextPage'])) {
            throw new LocalizedException(
                __('Ergonode %1 pagination metadata is missing or invalid for "%2".', $connection, $ownerCode)
            );
        }

        $hasNextPage = $pageInfo['hasNextPage'];
        if ($cursor !== null && $added === 0) {
            throw $this->paginationException($connection, $ownerCode);
        }

        if (!$hasNextPage) {
            return [true, $cursor];
        }

        $endCursor = isset($pageInfo['endCursor']) ? trim((string)$pageInfo['endCursor']) : '';
        if ($added === 0 || $endCursor === '' || $endCursor === $cursor) {
            throw $this->paginationException($connection, $ownerCode);
        }

        return [false, $endCursor];
    }

    private function paginationException(string $connection, string $ownerCode): LocalizedException
    {
        return new LocalizedException(
            __('Ergonode %1 pagination did not advance for "%2".', $connection, $ownerCode)
        );
    }
}
