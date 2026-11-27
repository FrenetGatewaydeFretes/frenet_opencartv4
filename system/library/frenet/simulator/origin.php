<?php
namespace Frenet\Simulator;

/**
 * Tells a quote the browser sent from another site, never from the store page, by the Origin and Sec-Fetch-Site headers.
 */
final class Origin {
	/**
	 * Whether the browser says the request comes from a page outside the store, by the hosts of the request and the store.
	 *
	 * @param array<string, mixed> $server
	 * @param array<int, mixed>    $store_urls
	 *
	 * @return bool
	 */
	public static function isForeign(array $server, array $store_urls): bool {
		$origin = $server['HTTP_ORIGIN'] ?? '';

		if (!is_string($origin) || $origin === '') {
			return ($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site';
		}

		$host = self::getHost($origin);

		if ($host === '') {
			return true;
		}

		$request_host = $server['HTTP_HOST'] ?? '';
		$hosts = [self::getHost('//' . (is_string($request_host) ? $request_host : ''))];

		foreach ($store_urls as $url) {
			$hosts[] = self::getHost(is_string($url) ? $url : '');
		}

		return !in_array($host, $hosts, true);
	}

	/**
	 * Host of the URL in lower case, or an empty string when it has none.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private static function getHost(string $url): string {
		$host = parse_url($url, PHP_URL_HOST);

		return is_string($host) ? strtolower($host) : '';
	}
}
