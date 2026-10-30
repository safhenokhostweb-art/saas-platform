/*
 * Phase 0.6 (ADR-065) — the Canvas icon picker's model, shared by the
 * platform Canvas and customer-site's Canvas editor and tested in Node.
 * DOM-free. The catalog is resources/icons/catalog.json (the same file
 * Support\IconLibrary renders from), handed over with load().
 *
 * An icon value is one of:
 *   - a catalog name:   "truck"   — rendered as inline SVG
 *   - a legacy value:   "🚚", "★" — rendered as text, exactly as before
 *   - empty:            ""        — no icon
 */
(function (root) {
  'use strict';

  let catalog = { categories: [], icons: {} };
  let names = [];

  function load(data) {
    const icons = data && typeof data.icons === 'object' && data.icons ? data.icons : {};
    catalog = { categories: Array.isArray(data && data.categories) ? data.categories : [], icons };
    names = Object.keys(icons);
    return api;
  }

  /*
   * Search text in one shape: Latin lower case, Persian ی/ک for their
   * Arabic forms, Latin digits, no ZWNJ/diacritics/tatweel, single spaces.
   */
  function normalize(text) {
    return String(text == null ? '' : text)
      .toLowerCase()
      .replace(/[يى]/g, 'ی')
      .replace(/ك/g, 'ک')
      .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0))
      .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660))
      .replace(/[ً-ٰٟـ]/g, '')
      .replace(/[‌‍\-_]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function name(value) {
    if (typeof value !== 'string') return null;
    const n = value.trim().toLowerCase();
    return n !== '' && Object.prototype.hasOwnProperty.call(catalog.icons, n) ? n : null;
  }

  function isIcon(value) {
    return name(value) !== null;
  }

  /** 'icon' | 'legacy' | 'empty' */
  function kind(value) {
    if (value == null || String(value).trim() === '') return 'empty';
    return isIcon(value) ? 'icon' : 'legacy';
  }

  function label(value) {
    const n = name(value);
    return n ? catalog.icons[n].label : '';
  }

  /** Categories in catalog order, each with its icon count. */
  function categories() {
    return catalog.categories.map((c) => ({
      key: c.key,
      label: c.label,
      count: names.filter((n) => catalog.icons[n].category === c.key).length,
    }));
  }

  /*
   * Icons matching every word of the query (name, Persian label or
   * keyword), best match first: exact, then prefix, then substring.
   * An empty query lists the category (or everything) in catalog order.
   */
  function search(query, category) {
    const words = normalize(query).split(' ').filter(Boolean);
    const out = [];
    names.forEach((n, order) => {
      const icon = catalog.icons[n];
      if (category && icon.category !== category) return;
      let score = 0;
      if (words.length) {
        // terms[0..1] (name, label) outrank keywords at the same match level.
        const terms = [n, icon.label].concat(icon.keywords || []).map(normalize);
        for (const w of words) {
          let best = 9;
          // whole term < one of its words < prefix < substring.
          terms.forEach((t, i) => {
            const own = i < 2 ? 0 : 1;
            if (t === w) best = Math.min(best, own);
            else if (t.split(' ').includes(w)) best = Math.min(best, 2 + own);
            else if (t.startsWith(w) || t.split(' ').some((p) => p.startsWith(w))) best = Math.min(best, 4 + own);
            else if (t.includes(w)) best = Math.min(best, 6);
          });
          if (best === 9) return;
          score += best;
        }
      }
      out.push({ name: n, label: icon.label, category: icon.category, score, order });
    });
    out.sort((a, b) => a.score - b.score || a.order - b.order);
    return out.map(({ name: n, label: l, category: c }) => ({ name: n, label: l, category: c }));
  }

  /* Same markup as Support\IconLibrary::svg(); bodies come only from the
   * catalog, never from the value being edited. */
  function svg(value) {
    const n = name(value);
    if (!n) return '';
    return '<svg class="tb-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">'
      + catalog.icons[n].body + '</svg>';
  }

  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  /** HTML previewing any stored value: SVG, escaped legacy text, or ''. */
  function previewHtml(value) {
    const k = kind(value);
    if (k === 'icon') return svg(value);
    return k === 'legacy' ? '<span class="tbip-legacy">' + escapeHtml(String(value).trim()) + '</span>' : '';
  }

  /** A short Persian description of the stored value, for the trigger. */
  function describe(value) {
    const k = kind(value);
    if (k === 'icon') return label(value);
    if (k === 'legacy') return 'ایموجی/متن (قدیمی)';
    return 'بدون آیکون';
  }

  const api = { load, normalize, name, isIcon, kind, label, categories, search, svg, previewHtml, describe };
  root.CanvasIcons = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
