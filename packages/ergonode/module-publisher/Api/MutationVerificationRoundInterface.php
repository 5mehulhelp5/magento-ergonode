<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

/** Optional lifecycle for sharing verification reads after one mutation attempt. */
interface MutationVerificationRoundInterface extends AmbiguousMutationVerifierInterface
{
    /**
     * Discard observations before the next mutation document is attempted.
     *
     * @return void
     */
    public function beginVerificationRound(): void;
}
