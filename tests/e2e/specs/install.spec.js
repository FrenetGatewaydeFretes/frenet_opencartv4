const path = require('path');
const { test, expect } = require('@playwright/test');

const PACKAGE = path.resolve(process.env.FRENET_PACKAGE || 'opencartfrenetv4.ocmod.zip');
const PACKAGE_NAME = 'Frenet';

const watch = (page) => {
	const errors = [];

	page.on('pageerror', (error) => errors.push(`js: ${error.message}`));
	page.on('response', (response) => {
		if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
	});

	return errors;
};

const login = async (page) => {
	await page.goto('admin/', { waitUntil: 'networkidle' });
	await page.fill('input[name=username]', 'admin');
	await page.fill('input[name=password]', 'admin');
	await Promise.all([page.waitForURL(/user_token=/), page.click('button[type=submit]')]);

	return new URL(page.url()).searchParams.get('user_token');
};

const upload = async (page, token) => {
	await page.goto(`admin/index.php?route=marketplace/installer&user_token=${token}`, { waitUntil: 'networkidle' });

	const chooser = page.waitForEvent('filechooser');

	await page.click('#button-upload');
	await (await chooser).setFiles(PACKAGE);
	await expect(page.locator('#extension tr', { hasText: PACKAGE_NAME })).toBeVisible({ timeout: 120000 });
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const installPackage = async (page) => {
	const row = page.locator('#extension tr', { hasText: PACKAGE_NAME });

	await expect(row.locator('a.btn-success')).toBeVisible();
	await row.locator('a.btn-success').click();
	await expect(row.locator('a.btn-warning')).toBeVisible({ timeout: 120000 });
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const shippingRow = async (page, token) => {
	await page.goto(`admin/index.php?route=marketplace/extension&type=shipping&user_token=${token}`, { waitUntil: 'networkidle' });
	await page.waitForSelector('#extension table');

	return page.locator('#extension tr', { hasText: 'Frenet' });
};

const installShipping = async (page, token) => {
	const row = await shippingRow(page, token);

	await expect(row).toHaveCount(1);
	await row.locator('a.btn-success').click();
	await expect(page.locator('#extension tr', { hasText: 'Frenet' }).locator('a.btn-primary')).toBeVisible();
	await expect(page.locator('#alert .alert-danger')).toHaveCount(0);
};

const openSettings = async (page, token) => {
	const row = await shippingRow(page, token);

	await Promise.all([page.waitForURL(/route=extension\/opencartfrenetv4\/shipping\/frenet/), row.locator('a.btn-primary').click()]);
	await page.waitForLoadState('networkidle');
	await expect(page.locator('h1')).toHaveText('Frenet');
	await expect(page.locator('#input-postcode')).toBeVisible();
};

test('installs the package from Extensions > Installer and opens the Frenet settings', async ({ page }, testInfo) => {
	const errors = watch(page);
	const token = await login(page);

	await test.step('Extensions > Installer uploads opencartfrenetv4.ocmod.zip', () => upload(page, token));
	await test.step('Extensions > Installer installs the uploaded package', () => installPackage(page));
	await page.screenshot({ path: testInfo.outputPath('installer.png'), fullPage: true });
	await test.step('Extensions > Extensions > Shipping installs Frenet', () => installShipping(page, token));
	await test.step('The Frenet settings open', () => openSettings(page, token));
	await page.screenshot({ path: testInfo.outputPath('settings.png'), fullPage: true });

	expect(errors).toEqual([]);
});
