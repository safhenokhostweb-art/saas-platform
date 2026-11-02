import { test, expect, type Page } from '@playwright/test';
import { gotoWithRetry, isTransientNavigationError } from '../sentinel/support/navigation';

/**
 * ADR-077 — the Sentinel suite retries a transient navigation failure once
 * (CDN timeout / net::ERR_ABORTED on a run's first load) and never retries
 * an HTTP error status. A routed fake origin, so no server is needed.
 */

const URL = 'http://sentinel-retry.test/';

/** Counts the helper's own page.goto calls; Chromium may re-request an aborted load internally. */
function countGotos(page: Page): () => number {
  let gotos = 0;
  const original = page.goto.bind(page);
  page.goto = ((...args: Parameters<Page['goto']>) => { gotos++; return original(...args); }) as Page['goto'];
  return () => gotos;
}

test('a transient first failure is retried once and the page loads', async ({ page }) => {
  let calls = 0;
  const gotos = countGotos(page);
  await page.route(URL, (route) => (++calls === 1 ? route.abort('timedout') : route.fulfill({ status: 200, contentType: 'text/html', body: '<html dir="rtl"><body>ok</body></html>' })));
  const result = await gotoWithRetry(page, URL);
  expect(result.retried).toBe(true);
  expect(result.firstError).toMatch(/net::ERR_TIMED_OUT/);
  expect(result.response?.status()).toBe(200);
  expect(gotos()).toBe(2);
});

test('an HTTP error is an answer: returned as is, never retried', async ({ page }) => {
  let calls = 0;
  await page.route(URL, (route) => { calls++; return route.fulfill({ status: 500, body: 'down' }); });
  const result = await gotoWithRetry(page, URL);
  expect(result.retried).toBe(false);
  expect(result.response?.status()).toBe(500);
  expect(calls).toBe(1);
});

test('a non-transient failure is not retried', async ({ page }) => {
  let calls = 0;
  await page.route(URL, (route) => { calls++; return route.abort('accessdenied'); });
  await expect(gotoWithRetry(page, URL)).rejects.toThrow(/ERR_ACCESS_DENIED/);
  expect(calls).toBe(1);
});

test('a second transient failure is not retried again', async ({ page }) => {
  const gotos = countGotos(page);
  await page.route(URL, (route) => route.abort('connectionreset'));
  await expect(gotoWithRetry(page, URL)).rejects.toThrow(/ERR_CONNECTION_RESET/);
  expect(gotos()).toBe(2);
});

test('the transient classifier', () => {
  expect(isTransientNavigationError(new Error('page.goto: Timeout 45000ms exceeded.'))).toBe(true);
  expect(isTransientNavigationError(new Error('page.goto: net::ERR_ABORTED at https://x/'))).toBe(true);
  expect(isTransientNavigationError(new Error('page.goto: net::ERR_CERT_AUTHORITY_INVALID'))).toBe(false);
  expect(isTransientNavigationError(new Error('page.goto: net::ERR_ACCESS_DENIED'))).toBe(false);
});
