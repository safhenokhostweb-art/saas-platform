import type { Page, Response } from '@playwright/test';

/**
 * ADR-077 — one navigation helper for every Sentinel spec.
 *
 * A run's first load through the production CDN sometimes times out or is
 * aborted (seen on 2026-11-02: page.goto timeout and net::ERR_ABORTED at
 * 360 px, while a direct load of the same page answered 200 in 1.5 s).
 * Such a transient network failure is retried exactly once. An HTTP error
 * status is an answer, not a network failure: it is returned unchanged and
 * reported by the caller, never retried away.
 */
const TRANSIENT = /Timeout \d+ms exceeded|net::ERR_(ABORTED|TIMED_OUT|CONNECTION_(RESET|CLOSED|REFUSED|TIMED_OUT)|NETWORK_CHANGED|EMPTY_RESPONSE|HTTP2_PROTOCOL_ERROR|QUIC_PROTOCOL_ERROR|SSL_PROTOCOL_ERROR|NAME_NOT_RESOLVED)|ECONNRESET/;

export const RETRY_DELAY_MS = 1500;

export function isTransientNavigationError(error: unknown): boolean {
  return TRANSIENT.test(error instanceof Error ? error.message : String(error));
}

export interface NavigationResult {
  response: Response | null;
  retried: boolean;
  firstError: string | null;
}

export async function gotoWithRetry(page: Page, url: string): Promise<NavigationResult> {
  try {
    return { response: await page.goto(url, { waitUntil: 'load' }), retried: false, firstError: null };
  } catch (error) {
    if (!isTransientNavigationError(error)) throw error;
    const firstError = (error instanceof Error ? error.message : String(error)).split('\n')[0];
    await page.waitForTimeout(RETRY_DELAY_MS);
    return { response: await page.goto(url, { waitUntil: 'load' }), retried: true, firstError };
  }
}
