<?php
namespace Frenet\Simulator;

/**
 * Finds the IP of the visitor, also behind Cloudflare or a proxy of the store network, where every request has the same address.
 */
final class ClientIp {
	/**
	 * @var string
	 */
	private const CLOUDFLARE_HEADER = 'HTTP_CF_CONNECTING_IP';

	/**
	 * @var string
	 */
	private const FORWARDED_HEADER = 'HTTP_X_FORWARDED_FOR';

	/**
	 * @var array<int, string>
	 */
	private const INTERNAL_RANGES = [
		'10.0.0.0/8',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'127.0.0.0/8',
		'100.64.0.0/10',
		'169.254.0.0/16',
		'::1/128',
		'fc00::/7',
		'fe80::/10'
	];

	/**
	 * @var array<int, string>
	 */
	private const CLOUDFLARE_RANGES = [
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32'
	];

	/**
	 * IP of the visitor: the last public X-Forwarded-For behind a proxy of the store network, CF-Connecting-IP from Cloudflare.
	 *
	 * @param array<string, mixed> $server
	 *
	 * @return string
	 */
	public static function fromServer(array $server): string {
		$remote = $server['REMOTE_ADDR'] ?? '';
		$peer = is_string($remote) ? $remote : '';

		if (self::inRanges($peer, self::INTERNAL_RANGES)) {
			$forwarded = self::getForwarded($server[self::FORWARDED_HEADER] ?? '');
			$peer = $forwarded !== '' ? $forwarded : $peer;
		}

		$visitor = $server[self::CLOUDFLARE_HEADER] ?? '';

		if (is_string($visitor) && filter_var($visitor, FILTER_VALIDATE_IP) !== false && self::inRanges($peer, self::CLOUDFLARE_RANGES)) {
			return $visitor;
		}

		return $peer;
	}

	/**
	 * Last address of X-Forwarded-For outside the store network, or an empty string when the header has none.
	 *
	 * @param mixed $header
	 *
	 * @return string
	 */
	private static function getForwarded(mixed $header): string {
		if (!is_string($header)) {
			return '';
		}

		foreach (array_reverse(explode(',', $header)) as $address) {
			$ip = trim($address);

			if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
				return '';
			}

			if (!self::inRanges($ip, self::INTERNAL_RANGES)) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Whether the IP is in one of the ranges, an IPv4 mapped as IPv6 compared as IPv4.
	 *
	 * @param string             $ip
	 * @param array<int, string> $ranges
	 *
	 * @return bool
	 */
	private static function inRanges(string $ip, array $ranges): bool {
		$packed = self::pack($ip);

		if ($packed === '') {
			return false;
		}

		foreach ($ranges as $range) {
			[$network, $bits] = explode('/', $range);

			if (self::inRange($packed, self::pack($network), (int)$bits)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Binary form of the IP, with an IPv4 mapped as IPv6 reduced to the IPv4, or an empty string when it is not an IP.
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	private static function pack(string $ip): string {
		$packed = filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : false;

		if ($packed === false) {
			return '';
		}

		return substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff" ? substr($packed, 12) : $packed;
	}

	/**
	 * Whether the first bits of the binary IP match the network.
	 *
	 * @param string $packed
	 * @param string $network
	 * @param int    $bits
	 *
	 * @return bool
	 */
	private static function inRange(string $packed, string $network, int $bits): bool {
		if (strlen($packed) !== strlen($network)) {
			return false;
		}

		$bytes = intdiv($bits, 8);

		if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
			return false;
		}

		$rest = $bits % 8;

		return $rest === 0 || ((ord($packed[$bytes]) ^ ord($network[$bytes])) & (0xFF << (8 - $rest)) & 0xFF) === 0;
	}
}
