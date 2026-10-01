<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Connection;

use Magento\Framework\Exception\LocalizedException;

class EndpointResolver
{
    private const string ERGONODE_DOMAIN_PATTERN =
        '#^https://[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.ergonode\.cloud$#iD';

    public function resolveGraphQlUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new LocalizedException(__('Ergonode URL is required.'));
        }

        if (!preg_match(self::ERGONODE_DOMAIN_PATTERN, $url)) {
            throw new LocalizedException(
                __('Ergonode URL must match https://{tenant}.ergonode.cloud.')
            );
        }

        return $url . '/api/graphql/';
    }
}
