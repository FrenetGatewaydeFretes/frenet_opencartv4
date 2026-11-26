<?php
namespace Frenet\Quote;

/**
 * Quote that could not be obtained after every attempt, or that the Frenet API refused; the buyer sees only that Frenet is unavailable.
 */
class UnavailableException extends Exception {}
