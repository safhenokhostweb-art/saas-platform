import fs from 'node:fs';
import path from 'node:path';
import { sentinelEnv } from './env';

/** ADR-077 — one Sentinel finding. `remedy` is the task text a developer acts on. */
export interface SentinelFinding {
  code: string;
  severity: 'info' | 'warning' | 'blocker';
  page: string;
  width: number | null;
  widget: string | null;
  slot?: string | null;
  selector?: string | null;
  message: string;
  evidence?: Record<string, unknown>;
  remedy: string;
  /** `ux-friction` findings are improvement tasks (priority P3). */
  category?: 'defect' | 'ux-friction';
}

/** Workers append to one JSONL file each; the reporter merges them. */
export function record(finding: SentinelFinding): void {
  const dir = path.join(sentinelEnv().outputDir, 'findings');
  fs.mkdirSync(dir, { recursive: true });
  fs.appendFileSync(path.join(dir, `${process.pid}.jsonl`), JSON.stringify(finding) + '\n');
}

/** Task text for the suite's own (non-profile) finding codes. */
export const REMEDIES: Record<string, string> = {
  PAGE_HTTP_ERROR: 'Fix the server error for this page; it must answer 200.',
  PAGE_HORIZONTAL_OVERFLOW: 'Make the listed elements fit the viewport (logical widths, min-width:0, wrapping or clipping) so the page never scrolls sideways.',
  WIDGET_OVERFLOW: 'Constrain this widget\'s content to the viewport at this width (check fixed widths, long words and grid tracks).',
  WIDGET_CONTENT_OVERFLOW: 'Keep the content inside its widget box (overflow-wrap:anywhere, min-width:0 on flex/grid children) or clip it intentionally.',
  BROKEN_IMAGE: 'Re-upload or re-link the image; the URL does not load.',
  REQUEST_FAILED: 'Fix or remove the failing same-origin request.',
  CONSOLE_ERROR: 'Fix the script error logged in the browser console.',
  PAGE_SCRIPT_ERROR: 'Fix the uncaught script error.',
  TEXT_CLIPPED: 'Let the text wrap or give it room; it is cut off without an ellipsis or tooltip.',
  TAP_TARGET_SMALL: 'Make the control at least 24×24 px on touch screens (WCAG 2.2 target size).',
  RTL_MISSING: 'Render the page with <html dir="rtl" lang="fa"> (Constitution §16).',
  FONT_NOT_LOADED: 'Serve Vazirmatn locally and apply it (theme-runtime::css.fonts).',
  MOTION_NOT_REDUCED: 'Stop infinite animations under prefers-reduced-motion: reduce.',
  NO_WIDGETS_FOUND: 'Nothing to audit per widget: the page has no Canvas widgets (data-widget-type).',
  TAB_STRIP_MISSING: 'Include theme-runtime::canvas.tab-strip in the editor (ADR-076).',
  TAB_STRIP_UNREACHABLE: 'Keep the page-tab strip inside the toolbar; it must scroll, not overflow (ADR-076).',
  EDITOR_HORIZONTAL_OVERFLOW: 'The editor page scrolls sideways; fit the toolbar to the viewport.',
  VISUAL_DIFF: 'Review the screenshot diff; update the baseline only if the change is intended (SENTINEL_UPDATE_BASELINES=1).',
  VISUAL_BASELINE_MISSING: 'Create the baseline once with SENTINEL_UPDATE_BASELINES=1 on a known-good release.',
  UX_FRICTION: 'Add the missing author controls to the widget schema and wire them in the renderer, so the setting is a real control in both editors.',
  SENTINEL_CHECK_ERROR: 'The check itself could not finish; rerun, and inspect the page if it repeats.',
};
