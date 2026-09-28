        {{-- Phase 2h (ADR-029): the shared structural/responsive/marquee
             CSS contract, identical to what customer-site's runtime
             includes — this used to be an independently maintained copy
             that silently drifted (Phase 2g found a missing grid
             breakpoint, a missing marquee clip, and an inconsistent
             muted-text color here). Do not re-add any of these rules
             locally; edit the shared package instead. --}}
        @include('theme-runtime::css.contract')
        body { font-family: 'Vazirmatn', sans-serif; margin: 0; padding: 0; direction: rtl; background: #ffffff; color: #1f2937; }
        {{-- Phase 2k: was fully hardcoded (#4f46e5/#fff, no var() at all) —
             a real, previously-undiscovered parity gap: the SaaS Quality
             Gate's own browser contrast check always rendered this same
             safe default color regardless of a Theme's actual primary
             color, so it could never detect the real WCAG failures 17
             canonical themes had on customer-site's runtime (which WAS
             token-bound). Token-bound now, consuming the same
             ThemeTokenNormalizer-derived --tb-on-primary/--tb-primary-text
             both hosts share. --}}
        .tb-hero          { min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 5rem 1.5rem; background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-hero h1       { font-size: clamp(1.75rem, 4vw, var(--tb-hero-title-size, 2.5rem)); font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
        .tb-hero p        { font-size: 1.1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-hero .tb-btn  { display: inline-flex; align-items: center; padding: 0.75rem 2rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: inherit; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; }
        .tb-cta           { padding: 4rem 1.5rem; text-align: center; background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-cta h2        { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.75rem; }
        .tb-cta p         { font-size: 1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-cta a         { display: inline-flex; padding: 0.65rem 1.75rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: inherit; border-radius: 0.5rem; font-weight: 600; text-decoration: none; }
        .tb-heading       { line-height: 1.4; margin-bottom: 1rem; font-weight: 700; }
        h1.tb-heading { font-size: clamp(1.75rem, 3vw, var(--tb-h1-size, 2.25rem)); }
        h2.tb-heading { font-size: clamp(1.5rem, 2.5vw, var(--tb-h2-size, 1.875rem)); }
        h3.tb-heading { font-size: clamp(1.25rem, 2vw, var(--tb-h3-size, 1.25rem)); }
        h4.tb-heading { font-size: var(--tb-h4-size, 1.125rem); }
        h5.tb-heading { font-size: var(--tb-h5-size, 1.125rem); }
        h6.tb-heading { font-size: var(--tb-h6-size, 1rem); }
        .tb-text          { line-height: 1.85; color: #374151; margin-bottom: 1rem; }
        .tb-button        { display: inline-flex; align-items: center; padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
        {{-- Commercial Productization fase-3 (visual identity audit): a
             real, confirmed SaaS-preview/customer-runtime parity gap — the
             generic .tb-btn base and .tb-btn-secondary were NEVER defined
             here (only .tb-btn-primary), so every block using a secondary
             CTA (hero_split, pricing_cards, testimonials, ...) rendered as
             a bare unstyled link in this preview while looking correct on
             customer-site. Mirrors customer-site's layout.blade.php values. --}}
        .tb-btn           { display: inline-block; padding: 0.75rem 1.5rem; border-radius: var(--tb-radius, 0.75rem); text-decoration: none; font-weight: 600; font-size: var(--tb-button-size, 1rem); transition: opacity 0.2s, transform 0.15s; }
        .tb-btn:hover     { opacity: 0.95; }
        .tb-btn-primary   { background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-btn-secondary { background: transparent; color: var(--tb-primary, #4f46e5); border: 2px solid currentColor; }
        .tb-bg-primary    { background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-bg-dark       { background: #1e293b; color: #fff; }
        .tb-bg-light      { background: #f8fafc; }
        .tb-bg-gray       { background: #f1f5f9; }

        {{-- Commercial Productization fase-3: the 7 canonical block types
             from ADR-026 (nav_header, nav_footer, hero_trust, about_section,
             service_cards, project_cards, contact_info) plus stats_row had
             ZERO CSS in this preview — real, confirmed parity gap found
             during the visual-identity audit (these render fully styled on
             customer-site via layout.blade.php, whose exact values are
             mirrored verbatim here). --}}
        .tb-features-section-header { text-align: center; margin-bottom: 2.5rem; }
        .tb-features-section-title { font-size: clamp(1.375rem, 2.5vw, var(--tb-section-title-size, 1.875rem)); font-weight: 800; color: var(--tb-text, #111827); margin: 0 0 0.5rem; }
        .tb-features-section-sub { color: var(--tb-text-muted, #6b7280); font-size: 1rem; margin: 0; }
        .tb-stats-grid { display: flex; flex-wrap: wrap; gap: 1.5rem; }
        .tb-stat-item { flex: 1 1 8rem; text-align: center; }
        .tb-stat-value { display: block; font-size: clamp(1.875rem, 3.5vw, var(--tb-big-number-size, 2.75rem)); font-weight: 800; color: var(--tb-primary-text, #4f46e5); }
        .tb-stat-suffix { font-size: 1.25rem; }
        .tb-stat-label { display: block; font-size: 0.875rem; color: var(--tb-text-muted, #6b7280); margin-top: 0.25rem; }
        .tb-service-card { display: block; background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; text-decoration: none; color: inherit; transition: box-shadow 0.2s; }
        .tb-service-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .tb-service-icon { font-size: 1.75rem; margin-bottom: 0.75rem; }
        .tb-service-title { font-size: 1rem; font-weight: 700; color: var(--tb-text, #111827); margin: 0 0 0.4rem; }
        .tb-service-text { font-size: 0.875rem; color: var(--tb-text-muted, #6b7280); line-height: 1.6; margin: 0; }
        .tb-services-grid, .tb-project-cards-grid { margin: 0; }
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

        {{-- Commercial Productization fase-3 (visual identity audit, round 2):
             a systematic class-by-class diff against the REAL authoritative
             customer runtime layout (resources/views/layouts/canvas-theme.blade.php
             — not themes/default/layout.blade.php, a separate legacy layout
             this preview was previously, incorrectly compared against) found
             many more block types with zero CSS here: features_grid,
             process_steps, team_cards, skills_bars, testimonials_slider
             (current grid-based markup), trust_badges, pricing_cards,
             newsletter_form, bento_grid, services_cards (style-* variant),
             contact_split, row/column layout primitives, and the
             secondary/surface/background/text bg helpers. Values below are
             copied verbatim from that authoritative file. --}}
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
        .tb-plan-price { font-size: clamp(2rem, 4vw, calc(var(--tb-big-number-size,3.5rem) * 0.8)); font-weight: 800; color: var(--tb-text, #0f172a); line-height: 1; }
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

        {{-- TASK 3/3 Phase 6.5 — parity fix. Per-block VISUAL CSS is
             deliberately host-owned per ADR-029 (each host keeps its own
             copy), but this preview's copy had silently fallen far behind
             customer-site's: hero_split/hero_gradient/nav_header/nav_footer/
             promo_banner/countdown/wishlist/loyalty/installment_badge had
             ZERO rules here. Because Redis was down in this environment,
             ThemeCommercialQualityGate's browser-backed ResponsiveReadinessCheck
             was silently reporting "unverified" instead of actually running —
             once Redis was restored the check ran for real and caught this
             preview rendering catastrophic horizontal overflow (232px tablet,
             610px mobile) on the Universal Theme's own home page, purely
             because these blocks rendered with no styling at all. Mirrors
             customer-site's resources/views/layouts/canvas-theme.blade.php
             rules verbatim so both hosts' previews match. --}}
        .tb-hero-split { display:flex; align-items:center; gap:3rem; flex-wrap:wrap; }
        .tb-hero-split-text { flex:1 1 300px; }
        .tb-hero-split-visual { flex:1 1 300px; text-align:center; }
        .tb-hero-split-badge { display:inline-block; background:rgba(99,102,241,0.1); color:var(--tb-primary,#6366f1); font-size:0.82rem; font-weight:700; padding:0.3rem 0.9rem; border-radius:999px; border:1px solid rgba(99,102,241,0.25); margin-bottom:1rem; letter-spacing:0.03em; }
        .tb-hero-split-title { font-size:clamp(1.75rem,4vw,var(--tb-hero-title-size,3.25rem)); font-weight:800; line-height:1.1; margin:0 0 1rem; letter-spacing:-0.02em; color:var(--tb-text,#0f172a); }
        .tb-hero-split-sub { font-size:1.1rem; color:var(--tb-text-muted,#4b5563); line-height:1.75; margin:0 0 1.75rem; max-width:42ch; }
        .tb-hero-split-actions { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center; }
        .tb-hero-split-img { width:100%; max-width:520px; height:380px; border-radius:1.25rem; object-fit:cover; box-shadow:0 20px 60px rgba(0,0,0,0.15); }
        .tb-hero-split-img-placeholder { width:100%; max-width:520px; height:380px; border-radius:1.25rem; background:linear-gradient(135deg,var(--tb-primary,#6366f1),var(--tb-secondary,#0ea5e9)); display:flex; align-items:center; justify-content:center; font-size:6rem; box-shadow:0 20px 60px rgba(99,102,241,0.25); }
        @media(max-width:767px) { .tb-hero-split{flex-direction:column-reverse;gap:1.5rem;} .tb-hero-split-title{font-size:1.875rem;} .tb-hero-split-img,.tb-hero-split-img-placeholder{max-width:100%;height:220px;} }

        .tb-hero-gradient { text-align:center; padding:5.5rem 1.5rem; position:relative; overflow:hidden; }
        .tb-hero-gradient-inner { max-width:58rem; margin:0 auto; position:relative; z-index:1; }
        .tb-hero-gradient-badge { display:inline-block; background:rgba(255,255,255,0.18); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.3); font-size:0.82rem; font-weight:700; padding:0.3rem 1.1rem; border-radius:999px; margin-bottom:1.25rem; }
        .tb-hero-gradient-title { font-size:clamp(2rem,5.5vw,calc(var(--tb-hero-title-size,4.5rem) * 1.15)); font-weight:800; line-height:1.05; margin:0 0 1.25rem; letter-spacing:-0.03em; }
        .tb-hero-gradient-sub { font-size:1.15rem; opacity:0.9; line-height:1.7; margin:0 auto; max-width:50ch; }
        @media(max-width:600px) { .tb-hero-gradient{padding:3.5rem 1rem;} .tb-hero-gradient-title{font-size:2rem;} }

        .tb-nav-header-inner { display: flex; align-items: center; gap: 1.5rem; min-height: 4.25rem; flex-wrap: wrap; padding: 0.5rem 0; }
        .tb-nav-header-logo { font-size: 1.35rem; font-weight: 800; text-decoration: none; color: inherit; letter-spacing: -0.01em; }
        .tb-nav-header-links { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1.5rem; margin-inline-start: auto; }
        .tb-nav-header-link { text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; opacity: 0.85; padding: 0.4rem 0; position: relative; transition: opacity 0.15s; }
        .tb-nav-header-link:hover { opacity: 1; }
        .tb-nav-header-link::after { content: ''; position: absolute; inset-inline: 0; bottom: -2px; height: 2px; background: currentColor; transform: scaleX(0); transition: transform 0.2s; transform-origin: center; }
        .tb-nav-header-link:hover::after { transform: scaleX(1); }
        {{-- TASK 3/3 Phase 6.5B — CSS parity only; nav_source=menu has
             nothing to render here by design. A Menu belongs to a real
             customer site (customer-site's own Menu/MenuItem models), and
             Master Theme authoring in this admin has no live customer site
             to preview against — $context['menu_html'] is correctly never
             populated on this side, so nav_source=menu previews as an
             intentionally empty nav until the theme is actually installed
             on a real site. --}}
        .tb-nav-header-links > a { text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; opacity: 0.85; padding: 0.4rem 0; }
        .tb-nav-header-links > a:hover { opacity: 1; }
        .tb-nav-header-actions { display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; }
        .tb-nav-header-search { position: relative; }
        .tb-nav-header-search-input { padding: 0.5rem 2.25rem 0.5rem 0.9rem; border: 1px solid rgba(0,0,0,0.12); border-radius: 999px; font-family: inherit; font-size: 0.85rem; background: rgba(0,0,0,0.03); color: inherit; width: 11rem; max-width: 40vw; }
        .tb-nav-header-search-input::placeholder { color: currentColor; opacity: 0.55; }
        .tb-nav-header-phone { text-decoration: none; color: inherit; font-weight: 700; font-size: 0.9rem; opacity: 0.9; white-space: nowrap; }
        .tb-nav-header-icon-link { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 999px; background: rgba(0,0,0,0.05); text-decoration: none; font-size: 1.05rem; transition: background 0.15s; }
        .tb-nav-header-icon-link:hover { background: rgba(0,0,0,0.1); }
        .tb-nav-header-cta { padding: 0.55rem 1.25rem; font-size: 0.9rem; white-space: nowrap; }
        @media (max-width: 1023px) { .tb-nav-header-search-input { width: 8rem; } }

        .tb-nav-footer .tb-container { padding-top: 3.5rem; padding-bottom: 2rem; }
        .tb-nav-footer-brand { margin-bottom: 2rem; }
        .tb-nav-footer-logo { font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem; }
        .tb-nav-footer-tagline { font-size: 0.9rem; opacity: 0.75; line-height: 1.7; margin: 0; max-width: 32rem; }
        .tb-nav-footer-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 2rem; margin-bottom: 2.5rem; }
        .tb-nav-footer-col-title { font-size: 0.85rem; font-weight: 700; margin: 0 0 1rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.04em; }
        .tb-nav-footer-links { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.7rem; }
        .tb-nav-footer-links a { text-decoration: none; color: inherit; opacity: 0.8; font-size: 0.9rem; transition: opacity 0.15s; }
        .tb-nav-footer-links a:hover { opacity: 1; }
        .tb-nav-footer-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-top: 1.75rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .tb-nav-footer-copyright { margin: 0; font-size: 0.85rem; opacity: 0.65; }
        .tb-nav-footer-legal-link { text-decoration: none; color: inherit; opacity: 0.7; font-size: 0.85rem; margin-inline-start: 1.25rem; }
        .tb-nav-footer-legal-link:hover { opacity: 1; }
        @media (max-width: 640px) {
            .tb-nav-footer .tb-container { padding-top: 2.5rem; }
            .tb-nav-footer-bottom { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .tb-nav-footer-legal-link { margin-inline-start: 0; margin-inline-end: 1rem; }
        }

        .tb-wishlist-link, .tb-loyalty-points-link { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.9rem; border-radius: 999px; background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; color: var(--tb-text, #111827) !important; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: border-color 0.15s, background 0.15s; }
        .tb-wishlist-link:hover, .tb-loyalty-points-link:hover { border-color: var(--tb-primary, #4f46e5); background: var(--tb-surface, #f9fafb); }
        .tb-wishlist-link-icon, .tb-loyalty-points-link-icon { padding: 0.5rem; width: 2.5rem; height: 2.5rem; justify-content: center; }
        .tb-installment-badge { display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .tb-installment-badge-compact { padding: 0.6rem 1rem; border-radius: 0.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46 !important; font-weight: 700; font-size: 0.85rem; }
        .tb-installment-badge-icon { font-size: 1rem; }
        .tb-installment-badge.tb-empty-state { display: block; }
        .tb-installment-badge-full { display: block; padding: 1.25rem; border-radius: var(--tb-radius, 0.75rem); background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; }
        .tb-installment-plans-list { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1rem; }
        .tb-installment-plan-row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.6rem 0.75rem; border-radius: 0.5rem; background: var(--tb-background, #f9fafb); font-size: 0.85rem; }
        .tb-installment-plan-name { font-weight: 700; }
        .tb-installment-plan-detail { color: var(--tb-text-muted, #4b5563); }

        .tb-promo-banner { text-align:center; padding:0.85rem 1.25rem; }
        .tb-promo-banner__link { display:inline-block; font-weight:700; font-size:0.95rem; text-decoration:none; letter-spacing:0.01em; }
        .tb-promo-info    { background:var(--tb-surface,#f9fafb); border-bottom:1px solid #e5e7eb; }
        .tb-promo-info .tb-promo-banner__link    { color:var(--tb-text,#111827); }
        .tb-promo-sale     { background:linear-gradient(90deg,var(--tb-primary,#4f46e5),var(--tb-secondary,#6366f1)); }
        .tb-promo-sale .tb-promo-banner__link     { color:var(--tb-on-primary,#fff); }
        .tb-promo-urgent   { background:#dc2626; }
        .tb-promo-urgent .tb-promo-banner__link   { color:#fff; }

        .tb-countdown { text-align:center; padding:2.5rem 1.5rem; background:var(--tb-surface,#f9fafb); border-radius:var(--tb-radius,0.75rem); border:1px solid #e5e7eb; }
        .tb-countdown__label { display:block; font-weight:700; font-size:1.1rem; margin-bottom:1rem; color:var(--tb-text,#111827); }
        .tb-countdown__hint { display:inline-block; font-size:1.5rem; font-weight:800; font-variant-numeric:tabular-nums; color:var(--tb-primary-text,#4f46e5); letter-spacing:0.02em; }
        @media(max-width:600px) { .tb-countdown__hint { font-size:1.15rem; } }

        .tb-team-avatar { width:84px; height:84px; border-radius:50%; object-fit:cover; margin:1.75rem auto 1rem; border:3px solid var(--tb-primary,#4f46e5); display:block; }

        {{-- gallery block: had ZERO CSS on either host — see the matching
             comment in customer-site's canvas-theme.blade.php. This is what
             ThemeCommercialQualityGate's browser-backed overflow check was
             really catching. --}}
        .tb-gallery-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.25rem; }
        .tb-gallery-cell { border-radius:0.75rem; overflow:hidden; }
        .tb-gallery-img { display:block; width:100%; height:16rem; object-fit:cover; }
        @media(max-width:480px) { .tb-gallery-grid { grid-template-columns:1fr; } .tb-gallery-img { height:12rem; } }

