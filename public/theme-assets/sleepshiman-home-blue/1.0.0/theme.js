(() => {
  const root = document;
  const sync = (track) => {
    const selector = `#${CSS.escape(track.id)}`;
    const disabled = track.scrollWidth <= track.clientWidth + 1;
    root.querySelectorAll(`[data-ss-scroll="${selector}"]`).forEach((button) => {
      button.disabled = disabled;
      button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    });
  };
  const syncAll = () => root.querySelectorAll('[data-ss-carousel]').forEach(sync);
  root.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ss-scroll]');
    if (!trigger) return;
    const track = root.querySelector(trigger.getAttribute('data-ss-scroll'));
    if (!track) return;
    track.scrollBy({ left: Number(trigger.dataset.direction || 1) * track.clientWidth * .8, behavior: 'smooth' });
  });
  root.addEventListener('DOMContentLoaded', syncAll, { once: true });
  window.addEventListener('load', syncAll, { once: true });
  window.addEventListener('resize', syncAll, { passive: true });
})();
