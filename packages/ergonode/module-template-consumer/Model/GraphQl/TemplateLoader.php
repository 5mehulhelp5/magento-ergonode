<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\GraphQl;

use Ergonode\Core\Model\GraphQl\Client;
use Ergonode\TemplateConsumer\Api\TemplateLoaderInterface;
use Magento\Framework\Exception\LocalizedException;

class TemplateLoader implements TemplateLoaderInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function load(string $templateCode): array
    {
        $data = $this->client->query(TemplateQueries::TEMPLATE_DETAILS, ['code' => $templateCode]);
        $template = isset($data['template']) && is_array($data['template'])
            ? $data['template']
            : null;
        if ($template === null) {
            throw new LocalizedException(__('Ergonode template "%1" was not found.', $templateCode));
        }
        if (($template['code'] ?? null) !== $templateCode || !is_array($template['name'] ?? null)) {
            throw new LocalizedException(__('Ergonode returned incomplete template data for "%1".', $templateCode));
        }

        return [
            'code' => $templateCode,
            'name' => array_values($template['name']),
        ];
    }
}
