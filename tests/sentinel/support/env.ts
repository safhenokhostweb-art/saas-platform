import path from 'node:path';

/** ADR-077 — every Sentinel option comes from the environment. */
export interface SentinelEnv {
  baseUrl: string;
  host: string;
  project: string;
  environment: string;
  outputDir: string;
  pages: string[];
  widths: number[];
  visual: boolean;
  updateBaselines: boolean;
  storageState: string | null;
  editorPaths: string[];
  ignoreHttpsErrors: boolean;
  navTimeout: number;
  globalTimeout: number;
  workers: number;
}

const list = (value: string | undefined, fallback: string): string[] =>
  (value && value.trim() !== '' ? value : fallback).split(',').map((s) => s.trim()).filter(Boolean);

export function sentinelEnv(): SentinelEnv {
  const baseUrl = (process.env.BASE_URL || '').trim();
  if (!/^https?:\/\/[^\s/]+/i.test(baseUrl)) {
    throw new Error('Sentinel: BASE_URL must be an http(s) origin, e.g. BASE_URL=http://127.0.0.1:8000');
  }
  const url = new URL(baseUrl);
  const widths = list(process.env.SENTINEL_WIDTHS, '360,390,768,1024,1280,1440').map(Number);
  if (widths.some((w) => !Number.isInteger(w) || w < 280 || w > 3840)) {
    throw new Error('Sentinel: SENTINEL_WIDTHS must be integers between 280 and 3840.');
  }
  const pages = list(process.env.SENTINEL_PAGES, '/');
  if (pages.some((p) => !p.startsWith('/') || p.startsWith('//'))) {
    throw new Error('Sentinel: SENTINEL_PAGES are same-origin paths starting with "/".');
  }
  const editorPaths = list(process.env.SENTINEL_EDITOR_PATHS, '');
  if (editorPaths.some((p) => !p.startsWith('/') || p.startsWith('//'))) {
    throw new Error('Sentinel: SENTINEL_EDITOR_PATHS are same-origin paths starting with "/".');
  }
  return {
    baseUrl: url.origin,
    host: url.host.replace(/[^a-z0-9.-]/gi, '_'),
    project: process.env.SENTINEL_PROJECT || 'unspecified',
    environment: process.env.SENTINEL_ENVIRONMENT || 'unspecified',
    outputDir: path.resolve(process.env.SENTINEL_OUTPUT_DIR || 'test-results/sentinel'),
    pages,
    widths,
    visual: process.env.SENTINEL_VISUAL === '1',
    updateBaselines: process.env.SENTINEL_UPDATE_BASELINES === '1',
    storageState: process.env.SENTINEL_STORAGE_STATE || null,
    editorPaths,
    ignoreHttpsErrors: process.env.SENTINEL_IGNORE_HTTPS_ERRORS === '1',
    navTimeout: Number(process.env.SENTINEL_NAV_TIMEOUT || 45000),
    globalTimeout: Number(process.env.SENTINEL_GLOBAL_TIMEOUT || 30 * 60 * 1000),
    workers: Number(process.env.SENTINEL_WORKERS || 2),
  };
}
