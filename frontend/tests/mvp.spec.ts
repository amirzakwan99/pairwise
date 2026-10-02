import { expect, test, type Page } from '@playwright/test';

const unique = `${Date.now()}`;
const password = 'password123';

async function register(page: Page, name: string, email: string) {
  await page.goto('/register');
  await page.getByLabel('Your name').fill(name);
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm password').fill(password);
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page.getByRole('heading', { name: 'Your groups' })).toBeVisible();
}

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Your groups' })).toBeVisible();
}

test('real sessions, equal/custom expenses, edits, memberships and responsive layouts', async ({ browser }) => {
  test.setTimeout(120000);
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  const friendEmail = `friend-${unique}@example.test`;
  const ownerEmail = `owner-${unique}@example.test`;
  await register(page, 'Friend', friendEmail);
  await page.goto('/profile');
  await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Sign in to your space' })).toBeVisible();
  await register(page, 'Owner', ownerEmail);
  await page.getByRole('link', { name: '+ New group' }).click();
  await page.getByLabel('Group name', { exact: true }).fill(`Weekend ${unique}`);
  await page.getByRole('button', { name: 'Create group', exact: true }).click();
  await expect(page.getByRole('heading', { name: `Weekend ${unique}` })).toBeVisible();
  const groupUrl = new URL(page.url()).pathname;
  await page.getByRole('button', { name: 'Members', exact: true }).click();
  await page.getByLabel('Email address').fill(friendEmail);
  await page.getByRole('button', { name: 'Add member', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Friend', exact: true })).toBeVisible();
  await page.getByRole('link', { name: '+ Add expense', exact: true }).click();
  await page.getByLabel('What was it?').fill('Dinner');
  await page.getByLabel('Amount (RM)', { exact: true }).fill('10.01');
  await page.getByLabel('Who paid?').selectOption({ label: 'Owner (you)' });
  await page.getByRole('button', { name: 'Save expense', exact: true }).click();
  await expect(page.getByText('Expense added successfully.')).toBeVisible();
  await page.getByRole('link', { name: /Dinner/ }).click();
  await expect(page.getByRole('heading', { name: 'Participants & saved shares' })).toBeVisible();
  await expect(page.getByText('Friend owes Owner')).toBeVisible();
  await page.getByRole('link', { name: 'Edit expense', exact: true }).click();
  await page.getByLabel('Custom amounts', { exact: true }).check();
  await page.getByLabel('Owner share (RM)').fill('7.00');
  await page.getByLabel('Friend share (RM)').fill('3.00');
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('split amounts must equal');
  await page.getByLabel('Friend share (RM)').fill('3.01');
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.getByText('Expense updated successfully.')).toBeVisible();
  await page.getByRole('button', { name: 'Who owes whom', exact: true }).click();
  await expect(page.getByText('You receive from Friend')).toBeVisible();
  await expect(page.getByText('RM3.01').first()).toBeVisible();
  await page.getByRole('button', { name: 'Overview', exact: true }).click();
  await expect(page.getByText('RM10.01').first()).toBeVisible();

  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    if (width === 320 || width === 1440) await page.screenshot({ path: `test-results/group-${width}.png`, fullPage: true });
  }
  await page.setViewportSize({ width: 320, height: 750 });
  await page.getByRole('link', { name: '+ Add expense', exact: true }).click();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.getByRole('link', { name: 'Cancel', exact: true }).click();
  await page.getByRole('button', { name: 'Members', exact: true }).click();
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(page.getByText('Former member', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Who owes whom', exact: true }).click();
  await expect(page.getByText('You receive from Friend')).toBeVisible();

  const friendContext = await browser.newContext();
  const friend = await friendContext.newPage();
  await login(friend, friendEmail);
  await friend.goto(groupUrl);
  await expect(friend.getByRole('alert')).toContainText('unauthorized');
  await friendContext.close();
  await page.getByRole('button', { name: 'Expenses', exact: true }).click();
  await page.getByRole('link', { name: /Dinner/ }).click();
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Delete expense', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'No expenses yet' })).toBeVisible();
  await page.getByRole('button', { name: 'Who owes whom', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'All square' })).toBeVisible();
  await page.goto('/profile');
  await page.getByLabel('Current password').fill(password);
  await page.getByLabel('New password', { exact: true }).fill('changed123');
  await page.getByLabel('Confirm new password').fill('changed123');
  await page.getByRole('button', { name: 'Change password', exact: true }).click();
  await expect(page.getByText('Password changed successfully.')).toBeVisible();
  await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Sign in to your space' })).toBeVisible();
  expect(errors).toEqual([]);
  await context.close();
});

test('CSRF is enforced on real SPA writes and expired sessions have useful feedback', async ({ page }) => {
  await page.goto('/login');
  // The browser supplies Origin/Referer. A direct fetch deliberately omits the XSRF header.
  const status = await page.evaluate(async () => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    return (await fetch('/api/auth/login', { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ email: 'amir@example.test', password: 'password123' }) })).status;
  });
  expect(status).toBe(419);
  await login(page, 'amir@example.test');
  await page.context().clearCookies();
  await page.goto('/profile');
  await expect(page.getByRole('heading', { name: 'Sign in to your space' })).toBeVisible();
});

test('loading, empty, retry and validation error states remain actionable', async ({ page }) => {
  await login(page, 'ali@example.test');
  await page.route('**/api/groups', async route => { await new Promise(resolve => setTimeout(resolve, 700)); await route.continue().catch(() => {}); });
  await page.goto('/groups');
  await expect(page.getByRole('status')).toContainText('Loading');
  await expect(page.getByRole('link', { name: /Langkawi Trip/ }).last()).toBeVisible();
  await page.unrouteAll({ behavior: 'wait' });
  await page.route('**/api/groups', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Temporarily unavailable' }) }));
  await page.goto('/groups');
  await expect(page.getByRole('alert')).toContainText('Temporarily unavailable');
  await page.unroute('**/api/groups');
  await page.getByRole('button', { name: 'Try again' }).click();
  await expect(page.getByRole('link', { name: /Langkawi Trip/ }).last()).toBeVisible();
});
