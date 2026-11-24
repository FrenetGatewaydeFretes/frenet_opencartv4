<?php
namespace Frenet\Http;

/**
 * Raw HTTP answer, kept apart from any Frenet payload parsing so the transport stays reusable.
 */
class Response {
	/**
	 * @var array<int, int>
	 */
	private const TEMPORARY_STATUSES = [408, 429];

	public function __construct(private int $status_code, private string $body) {}

	/**
	 * HTTP status code of the answer.
	 *
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}

	/**
	 * Body of the answer exactly as received.
	 *
	 * @return string
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * True for any 2xx status.
	 *
	 * @return bool
	 */
	public function isSuccessful(): bool {
		return $this->status_code >= 200 && $this->status_code < 300;
	}

	/**
	 * True for 4xx statuses, which repeating the same request cannot fix, except 408 and 429 that only ask to try again.
	 *
	 * @return bool
	 */
	public function isRefused(): bool {
		return $this->status_code >= 400 && $this->status_code < 500 && !in_array($this->status_code, self::TEMPORARY_STATUSES, true);
	}
}
