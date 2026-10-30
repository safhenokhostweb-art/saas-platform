/*
 * Phase 3b (ADR-069) — pricing_cards behaviour, inlined once per render by
 * BlockRenderer::pricingAssets() when a block has the billing switch or a
 * feature tooltip. Delegated, so blocks added later work too.
 *
 *  - [data-tb-billing] buttons switch every [data-tb-cycle] price inside
 *    their [data-tb-pricing] block (monthly ↔ yearly) and keep aria-pressed
 *    true to the choice;
 *  - .tb-tip tooltips show on hover and focus (CSS); a tap toggles them,
 *    an outside tap closes them, and Esc dismisses a showing one until the
 *    pointer or focus leaves (WCAG 1.4.13).
 *
 */
(function (root) {
  'use strict';
  const document = root.document;
  if (root.TbPricing || !document || typeof document.addEventListener !== 'function') return;

  function setCycle(block, active) {
    block.querySelectorAll('[data-tb-cycle]').forEach((el) => {
      el.hidden = el.getAttribute('data-tb-cycle') !== active;
    });
    block.querySelectorAll('[data-tb-billing]').forEach((b) => {
      const on = b.getAttribute('data-tb-billing') === active;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('is-active', on);
    });
    block.setAttribute('data-tb-cycle-active', active);
  }

  function closeTips(except) {
    document.querySelectorAll('.tb-tip.is-open').forEach((t) => { if (t !== except) t.classList.remove('is-open'); });
  }

  document.addEventListener('click', (e) => {
    const option = e.target.closest && e.target.closest('[data-tb-billing]');
    const block = option && option.closest('[data-tb-pricing]');
    if (option && block) {
      setCycle(block, option.getAttribute('data-tb-billing') === 'yearly' ? 'yearly' : 'monthly');
      return;
    }
    const trigger = e.target.closest && e.target.closest('.tb-tip-trigger');
    if (trigger) {
      const tip = trigger.closest('.tb-tip');
      tip.classList.remove('is-dismissed');
      tip.classList.toggle('is-open');
      closeTips(tip);
      return;
    }
    closeTips(null);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    let dismissed = false;
    document.querySelectorAll('.tb-tip').forEach((t) => {
      if (t.classList.contains('is-open') || t.matches(':hover') || t.contains(document.activeElement)) {
        t.classList.remove('is-open');
        t.classList.add('is-dismissed');
        dismissed = true;
      }
    });
    if (dismissed) e.stopPropagation();
  });

  const undismiss = (e) => {
    const tip = e.target.closest && e.target.closest('.tb-tip');
    if (tip) tip.classList.remove('is-dismissed');
  };
  document.addEventListener('mouseleave', undismiss, true);
  document.addEventListener('focusout', undismiss);

  root.TbPricing = { setCycle, closeTips };
})(typeof window !== 'undefined' ? window : globalThis);
