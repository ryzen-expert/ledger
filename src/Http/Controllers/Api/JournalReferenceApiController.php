<?php

declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Reference;
use Illuminate\Http\Request;

class JournalReferenceApiController extends ApiController
{
    /**
     * Perform a reference operation.
     *
     * @throws Breaker
     */
    protected function runCore(Request $request, string $operation): array
    {
        $opFlags = self::getOpFlags($operation);
        $message = Reference::fromRequest($request, $opFlags);

        return $message->run();
    }
}
