import { test, expect } from '@playwright/test';
import { loadFixtures, loginAsAdmin, canvasUrl, type E2EFixtures } from './helpers';

let fixtures: E2EFixtures;

test.beforeAll(() => {
  fixtures = loadFixtures();
});

test('Platform Canvas uses stable node selection with a derived path after reorder', async ({ page }) => {
  const pageErrors: string[] = [];
  page.on('pageerror', error => pageErrors.push(error.message));

  await loginAsAdmin(page, fixtures);
  await page.goto(canvasUrl(fixtures));
  const canvasRoot = page.locator('[x-data="themeCanvas()"]');
  await expect(canvasRoot).toBeAttached();
  await expect.poll(async () => page.evaluate(() => Boolean(window.CanvasIdentity))).toBe(true);
  await page.waitForTimeout(1_000);
  expect(pageErrors, 'Canvas must initialize without JavaScript errors').toEqual([]);
  await expect.poll(async () => canvasRoot.evaluate((root: HTMLElement & { _x_dataStack?: unknown[] }) => Boolean(root._x_dataStack?.[0]))).toBe(true);

  const result = await page.evaluate(() => {
    const root = document.querySelector('[x-data="themeCanvas()"]') as HTMLElement & { _x_dataStack?: any[] };
    const editor = root._x_dataStack![0];
    const originalBlocks = editor.blocks;
    const originalState = editor.documentState;

    try {
      editor.blocks = [{
        id: 'section-contract',
        type: 'section',
        children: [{
          id: 'column-contract',
          type: 'column',
          children: [
            { id: 'heading-contract', type: 'heading', props: { text: 'A' } },
            { id: 'image-contract', type: 'image', props: { src: 'a.jpg' } },
          ],
        }],
      }];
      editor.documentState = new window.CanvasDocumentState(editor.blocks);
      editor.selectBlock([0, 0, 0]);
      const beforePath = [...editor.selectedPath];

      editor.treeOperations.moveNode(editor.blocks, 'heading-contract', 'column-contract', 2);

      return {
        selectedNodeId: editor.documentState.selectedNodeId,
        selectedBlockId: editor.selectedBlock?.id ?? null,
        beforePath,
        afterPath: [...editor.selectedPath],
        inverseNodeId: window.CanvasIdentity.nodeAtPath(editor.blocks, editor.selectedPath)?.id ?? null,
      };
    } finally {
      editor.blocks = originalBlocks;
      editor.documentState = originalState;
    }
  });

  expect(result).toEqual({
    selectedNodeId: 'heading-contract',
    selectedBlockId: 'heading-contract',
    beforePath: [0, 0, 0],
    afterPath: [0, 0, 1],
    inverseNodeId: 'heading-contract',
  });
  expect(pageErrors).toEqual([]);
});

declare global {
  interface Window {
    CanvasIdentity: {
      nodeAtPath(tree: unknown[], path: number[]): { id?: string } | null;
    };
    CanvasDocumentState: new (tree: unknown[]) => unknown;
  }
}
