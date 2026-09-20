<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<script>(function(){
    var KEY = 'tb-theme';
    function resolve(p) {
        if (p === 'system') {
            try { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            catch (e) { return 'light'; }
        }
        return p === 'dark' ? 'dark' : 'light';
    }
    function apply(p) {
        document.documentElement.setAttribute('data-theme', resolve(p));
        document.documentElement.setAttribute('data-theme-pref', p);
    }
    var pref = 'system';
    try { pref = localStorage.getItem(KEY) || 'system'; } catch (e) {}
    apply(pref);
    window.__tbTheme = {
        get: function () { return pref; },
        set: function (p) {
            pref = (p === 'light' || p === 'dark' || p === 'system') ? p : 'system';
            apply(pref);
            try { localStorage.setItem(KEY, pref); } catch (e) {}
            document.dispatchEvent(new CustomEvent('tb-theme-change', { detail: { pref: pref, resolved: resolve(pref) } }));
        },
        cycle: function () {
            var order = ['light', 'dark', 'system'];
            this.set(order[(order.indexOf(pref) + 1) % order.length]);
        }
    };
})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Preview</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    direction: rtl;
    background: #ffffff;
    color: #1f2937;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
/* Base TB styles */
.tb-container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
.tb-section { width: 100%; }
.tb-section-inner { width: 100%; }
.tb-padding-none  { padding-top: 0; padding-bottom: 0; }
.tb-padding-sm    { padding-top: 2rem; padding-bottom: 2rem; }
.tb-padding-md    { padding-top: 4rem; padding-bottom: 4rem; }
.tb-padding-lg    { padding-top: 6rem; padding-bottom: 6rem; }
.tb-padding-xl    { padding-top: 8rem; padding-bottom: 8rem; }
.tb-grid          { display: grid; gap: 1.5rem; }
.tb-cols-2        { grid-template-columns: repeat(2, 1fr); }
.tb-cols-3        { grid-template-columns: repeat(3, 1fr); }
.tb-cols-4        { grid-template-columns: repeat(4, 1fr); }
{{-- Commercial Productization fase-3: real, confirmed bug — every block
     renderer actually emits `tb-grid tb-grid-cols-{N}` (see
     BlockRenderer.php), never the `tb-cols-N` this file defined; every
     multi-column block (product_grid, category_slider grid layout,
     team_cards, ...) rendered as an unstyled single column here. Additive
     fix: keep tb-cols-N (used elsewhere in this file) and add the classes
     the renderers actually emit. --}}
.tb-grid.tb-grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.tb-grid.tb-grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.tb-grid.tb-grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.tb-grid.tb-grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.tb-grid.tb-grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.tb-grid.tb-grid-cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
@media (max-width: 767px) {
    .tb-grid.tb-grid-cols-2, .tb-grid.tb-grid-cols-3, .tb-grid.tb-grid-cols-4,
    .tb-grid.tb-grid-cols-5, .tb-grid.tb-grid-cols-6 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tb-grid.tb-grid-cols-1 { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .tb-grid.tb-grid-cols-2, .tb-grid.tb-grid-cols-3, .tb-grid.tb-grid-cols-4 { grid-template-columns: 1fr; }
}
.tb-hero          { min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 5rem 1.5rem; }
.tb-hero h1       { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
.tb-hero p        { font-size: 1.1rem; opacity: 0.85; margin-bottom: 1.5rem; }
.tb-hero .tb-btn  { display: inline-flex; align-items: center; padding: 0.75rem 2rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: #fff; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; }
.tb-cta           { padding: 4rem 1.5rem; text-align: center; }
.tb-heading       { line-height: 1.4; margin: 0 0 1rem; font-weight: 700; }
.tb-text          { line-height: 1.85; color: #374151; margin: 0 0 1rem; }
.tb-img           { max-width: 100%; border-radius: 0.5rem; }
.tb-button        { display: inline-flex; align-items: center; padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
{{-- Commercial Productization fase-3: same real .tb-btn/.tb-btn-secondary
     gap as canvas-preview.blade.php — see the matching comment there. --}}
.tb-btn           { display: inline-block; padding: 0.75rem 1.5rem; border-radius: var(--tb-radius, 0.75rem); text-decoration: none; font-weight: 600; font-size: 1rem; transition: opacity 0.2s; }
.tb-btn:hover     { opacity: 0.95; }
.tb-btn-primary   { background: var(--tb-primary, #4f46e5); color: #fff; }
.tb-btn-secondary { background: transparent; color: var(--tb-primary, #4f46e5); border: 2px solid currentColor; }
.tb-divider       { border: none; border-top: 1px solid #e5e7eb; margin: 1rem 0; }
.tb-bg-primary    { background: var(--tb-primary, #4f46e5); color: #fff; }
.tb-bg-dark       { background: #1e293b; color: #fff; }
.tb-bg-light      { background: #f8fafc; }
.tb-bg-gray       { background: #f1f5f9; }

{{-- Commercial Productization fase-3: the 7 canonical block types from
     ADR-026 plus stats_row had ZERO CSS in this live-preview either — see
     the matching comment/values in canvas-preview.blade.php. --}}
.tb-features-section-header { text-align: center; margin-bottom: 2.5rem; }
.tb-features-section-title { font-size: clamp(1.375rem, 2.5vw, 1.875rem); font-weight: 800; color: var(--tb-text, #111827); margin: 0 0 0.5rem; }
.tb-features-section-sub { color: var(--tb-text-muted, #6b7280); font-size: 1rem; margin: 0; }
.tb-stats-grid { display: flex; flex-wrap: wrap; gap: 1.5rem; }
.tb-stat-item { flex: 1 1 8rem; text-align: center; }
.tb-stat-value { display: block; font-size: 1.875rem; font-weight: 800; color: var(--tb-primary-text, #4f46e5); }
.tb-stat-suffix { font-size: 1.25rem; }
.tb-stat-label { display: block; font-size: 0.875rem; color: var(--tb-text-muted, #6b7280); margin-top: 0.25rem; }
.tb-service-card { display: block; background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; text-decoration: none; color: inherit; transition: box-shadow 0.2s; }
.tb-service-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.tb-service-icon { font-size: 1.75rem; margin-bottom: 0.75rem; }
.tb-service-title { font-size: 1rem; font-weight: 700; color: var(--tb-text, #111827); margin: 0 0 0.4rem; }
.tb-service-text { font-size: 0.875rem; color: var(--tb-text-muted, #6b7280); line-height: 1.6; margin: 0; }
.tb-project-card { display: block; background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; text-decoration: none; color: inherit; }
.tb-project-card-category { display: inline-block; background: var(--tb-background, #eff6ff); color: var(--tb-primary, #4f46e5); padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.5rem; }
.tb-project-card-title { font-size: 1.0625rem; font-weight: 700; color: var(--tb-text, #111827); margin: 0 0 0.4rem; }
.tb-project-card-text { font-size: 0.875rem; color: var(--tb-text-muted, #6b7280); line-height: 1.5; margin: 0; }
.tb-contact-info-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
.tb-contact-info-item { display: flex; align-items: flex-start; gap: 0.65rem; }
.tb-contact-info-icon { font-size: 1.25rem; }
.tb-contact-info-label { font-size: 0.75rem; font-weight: 700; color: var(--tb-text-muted, #6b7280); text-transform: uppercase; letter-spacing: 0.04em; margin: 0; }
.tb-contact-info-value { font-size: 0.9375rem; font-weight: 600; color: var(--tb-text, #111827); margin: 0.15rem 0 0; }
.tb-about-section { max-width: 48rem; margin: 0 auto; text-align: center; }
.tb-about-features { list-style: none; padding: 0; margin: 1rem 0; text-align: start; display: inline-block; }
.tb-about-feature { padding: 0.3rem 0; }
.tb-about-stats { justify-content: center; margin-top: 1.5rem; }
.tb-hero-trust-inner { max-width: 42rem; margin: 0 auto; text-align: center; }
.tb-hero-trust-meta { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; margin-top: 1.25rem; font-size: 0.9rem; color: var(--tb-text-muted, #6b7280); }
.tb-hero-trust-rating, .tb-hero-trust-phone { display: inline-flex; align-items: center; gap: 0.4rem; }

/* Commercial Productization fase-3 (round 2): same comprehensive parity
   port as canvas-preview.blade.php — see its matching comment for why. */
.tb-features-section-header { text-align: center; margin-bottom: 2.5rem; }
.tb-feature-card { padding: 1.75rem; border-radius: var(--tb-radius, 0.75rem); transition: transform 0.2s, box-shadow 0.2s; }
.tb-feature-card-cards { border: 1px solid #e5e7eb; background: var(--tb-surface, #fff); box-shadow: var(--tb-shadow-sm); }
.tb-feature-card-bordered { border: 2px solid #e5e7eb; background: transparent; }
.tb-feature-card-minimal { border: none; background: transparent; }
.tb-feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
.tb-feature-icon { font-size: 2.5rem; margin-bottom: 1rem; line-height: 1; }
.tb-feature-title { font-size: 1.05rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--tb-text, #0f172a); }
.tb-feature-text { color: var(--tb-text-muted, #4b5563); font-size: 0.875rem; line-height: 1.7; margin: 0; }
.tb-stat-divider { width: 1px; height: 80px; background: rgba(0,0,0,0.1); align-self: center; flex-shrink: 0; }
.tb-client-logos-title { text-align: center; font-size: 0.8rem; color: var(--tb-text-muted, #6b7280); margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
.tb-steps-grid { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: center; }
.tb-step-item { flex: 1 1 200px; text-align: center; padding: 1.5rem 1.25rem; max-width: 240px; }
.tb-step-number { width: 3.25rem; height: 3.25rem; border-radius: 50%; background: var(--tb-primary, #6366f1); color: #fff; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 4px 16px rgba(99,102,241,0.3); }
.tb-step-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--tb-text, #0f172a); }
.tb-step-text { font-size: 0.875rem; color: var(--tb-text-muted, #4b5563); line-height: 1.65; margin: 0; }
@media(max-width:600px) { .tb-step-item{flex:1 1 140px;max-width:none;} }
.tb-team-member { background: var(--tb-surface, #fff); border-radius: var(--tb-radius, 0.75rem); border: 1px solid #e5e7eb; overflow: hidden; text-align: center; box-shadow: var(--tb-shadow-sm); transition: transform 0.2s, box-shadow 0.2s; padding-bottom: 1.5rem; }
.tb-team-member:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
.tb-team-avatar { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; margin: 1.75rem auto 1rem; border: 3px solid var(--tb-primary, #6366f1); display: block; }
.tb-team-avatar-placeholder { width: 84px; height: 84px; border-radius: 50%; background: linear-gradient(135deg, var(--tb-primary, #6366f1), var(--tb-secondary, #0ea5e9)); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: #fff; margin: 1.75rem auto 1rem; }
.tb-team-name { font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--tb-text, #0f172a); }
.tb-team-role { font-size: 0.85rem; color: var(--tb-primary-text, #6366f1); font-weight: 600; margin: 0 0 0.75rem; }
.tb-team-bio { font-size: 0.85rem; color: var(--tb-text-muted, #4b5563); line-height: 1.55; margin: 0 1.25rem; }
.tb-skills-list { max-width: 640px; }
.tb-skill-item { margin-bottom: 1.5rem; }
.tb-skill-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: var(--tb-text, #1f2937); }
.tb-skill-bar-track { background: #e5e7eb; border-radius: 999px; height: 10px; overflow: hidden; }
.tb-skill-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(to left, var(--tb-secondary, #0ea5e9), var(--tb-primary, #6366f1)); width: 0; transition: width 1.4s cubic-bezier(0.4,0,0.2,1); }
.tb-testimonials-header { text-align: center; margin-bottom: 2rem; }
.tb-testimonials-grid { display: grid; gap: 1.5rem; }
.tb-testimonials-grid.cols-1 { grid-template-columns: 1fr; max-width: 640px; margin: 0 auto; }
.tb-testimonials-grid.cols-2 { grid-template-columns: repeat(2,1fr); }
.tb-testimonials-grid.cols-3 { grid-template-columns: repeat(3,1fr); }
.tb-testimonial-card { background: var(--tb-surface, #fff); border-radius: var(--tb-radius, 0.75rem); border: 1px solid #e5e7eb; padding: 1.75rem; box-shadow: var(--tb-shadow-sm); display: flex; flex-direction: column; }
.tb-testimonial-stars { color: #f59e0b; font-size: 0.95rem; margin-bottom: 0.75rem; letter-spacing: 2px; }
.tb-testimonial-text { font-size: 0.95rem; line-height: 1.75; color: var(--tb-surface-text, #1f2937); margin: 0 0 1.25rem; font-style: italic; flex: 1; }
.tb-testimonial-author { display: flex; align-items: center; gap: 0.75rem; }
.tb-testimonial-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--tb-primary, #6366f1), var(--tb-secondary, #0ea5e9)); display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.tb-testimonial-name { font-weight: 700; font-size: 0.9rem; color: var(--tb-text, #0f172a); }
.tb-testimonial-role { font-size: 0.8rem; color: var(--tb-text-muted, #6b7280); margin-top: 0.1rem; }
@media(max-width:767px) { .tb-testimonials-grid.cols-2,.tb-testimonials-grid.cols-3{grid-template-columns:1fr;} }
.tb-trust-grid { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; }
.tb-trust-badge { display: flex; align-items: center; gap: 1rem; padding: 1.1rem 1.5rem; border: 1px solid #e5e7eb; border-radius: var(--tb-radius, 0.75rem); background: var(--tb-surface, #fff); box-shadow: var(--tb-shadow-sm); flex: 1 1 210px; }
.tb-trust-icon { font-size: 1.875rem; flex-shrink: 0; }
.tb-trust-info-title { font-weight: 700; font-size: 0.9rem; margin: 0 0 0.15rem; color: var(--tb-text, #0f172a); }
.tb-trust-info-text { font-size: 0.8rem; color: var(--tb-text-muted, #6b7280); margin: 0; }
@media(max-width:500px) { .tb-trust-badge{flex:1 1 150px;padding:0.75rem 1rem;} }
.tb-pricing-header { text-align: center; margin-bottom: 2.5rem; }
.tb-pricing-grid { display: grid; gap: 1.5rem; align-items: start; }
.tb-pricing-grid.cols-1 { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
.tb-pricing-grid.cols-2 { grid-template-columns: repeat(2,1fr); }
.tb-pricing-grid.cols-3 { grid-template-columns: repeat(3,1fr); }
.tb-pricing-grid.cols-4 { grid-template-columns: repeat(4,1fr); }
.tb-pricing-card { border-radius: var(--tb-radius, 0.75rem); border: 1px solid #e5e7eb; padding: 2rem; background: var(--tb-surface, #fff); box-shadow: var(--tb-shadow-sm); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
.tb-pricing-card.highlighted { border-color: var(--tb-primary, #6366f1); border-width: 2px; box-shadow: 0 4px 32px rgba(99,102,241,0.18); transform: translateY(-6px); }
.tb-plan-badge { display: inline-block; background: var(--tb-primary, #6366f1); color: #fff; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.85rem; border-radius: 999px; margin-bottom: 1rem; }
.tb-plan-name { font-size: 0.85rem; font-weight: 700; color: var(--tb-text-muted, #6b7280); margin: 0 0 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
.tb-plan-price { font-size: 2.75rem; font-weight: 800; color: var(--tb-text, #0f172a); line-height: 1; }
.tb-plan-period { font-size: 0.85rem; color: var(--tb-text-muted, #6b7280); margin: 0.25rem 0 1.5rem; }
.tb-plan-features { list-style: none; padding: 0; margin: 0 0 2rem; flex: 1; }
.tb-plan-features li { padding: 0.55rem 0; font-size: 0.9rem; color: var(--tb-text, #374151); border-bottom: 1px solid #f3f4f6; display: flex; align-items: flex-start; gap: 0.5rem; }
.tb-plan-features li::before { content: '✓'; color: var(--tb-primary-text, #6366f1); font-weight: 800; flex-shrink: 0; }
.tb-plan-features li:last-child { border-bottom: none; }
@media(max-width:767px) { .tb-pricing-grid.cols-2,.tb-pricing-grid.cols-3,.tb-pricing-grid.cols-4{grid-template-columns:1fr;} .tb-pricing-card.highlighted{transform:none;} }
.tb-newsletter { padding: 3.5rem 2rem; border-radius: 1rem; text-align: center; }
.tb-newsletter-title { font-size: clamp(1.5rem,3vw,2.25rem); font-weight: 800; margin: 0 0 0.75rem; letter-spacing: -0.01em; }
.tb-newsletter-sub { font-size: 1rem; margin: 0 0 1.75rem; opacity: 0.88; max-width: 50ch; margin-left: auto; margin-right: auto; }
.tb-newsletter-form { display: flex; gap: 0.75rem; max-width: 500px; margin: 0 auto; flex-wrap: wrap; }
.tb-newsletter-input { flex: 1 1 240px; padding: 0.8rem 1.1rem; border-radius: var(--tb-radius, 0.75rem); border: 2px solid rgba(255,255,255,0.2); font-family: inherit; font-size: 1rem; background: rgba(255,255,255,0.95); color: #0f172a; }
.tb-newsletter-input:focus { outline: none; border-color: rgba(255,255,255,0.6); }
.tb-newsletter-btn { padding: 0.8rem 1.5rem; border-radius: var(--tb-radius, 0.75rem); border: none; font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
.tb-newsletter-btn:hover { opacity: 0.9; }
@media(max-width:480px) { .tb-newsletter-form{flex-direction:column;} .tb-newsletter-input,.tb-newsletter-btn{width:100%;} }
.tb-bento-header { text-align: center; margin-bottom: 2rem; }
.tb-bento-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; grid-auto-rows: minmax(160px,auto); }
.tb-bento-item { border-radius: 1rem; padding: 1.75rem; display: flex; flex-direction: column; justify-content: flex-end; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid rgba(0,0,0,0.06); min-height: 160px; }
.tb-bento-item:hover { transform: scale(1.02); box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
.tb-bento-item.large { grid-column: span 2; }
.tb-bento-item.tall { grid-row: span 2; }
.tb-bento-item.featured { grid-column: span 2; grid-row: span 2; min-height: 320px; }
.tb-bento-item-icon { font-size: 2.25rem; margin-bottom: 0.75rem; line-height: 1; }
.tb-bento-item-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.3rem; }
.tb-bento-item-text { font-size: 0.85rem; line-height: 1.55; margin: 0; opacity: 0.8; }
@media(max-width:767px) { .tb-bento-grid{grid-template-columns:repeat(2,1fr);} .tb-bento-item.large,.tb-bento-item.featured{grid-column:span 2;} .tb-bento-item.tall{grid-row:span 1;} }
@media(max-width:480px) { .tb-bento-grid{grid-template-columns:1fr;} .tb-bento-item.large,.tb-bento-item.featured,.tb-bento-item.tall{grid-column:span 1;grid-row:span 1;} }
.tb-service-card.style-bordered { border: 1px solid #e5e7eb; background: var(--tb-surface, #fff); box-shadow: var(--tb-shadow-sm); }
.tb-service-card.style-filled { background: linear-gradient(135deg, rgba(99,102,241,0.06), rgba(14,165,233,0.06)); border: 1px solid rgba(99,102,241,0.15); }
.tb-service-card.style-minimal { border: none; background: transparent; padding-right: 0; padding-left: 0; }
.tb-service-link { font-size: 0.875rem; font-weight: 700; color: var(--tb-primary-text, #6366f1); text-decoration: none; }
.tb-service-link:hover { text-decoration: underline; }
.tb-contact-split { display: flex; gap: 3rem; align-items: flex-start; flex-wrap: wrap; }
.tb-contact-info { flex: 1 1 260px; }
.tb-contact-form-wrap { flex: 1 1 320px; }
.tb-contact-form-simple { background: var(--tb-surface, #fff); border-radius: var(--tb-radius, 0.75rem); padding: 2rem; border: 1px solid #e5e7eb; box-shadow: var(--tb-shadow-sm); }
.tb-contact-form-simple input, .tb-contact-form-simple textarea { width: 100%; padding: 0.7rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; font-family: inherit; font-size: 0.95rem; color: var(--tb-text, #1f2937); background: #fff; box-sizing: border-box; transition: border-color 0.15s; }
.tb-contact-form-simple input:focus, .tb-contact-form-simple textarea:focus { outline: none; border-color: var(--tb-primary, #6366f1); }
@media(max-width:767px) { .tb-contact-split{flex-direction:column;gap:1.5rem;} }
.tb-row { display: flex; flex-wrap: wrap; gap: 1rem; }
.tb-column { min-width: 0; }
.tb-col-full { flex: 1 1 100%; }
.tb-col-1-2 { flex: 1 1 calc(50% - 0.5rem); }
.tb-col-1-3 { flex: 1 1 calc(33.333% - 0.67rem); }
.tb-col-2-3 { flex: 1 1 calc(66.666% - 0.67rem); }
.tb-col-1-4 { flex: 1 1 calc(25% - 0.75rem); }
.tb-col-3-4 { flex: 1 1 calc(75% - 0.75rem); }
@media (max-width: 767px) { .tb-col-1-2, .tb-col-1-3, .tb-col-2-3, .tb-col-1-4, .tb-col-3-4 { flex: 1 1 100%; } }
.tb-bg-secondary { background-color: var(--tb-secondary, #7c3aed) !important; color: var(--tb-on-secondary, #fff) !important; }
.tb-bg-surface { background-color: var(--tb-surface, #fff) !important; }
.tb-bg-background { background-color: var(--tb-background, #f9fafb) !important; }
.tb-bg-text { background-color: var(--tb-text, #111827) !important; color: #fff !important; }
.tb-product-card { background: var(--tb-surface, #fff); border-radius: var(--tb-radius, 0.75rem); border: 1px solid #e5e7eb; padding: 0; overflow: hidden; box-shadow: var(--tb-shadow-sm); text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: box-shadow 0.2s, transform 0.15s; }
.tb-product-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); transform: translateY(-2px); }
.tb-product-card-img { aspect-ratio: 1; background: #f3f4f6; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.tb-product-card-img img { width: 100%; height: 100%; object-fit: cover; }
.tb-product-card-noimg { font-size: 2.5rem; color: #9ca3af; }
.tb-product-card-inner { padding: 1rem; flex: 1; }
.tb-product-title { font-weight: 700; font-size: 1rem; margin: 0 0 0.4rem; color: var(--tb-text, #111827); }
.tb-product-price { font-weight: 700; color: var(--tb-primary-text, #4f46e5); font-size: 0.95rem; }
.tb-product-grid-empty { padding: 2rem; text-align: center; background: #f9fafb; border-radius: 0.75rem; border: 1px dashed #d1d5db; }
.tb-product-grid-empty p { margin-bottom: 1rem; color: #6b7280; }
.tb-blog-card { display: flex; flex-direction: column; background: var(--tb-surface, #fff); border-radius: var(--tb-radius, 0.75rem); overflow: hidden; border: 1px solid #e5e7eb; text-decoration: none; color: inherit; box-shadow: var(--tb-shadow-sm); transition: box-shadow 0.2s, transform 0.15s; }
.tb-blog-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); transform: translateY(-2px); }
.tb-blog-card-img { aspect-ratio: 16/10; background: #f3f4f6; overflow: hidden; }
.tb-blog-card-img img { width: 100%; height: 100%; object-fit: cover; }
.tb-blog-card-noimg { display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #9ca3af; min-height: 120px; }
.tb-blog-card-body { padding: 1.25rem; flex: 1; }
.tb-blog-card-title { font-size: 1.05rem; font-weight: 700; margin: 0 0 0.4rem; line-height: 1.4; }
.tb-blog-card-meta { font-size: 0.8rem; color: var(--tb-text-muted, #4b5563); margin: 0 0 0.4rem; }
.tb-blog-card-excerpt { font-size: 0.88rem; color: #6b7280; margin: 0; line-height: 1.6; }

/* ── Editor canvas overlay styles ── */
.tb-canvas-block {
    position: relative;
    cursor: pointer;
    outline: 2px solid transparent;
    outline-offset: 0;
    transition: outline-color 0.12s ease;
    min-height: 16px;
}
.tb-canvas-block:hover {
    outline: 2px solid rgba(99, 102, 241, 0.55);
}
.tb-canvas-block.tb-selected {
    outline: 2px solid #4f46e5;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}
.tb-block-badge {
    display: none;
    position: absolute;
    top: 0;
    right: 0;
    z-index: 200;
    background: #4f46e5;
    color: #fff;
    font-size: 10px;
    font-family: 'Vazirmatn', sans-serif;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 0 0 0 5px;
    pointer-events: none;
    white-space: nowrap;
    line-height: 1.6;
}
.tb-canvas-block:hover .tb-block-badge,
.tb-canvas-block.tb-selected .tb-block-badge {
    display: block;
}

/* ── Drop zones (TB3-03) ── */
.tb-drop-zone {
    height: 0;
    margin: 0;
    transition: height 0.15s ease, opacity 0.15s ease;
    position: relative;
    overflow: visible;
    opacity: 0;
}
body.tb-drag-active .tb-drop-zone {
    height: 10px;
    opacity: 1;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 5px;
}
.tb-drop-zone.tb-drop-active {
    height: 10px !important;
    opacity: 1 !important;
    background: #4f46e5 !important;
    border-radius: 5px;
}
.tb-drop-zone.tb-drop-active::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 14px;
    height: 14px;
    background: #4f46e5;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #4f46e5;
}

/* ── Visibility toggles (TB3-05 advanced tab) ── */
@media (min-width: 1024px) { .tb-hide-desktop { display: none !important; } }
@media (min-width: 768px) and (max-width: 1023px) { .tb-hide-tablet { display: none !important; } }
@media (max-width: 767px) { .tb-hide-mobile { display: none !important; } }

/* ── Entrance animations (TB3-05) ── */
@keyframes tb-fade-in    { from { opacity: 0; } to { opacity: 1; } }
@keyframes tb-slide-up   { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tb-slide-right{ from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes tb-zoom-in    { from { opacity: 0; transform: scale(0.88); } to { opacity: 1; transform: scale(1); } }
@keyframes tb-bounce-in  { 0% { opacity:0; transform:scale(0.3); } 50% { opacity:1; transform:scale(1.05); } 70% { transform:scale(0.9); } 100% { transform:scale(1); } }
.tb-animate-fade-in     { animation: tb-fade-in     var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-slide-up    { animation: tb-slide-up    var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-slide-right { animation: tb-slide-right var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-zoom-in     { animation: tb-zoom-in     var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-bounce-in   { animation: tb-bounce-in   var(--tb-animate-dur, 700ms) ease both; }

/* ── Inline editing (TB3-04) ── */
[data-tb-editable][contenteditable="true"] {
    outline: 2px dashed #6366f1;
    outline-offset: 2px;
    border-radius: 3px;
    cursor: text;
    min-width: 20px;
    min-height: 1em;
    padding: 2px 4px;
    white-space: pre-wrap;
}

/* ── TB4: Flex Container ── */
.tb-flex-container { width: 100%; }
.tb-fc-empty {
    display: flex; align-items: center; justify-content: center;
    min-height: 80px; border: 2px dashed #c7d2fe; border-radius: 8px;
    color: #818cf8; font-size: 0.8rem; font-weight: 500;
    background: rgba(99,102,241,0.04); width: 100%;
}

/* ── TB4: Nested block highlight (selected child inside container) ── */
.tb-canvas-block[data-tb-container="1"] > .tb-canvas-block:hover {
    outline: 2px solid rgba(99,102,241,0.45);
}
.tb-canvas-block[data-tb-container="1"].tb-selected {
    outline: 2px solid #10b981;
    box-shadow: 0 0 0 4px rgba(16,185,129,0.12);
}

/* ── TB4: Container drop zone during drag ── */
.tb-canvas-block[data-tb-container="1"].tb-container-drag-over {
    outline: 2px dashed #6366f1 !important;
    background: rgba(99,102,241,0.04) !important;
}

/* ── Module placeholder (admin canvas, no live data) ── */
.tb-module-placeholder {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8effe 100%);
    border: 2px dashed #a5b4fc;
    border-radius: 0.75rem;
    padding: 2.5rem 2rem;
    text-align: center;
    color: #6366f1;
    font-size: 0.95rem;
    font-weight: 600;
}
.tb-module-placeholder .tb-module-icon { font-size: 2rem; margin-bottom: 0.5rem; }

{!! $css !!}
</style>
@foreach(($extAssets['css'] ?? []) as $__extHref)
<link rel="stylesheet" href="{{ $__extHref }}">
@endforeach
</head>
<body>

@if(empty($blocks))
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;text-align:center;color:#9ca3af;padding:2rem;">
    <div style="font-size:3.5rem;margin-bottom:1rem;opacity:0.4;">🧩</div>
    <p style="font-size:1rem;font-weight:600;margin:0 0 0.5rem;">صفحه هنوز بلوکی ندارد</p>
    <p style="font-size:0.85rem;margin:0;">از کتابخانه بلوک بکشید یا روی آن کلیک کنید</p>
</div>
@else

{{-- First drop zone (before first block) --}}
<div class="tb-drop-zone"
     data-before="{{ $blocks[0]['id'] ?? '__end__' }}"
     data-index="0"></div>

@foreach($blocks as $index => $block)
@php
    $bid = $block['id'] ?? '';
    $nextBlock = $blocks[$index + 1] ?? null;
@endphp
{{-- Rendered block (already wrapped in .tb-canvas-block by BlockRenderer editor_mode) --}}
{!! $renderedBlocks[$bid] ?? '' !!}

{{-- Drop zone after this block --}}
<div class="tb-drop-zone"
     data-before="{{ $nextBlock ? $nextBlock['id'] : '__end__' }}"
     data-index="{{ $index + 1 }}"></div>
@endforeach

@endif

@foreach(($extAssets['js'] ?? []) as $__extSrc)
<script src="{{ $__extSrc }}" defer></script>
@endforeach

<script>
(function () {
    'use strict';

    var state = {
        selectedBlockId: null,
        dragActive: false
    };

    function notifyParent(msg) {
        try { parent.postMessage(msg, '*'); } catch (_) {}
    }

    /* ── TB3-04: Inline editing ──────────────────────────────────── */
    var inlineState = { editing: false, propKey: null, inlineType: null, debounceTimer: null };

    function enableInlineEditing(editableEl) {
        if (inlineState.editing && inlineState.el === editableEl) return;
        disableInlineEditing();
        inlineState.editing   = true;
        inlineState.el        = editableEl;
        inlineState.propKey   = editableEl.getAttribute('data-tb-editable');
        inlineState.inlineType = editableEl.getAttribute('data-tb-inline-type') || 'text';
        editableEl.contentEditable = 'true';
        editableEl.setAttribute('spellcheck', 'false');
        // For plain text, prevent rich paste
        if (inlineState.inlineType === 'text') {
            editableEl.addEventListener('paste', onInlinePaste, { once: false });
        }
        editableEl.addEventListener('input',   onInlineInput);
        editableEl.addEventListener('blur',    onInlineBlur);
        editableEl.addEventListener('keydown', onInlineKeydown);
        // Place cursor at end
        var sel = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(editableEl);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function disableInlineEditing() {
        if (!inlineState.el) return;
        var el = inlineState.el;
        el.contentEditable = 'false';
        el.removeEventListener('input',   onInlineInput);
        el.removeEventListener('blur',    onInlineBlur);
        el.removeEventListener('keydown', onInlineKeydown);
        el.removeEventListener('paste',   onInlinePaste);
        inlineState.editing = false;
        inlineState.el      = null;
    }

    function onInlinePaste(e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    }

    function onInlineInput() {
        clearTimeout(inlineState.debounceTimer);
        inlineState.debounceTimer = setTimeout(function () {
            sendInlineChange(false);
        }, 250);
    }

    function onInlineBlur() {
        clearTimeout(inlineState.debounceTimer);
        sendInlineChange(true);
        disableInlineEditing();
    }

    function onInlineKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            disableInlineEditing();
        } else if (e.key === 'Enter' && inlineState.inlineType === 'text') {
            e.preventDefault();
            clearTimeout(inlineState.debounceTimer);
            sendInlineChange(true);
            disableInlineEditing();
        }
    }

    function sendInlineChange(isFinal) {
        if (!inlineState.el) return;
        var val = inlineState.inlineType === 'html'
            ? inlineState.el.innerHTML
            : inlineState.el.textContent;
        notifyParent({ action: 'inlinePropChange', propKey: inlineState.propKey, value: val, final: !!isFinal });
    }

    /* ── Block click → select (or inline edit on double-click) ─── */
    document.addEventListener('dblclick', function (e) {
        var editableEl = e.target.closest('[data-tb-editable]');
        if (editableEl) {
            e.stopPropagation();
            enableInlineEditing(editableEl);
        }
    }, true);

    document.addEventListener('click', function (e) {
        // If clicking on active contenteditable, don't re-select block
        if (inlineState.editing && e.target.closest('[data-tb-editable][contenteditable="true"]')) return;
        // If clicking outside editable while editing, blur it
        if (inlineState.editing && !e.target.closest('[data-tb-editable]')) {
            disableInlineEditing();
        }
        var blockEl = e.target.closest('[data-tb-block-id]');
        if (blockEl) {
            e.stopPropagation();
            selectBlock(blockEl.getAttribute('data-tb-block-id'));
            notifyParent({ action: 'blockSelected', blockId: state.selectedBlockId });
        }
    }, true);

    /* ── Selection ────────────────────────────────────────────── */
    function selectBlock(blockId) {
        var prev = document.querySelector('.tb-canvas-block.tb-selected');
        if (prev) prev.classList.remove('tb-selected');
        state.selectedBlockId = blockId;
        if (blockId) {
            var el = document.querySelector('[data-tb-block-id="' + blockId + '"]');
            if (el) {
                el.classList.add('tb-selected');
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }

    /* ── Block HTML update (live prop edit) ───────────────────── */
    function updateBlockHtml(blockId, html) {
        disableInlineEditing();
        var el = document.querySelector('[data-tb-block-id="' + blockId + '"]');
        if (!el) return;
        var badge = el.querySelector('.tb-block-badge');
        el.innerHTML = html;
        if (badge) el.insertBefore(badge, el.firstChild);
        if (state.selectedBlockId === blockId) el.classList.add('tb-selected');
    }

    /* ── Drop zone helpers (TB3-03 + TB4) ────────────────────── */
    function setDropTarget(beforeId) {
        document.querySelectorAll('.tb-drop-zone').forEach(function (el) {
            el.classList.toggle('tb-drop-active', el.getAttribute('data-before') === beforeId);
        });
    }

    // TB4: highlight a container as the drag target
    var currentContainerTarget = null;
    function setContainerTarget(containerId) {
        if (currentContainerTarget) {
            currentContainerTarget.classList.remove('tb-container-drag-over');
            currentContainerTarget = null;
        }
        if (containerId) {
            var el = document.querySelector('[data-tb-block-id="' + containerId + '"][data-tb-container="1"]');
            if (el) { el.classList.add('tb-container-drag-over'); currentContainerTarget = el; }
        }
    }

    // TB4: only searches top-level blocks (direct children of body)
    function getTopLevelBlocks() {
        return Array.from(document.body.querySelectorAll(':scope > [data-tb-block-id], :scope > .tb-style-wrap > [data-tb-block-id]'));
    }

    function getDropIndexAtY(clientY) {
        var blocks = getTopLevelBlocks();
        var result = { index: blocks.length, beforeId: '__end__', containerId: null };
        for (var i = 0; i < blocks.length; i++) {
            var rect = blocks[i].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                result = { index: i, beforeId: blocks[i].getAttribute('data-tb-block-id'), containerId: null };
                break;
            }
        }
        return result;
    }

    // TB4: find the innermost container block under the cursor
    function getContainerAtPoint(x, y) {
        var els = document.elementsFromPoint(x, y);
        for (var i = 0; i < els.length; i++) {
            var el = els[i].closest('[data-tb-container="1"]');
            if (el) return el.getAttribute('data-tb-block-id');
        }
        return null;
    }

    /* ── Height notification ──────────────────────────────────── */
    function sendHeight() {
        notifyParent({ action: 'iframeHeight', height: Math.max(200, document.body.scrollHeight) });
    }

    /* ── postMessage bridge (parent → iframe) ─────────────────── */
    window.addEventListener('message', function (e) {
        var d = e.data;
        if (!d || !d.action) return;

        switch (d.action) {
            case 'select':
                selectBlock(d.blockId);
                break;
            case 'updateBlock':
                updateBlockHtml(d.blockId, d.html || '');
                sendHeight();
                break;
            case 'showDropZones':
                document.body.classList.toggle('tb-drag-active', !!d.visible);
                if (!d.visible) { setDropTarget(null); setContainerTarget(null); }
                break;
            case 'setDropTarget':
                setDropTarget(d.beforeId || null);
                break;
            // TB4: highlight container for drag-into
            case 'setContainerTarget':
                setContainerTarget(d.containerId || null);
                break;
            case 'getDropIndex':
                var clientY = d.clientY || 0;
                var clientX = d.clientX || 0;
                var res = getDropIndexAtY(clientY);
                // TB4: also check if hovering a container
                var cid = getContainerAtPoint(clientX, clientY);
                notifyParent({ action: 'dropIndexResult', index: res.index, beforeId: res.beforeId, containerId: cid });
                break;
        }
    });

    /* ── Signal ready + initial height ───────────────────────── */
    notifyParent({ action: 'ready' });
    if (document.readyState === 'complete') {
        sendHeight();
    } else {
        window.addEventListener('load', sendHeight);
    }
    // Also watch for DOM resizes (images loading, etc.)
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(sendHeight).observe(document.body);
    }
}());
</script>
</body>
</html>
