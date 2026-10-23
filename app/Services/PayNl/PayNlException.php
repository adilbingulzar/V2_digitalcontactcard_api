<?php

namespace App\Services\PayNl;

use RuntimeException;

/**
 * Pay.nl could not be reached or refused a request.
 */
class PayNlException extends RuntimeException {}
