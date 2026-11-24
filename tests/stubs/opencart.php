<?php
namespace Opencart\System\Engine {
	if (!class_exists(Config::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Config, without loading files from DIR_CONFIG.
		 */
		class Config {
			/**
			 * @var array<string, mixed>
			 */
			private array $data = [];

			/**
			 * Value of a setting, or null when it is not set.
			 *
			 * @param string $key
			 *
			 * @return mixed
			 */
			public function get(string $key): mixed {
				return $this->data[$key] ?? null;
			}

			/**
			 * Sets a setting value.
			 *
			 * @param string $key
			 * @param mixed  $value
			 *
			 * @return void
			 */
			public function set(string $key, mixed $value): void {
				$this->data[$key] = $value;
			}

			/**
			 * Whether a setting has a value.
			 *
			 * @param string $key
			 *
			 * @return bool
			 */
			public function has(string $key): bool {
				return isset($this->data[$key]);
			}
		}
	}
}

namespace Opencart\System\Library {
	if (!class_exists(Log::class, false)) {
		/**
		 * Same public API as the OpenCart 4 core Log, keeping lines in memory instead of a file under DIR_LOGS.
		 */
		class Log {
			/**
			 * @var array<int, string>
			 */
			public array $lines = [];

			public function __construct(public string $filename) {}

			/**
			 * Keeps the message as the core writes it.
			 *
			 * @param mixed $message
			 *
			 * @return void
			 */
			public function write(mixed $message): void {
				$this->lines[] = print_r($message, true);
			}
		}
	}
}
