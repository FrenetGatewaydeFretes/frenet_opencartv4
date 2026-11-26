#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 3 ] || [ $# -gt 4 ]; then
	echo "Usage: $0 <opencart-version> <php-version> <opencartfrenetv4.ocmod.zip> [<previous.ocmod.zip>]" >&2
	exit 2
fi

OC_VERSION=$1
export PHP_VERSION=$2
PACKAGE=$(realpath "$3")
PREVIOUS_SOURCE=$([ $# -eq 4 ] && realpath "$4" || true)
MODE=$([ -n "$PREVIOUS_SOURCE" ] && echo upgrade || echo install)
DIR=$(cd "$(dirname "$0")" && pwd)
WORK="$DIR/.work"
ARTIFACTS="$WORK/artifacts/$OC_VERSION-php$PHP_VERSION-$MODE"
PREVIOUS_PACKAGE=$([ "$MODE" = upgrade ] && echo "$WORK/previous/opencartfrenetv4.ocmod.zip" || true)
PLAYWRIGHT_VERSION=$(sed -n 's/.*"@playwright\/test": "\([^"]*\)".*/\1/p' "$DIR/package.json")
export E2E_PORT=${E2E_PORT:-8080}
export OPENCART_ZIP="$WORK/opencart/opencart-$OC_VERSION.zip"
BASE_URL="http://localhost:$E2E_PORT/"
TOKEN=$([ "$MODE" = upgrade ] && echo e2e-token || echo single-service)

dc() {
	docker compose -f "$DIR/compose.yml" "$@"
}

sql() {
	dc exec -T db mariadb -uopencart -popencart opencart -N -e "$1"
}

has_column() {
	[ "$(sql "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'opencart' AND TABLE_NAME = '$1' AND COLUMN_NAME = '$2'")" = 1 ]
}

fill_nulls() {
	local columns

	columns=$(sql "SELECT GROUP_CONCAT(CONCAT('\`', COLUMN_NAME, '\` = COALESCE(\`', COLUMN_NAME, '\`, ', IF(DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext'), '\'\'', '0'), ')')) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'opencart' AND TABLE_NAME = '$1' AND IS_NULLABLE = 'YES' AND DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'tinyint', 'smallint', 'int', 'decimal')")
	[ "$columns" = NULL ] || sql "UPDATE $1 SET $columns"
}

store_log() {
	dc exec -T web sh -c 'cat "$(php -r "require \"/var/www/html/config.php\"; echo DIR_LOGS;")$1" 2>/dev/null' sh "$1"
}

playwright() {
	if command -v npx > /dev/null && [ -d node_modules/@playwright/test ]; then
		BASE_URL="$BASE_URL" E2E_MODE="$MODE" FRENET_PACKAGE="$PACKAGE" FRENET_PREVIOUS_PACKAGE="$PREVIOUS_PACKAGE" npx playwright test --grep "$1"
	else
		docker run --rm --network host --ipc host -u "$(id -u):$(id -g)" -e HOME=/tmp -e BASE_URL="$BASE_URL" -e E2E_MODE="$MODE" \
			-e FRENET_PACKAGE=/package/opencartfrenetv4.ocmod.zip -v "$PACKAGE":/package/opencartfrenetv4.ocmod.zip:ro \
			${PREVIOUS_PACKAGE:+-e FRENET_PREVIOUS_PACKAGE=/previous/opencartfrenetv4.ocmod.zip -v "$PREVIOUS_PACKAGE":/previous/opencartfrenetv4.ocmod.zip:ro} \
			-v "$DIR":/e2e -w /e2e "mcr.microsoft.com/playwright:v$PLAYWRIGHT_VERSION-noble" sh -c "npm ci --no-audit --no-fund --silent && npx playwright test --grep '$1'"
	fi
}

collect() {
	local status=$?

	mkdir -p "$ARTIFACTS"
	dc logs --no-color > "$ARTIFACTS/compose.log" 2>&1 || true
	{ store_log error.log; store_log frenet.log; } > "$ARTIFACTS/opencart.log" 2>&1 || true
	cp "$WORK/log/requests.jsonl" "$ARTIFACTS/mock-requests.jsonl" 2>/dev/null || true
	[ -d "$DIR/test-results" ] && cp -r "$DIR/test-results" "$ARTIFACTS/" || true
	[ "${E2E_KEEP:-0}" = 1 ] || dc down -v --remove-orphans > /dev/null 2>&1 || true
	exit $status
}

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

[ "$(basename "$PACKAGE")" = opencartfrenetv4.ocmod.zip ] || fail "the package must be named opencartfrenetv4.ocmod.zip: OpenCart 4 installs it in a folder with the name of the file"

mkdir -p "$WORK/opencart" "$WORK/tls" "$WORK/log" "$WORK/previous"
rm -rf "$ARTIFACTS" "$DIR/test-results"
[ "$MODE" = upgrade ] && cp "$PREVIOUS_SOURCE" "$PREVIOUS_PACKAGE"

if [ ! -s "$OPENCART_ZIP" ]; then
	curl -fsSL --retry 3 --retry-delay 5 -o "$OPENCART_ZIP.part" "https://github.com/opencart/opencart/releases/download/$OC_VERSION/opencart-$OC_VERSION.zip"
	mv "$OPENCART_ZIP.part" "$OPENCART_ZIP"
fi

(
	cd "$WORK/tls"
	openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj '/CN=Frenet E2E CA' -addext 'basicConstraints=critical,CA:TRUE' -keyout ca.key -out ca.pem 2> /dev/null
	openssl req -newkey rsa:2048 -nodes -subj '/CN=api.frenet.com.br' -keyout server.key -out server.csr 2> /dev/null
	printf 'subjectAltName=DNS:api.frenet.com.br\nbasicConstraints=CA:FALSE\nextendedKeyUsage=serverAuth\n' > server.ext
	openssl x509 -req -in server.csr -CA ca.pem -CAkey ca.key -CAcreateserial -days 2 -extfile server.ext -out server.pem 2> /dev/null
	chmod 644 server.key
)

: > "$WORK/log/requests.jsonl"
chmod 666 "$WORK/log/requests.jsonl"

dc down -v --remove-orphans > /dev/null 2>&1 || true
trap collect EXIT
dc up -d --build --quiet-pull

echo "Installing OpenCart $OC_VERSION on PHP $PHP_VERSION"
dc exec -T web update-ca-certificates > /dev/null
dc exec -T web sh -euc '
	rm -rf /tmp/oc && mkdir /tmp/oc && cd /tmp/oc && unzip -q /opencart.zip
	upload=$(dirname "$(find . -maxdepth 4 -path "*/upload/index.php" | head -1)")
	cp -a "$upload/." /var/www/html/
	cd /var/www/html
	[ -f config.php ] || cp config-dist.php config.php
	[ -f admin/config.php ] || cp admin/config-dist.php admin/config.php
	chown -R www-data:www-data /var/www/html
	rm -rf /tmp/oc
'
install_fields='db_driver=mysqli&db_hostname=db&db_username=opencart&db_password=opencart&db_database=opencart&db_port=3306&db_prefix=oc_&username=admin&password=admin&email=e2e%40example.com'

if [ "$OC_VERSION" = 4.0.0.0 ]; then
	next=$(curl -s -o /dev/null -w '%{redirect_url}' -X POST "${BASE_URL}install/index.php?route=install/step_3&language=en-gb" --data "$install_fields")
	[[ $next == *install/step_4* ]] || fail "the web installer of OpenCart $OC_VERSION did not finish"
else
	dc exec -T -u www-data web php /var/www/html/install/cli_install.php install \
		--db_driver mysqli --db_hostname db --db_username opencart --db_password opencart --db_database opencart \
		--db_port 3306 --db_prefix oc_ --username admin --password admin --email e2e@example.com --http_server "$BASE_URL" | tail -1
fi

dc exec -T web rm -rf /var/www/html/install
dc exec -T web php -r '$h = curl_init("https://api.frenet.com.br/mock/status/200"); curl_setopt($h, CURLOPT_RETURNTRANSFER, true); curl_exec($h); exit(curl_getinfo($h, CURLINFO_RESPONSE_CODE) === 200 ? 0 : 1);' \
	|| fail "the store cannot reach the Frenet mock over HTTPS"

echo "Adding a customer in Rio de Janeiro, cash on delivery, checkout without terms (4.0.0.0 asks for a missing page) and the Frenet settings ($MODE)"
sql "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';
	INSERT INTO oc_customer SET customer_group_id = 1, store_id = 0, language_id = 1, firstname = 'Teste', lastname = 'Frenet', email = 'e2e@example.com', telephone = '21999999999', password = MD5('e2e-password'), status = 1, date_added = NOW();
	INSERT INTO oc_address SET customer_id = LAST_INSERT_ID(), firstname = 'Teste', lastname = 'Frenet', address_1 = 'Avenida Rio Branco, 1', city = 'Rio de Janeiro', postcode = '20040-020', country_id = 30, zone_id = (SELECT zone_id FROM oc_zone WHERE country_id = 30 AND code = 'RJ');
	INSERT INTO oc_extension SET extension = 'opencart', type = 'payment', code = 'cod';
	UPDATE oc_setting SET value = '0' WHERE \`key\` = 'config_checkout_id';
	INSERT INTO oc_setting (store_id, code, \`key\`, value, serialized) VALUES
		(0, 'payment_cod', 'payment_cod_status', '1', 0),
		(0, 'payment_cod', 'payment_cod_order_status_id', '1', 0),
		(0, 'payment_cod', 'payment_cod_geo_zone_id', '0', 0),
		(0, 'payment_cod', 'payment_cod_sort_order', '1', 0),
		(0, 'shipping_frenet', 'shipping_frenet_status', '1', 0),
		(0, 'shipping_frenet', 'shipping_frenet_postcode', '01310-100', 0),
		(0, 'shipping_frenet', 'shipping_frenet_sort_order', '1', 0);"
fill_nulls oc_customer
fill_nulls oc_address
has_column oc_customer address_id && sql "UPDATE oc_customer c JOIN oc_address a ON a.customer_id = c.customer_id SET c.address_id = a.address_id"
has_column oc_address default && sql "UPDATE oc_address SET \`default\` = 1"

if [ "$MODE" = upgrade ]; then
	sql "INSERT INTO oc_setting (store_id, code, \`key\`, value, serialized) VALUES
		(0, 'shipping_frenet', 'shipping_frenet_contrato_token', '$TOKEN', 0),
		(0, 'shipping_frenet', 'shipping_frenet_contrato_codigo', 'e2e-key', 0),
		(0, 'shipping_frenet', 'shipping_frenet_contrato_senha', 'e2e-password', 0)"
else
	sql "INSERT INTO oc_setting (store_id, code, \`key\`, value, serialized) VALUES (0, 'shipping_frenet', 'shipping_frenet_token', '$TOKEN', 0)"
fi

cd "$DIR"

if [ "$MODE" = upgrade ]; then
	echo "Running Playwright $PLAYWRIGHT_VERSION: version 1.0.0 from Extensions > Installer"
	playwright @legacy
	echo "Copying the new package over extension/opencartfrenetv4, as an update by FTP does"
	dc cp "$PACKAGE" web:/tmp/opencartfrenetv4.ocmod.zip
	dc exec -T web sh -euc 'cd /var/www/html/extension/opencartfrenetv4 && unzip -oq /tmp/opencartfrenetv4.ocmod.zip && chown -R www-data:www-data . && rm /tmp/opencartfrenetv4.ocmod.zip'
	echo "Running Playwright $PLAYWRIGHT_VERSION: checkout and settings after the update"
	playwright @upgrade
else
	echo "Running Playwright $PLAYWRIGHT_VERSION: install, settings and checkout"
	playwright @install
fi

echo "Checking the order, the settings, the mock, the library and the logs"
order=$(sql "SELECT * FROM oc_order WHERE order_id = 1 AND order_status_id > 0\G")
[[ $order == *frenet.0* ]] || fail "order 1 was not placed with a Frenet shipping code"
[[ $order == *SEDEX* || $order == *PAC* ]] || fail "order 1 does not keep the name of the Frenet service"
saved_token=$(sql "SELECT value FROM oc_setting WHERE \`key\` = 'shipping_frenet_token'")
[ "$saved_token" = "$TOKEN" ] || fail "shipping_frenet_token is '$saved_token', expected '$TOKEN'"
legacy=$(sql "SELECT COUNT(*) FROM oc_setting WHERE \`key\` LIKE 'shipping_frenet_contrato_%'")
[ "$legacy" = 0 ] || fail "$legacy setting(s) of version 1.0.0 are still saved"
grep -q "\"token\":\"$TOKEN\"" "$WORK/log/requests.jsonl" || fail "the mock received no quote with the token $TOKEN"
grep -q '"PlatformName":"OpenCart4"' "$WORK/log/requests.jsonl" || fail "the quote did not identify the platform as OpenCart4"
grep -q "\"user-agent\":\"Frenet-OpenCart4/3.0.0 (OpenCart $OC_VERSION)\"" "$WORK/log/requests.jsonl" || fail "the quote did not send the module and OpenCart versions"
dc exec -T -u www-data web php /check-library.php || fail "the Frenet library did not load with the OpenCart autoloader"
php_errors=$(store_log error.log | grep -ci 'frenet' || true)
[ "$php_errors" = 0 ] || fail "error.log has $php_errors line(s) about Frenet"

echo "OK: OpenCart $OC_VERSION (PHP $PHP_VERSION) $MODE: Frenet quoted over HTTPS and order 1 placed with $(grep -o 'frenet\.[0-9A-Z]*' <<< "$order" | head -1)"
