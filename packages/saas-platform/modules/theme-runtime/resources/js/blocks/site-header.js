/*
 * Phase 1 (ADR-067) — the nav_header block's live behaviour, inlined once
 * per render by BlockRenderer::siteHeaderAssets().
 *
 *  - data-tb-header="sticky smart overlay" flags act on the page's banner
 *    element (the host wraps the block in its own <header>), so sticking,
 *    the scroll-shrink, hide-on-scroll-down and transparent-over-hero work
 *    whatever the host chrome is:
 *      .tb-hdr-sticky  sticks; .is-scrolled shrinks it past 8 px
 *      .tb-hdr-smart   .is-hidden while scrolling down, back when scrolling up
 *      .tb-hdr-overlay transparent over a first-section hero; solid once scrolled
 *    Overlay text colour: data-tb-overlay-text, or "auto" = light or dark
 *    text from the hero's own background.
 *  - Mega menus toggle by delegation (touch, keyboard, Esc, outside click),
 *    so a cloned copy — e.g. in the host's mobile menu — works too.
 *
 * Pure helpers are exposed on window.TbSiteHeader for the Node tests.
 */
(function (root) {
  'use strict';
  if (root.TbSiteHeader && root.TbSiteHeader.version === 1) return;

  /** Relative luminance (0..1) of an rgb()/rgba() string; null when unreadable or transparent. */
  function luminance(color) {
    const m = String(color || '').match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\s*\)/i);
    if (!m) return null;
    if (m[4] !== undefined && parseFloat(m[4]) === 0) return null;
    const [r, g, b] = [m[1], m[2], m[3]].map((v) => {
      const c = Math.min(255, parseFloat(v)) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  }

  /** Text colour for the overlay: white on dark or image heroes, near-black on light ones. */
  function overlayTextFor(background, hasImage) {
    if (hasImage) return '#ffffff';
    const l = luminance(background);
    return l !== null && l > 0.45 ? '#111827' : '#ffffff';
  }

  /** Scroll state from the previous and current offsets; `threshold` (default 8px) is when it counts as scrolled. */
  function scrollState(prevY, y, headerHeight, wasHidden, threshold) {
    const scrolled = y > (Number.isFinite(threshold) ? threshold : 8);
    let hidden = wasHidden;
    if (y <= headerHeight) hidden = false;
    else if (y > prevY + 6) hidden = true;
    else if (y < prevY - 6) hidden = false;
    return { scrolled, hidden };
  }

  const HERO = /(^|\s)tb-(hero|slider|carousel|video-hero)/;

  const api = { version: 1, luminance, overlayTextFor, scrollState, HERO };
  root.TbSiteHeader = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  const document = root.document;
  if (!document || typeof document.addEventListener !== 'function') return;

  function bannerOf(block) {
    return block.closest('header, [role="banner"]') || block;
  }

  /** The first rendered block after the banner, if it is a hero/slider. */
  function heroAfter(host) {
    let next = host.nextElementSibling;
    while (next && (/^(SCRIPT|STYLE|TEMPLATE)$/.test(next.tagName) || next.classList.contains('visually-hidden') || next.offsetHeight === 0)) {
      next = next.nextElementSibling;
    }
    if (!next) return null;
    const first = next.matches('[id^="tb-"]') && HERO.test(next.className) ? next : next.querySelector('[id^="tb-"]');
    return first && HERO.test(first.className) ? first : null;
  }

  function heroHasImage(hero) {
    // A photo or video under the header: light text (heroes darken media for text anyway).
    if (hero.querySelector('img, video, picture')) return true;
    return /url\(/.test(root.getComputedStyle(hero).backgroundImage || '');
  }

  function heroBackground(hero) {
    let el = hero;
    while (el && el !== document.documentElement) {
      const bg = root.getComputedStyle(el).backgroundColor;
      if (luminance(bg) !== null) return bg;
      const img = root.getComputedStyle(el).backgroundImage;
      if (img && img !== 'none') {
        const stop = img.match(/rgba?\([^)]*\)|#[0-9a-f]{3,8}/i);
        if (stop) return stop[0].startsWith('#') ? hexToRgb(stop[0]) : stop[0];
      }
      el = el.parentElement;
    }
    return 'rgb(255, 255, 255)';
  }

  function hexToRgb(hex) {
    let h = hex.slice(1);
    if (h.length <= 4) h = h.split('').map((c) => c + c).join('');
    return `rgb(${parseInt(h.slice(0, 2), 16)}, ${parseInt(h.slice(2, 4), 16)}, ${parseInt(h.slice(4, 6), 16)})`;
  }

  function init(block) {
    if (block.dataset.tbHeaderInit) return;
    block.dataset.tbHeaderInit = '1';
    const flags = (block.getAttribute('data-tb-header') || '').split(/\s+/).filter(Boolean);
    if (!flags.length) return;
    const host = bannerOf(block);
    const sticky = flags.includes('sticky');
    const hero = flags.includes('overlay') ? heroAfter(host) : null;
    // ADR-082 step 4: behaviour from the block (integers / allowlisted words, set by the renderer).
    const threshold = parseInt(block.getAttribute('data-tb-scroll-threshold') || '', 10);
    const offset = parseInt(block.getAttribute('data-tb-sticky-offset') || '', 10);
    const stickyOn = block.getAttribute('data-tb-sticky-on');
    const stickyQuery = stickyOn === 'desktop' ? '(min-width: 1024px)' : stickyOn === 'tablet' ? '(min-width: 768px)' : null;

    if (sticky) {
      if (host !== block) block.style.position = 'static'; // the banner sticks, not the block inside it
      if (flags.includes('noshrink')) host.classList.add('tb-hdr-no-shrink');
      if (/(^|\s)scrolled-shadow(\s|$)/.test(block.getAttribute('data-tb-hdr-set') || '')) host.classList.add('tb-hdr-own-shadow');
      if (offset > 0) host.style.top = offset + 'px';
      // Sticky on every device, or only from the tablet / desktop width up.
      const applySticky = () => {
        const on = !stickyQuery || root.matchMedia(stickyQuery).matches;
        host.classList.toggle('tb-hdr-sticky', on);
        host.classList.toggle('tb-hdr-smart', on && flags.includes('smart'));
        if (!on) host.classList.remove('is-hidden');
      };
      applySticky();
      if (stickyQuery) {
        const mq = root.matchMedia(stickyQuery);
        if (mq.addEventListener) mq.addEventListener('change', applySticky);
        else if (mq.addListener) mq.addListener(applySticky);
      }
    }
    if (hero) {
      const wanted = block.getAttribute('data-tb-overlay-text') || 'auto';
      const text = wanted === 'auto' ? overlayTextFor(heroBackground(hero), heroHasImage(hero)) : wanted;
      host.style.setProperty('--tb-hdr-overlay-text', text);
      host.classList.add('tb-hdr-overlay');
      // The hero now sits under the header: pad its content by the header's height.
      const setHeight = () => { if (!host.classList.contains('is-scrolled')) hero.style.setProperty('--tb-hdr-h', host.offsetHeight + 'px'); }; // the unscrolled height only, so the shrink never shifts the page
      hero.classList.add('tb-under-overlay');
      setHeight();
      if (typeof root.ResizeObserver === 'function') new root.ResizeObserver(setHeight).observe(host);
    }
    if (!sticky && !hero) return;

    let prevY = root.scrollY;
    let hidden = false;
    let ticking = false;
    function update() {
      ticking = false;
      const y = root.scrollY;
      const state = scrollState(prevY, y, host.offsetHeight, hidden, Number.isFinite(threshold) ? threshold : undefined);
      hidden = state.hidden && host.classList.contains('tb-hdr-smart') && !host.contains(document.activeElement);
      host.classList.toggle('is-scrolled', state.scrolled);
      host.classList.toggle('is-hidden', hidden);
      prevY = y;
    }
    root.addEventListener('scroll', () => {
      if (!ticking) {
        ticking = true;
        root.requestAnimationFrame(update);
      }
    }, { passive: true });
    host.addEventListener('focusin', () => {
      hidden = false;
      host.classList.remove('is-hidden');
    });
    update();
  }

  function initAll() {
    document.querySelectorAll('.tb-nav-header[data-tb-header]').forEach(init);
  }

  /* ── Mega menus ──────────────────────────────────────────────────── */
  // WU-D: same desktop step as the stylesheet's hover rules (lg = 1024px).
  const touchMenus = () => !root.matchMedia('(hover: hover) and (min-width: 1024px)').matches;

  function setOpen(item, open) {
    item.classList.toggle('tb-mega-open', open);
    const trigger = item.querySelector('.tb-mega-menu-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function closeAll(except) {
    document.querySelectorAll('.tb-mega-menu-item.tb-mega-open').forEach((item) => {
      if (item !== except) setOpen(item, false);
    });
  }

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest && e.target.closest('.tb-mega-menu-trigger');
    if (trigger && touchMenus()) {
      e.preventDefault();
      const item = trigger.closest('.tb-mega-menu-item');
      const open = !item.classList.contains('tb-mega-open');
      closeAll(item);
      setOpen(item, open);
      return;
    }
    if (!(e.target.closest && e.target.closest('.tb-mega-menu-item'))) closeAll(null);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = document.querySelector('.tb-mega-menu-item.tb-mega-open, .tb-mega-menu-item:focus-within');
    if (!open) return;
    closeAll(null);
    const trigger = open.querySelector('.tb-mega-menu-trigger');
    if (trigger) {
      trigger.focus();
      open.classList.add('tb-mega-suppress'); // hide the focus-within panel until focus moves on
    }
  });
  document.addEventListener('focusout', (e) => {
    const item = e.target.closest && e.target.closest('.tb-mega-menu-item');
    if (item && !item.contains(e.relatedTarget)) {
      item.classList.remove('tb-mega-suppress');
      if (!touchMenus()) setOpen(item, false);
    }
  });
  // Hover on desktop is CSS; keep aria-expanded truthful.
  document.addEventListener('mouseover', (e) => {
    const item = e.target.closest && e.target.closest('.tb-mega-menu-item');
    if (item && !touchMenus()) item.querySelector('.tb-mega-menu-trigger')?.setAttribute('aria-expanded', 'true');
  });
  document.addEventListener('mouseout', (e) => {
    const item = e.target.closest && e.target.closest('.tb-mega-menu-item');
    if (item && !item.contains(e.relatedTarget) && !touchMenus() && !item.classList.contains('tb-mega-open')) {
      item.querySelector('.tb-mega-menu-trigger')?.setAttribute('aria-expanded', 'false');
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})(typeof window !== 'undefined' ? window : globalThis);
