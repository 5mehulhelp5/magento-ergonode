<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Exception;

/** A missing, invalid or rejected connection that scheduled synchronization may skip. */
class ConnectionConfigurationException extends GraphQlRequestException
{
}
