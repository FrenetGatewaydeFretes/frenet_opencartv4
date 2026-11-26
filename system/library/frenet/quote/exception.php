<?php
namespace Frenet\Quote;

use RuntimeException;

/**
 * Quote the Frenet API answered but that cannot be used: an HTTP error or a body that is not a quote.
 */
class Exception extends RuntimeException {}
