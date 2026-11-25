<?php
declare(strict_types=1);

namespace Frenet\Tests\Http;

use Frenet\Http\Response;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase {
	public function testClassifiesTheStatusCode(): void {
		self::assertTrue((new Response(200, ''))->isSuccessful());
		self::assertFalse((new Response(200, ''))->isRefused());

		self::assertFalse((new Response(401, ''))->isSuccessful());
		self::assertTrue((new Response(401, ''))->isRefused());

		self::assertFalse((new Response(503, ''))->isSuccessful());
		self::assertFalse((new Response(503, ''))->isRefused());
	}

	public function testAsksToTryAgainOnTimeoutAndTooManyRequests(): void {
		self::assertFalse((new Response(408, ''))->isRefused());
		self::assertFalse((new Response(429, ''))->isRefused());
		self::assertTrue((new Response(400, ''))->isRefused());
		self::assertTrue((new Response(403, ''))->isRefused());
	}

	#[TestWith([199, false, false])]
	#[TestWith([200, true, false])]
	#[TestWith([299, true, false])]
	#[TestWith([300, false, false])]
	#[TestWith([399, false, false])]
	#[TestWith([400, false, true])]
	#[TestWith([499, false, true])]
	#[TestWith([500, false, false])]
	public function testClassifiesTheLimitsOfEachRange(int $status, bool $successful, bool $refused): void {
		$response = new Response($status, '');

		self::assertSame($successful, $response->isSuccessful());
		self::assertSame($refused, $response->isRefused());
	}

	public function testKeepsTheBody(): void {
		self::assertSame('{"a":1}', (new Response(200, '{"a":1}'))->getBody());
	}
}
