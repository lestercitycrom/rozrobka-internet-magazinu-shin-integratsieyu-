<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/** A definitive provider decline, not an ambiguous transport failure. */
final class PaymentProviderRejected extends RuntimeException {}
