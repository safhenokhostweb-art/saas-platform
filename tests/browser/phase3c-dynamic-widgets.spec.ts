import { test, expect, type Page } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

const fx = loadFixtures();

async function renderBlock(page: Page, block: Record<string, unknown>): Promise<string> {
  const result = await page.evaluate(async ({ themeId, block }) => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(`/admin/theme-builder/${themeId}/canvas/render-block`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ block }),
    });
    return { status: response.status, body: await response.json() };
  }, { themeId: fx.theme_id, block });

  expect(result.status).toBe(200);
  return result.body.html as string;
}

test('Phase 3C dynamic widgets expose loading, error, empty, and escaped success states', async ({ page }) => {
  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));

  const loading = await renderBlock(page, {
    id: 'dynamic-loading',
    type: 'dynamic_feed_slider',
    props: {
      source_type: 'api',
      endpoint_url: 'https://feeds.example.test/posts',
      query_params: '{"limit":4}',
    },
  });
  expect(loading).toContain('data-state="loading"');
  expect(loading).toContain('tb-dynamic-state-loading');

  const error = await renderBlock(page, {
    id: 'dynamic-error',
    type: 'dynamic_feed_slider',
    props: {
      source_type: 'api',
      endpoint_url: 'javascript:alert(1)',
      query_params: '{}',
    },
  });
  expect(error).toContain('data-state="error"');
  expect(error).not.toContain('javascript:');

  const empty = await renderBlock(page, {
    id: 'dynamic-empty',
    type: 'dynamic_product_grid',
    props: { source_type: 'static', endpoint_url: '', query_params: '{}', static_items: '[]' },
  });
  expect(empty).toContain('data-state="empty"');

  const success = await renderBlock(page, {
    id: 'dynamic-success',
    type: 'dynamic_product_grid',
    props: {
      source_type: 'static',
      endpoint_url: '',
      query_params: '{}',
      columns: '3',
      static_items: JSON.stringify([{
        title: '<img src=x onerror=alert(1)>Browser marker',
        url: 'javascript:alert(1)',
        price: '<script>alert(1)</script>',
      }]),
    },
  });
  expect(success).toContain('data-state="success"');
  expect(success).toContain('&lt;img src=x onerror=alert(1)&gt;Browser marker');
  expect(success).not.toContain('<script>alert(1)</script>');
  expect(success).not.toContain('href="javascript:');

  // The editor chrome does not load tenant presentation CSS; its dedicated
  // preview route does. Verify logical layout in the real runtime surface.
  await page.setViewportSize({ width: 1366, height: 900 });
  await page.goto(`/admin/theme-builder/${fx.theme_id}/canvas/home/preview?target=page`);
  await page.locator('body').evaluate((body, html) => {
    const fixture = document.createElement('div');
    fixture.id = 'dynamic-browser-fixture';
    fixture.innerHTML = html;
    body.append(fixture);
  }, success);
  await expect(page.locator('#dynamic-browser-fixture .tb-dynamic-product-grid')).toBeVisible();

  for (const direction of ['rtl', 'ltr'] as const) {
    const geometry = await page.locator('#dynamic-browser-fixture').evaluate((fixture, dir) => {
      fixture.setAttribute('dir', dir);
      const grid = fixture.querySelector<HTMLElement>('.tb-dynamic-product-items')!;
      const style = getComputedStyle(grid);
      return { direction: style.direction, columns: style.gridTemplateColumns.split(' ').length };
    }, direction);
    expect(geometry.direction).toBe(direction);
    expect(geometry.columns).toBe(3);
  }
});
