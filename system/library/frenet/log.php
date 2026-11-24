<?php
namespace Frenet;

use Opencart\System\Library\Log as CoreLog;

/**
 * Writes Frenet entries to their own log file, always masking secrets so a shared log never leaks the token.
 */
class Log {
	/**
	 * @var string
	 */
	public const FILENAME = 'frenet.log';

	/**
	 * @var string
	 */
	private const MASK = '********';

	/**
	 * @var array<int, string>
	 */
	private const SECRET_KEYS = ['token'];

	public function __construct(private CoreLog $writer, private bool $debug) {}

	/**
	 * Records a failure; written regardless of the debug setting.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $context
	 *
	 * @return void
	 */
	public function error(string $message, array $context = []): void {
		$this->write('ERROR', $message, $context);
	}

	/**
	 * Records request and response details; written only while debug is enabled.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $context
	 *
	 * @return void
	 */
	public function debug(string $message, array $context = []): void {
		if ($this->debug) {
			$this->write('DEBUG', $message, $context);
		}
	}

	/**
	 * Writes one line with the level, the message and the masked context as JSON.
	 *
	 * @param string               $level
	 * @param string               $message
	 * @param array<string, mixed> $context
	 *
	 * @return void
	 */
	private function write(string $level, string $message, array $context): void {
		$line = $level . ' ' . $message;

		if ($context) {
			$line .= ' ' . json_encode($this->mask($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$this->writer->write($line);
	}

	/**
	 * Replaces the secret values at any depth of the context.
	 *
	 * @param array<mixed> $context
	 *
	 * @return array<mixed>
	 */
	private function mask(array $context): array {
		foreach ($context as $key => $value) {
			if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
				$context[$key] = self::MASK;
			} elseif (is_array($value)) {
				$context[$key] = $this->mask($value);
			}
		}

		return $context;
	}
}
