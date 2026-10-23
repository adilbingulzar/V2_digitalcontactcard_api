<?php

namespace App\Services\Wallet;

use RuntimeException;

/**
 * A wallet pass could not be made, usually because its certificates are not configured.
 */
class WalletException extends RuntimeException {}
