<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Plugin;

use Ergonode\ProductConsumer\Model\Pipeline\BatchScope;
use Ergonode\Media\Model\Queue\QueuePublisher;

/** The active product pipeline consumes its own media; independent multimedia imports retain their queue. */
class InlineMediaDispatch
{
    public function __construct(private readonly BatchScope $scope)
    {
    }

    public function aroundDispatch(QueuePublisher $subject, callable $proceed): void
    {
        if ($this->scope->get() === null) {
            $proceed();
        }
    }
}
