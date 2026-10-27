/*
 * Phase 0.5 (ADR-064) — the Canvas colour control's value model, shared by
 * the platform Canvas and customer-site's Canvas editor and tested in Node.
 * DOM-free. A colour value is one of:
 *
 *   - a theme swatch:  var(--tb-primary, #4f46e5)  — follows the theme
 *   - a theme swatch with alpha:
 *       color-mix(in srgb, var(--tb-primary, #4f46e5) 50%, transparent)
 *   - a fixed colour:  #rrggbb, or rgba(r, g, b, a) with alpha
 *   - transparent
 *
 * The renderer's BlockRenderer::cssColor() accepts exactly these shapes.
 */
(function (root) {
  'use strict';

  // Same defaults as ThemeTokenNormalizer, so a swatch's fallback is what
  // a theme without that colour renders anyway.
  const TOKENS = [
    { token: 'primary', label: 'رنگ اصلی', key: 'primary_color', fallback: '#4f46e5' },
    { token: 'secondary', label: 'رنگ ثانویه', key: 'secondary_color', fallback: '#6366f1' },
    { token: 'accent', label: 'رنگ تأکیدی', key: 'accent_color', fallback: '#818cf8' },
    { token: 'background', label: 'پس‌زمینه', key: 'background_color', fallback: '#ffffff' },
    { token: 'surface', label: 'سطح کارت‌ها', key: 'surface_color', fallback: '#f9fafb' },
    { token: 'text', label: 'متن اصلی', key: 'text_color', fallback: '#111827' },
    { token: 'text-muted', label: 'متن کم‌رنگ', key: 'muted_text_color', fallback: '#4b5563' },
    { token: 'border', label: 'خط جداکننده', key: 'border_color', fallback: '#e5e7eb' },
  ];

  const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

  function normalizeHex(hex) {
    if (typeof hex !== 'string' || !HEX.test(hex.trim())) return null;
    let h = hex.trim().slice(1).toLowerCase();
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    return '#' + h.slice(0, 6);
  }

  function hexAlpha(hex) {
    const h = typeof hex === 'string' ? hex.trim() : '';
    return /^#[0-9a-f]{8}$/i.test(h) ? Math.round((parseInt(h.slice(7, 9), 16) / 255) * 100) : 100;
  }

  function toRgb(hex) {
    const h = normalizeHex(hex);
    return h ? [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16)) : null;
  }

  function toHex(r, g, b) {
    return '#' + [r, g, b].map((n) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0')).join('');
  }

  /** The theme's resolved swatch colours, from a global_styles object. */
  function palette(globalStyles) {
    const gs = globalStyles || {};
    const colors = gs.colors || {};
    return TOKENS.map((t) => ({
      ...t,
      value: normalizeHex(gs[t.key]) || normalizeHex(colors[t.token.replace('-', '_')]) || t.fallback,
      css: tokenValue(t.token),
    }));
  }

  function tokenValue(token) {
    const t = TOKENS.find((x) => x.token === token);
    return t ? `var(--tb-${t.token}, ${t.fallback})` : '';
  }

  /**
   * @returns {{kind: 'empty'|'transparent'|'token'|'hex'|'rgba'|'other', token: string|null, hex: string|null, alpha: number}}
   *   hex is the resolved base colour (for a token, from the palette); alpha is 0–100.
   */
  function parse(value, pal) {
    const v = typeof value === 'string' ? value.trim() : '';
    const resolve = (token) => ((pal || palette({})).find((t) => t.token === token) || {}).value || null;
    if (v === '') return { kind: 'empty', token: null, hex: null, alpha: 100 };
    if (v.toLowerCase() === 'transparent') return { kind: 'transparent', token: null, hex: null, alpha: 0 };
    let m = v.match(/^var\(\s*--tb-([a-z-]+)\s*(?:,\s*([^)]+))?\)$/i);
    if (m) return { kind: 'token', token: m[1], hex: resolve(m[1]) || normalizeHex(m[2]), alpha: 100 };
    m = v.match(/^color-mix\(\s*in srgb\s*,\s*var\(\s*--tb-([a-z-]+)\s*(?:,\s*([^)]+))?\)\s+(\d{1,3})%\s*,\s*transparent\s*\)$/i);
    if (m) return { kind: 'token', token: m[1], hex: resolve(m[1]) || normalizeHex(m[2]), alpha: Math.min(100, Number(m[3])) };
    if (HEX.test(v)) return { kind: 'hex', token: null, hex: normalizeHex(v), alpha: hexAlpha(v) };
    m = v.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+)\s*)?\)$/i);
    if (m) {
      const a = m[4] === undefined ? 1 : Math.max(0, Math.min(1, Number(m[4])));
      return { kind: 'rgba', token: null, hex: toHex(m[1], m[2], m[3]), alpha: Math.round(a * 100) };
    }
    return { kind: 'other', token: null, hex: null, alpha: 100 };
  }

  /** The value to store for a theme swatch at the given alpha (0–100). */
  function swatch(token, alpha) {
    const base = tokenValue(token);
    if (!base) return '';
    const a = Math.round(Math.max(0, Math.min(100, alpha ?? 100)));
    return a >= 100 ? base : `color-mix(in srgb, ${base} ${a}%, transparent)`;
  }

  /** The value to store for a fixed colour at the given alpha (0–100). */
  function fixed(hex, alpha) {
    const rgb = toRgb(hex);
    if (!rgb) return '';
    const a = Math.round(Math.max(0, Math.min(100, alpha ?? 100)));
    return a >= 100 ? normalizeHex(hex) : `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${a / 100})`;
  }

  /** Same colour, new alpha: a swatch stays a swatch, a fixed colour stays fixed. */
  function withAlpha(value, alpha, pal) {
    const p = parse(value, pal);
    if (p.kind === 'token') return swatch(p.token, alpha);
    if (p.kind === 'hex' || p.kind === 'rgba') return fixed(p.hex, alpha);
    return value;
  }

  /** A CSS colour that previews the value without the theme's variables. */
  function previewCss(value, pal) {
    const p = parse(value, pal);
    if (p.kind === 'transparent' || p.kind === 'empty') return 'transparent';
    if (!p.hex) return typeof value === 'string' ? value : 'transparent';
    const rgb = toRgb(p.hex);
    return `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${p.alpha / 100})`;
  }

  /** The #rrggbb an <input type="color"> can show for the value. */
  function inputHex(value, pal, fallback) {
    return parse(value, pal).hex || normalizeHex(fallback) || '#000000';
  }

  const api = { TOKENS, palette, tokenValue, parse, swatch, fixed, withAlpha, previewCss, inputHex, normalizeHex };
  root.CanvasColor = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
