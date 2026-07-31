<?php

namespace App\Modules\NdiImport\Exceptions;

use RuntimeException;

class NdiPublishFailed extends RuntimeException
{
    public function __construct(public readonly string $runId, ?\Throwable $previous = null)
    {
        parent::__construct("NDI import run {$runId} could not be published.", previous: $previous);
    }
}
