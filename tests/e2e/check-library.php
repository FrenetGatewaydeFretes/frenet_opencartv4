<?php
use Frenet\Http\Response;
use Frenet\Log;
use Frenet\Platform;
use Opencart\System\Engine\Autoloader;
use Opencart\System\Library\Log as CoreLog;

$root = '/var/www/html/';

require $root . 'config.php';
require DIR_SYSTEM . 'engine/autoloader.php';

$autoloader = new Autoloader();
$autoloader->register('Opencart\System', DIR_SYSTEM);
$autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');

spl_autoload_register([$autoloader, 'load']);

preg_match("/define\\('VERSION', '([^']+)'\\)/", (string)file_get_contents($root . 'index.php'), $match);

$platform = new Platform($match[1] ?? '');
$writer = new CoreLog(Log::FILENAME);
$response = new Response(200, '');

(new Log($writer, true))->debug('E2E library check', ['token' => 'e2e-secret', 'version' => $platform->getVersion(), 'success' => $response->isSuccessful()]);

unset($writer);

$line = (string)file_get_contents(DIR_LOGS . Log::FILENAME);

if (!str_contains($line, 'E2E library check') || str_contains($line, 'e2e-secret') || !str_contains($line, '"version":"' . $platform->getVersion() . '"')) {
	fwrite(STDERR, "frenet.log does not have the masked entry:\n" . $line);

	exit(1);
}

echo 'Library loaded on OpenCart ' . $platform->getVersion() . ' with event action ' . $platform->getEventAction('extension/opencartfrenetv4/shipping/frenet', 'addSimulator') . PHP_EOL;
