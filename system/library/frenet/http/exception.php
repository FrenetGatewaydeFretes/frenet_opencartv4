<?php
namespace Frenet\Http;

use RuntimeException;

/**
 * Transport failure (DNS, connection, TLS or timeout) where no HTTP answer was received.
 */
class Exception extends RuntimeException {}
