const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const PACKAGE = path.resolve(process.env.FRENET_PACKAGE || 'opencartfrenetv4.ocmod.zip');
const PREVIOUS_PACKAGE = process.env.FRENET_PREVIOUS_PACKAGE ? path.resolve(process.env.FRENET_PREVIOUS_PACKAGE) : '';
const PACKAGE_NAME = 'Frenet';
const CUSTOMER = { email: 'e2e@example.com', password: 'e2e-password' };
const PRODUCT_ID = 40;
const UNAVAILABLE = 'Frenet shipping is unavailable right now. Choose another delivery option or try again in a moment.';
const NO_SERVICES = 'No Frenet delivery option serves this address.';
const SCENARIOS = {
	slow: { error: UNAVAILABLE, limit: 2 * 2000 + 300 + 1000 },
	'status-503': { error: UNAVAILABLE },
	'status-401': { error: UNAVAILABLE },
	empty: { error: NO_SERVICES },
	'with-errors': { names: [/^SEDEX - 3 dia\(s\) - /, /^Jadlog Package - /] },
	broken: { error: UNAVAILABLE }
};
const route = (path, method) => new RegExp(`route=${path}(\\||%7C|\\.)${method}`, 'i');

const watch = (page) => {
	const errors = [];

	page.on('pageerror', (error) => errors.push(`js: ${error.message}`));
	page.on('response', (response) => {
		if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
	});

	return errors;
};

const login = async (page) => {
	await page.goto('admin/');
	await page.locator('input[name=username]').fill('admin');
	await page.locator('input[name=password]').fill('admin');
	await page.locator('button[type=submit]').click();
	await expect(page).toHaveURL(/user_token=/);

	return new URL(page.url()).searchParams.get('user_token');
};

const upload = async (page, token, file) => {
	await page.goto(`admin/index.php?route=marketplace/installer&user_token=${token}`);

	const chooser = page.waitForEvent('filechooser');

	await page.locator('#button-upload').click();
	await (await chooser).setFiles(file);
	await expect(page.locator('#extension tr', { hasText: PACKAGE_NAME })).toBeVisible({ timeout: 120000 });
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const installPackage = async (page, version) => {
	const row = page.locator('#extension tr', { hasText: PACKAGE_NAME });

	await expect(row).toContainText(version);
	await row.locator('a.btn-success').click();
	await expect(row.locator('a.btn-warning')).toBeVisible({ timeout: 120000 });
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const shippingRow = async (page, token) => {
	await page.goto(`admin/index.php?route=marketplace/extension&type=shipping&user_token=${token}`);

	const row = page.locator('#extension tr', { hasText: 'Frenet' });

	await expect(row).toHaveCount(1);

	return row;
};

const installShipping = async (page, token) => {
	const row = await shippingRow(page, token);

	await row.locator('a.btn-success').click();
	await expect(page.locator('#extension tr', { hasText: 'Frenet' }).locator('a.btn-primary')).toBeVisible();
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const openSettings = async (page, token) => {
	const row = await shippingRow(page, token);

	await row.locator('a.btn-primary').click();
	await expect(page).toHaveURL(/route=extension\/opencartfrenetv4\/shipping\/frenet/);
	await expect(page.locator('h1')).toHaveText('Frenet');
	await expect(page.locator('#input-postcode')).toBeVisible();
};

const checkSettings = async (page, token, saved_token, testInfo) => {
	await openSettings(page, token);
	await expect(page.locator('#input-token')).toHaveValue(saved_token);
	await expect(page.locator('#frenet-version')).toHaveText(/3\.0\.0/);
	await expect(page.locator('#input-timeout')).toHaveValue('5');
	await expect(page.locator('#input-attempts')).toHaveValue('3');
	await expect(page.locator('#input-debug')).toHaveValue('0');
	await page.screenshot({ path: testInfo.outputPath('settings.png'), fullPage: true });
};

const startCheckout = async (page) => {
	await page.goto('index.php?route=account/login');
	await page.locator('#input-email').fill(CUSTOMER.email);
	await page.locator('#input-password').fill(CUSTOMER.password);
	await page.locator('#form-login button[type=submit]').click();
	await expect(page).toHaveURL(/account\/account/);

	const added = await page.request.post('index.php?route=checkout/cart|add', { form: { product_id: String(PRODUCT_ID), quantity: '1' } });

	expect(added.ok()).toBeTruthy();

	await page.goto('index.php?route=checkout/checkout');
	await expect(page.locator('#input-shipping-address option')).toHaveCount(2);
};

const showShippingMethods = async (page) => {
	const modal = (await page.locator('#button-shipping-methods').count()) > 0;
	const quoted = page.waitForResponse(route('checkout/shipping_method', modal ? 'quote' : 'getMethods'));
	const started = Date.now();

	await page.locator('#input-shipping-address').selectOption({ index: 1 });

	if (modal) await page.locator('#button-shipping-methods').click();

	await quoted;

	const milliseconds = Date.now() - started;

	if (modal) {
		return {
			milliseconds,
			frenet: page.locator('#modal-shipping label[for^="input-shipping-method-frenet-"]'),
			error: page.locator('#modal-shipping .alert-danger'),
			others: page.locator('#modal-shipping input[name=shipping_method]:not([value^="frenet."])')
		};
	}

	return {
		milliseconds,
		frenet: page.locator('#input-shipping-method option[value^="frenet."]'),
		error: page.locator('#input-shipping-method optgroup[label="Frenet"] option[disabled]'),
		others: page.locator('#input-shipping-method option[value*="."]:not([value^="frenet."])')
	};
};

const checkout = async (page, services, testInfo) => {
	await startCheckout(page);
	await page.locator('#input-shipping-address').selectOption({ index: 1 });

	const modal = (await page.locator('#button-shipping-methods').count()) > 0;
	const name = modal ? await chooseInModal(page, services, testInfo) : await chooseInSelect(page, services, testInfo);

	await choosePayment(page);
	await expect(page.locator('#checkout-confirm')).toContainText(name);
	await page.locator('#button-confirm').click();
	await expect(page).toHaveURL(/checkout\/success/, { timeout: 60000 });

	return name;
};

const chooseInSelect = async (page, services, testInfo) => {
	const options = page.locator('#input-shipping-method option[value^="frenet."]');

	await expect(options).toHaveCount(services, { timeout: 60000 });

	const option = options.first();
	const name = (await option.innerText()).replace(/\s+-\s+[^-]*$/, '').trim();

	await page.screenshot({ path: testInfo.outputPath('checkout-shipping.png'), fullPage: true });

	const payment_methods = page.waitForResponse(route('checkout/payment_method', 'getMethods'));

	await page.locator('#input-shipping-method').selectOption(await option.getAttribute('value'));
	await payment_methods;

	return name;
};

const chooseInModal = async (page, services, testInfo) => {
	await page.locator('#button-shipping-methods').click();

	const radios = page.locator('#modal-shipping input[name=shipping_method][value^="frenet."]');

	await expect(radios).toHaveCount(services, { timeout: 60000 });

	const radio = radios.first();
	const name = (await page.locator(`label[for="${await radio.getAttribute('id')}"]`).innerText()).replace(/\s+-\s+[^-]*$/, '').trim();

	await radio.check();
	await page.screenshot({ path: testInfo.outputPath('checkout-shipping.png'), fullPage: true });
	await page.locator('#button-shipping-method').click();
	await expect(page.locator('#input-shipping-code')).toHaveValue(await radio.getAttribute('value'));

	return name;
};

const choosePayment = async (page) => {
	if (await page.locator('#button-payment-methods').count()) {
		await page.locator('#button-payment-methods').click();
		await page.locator('#modal-payment input[name=payment_method][value^="cod"]').check();
		await page.locator('#button-payment-method').click();
		await expect(page.locator('#input-payment-code')).toHaveValue(/^cod/);
	} else {
		const saved = page.waitForResponse(route('checkout/payment_method', 'save'));

		await page.locator('#input-payment-method').selectOption('cod');
		await saved;
	}

	const agree = page.locator('#input-checkout-agree, #input-agree');

	if (await agree.count()) await agree.check();

	await expect(page.locator('#button-confirm')).toBeVisible({ timeout: 60000 });
};

const checkOrder = async (page, token, name, testInfo) => {
	await page.goto(`admin/index.php?route=sale/order|info&order_id=1&user_token=${token}`);
	await expect(page.locator('#content')).toContainText(name);
	await page.screenshot({ path: testInfo.outputPath('admin-order.png'), fullPage: true });
};

test('@install installs the package, shows the version, quotes a single service at checkout and places the order', async ({ page }, testInfo) => {
	const errors = watch(page);
	const token = await login(page);
	let name = '';

	await test.step('Extensions > Installer uploads and installs opencartfrenetv4.ocmod.zip 3.0.0', async () => {
		await upload(page, token, PACKAGE);
		await installPackage(page, '3.0.0');
		await page.screenshot({ path: testInfo.outputPath('installer.png'), fullPage: true });
	});
	await test.step('Extensions > Extensions > Shipping installs Frenet', () => installShipping(page, token));
	await test.step('The settings show the token and the module version', () => checkSettings(page, token, 'single-service', testInfo));
	await test.step('Checkout offers the only Frenet service and places the order', async () => {
		name = await checkout(page, 1, testInfo);
	});
	await test.step('The order keeps the Frenet shipping', () => checkOrder(page, token, name, testInfo));

	expect(errors).toEqual([]);
});

test('@legacy installs version 1.0.0 from Extensions > Installer', async ({ page }, testInfo) => {
	const errors = watch(page);
	const token = await login(page);

	await test.step('Extensions > Installer uploads and installs version 1.0.0', async () => {
		await upload(page, token, PREVIOUS_PACKAGE);
		await installPackage(page, '1.0.0');
	});
	await test.step('Extensions > Extensions > Shipping installs Frenet', () => installShipping(page, token));
	await page.screenshot({ path: testInfo.outputPath('legacy-installed.png'), fullPage: true });

	expect(errors).toEqual([]);
});

test('@upgrade quotes with the token of version 1.0.0 before the settings are opened, then moves it to the new key', async ({ page }, testInfo) => {
	const errors = watch(page);
	const token = await login(page);
	let name = '';

	await test.step('Checkout offers the Frenet services with the saved token and places the order', async () => {
		name = await checkout(page, 2, testInfo);
	});

	await test.step('The settings show the token moved to the new key and the module version', () => checkSettings(page, token, 'e2e-token', testInfo));
	await test.step('The order keeps the Frenet shipping', () => checkOrder(page, token, name, testInfo));

	expect(errors).toEqual([]);
});

test('@block shows in the Frenet block of the checkout what the Frenet API answered for the scenario of the run', async ({ page }, testInfo) => {
	const scenario = process.env.E2E_SCENARIO || '';
	const expected = SCENARIOS[scenario];

	test.skip(!expected, 'no scenario');

	const errors = watch(page);

	await login(page);
	await startCheckout(page);

	const block = await showShippingMethods(page);

	if (expected.error) {
		await expect(block.error).toHaveText(expected.error, { timeout: 60000 });
		await expect(block.frenet).toHaveCount(0);
		await expect(page.locator('body')).not.toContainText(/\.php|opencartfrenetv4|Frenet\\/);
	} else {
		await expect(block.frenet).toHaveCount(expected.names.length, { timeout: 60000 });

		for (const [index, name] of expected.names.entries()) {
			await expect(block.frenet.nth(index)).toHaveText(name);
		}
	}

	if (expected.limit) expect(block.milliseconds).toBeLessThan(expected.limit);

	await expect(block.others.first()).toBeAttached();
	await page.screenshot({ path: testInfo.outputPath(`checkout-${scenario}.png`), fullPage: true });
	await fs.promises.writeFile(testInfo.outputPath(`checkout-${scenario}.json`), JSON.stringify({
		scenario,
		frenet: await block.frenet.allInnerTexts(),
		error: await block.error.allInnerTexts(),
		others: await block.others.count(),
		milliseconds: block.milliseconds
	}, null, 2));

	expect(errors).toEqual([]);
});
