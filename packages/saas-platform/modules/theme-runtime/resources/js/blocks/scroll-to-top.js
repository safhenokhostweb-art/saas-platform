/*!
 * Global Scroll-to-Top Block Runtime (Commercial Grade)
 * ====================================================
 * Features:
 * - Viewport scroll tracking with requestAnimationFrame (60fps, no layout thrashing)
 * - Dynamic fade-in/fade-out based on configurable data-tb-threshold
 * - Smooth scroll to top with prefers-reduced-motion fallback
 * - Canvas editor / live preview integration (interactive in preview mode)
 * - ThemeCanvasRuntime & ThemeBlockSDK lifecycle event compatibility
 * - MutationObserver for dynamic block re-renders
 */
(function (root) {
  'use strict';

  if (!root) return;

  var document = root.document;
  if (!document || typeof document.addEventListener !== 'function') return;

  var isScrolling = false;

  function isReducedMotion() {
    try {
      return root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  function getScrollY() {
    return root.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
  }

  function scrollToTop() {
    try {
      root.scrollTo({
        top: 0,
        behavior: isReducedMotion() ? 'auto' : 'smooth'
      });
    } catch (e) {
      root.scrollTo(0, 0);
    }
  }

  function updateButtonVisibility(btn) {
    if (!btn) return;

    var isEditor = btn.classList.contains('is-editor-mode') ||
                   (btn.closest && btn.closest('.tb-canvas-block') !== null) ||
                   document.body.classList.contains('tb-editor-mode') ||
                   document.body.classList.contains('tb-canvas-live-preview');

    var threshold = parseInt(btn.getAttribute('data-tb-threshold'), 10);
    if (isNaN(threshold) || threshold < 0) threshold = 300;

    var currentY = getScrollY();

    if (isEditor || currentY >= threshold) {
      btn.classList.add('is-visible');
    } else {
      btn.classList.remove('is-visible');
    }
  }

  function onScroll() {
    if (isScrolling) return;
    isScrolling = true;
    (root.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); })(function () {
      var buttons = document.querySelectorAll('.tb-scroll-to-top');
      for (var i = 0; i < buttons.length; i++) {
        updateButtonVisibility(buttons[i]);
      }
      isScrolling = false;
    });
  }

  function initButton(btn) {
    if (!btn || btn._tbSttInit) return;
    btn._tbSttInit = true;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      scrollToTop();
    });

    updateButtonVisibility(btn);
  }

  function scan() {
    var buttons = document.querySelectorAll('.tb-scroll-to-top');
    for (var i = 0; i < buttons.length; i++) {
      initButton(buttons[i]);
    }
  }

  // Register scroll & DOM ready listeners
  root.addEventListener('scroll', onScroll, { passive: true });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }

  // Canvas / Dynamic DOM mutations observer
  if (root.MutationObserver && document.body) {
    var observer = new MutationObserver(function (mutations) {
      var needsScan = false;
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].addedNodes && mutations[i].addedNodes.length > 0) {
          needsScan = true;
          break;
        }
      }
      if (needsScan) scan();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // ThemeCanvasRuntime registration if present
  if (root.ThemeCanvasRuntime && typeof root.ThemeCanvasRuntime.registerBlock === 'function') {
    root.ThemeCanvasRuntime.registerBlock('scroll_to_top', {
      init: function (ctx) {
        var btn = ctx.element ? ctx.element.querySelector('.tb-scroll-to-top') : null;
        if (btn) initButton(btn);
        return true;
      },
      update: function (ctx) {
        var btn = ctx.element ? ctx.element.querySelector('.tb-scroll-to-top') : null;
        if (btn) {
          btn._tbSttInit = false;
          initButton(btn);
        }
      }
    });
  }

  // Expose public API
  var api = {
    version: 1,
    scrollToTop: scrollToTop,
    scan: scan,
    update: onScroll
  };

  root.TbScrollToTop = api;
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : this);
