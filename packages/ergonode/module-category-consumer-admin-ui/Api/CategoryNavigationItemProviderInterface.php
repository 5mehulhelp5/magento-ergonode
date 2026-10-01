<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Api;

use Ergonode\CategoryAdminUi\Api\CategoryNavigationItemProviderInterface as SharedItemProviderInterface;

/** Existing attribute UI contributions use this forwarding contract; see README. */
interface CategoryNavigationItemProviderInterface extends SharedItemProviderInterface
{
}
