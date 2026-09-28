import { test, expect } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl } from './helpers';

const fx = loadFixtures();

test('registry containers expose recursive editor drop targets', async ({ page }) => {
  await loginAsAdmin(page, fx);
  await page.goto(canvasUrl(fx));

  const result = await page.evaluate(async ({ themeId }) => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(`/admin/theme-builder/${themeId}/canvas/render-block`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({
        block: {
          id: 'outer-modal',
          type: 'modal',
          props: {},
          children: [{
            id: 'inner-tabs',
            type: 'tabs',
            props: {},
            children: [{
              id: 'tab-panel',
              type: 'tab_panel',
              props: { title: 'Nested tab' },
              children: [{
                id: 'nested-text',
                type: 'text',
                props: { content: 'recursive-browser-marker' },
              }],
            }],
          }],
        },
      }),
    });

    return { status: response.status, body: await response.json() };
  }, { themeId: fx.theme_id });

  expect(result.status).toBe(200);
  expect(result.body.html).toContain('recursive-browser-marker');

  const attributes = await page.evaluate((html) => {
    const document = new DOMParser().parseFromString(html, 'text/html');
    return ['outer-modal', 'inner-tabs', 'tab-panel'].map((id) => ({
      id,
      container: document.querySelector(`[data-tb-block-id="${id}"]`)?.getAttribute('data-tb-container'),
    }));
  }, result.body.html as string);

  expect(attributes).toEqual([
    { id: 'outer-modal', container: '1' },
    { id: 'inner-tabs', container: '1' },
    { id: 'tab-panel', container: '1' },
  ]);
});
