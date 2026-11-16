<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\Support\IconLibrary;
use SaaSPlatform\ThemeRuntime\Support\PersianNumber;

/**
 * Solar Industry Theme-Scoped Widget Renderer Trait.
 *
 * Renders the `solar-energy-cycle` theme section exclusively for the `solar-industry` template
 * (not the licensed `solar-motion-graphic` library motion; see SolarIndustryBlockTypes).
 * Features:
 *   - Dynamic SVG/CSS energy particle animation (Photons -> PV Cells -> Inverter -> Battery/Grid).
 *   - Lottie JSON vector animation loader and transparent WebM video playback.
 *   - 100% Granular Canvas Inspector controls for animation timing, speed, loop, and dimensions.
 *   - Strict Persian RTL layout and theme typography inheritance via `var(--tb-font-family, inherit)`.
 *   - Zero layout shift (CLS: 0) and responsive container queries.
 */
trait SolarIndustryBlockRendererTrait
{
    /** Escape HTML text safely */
    private function solarEscape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Format number with Persian digits if enabled */
    private function solarNum(mixed $value, bool $persianDigits = true): string
    {
        $str = (string) ($value ?? '');
        if (!$persianDigits) {
            return $this->solarEscape($str);
        }
        return $this->solarEscape(PersianNumber::digits($str));
    }

    /** Sanitize and format URL */
    private function solarUrl(mixed $value): string
    {
        $url = trim((string) ($this->linkValue($value) ?? ''));
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || preg_match('/^(https?:|mailto:|tel:)/i', $url) === 1
            || (preg_match('#^[a-zA-Z0-9][a-zA-Z0-9._/-]*$#', $url) === 1 && !str_contains($url, '..'))) {
            return $this->solarEscape($url === '' ? '#' : $url);
        }
        return '#';
    }

    /** Render the theme-scoped solar-energy-cycle block (dispatched as render + Str::studly(type)). */
    protected function renderSolarEnergyCycle(array $props, string $inner, string $id): string
    {
        $mode = (string) ($props['mode'] ?? 'svg_particle');
        if (!in_array($mode, ['svg_particle', 'lottie', 'webm_video'], true)) {
            $mode = 'svg_particle';
        }

        // Persian text layers
        $eyebrow = (string) ($props['eyebrow'] ?? 'فناوری پیشرفته تبدیل فوتوالکتریک');
        $title = (string) ($props['title'] ?? 'چرخه هوشمند تبدیل و ذخیره‌سازی انرژی پاک');
        $subtitle = (string) ($props['subtitle'] ?? '');
        $badgeLabel = (string) ($props['badge_label'] ?? 'راندمان فعال ۲۳.۵٪');
        $badgeIcon = (string) ($props['badge_icon'] ?? 'zap');
        $showCta = (bool) ($props['show_cta'] ?? true);
        $ctaLabel = (string) ($props['cta_label'] ?? 'محاسبه تولید و ظرفیت خورشیدی');
<<<<<<< HEAD
        // A `link` field may arrive structured ({url, ...}); read it through the
        // shared link reader, never a string cast.
        $ctaUrl = $this->linkValue($props['cta_url'] ?? '') ?: '/page/solar_calculator';
=======
        $ctaUrl = (string) ($props['cta_url'] ?? '/page/solar_calculator');
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)

        // Dynamic Counters
        $showCounters = (bool) ($props['show_counters'] ?? true);
        $persianDigits = (bool) ($props['persian_digits'] ?? true);
        $powerVal = (string) ($props['counter_power_value'] ?? '۸.۴');
        $powerUnit = (string) ($props['counter_power_unit'] ?? 'کیلووات');
        $powerLabel = (string) ($props['counter_power_label'] ?? 'تولید برق هم‌اکنون');

        $effVal = (string) ($props['counter_efficiency_value'] ?? '۲۳.۵');
        $effUnit = (string) ($props['counter_efficiency_unit'] ?? 'درصد');
        $effLabel = (string) ($props['counter_efficiency_label'] ?? 'بازدهی فتوولتائیک');

        $co2Val = (string) ($props['counter_co2_value'] ?? '۴.۲');
        $co2Unit = (string) ($props['counter_co2_unit'] ?? 'تن در سال');
        $co2Label = (string) ($props['counter_co2_label'] ?? 'جلوگیری از انتشار CO₂');

        $dailyVal = (string) ($props['counter_daily_value'] ?? '۳۸.۶');
        $dailyUnit = (string) ($props['counter_daily_unit'] ?? 'کیلووات‌ساعت');
        $dailyLabel = (string) ($props['counter_daily_label'] ?? 'تولید تجمعی امروز');

        // Steps
        $showSteps = (bool) ($props['show_steps'] ?? true);
        $steps = is_array($props['steps'] ?? null) ? $props['steps'] : [];

        // Animation timings & loops
        $speed = (string) ($props['animation_speed'] ?? '1x');
        $speedMultiplier = match ($speed) {
            '0.5x' => 2.0,
            '1.5x' => 0.66,
            '2x' => 0.5,
            default => 1.0,
        };
        $baseDuration = is_numeric($props['animation_duration'] ?? null) ? (float) $props['animation_duration'] : 4.0;
        if ($baseDuration <= 0.5) $baseDuration = 4.0;
        $activeDuration = round($baseDuration * $speedMultiplier, 2);

        $loop = (string) ($props['loop_behavior'] ?? 'infinite');
        $iterationCount = match ($loop) {
            'once' => '1',
            'alternate' => 'infinite',
            default => 'infinite',
        };
        $animationDirection = ($loop === 'alternate') ? 'alternate' : 'normal';

        $pauseOnHover = (bool) ($props['pause_on_hover'] ?? true);
        $autoplay = (bool) ($props['autoplay'] ?? true);

        // Dimensions & Container Style
        $heightKey = (string) ($props['container_height'] ?? 'regular');
        $heightCss = match ($heightKey) {
            'compact' => '440px',
            'tall' => '680px',
            'auto' => 'auto',
            default => '560px',
        };

        $widthKey = (string) ($props['max_width'] ?? 'contained');
        $maxWidthCss = match ($widthKey) {
            'narrow' => '960px',
            'full' => '100%',
            default => '1200px',
        };

        $scale = (string) ($props['scale'] ?? '100%');
        $aspectRatio = (string) ($props['aspect_ratio'] ?? '16/9');
        $spacingKey = (string) ($props['section_spacing'] ?? 'regular');
        $spacingClass = match ($spacingKey) {
            'none' => 'py-0',
            'compact' => 'py-6 md:py-8',
            'large' => 'py-16 md:py-24',
            default => 'py-10 md:py-14',
        };

        // Color Theme & Tokens
        $colorTheme = (string) ($props['color_theme'] ?? 'dark-industrial');
        $glowEffect = (bool) ($props['glow_effect'] ?? true);

        [$accentColor, $panelColor, $particleColor, $glowFilter] = match ($colorTheme) {
            'solar-gold' => ['#f59e0b', '#0f172a', '#fbbf24', 'drop-shadow(0 0 12px rgba(245, 158, 11, 0.45))'],
            'eco-emerald' => ['#10b981', '#064e3b', '#34d399', 'drop-shadow(0 0 12px rgba(16, 185, 129, 0.45))'],
            'deep-cyan' => ['#06b6d4', '#082f49', '#38bdf8', 'drop-shadow(0 0 12px rgba(6, 182, 212, 0.45))'],
            'custom' => [
                (string) ($props['custom_accent_color'] ?? '#f59e0b'),
                (string) ($props['custom_panel_color'] ?? '#0f172a'),
                (string) ($props['custom_particle_color'] ?? '#38bdf8'),
                $glowEffect ? 'drop-shadow(0 0 12px rgba(245, 158, 11, 0.45))' : 'none',
            ],
            default => ['#f59e0b', '#0b1120', '#38bdf8', 'drop-shadow(0 0 16px rgba(245, 158, 11, 0.35))'],
        };

        if (!$glowEffect) {
            $glowFilter = 'none';
        }

        // Render Graphics Area
        $graphicHtml = match ($mode) {
            'lottie' => $this->renderSolarLottieCanvas($props, $id, $activeDuration, $autoplay, $loop),
            'webm_video' => $this->renderSolarWebmVideo($props, $id, $autoplay, $loop),
            default => $this->renderSolarSvgParticleCanvas($props, $id, $activeDuration, $iterationCount, $animationDirection, $accentColor, $panelColor, $particleColor, $glowEffect),
        };

        // Render Steps List
        $stepsHtml = '';
        if ($showSteps && !empty($steps)) {
            $stepsHtml = $this->renderSolarStepsGrid($steps, $persianDigits);
        }

        // Render Live Counters Bar
        $countersHtml = '';
        if ($showCounters) {
            $countersHtml = $this->renderSolarCountersBar([
                ['val' => $powerVal, 'unit' => $powerUnit, 'label' => $powerLabel, 'icon' => 'zap', 'color' => 'text-amber-400'],
                ['val' => $effVal, 'unit' => $effUnit, 'label' => $effLabel, 'icon' => 'sun', 'color' => 'text-emerald-400'],
                ['val' => $dailyVal, 'unit' => $dailyUnit, 'label' => $dailyLabel, 'icon' => 'battery', 'color' => 'text-cyan-400'],
                ['val' => $co2Val, 'unit' => $co2Unit, 'label' => $co2Label, 'icon' => 'shield', 'color' => 'text-teal-400'],
            ], $persianDigits);
        }

        // CTA Button
        $ctaHtml = '';
        if ($showCta && $ctaLabel !== '') {
            $ctaHtml = '<div class="mt-8 flex justify-center">
                <a href="' . $this->solarUrl($ctaUrl) . '" class="smg-cta-btn inline-flex items-center gap-2.5 px-6 py-3.5 rounded-xl font-bold text-sm tracking-wide text-slate-900 transition-all duration-300 shadow-lg hover:shadow-amber-500/25 hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--smg-accent, #f59e0b) 0%, #fbbf24 100%);">
                    <span>' . $this->solarEscape($ctaLabel) . '</span>
                    <svg class="w-4 h-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>';
        }

        // Output root container
        return <<<HTML
<section id="tb-{$id}" class="smg-widget-root {$spacingClass} relative overflow-hidden" dir="rtl" role="region" aria-label="{$this->solarEscape($title)}"
    style="
        --smg-accent: {$accentColor};
        --smg-panel: {$panelColor};
        --smg-particle: {$particleColor};
        --smg-duration: {$activeDuration}s;
        --smg-iterations: {$iterationCount};
        --smg-direction: {$animationDirection};
        --smg-font: var(--tb-font-family, 'Vazirmatn', inherit);
        font-family: var(--smg-font);
    "
>
    <!-- Scoped Style Block -->
    {$this->renderSolarStyles($pauseOnHover, $glowEffect)}

    <div class="smg-container mx-auto px-4 md:px-6 relative z-10" style="max-width: {$maxWidthCss};">
        <!-- Header Text Area -->
        <div class="smg-header text-center mb-8 md:mb-12 max-w-3xl mx-auto">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold mb-4 smg-badge border border-amber-500/30 bg-amber-500/10 text-amber-300">
                <span class="smg-badge-pulse inline-block w-2 h-2 rounded-full bg-amber-400"></span>
                <span>{$this->solarEscape($eyebrow)}</span>
                <span class="opacity-40">|</span>
                <span class="inline-flex items-center gap-1 font-mono text-[11px] text-amber-200">
                    {$this->solarIcon($badgeIcon)}
                    {$this->solarEscape($badgeLabel)}
                </span>
            </div>
            <h2 class="text-2xl md:text-4xl font-extrabold text-white tracking-tight leading-tight mb-4 smg-title" style="font-family: var(--smg-font);">
                {$this->solarEscape($title)}
            </h2>
            <p class="text-slate-300 text-sm md:text-base leading-relaxed max-w-2xl mx-auto smg-subtitle" style="font-family: var(--smg-font);">
                {$this->solarEscape($subtitle)}
            </p>
        </div>

        <!-- Central Energy Cycle Canvas Frame -->
        <div class="smg-canvas-frame relative rounded-2xl md:rounded-3xl border border-slate-700/60 bg-slate-900/80 backdrop-blur-xl p-4 md:p-8 shadow-2xl overflow-hidden"
            style="min-height: {$heightCss};"
        >
            <div class="smg-canvas-inner relative w-full h-full flex items-center justify-center" style="transform: scale({$scale}); transform-origin: center center;">
                {$graphicHtml}
            </div>
        </div>

        <!-- Live Performance Counters Bar -->
        {$countersHtml}

        <!-- Energy Conversion Stages / Flow Tooltips -->
        {$stepsHtml}

        <!-- Action CTA -->
        {$ctaHtml}
    </div>
</section>
HTML;
    }

    /**
     * Dynamic SVG Photovoltaic Conversion Graphic with Pure Hardware-Accelerated CSS Particle Animations.
     * Illustrates:
     *   1. Sun radiation with pulsating light corona and directional photon particles.
     *   2. Photovoltaic panel array converting photons into DC charge carriers.
     *   3. Conduit lines transmitting energy to MPPT solar inverter.
     *   4. High-efficiency inverter modulating AC sinusoidal waves.
     *   5. Split energy distribution into storage lithium battery and smart consumer grid.
     */
    private function renderSolarSvgParticleCanvas(
        array $props,
        string $id,
        float $duration,
        string $iterationCount,
        string $direction,
        string $accentColor,
        string $panelColor,
        string $particleColor,
        bool $glow
    ): string {
        $filterAttr = $glow ? 'filter="url(#smg-glow-' . $id . ')"' : '';

        return <<<SVG
<div class="smg-svg-wrapper w-full h-full flex items-center justify-center relative select-none">
    <svg class="smg-motion-svg w-full h-auto max-h-[520px]" viewBox="0 0 1200 620" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
        <defs>
            <!-- Glow Filters -->
            <filter id="smg-glow-{$id}" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur in="SourceGraphic" stdDeviation="6" result="blur" />
                <feMerge>
                    <feMergeNode in="blur" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>
            <filter id="smg-sun-glow-{$id}" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur in="SourceGraphic" stdDeviation="14" result="sunblur" />
                <feMerge>
                    <feMergeNode in="sunblur" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>

            <!-- Gradients -->
            <radialGradient id="smg-sun-grad-{$id}" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#fffbeb" />
                <stop offset="40%" stop-color="var(--smg-accent, #f59e0b)" />
                <stop offset="85%" stop-color="#d97706" />
                <stop offset="100%" stop-color="#b45309" stop-opacity="0" />
            </radialGradient>

            <linearGradient id="smg-panel-grad-{$id}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#1e293b" />
                <stop offset="45%" stop-color="#0f172a" />
                <stop offset="100%" stop-color="#020617" />
            </linearGradient>

            <linearGradient id="smg-inverter-grad-{$id}" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#334155" />
                <stop offset="100%" stop-color="#1e293b" />
            </linearGradient>

            <linearGradient id="smg-battery-grad-{$id}" x1="0%" y1="100%" x2="0%" y2="0%">
                <stop offset="0%" stop-color="#059669" />
                <stop offset="100%" stop-color="#10b981" />
            </linearGradient>

            <linearGradient id="smg-ray-grad-{$id}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--smg-accent, #f59e0b)" stop-opacity="0.8" />
                <stop offset="100%" stop-color="var(--smg-particle, #38bdf8)" stop-opacity="0.1" />
            </linearGradient>
        </defs>

        <!-- Background Ambient Grid Lines -->
        <g class="smg-bg-grid" opacity="0.12" stroke="#475569" stroke-width="1" stroke-dasharray="4 6">
            <line x1="50" y1="150" x2="1150" y2="150" />
            <line x1="50" y1="310" x2="1150" y2="310" />
            <line x1="50" y1="470" x2="1150" y2="470" />
            <line x1="250" y1="50" x2="250" y2="570" />
            <line x1="600" y1="50" x2="600" y2="570" />
            <line x1="950" y1="50" x2="950" y2="570" />
        </g>

        <!-- ── ZONE 1: THE SUN & PHOTON EMISSION (Right Side in RTL) ── -->
        <g class="smg-zone-sun" transform="translate(1000, 110)">
            <!-- Outer Pulsing Corona -->
            <circle class="smg-sun-corona" cx="0" cy="0" r="85" fill="url(#smg-sun-grad-{$id})" opacity="0.4" />
            <circle class="smg-sun-pulse-ring" cx="0" cy="0" r="65" stroke="var(--smg-accent, #f59e0b)" stroke-width="2" opacity="0.6" stroke-dasharray="8 6" />
            <!-- Core Sun Sphere -->
            <circle class="smg-sun-core" cx="0" cy="0" r="48" fill="#fbbf24" filter="url(#smg-sun-glow-{$id})" />
            <circle cx="0" cy="0" r="38" fill="#fef3c7" opacity="0.9" />

            <!-- Radiant Sun Rays -->
            <g class="smg-sun-rays" stroke="var(--smg-accent, #f59e0b)" stroke-width="2.5" stroke-linecap="round" opacity="0.75">
                <line x1="0" y1="-58" x2="0" y2="-76" />
                <line x1="41" y1="-41" x2="54" y2="-54" />
                <line x1="58" y1="0" x2="76" y2="0" />
                <line x1="41" y1="41" x2="54" y2="54" />
                <line x1="0" y1="58" x2="0" y2="76" />
                <line x1="-41" y1="41" x2="-54" y2="54" />
                <line x1="-58" y1="0" x2="-76" y2="0" />
                <line x1="-41" y1="-41" x2="-54" y2="-54" />
            </g>
        </g>

<<<<<<< HEAD
        <!-- Photon Beam Wave Guides: Sun to the solar PV panels -->
=======
        <!-- Photon Beam Wave Guides: Sun to Solar PV Array -->
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
        <g class="smg-photon-stream" stroke="url(#smg-ray-grad-{$id})" stroke-width="1.8" stroke-dasharray="6 8" opacity="0.6">
            <path d="M 940 140 C 880 200, 830 250, 770 300" />
            <path d="M 960 170 C 890 230, 840 280, 750 330" />
            <path d="M 920 120 C 860 180, 810 230, 730 280" />
        </g>

        <!-- Animated Traveling Photons -->
        <g class="smg-photons-active">
            <circle class="smg-photon smg-p1" cx="940" cy="140" r="4" fill="#fef08a" {$filterAttr} />
            <circle class="smg-photon smg-p2" cx="960" cy="170" r="3.5" fill="#fef08a" {$filterAttr} />
            <circle class="smg-photon smg-p3" cx="920" cy="120" r="4.5" fill="#fef08a" {$filterAttr} />
            <circle class="smg-photon smg-p4" cx="870" cy="210" r="3" fill="#fde047" />
            <circle class="smg-photon smg-p5" cx="830" cy="250" r="4" fill="#fde047" />
        </g>

        <!-- ── ZONE 2: PHOTOVOLTAIC SOLAR PANEL ARRAY ── -->
        <g class="smg-zone-panel" transform="translate(620, 240)">
            <!-- Outer 3D Panel Casing -->
            <polygon points="0,60 180,0 210,170 30,230" fill="url(#smg-panel-grad-{$id})" stroke="#475569" stroke-width="2.5" />
            <polygon points="-5,62 178,-3 182,3 -1,68" fill="#64748b" opacity="0.4" />

            <!-- Solar Cell Matrix Grid Lines -->
            <!-- Horizontal Busbars -->
            <path d="M 10,74 L 184,17 M 20,118 L 194,61 M 26,162 L 200,105 M 32,206 L 206,149" stroke="var(--smg-particle, #38bdf8)" stroke-width="1.2" opacity="0.65" />
            <!-- Vertical Fingers -->
            <path d="M 45,46 L 73,212 M 90,31 L 118,197 M 135,16 L 163,182" stroke="#60a5fa" stroke-width="0.8" opacity="0.5" />

            <!-- Silicon Active Surface Shimmer -->
            <polygon points="4,62 176,4 204,166 28,224" fill="var(--smg-particle, #38bdf8)" opacity="0.08" class="smg-panel-shimmer" />

            <!-- Electron Excitation Sparks on Impact Zone -->
            <g class="smg-electron-sparks">
                <circle class="smg-spark smg-sp1" cx="80" cy="110" r="3" fill="#67e8f9" {$filterAttr} />
                <circle class="smg-spark smg-sp2" cx="130" cy="85" r="3.5" fill="#38bdf8" {$filterAttr} />
                <circle class="smg-spark smg-sp3" cx="110" cy="150" r="2.8" fill="#93c5fd" />
            </g>

            <!-- Label -->
            <text x="110" y="250" fill="#94a3b8" font-size="12" font-weight="600" text-anchor="middle" font-family="var(--smg-font)">
                پنل فتوولتائیک DC
            </text>
        </g>

        <!-- DC Conduit: Solar Panel to Inverter -->
        <g class="smg-conduit-dc" stroke="#475569" stroke-width="6" stroke-linecap="round">
            <path d="M 645,460 L 530,460 L 530,340 L 480,340" />
        </g>
        <g class="smg-conduit-dc-flow" stroke="var(--smg-accent, #f59e0b)" stroke-width="3" stroke-linecap="round" stroke-dasharray="10 14" opacity="0.9">
            <path class="smg-pulse-dc" d="M 645,460 L 530,460 L 530,340 L 480,340" />
        </g>

        <!-- ── ZONE 3: SMART MPPT INVERTER ── -->
        <g class="smg-zone-inverter" transform="translate(340, 240)">
            <!-- Inverter Chassis -->
            <rect x="0" y="0" width="140" height="200" rx="14" fill="url(#smg-inverter-grad-{$id})" stroke="#64748b" stroke-width="2.5" />
            <!-- Heat Sink Fins -->
            <line x1="20" y1="8" x2="120" y2="8" stroke="#94a3b8" stroke-width="2" opacity="0.5" />
            <line x1="20" y1="15" x2="120" y2="15" stroke="#94a3b8" stroke-width="2" opacity="0.5" />

            <!-- LCD Digital Status Display -->
            <rect x="20" y="32" width="100" height="50" rx="6" fill="#020617" stroke="#334155" stroke-width="1.5" />
            <text x="70" y="52" fill="#38bdf8" font-size="11" font-weight="700" text-anchor="middle" font-family="var(--smg-font)">
                MPPT 98.8%
            </text>
            <text x="70" y="70" fill="#22c55e" font-size="12" font-weight="800" text-anchor="middle" font-family="monospace">
                230V ~ 50Hz
            </text>

            <!-- AC Wave Visualizer inside Inverter -->
            <path class="smg-ac-sine-wave" d="M 30,120 Q 45,100 60,120 T 90,120 T 110,120" fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round" {$filterAttr} />

            <!-- Status Indicator LEDs -->
            <circle class="smg-led smg-led-green" cx="35" cy="155" r="4" fill="#22c55e" {$filterAttr} />
            <circle cx="55" cy="155" r="4" fill="#3b82f6" opacity="0.8" />
            <circle cx="75" cy="155" r="4" fill="#f59e0b" opacity="0.6" />

            <!-- Label -->
            <text x="70" y="182" fill="#cbd5e1" font-size="11" font-weight="700" text-anchor="middle" font-family="var(--smg-font)">
                اینورتر خورشیدی
            </text>
        </g>

        <!-- AC Conduit: Inverter split to Battery & Grid -->
        <g class="smg-conduit-ac" stroke="#475569" stroke-width="6" stroke-linecap="round">
            <!-- Line to Battery (Top Left) -->
            <path d="M 340,300 L 250,300 L 250,180 L 190,180" />
            <!-- Line to Home/Grid (Bottom Left) -->
            <path d="M 340,380 L 250,380 L 250,460 L 190,460" />
        </g>
        <g class="smg-conduit-ac-flow" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-dasharray="10 14" opacity="0.9">
            <path class="smg-pulse-ac-batt" d="M 340,300 L 250,300 L 250,180 L 190,180" />
            <path class="smg-pulse-ac-grid" d="M 340,380 L 250,380 L 250,460 L 190,460" />
        </g>

        <!-- ── ZONE 4: STORAGE BATTERY UNIT ── -->
        <g class="smg-zone-battery" transform="translate(70, 90)">
            <rect x="0" y="0" width="120" height="150" rx="10" fill="#0f172a" stroke="#334155" stroke-width="2" />
            <!-- Terminal Nodes -->
            <rect x="35" y="-8" width="18" height="8" rx="2" fill="#94a3b8" />
            <rect x="67" y="-8" width="18" height="8" rx="2" fill="#94a3b8" />

            <!-- Battery Level Bars -->
            <g class="smg-battery-levels" fill="#10b981" opacity="0.85">
                <rect x="18" y="100" width="84" height="20" rx="4" />
                <rect x="18" y="74" width="84" height="20" rx="4" />
                <rect class="smg-battery-pulse-bar" x="18" y="48" width="84" height="20" rx="4" fill="url(#smg-battery-grad-{$id})" {$filterAttr} />
                <rect x="18" y="22" width="84" height="20" rx="4" fill="#334155" opacity="0.4" />
            </g>

            <text x="60" y="138" fill="#6ee7b7" font-size="11" font-weight="700" text-anchor="middle" font-family="var(--smg-font)">
                ذخیره‌ساز لیتیومی
            </text>
        </g>

        <!-- ── ZONE 5: SMART GRID & HOME CONSUMER ── -->
        <g class="smg-zone-grid" transform="translate(70, 390)">
            <!-- Smart Substation / Home Unit Box -->
            <rect x="0" y="0" width="120" height="130" rx="10" fill="#0f172a" stroke="#334155" stroke-width="2" />
            <!-- Power Pylon / Tower Glyph -->
            <path d="M 60,25 L 40,95 M 60,25 L 80,95 M 35,50 L 85,50 M 30,75 L 90,75" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="60" cy="25" r="4" fill="#38bdf8" {$filterAttr} />

            <text x="60" y="118" fill="#7dd3fc" font-size="11" font-weight="700" text-anchor="middle" font-family="var(--smg-font)">
                شبکه سراسری و مصرف
            </text>
        </g>
    </svg>
</div>
SVG;
    }

    /**
     * Render Lottie Vector Animation Mode.
     * Includes isolated player loading and responsive sizing.
     */
    private function renderSolarLottieCanvas(array $props, string $id, float $duration, bool $autoplay, string $loop): string
    {
<<<<<<< HEAD
        // Media sources: unresolved values count as empty and the scheme is
        // validated (escapeUrl), so a javascript: URL never reaches src.
        $lottieUrl = $this->escapeUrl($this->mediaSrc($props['lottie_url'] ?? ''), '');
        $poster = $this->mediaSrc($props['poster_image'] ?? '') ?: '/storage/solar-assets/solar-panel-grid.jpg';
=======
        $lottieUrl = trim((string) ($props['lottie_url'] ?? ''));
        $poster = (string) ($props['poster_image'] ?? '/storage/solar-assets/solar-panel-grid.jpg');
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
        $autoAttr = $autoplay ? 'autoplay' : '';
        $loopAttr = ($loop === 'infinite' || $loop === 'alternate') ? 'loop' : '';

        if ($lottieUrl === '') {
            // Default built-in Lottie animation fallback or hint
            return <<<HTML
<div class="smg-lottie-container w-full h-full flex flex-col items-center justify-center text-center p-6 bg-slate-950/60 rounded-xl border border-dashed border-amber-500/40">
    <div class="w-16 h-16 rounded-full bg-amber-500/10 text-amber-400 flex items-center justify-center mb-4">
        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <h4 class="text-white font-bold text-base mb-1" style="font-family: var(--smg-font);">پخش‌کننده انیمیشن Lottie آماده است</h4>
    <p class="text-slate-400 text-xs max-w-md mb-4" style="font-family: var(--smg-font);">
        لطفاً نشانی فایل JSON انیمیشن لوتی خود را در بخش تنظیمات بلوک (موتور انیمیشن) وارد نمایید تا انیمیشن برداری بدون افت کیفیت رندر شود.
    </p>
    <div class="w-full max-w-sm rounded-lg overflow-hidden opacity-40">
        <img src="{$this->solarEscape($poster)}" alt="پوستر خورشیدی" class="w-full h-32 object-cover">
    </div>
</div>
HTML;
        }

        // Vendored, version-pinned and integrity-checked (VendorAssets), never
        // a public CDN: no supply-chain or availability dependency.
        $playerScript = VendorAssets::scriptTag(VendorAssets::LOTTIE_PLAYER);

        return <<<HTML
<div class="smg-lottie-player-wrapper w-full h-full flex items-center justify-center relative">
    {$playerScript}
    <lottie-player
<<<<<<< HEAD
        src="{$lottieUrl}"
=======
        src="{$this->solarEscape($lottieUrl)}"
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
        background="transparent"
        speed="1"
        style="width: 100%; max-height: 520px;"
        {$loopAttr}
        {$autoAttr}
    ></lottie-player>
</div>
HTML;
    }

    /**
     * Render Transparent WebM Video Mode with MP4 fallback.
     */
    private function renderSolarWebmVideo(array $props, string $id, bool $autoplay, string $loop): string
    {
<<<<<<< HEAD
        $webmUrl = $this->escapeUrl($this->mediaSrc($props['video_webm_url'] ?? ''), '');
        $mp4Url = $this->escapeUrl($this->mediaSrc($props['video_mp4_fallback_url'] ?? ''), '');
        $poster = $this->mediaSrc($props['poster_image'] ?? '') ?: '/storage/solar-assets/solar-panel-grid.jpg';
=======
        $webmUrl = trim((string) ($props['video_webm_url'] ?? ''));
        $mp4Url = trim((string) ($props['video_mp4_fallback_url'] ?? ''));
        $poster = (string) ($props['poster_image'] ?? '/storage/solar-assets/solar-panel-grid.jpg');
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
        $autoAttr = $autoplay ? 'autoplay muted' : '';
        $loopAttr = ($loop === 'infinite' || $loop === 'alternate') ? 'loop' : '';

        if ($webmUrl === '' && $mp4Url === '') {
            return <<<HTML
<div class="smg-video-container w-full h-full flex flex-col items-center justify-center text-center p-6 bg-slate-950/60 rounded-xl border border-dashed border-sky-500/40">
    <div class="w-16 h-16 rounded-full bg-sky-500/10 text-sky-400 flex items-center justify-center mb-4">
        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><path d="m10 8 6 4-6 4V8z"/></svg>
    </div>
    <h4 class="text-white font-bold text-base mb-1" style="font-family: var(--smg-font);">پخش‌کننده ویدیویی WebM</h4>
    <p class="text-slate-400 text-xs max-w-md mb-4" style="font-family: var(--smg-font);">
        نشانی ویدیوی WebM با کانال آلفا (شفاف) یا ویدیوی MP4 را در اینسپکتور وارد نمایید تا انیمیشن با کیفیت بالا پخش شود.
    </p>
    <div class="w-full max-w-sm rounded-lg overflow-hidden border border-slate-800">
        <img src="{$this->solarEscape($poster)}" alt="پیش‌نمایش ویدیو" class="w-full h-36 object-cover">
    </div>
</div>
HTML;
        }

        $sources = '';
        if ($webmUrl !== '') {
<<<<<<< HEAD
            $sources .= '<source src="' . $webmUrl . '" type="video/webm">';
        }
        if ($mp4Url !== '') {
            $sources .= '<source src="' . $mp4Url . '" type="video/mp4">';
=======
            $sources .= '<source src="' . $this->solarEscape($webmUrl) . '" type="video/webm">';
        }
        if ($mp4Url !== '') {
            $sources .= '<source src="' . $this->solarEscape($mp4Url) . '" type="video/mp4">';
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
        }

        return <<<HTML
<div class="smg-video-wrapper w-full h-full flex items-center justify-center relative">
    <video
        class="w-full h-auto max-h-[520px] rounded-xl object-contain"
        poster="{$this->solarEscape($poster)}"
        playsinline
        {$autoAttr}
        {$loopAttr}
    >
        {$sources}
        مرورگر شما از پخش این فرمت ویدیویی پشتیبانی نمی‌کند.
    </video>
</div>
HTML;
    }

    /**
     * Render the Live Performance Counters Bar.
     */
    private function renderSolarCountersBar(array $counters, bool $persianDigits): string
    {
        $itemsHtml = '';
        foreach ($counters as $c) {
            $val = $this->solarNum($c['val'], $persianDigits);
            $itemsHtml .= <<<ITEM
<div class="smg-counter-item flex-1 min-w-[140px] p-4 rounded-xl bg-slate-800/60 border border-slate-700/50 backdrop-blur-md text-center transition-all duration-300 hover:border-amber-500/40 hover:bg-slate-800/80">
    <div class="flex items-center justify-center gap-1.5 text-xs text-slate-400 mb-1.5" style="font-family: var(--smg-font);">
        <span class="w-4 h-4 {$c['color']}">{$this->solarIcon($c['icon'])}</span>
        <span>{$this->solarEscape($c['label'])}</span>
    </div>
    <div class="text-xl md:text-2xl font-black text-white tracking-tight flex items-baseline justify-center gap-1" style="font-family: var(--smg-font);">
        <span>{$val}</span>
        <span class="text-xs font-normal text-slate-400">{$this->solarEscape($c['unit'])}</span>
    </div>
</div>
ITEM;
        }

        return <<<HTML
<div class="smg-counters-bar grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mt-6 md:mt-8">
    {$itemsHtml}
</div>
HTML;
    }

    /**
     * Render the Energy Conversion Steps Flow & Tooltips.
     */
    private function renderSolarStepsGrid(array $steps, bool $persianDigits): string
    {
        $cards = '';
        foreach ($steps as $index => $step) {
            $num = $this->solarNum($step['step_num'] ?? ($index + 1), $persianDigits);
            $title = (string) ($step['title'] ?? '');
            $desc = (string) ($step['desc'] ?? '');
            $badge = (string) ($step['badge'] ?? '');
            $tooltip = (string) ($step['tooltip'] ?? '');
<<<<<<< HEAD
            $iconName = IconLibrary::name($step['icon'] ?? 'sun');
            $iconMarkup = $iconName !== null ? $this->iconHtml($iconName) : '';
            if ($iconMarkup === '') {
                $iconMarkup = $this->solarIcon(is_string($step['icon'] ?? null) ? $step['icon'] : 'sun');
            }
=======
            $icon = (string) ($step['icon'] ?? 'sun');
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)

            $cards .= <<<CARD
<div class="smg-step-card group relative p-5 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-amber-500/50 transition-all duration-300 hover:shadow-xl hover:shadow-amber-500/10 flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between mb-3">
            <span class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center text-sm font-bold">
                {$num}
            </span>
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-800 text-amber-300/90 border border-slate-700">
                {$this->solarEscape($badge)}
            </span>
        </div>
        <div class="flex items-center gap-2 mb-2">
<<<<<<< HEAD
            <span class="smg-step-icon w-5 h-5 text-amber-400">{$iconMarkup}</span>
=======
            <span class="w-5 h-5 text-amber-400">{$this->solarIcon($icon)}</span>
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
            <h4 class="font-bold text-white text-sm md:text-base group-hover:text-amber-300 transition-colors" style="font-family: var(--smg-font);">
                {$this->solarEscape($title)}
            </h4>
        </div>
        <p class="text-xs text-slate-400 leading-relaxed mb-3" style="font-family: var(--smg-font);">
            {$this->solarEscape($desc)}
        </p>
    </div>

    <!-- Interactive Tooltip Pill -->
    <div class="pt-3 border-t border-slate-800/80 flex items-center gap-1.5 text-[11px] text-slate-400 group-hover:text-slate-300 transition-colors">
        <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span class="truncate" title="{$this->solarEscape($tooltip)}">{$this->solarEscape($tooltip)}</span>
    </div>
</div>
CARD;
        }

        return <<<HTML
<div class="smg-steps-flow mt-8 md:mt-12">
    <div class="text-center mb-6">
        <h3 class="text-lg md:text-xl font-bold text-white mb-1" style="font-family: var(--smg-font);">
            مراحل فنی تبدیل فوتوالکتریک تا مصرف
        </h3>
        <p class="text-xs text-slate-400" style="font-family: var(--smg-font);">
            فرآیند گام‌به‌گام انتقال بار و توان در نیروگاه‌های متصل و منفصل از شبکه
        </p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {$cards}
    </div>
</div>
HTML;
    }

    /**
     * Inline SVG icons for performance and crisp rendering.
     */
    private function solarIcon(string $name): string
    {
        return match ($name) {
            'sun' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>',
            'cpu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9zM9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3"/></svg>',
            'battery' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="16" height="10" rx="2"/><path d="M22 11v2"/></svg>',
            'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'grid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        };
    }

    /**
     * Pure Scoped CSS Styles with Hardware-Accelerated Animations.
     */
    private function renderSolarStyles(bool $pauseOnHover, bool $glow): string
    {
        $pauseRule = $pauseOnHover ? '.smg-widget-root:hover * { animation-play-state: paused !important; }' : '';

        return <<<CSS
<style>
<<<<<<< HEAD
/* Catalog icons (24px markup) fill the step's icon slot like the inline glyphs. */
.smg-step-icon { display: inline-flex; }
.smg-step-icon > svg { inline-size: 100%; block-size: 100%; }

=======
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
/* ── Solar Energy Cycle Scoped Keyframe Animations ── */
@keyframes smg-sun-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes smg-sun-breathe {
    0%, 100% { transform: scale(1); opacity: 0.4; }
    50% { transform: scale(1.15); opacity: 0.7; }
}

@keyframes smg-photon-travel {
    0% { transform: translate3d(0, 0, 0); opacity: 0; }
    15% { opacity: 1; }
    85% { opacity: 0.9; }
    100% { transform: translate3d(-240px, 190px, 0); opacity: 0; }
}

@keyframes smg-spark-discharge {
    0%, 100% { transform: scale(0.6); opacity: 0.2; }
    50% { transform: scale(1.4); opacity: 1; }
}

@keyframes smg-dash-pulse {
    0% { stroke-dashoffset: 48; }
    100% { stroke-dashoffset: 0; }
}

@keyframes smg-sine-oscillation {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

@keyframes smg-led-blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

@keyframes smg-badge-beacon {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.4; }
}

/* ── Animation Bindings ── */
.smg-sun-rays {
    transform-origin: 0 0;
    animation: smg-sun-spin calc(var(--smg-duration, 4s) * 3) linear infinite;
}

.smg-sun-corona {
    transform-origin: 0 0;
    animation: smg-sun-breathe var(--smg-duration, 4s) ease-in-out infinite;
}

.smg-photon {
    animation: smg-photon-travel var(--smg-duration, 4s) cubic-bezier(0.4, 0, 0.2, 1) infinite;
    animation-direction: var(--smg-direction, normal);
}

.smg-p1 { animation-delay: 0s; }
.smg-p2 { animation-delay: calc(var(--smg-duration, 4s) * 0.2); }
.smg-p3 { animation-delay: calc(var(--smg-duration, 4s) * 0.4); }
.smg-p4 { animation-delay: calc(var(--smg-duration, 4s) * 0.6); }
.smg-p5 { animation-delay: calc(var(--smg-duration, 4s) * 0.8); }

.smg-spark {
    animation: smg-spark-discharge calc(var(--smg-duration, 4s) * 0.35) ease-in-out infinite;
}
.smg-sp1 { animation-delay: 0.1s; }
.smg-sp2 { animation-delay: 0.4s; }
.smg-sp3 { animation-delay: 0.7s; }

.smg-pulse-dc, .smg-pulse-ac-batt, .smg-pulse-ac-grid {
    animation: smg-dash-pulse calc(var(--smg-duration, 4s) * 0.5) linear infinite;
    animation-direction: var(--smg-direction, normal);
}

.smg-ac-sine-wave {
    animation: smg-sine-oscillation 1.2s ease-in-out infinite;
}

.smg-led-green {
    animation: smg-led-blink 1s ease-in-out infinite;
}

.smg-badge-pulse {
    animation: smg-badge-beacon 1.8s ease-in-out infinite;
}

.smg-battery-pulse-bar {
    animation: smg-spark-discharge 2s ease-in-out infinite;
}

{$pauseRule}

/* ── Accessibility: Reduced Motion ── */
@media (prefers-reduced-motion: reduce) {
    .smg-sun-rays,
    .smg-sun-corona,
    .smg-photon,
    .smg-spark,
    .smg-pulse-dc,
    .smg-pulse-ac-batt,
    .smg-pulse-ac-grid,
    .smg-ac-sine-wave,
    .smg-led-green,
    .smg-badge-pulse,
    .smg-battery-pulse-bar {
        animation: none !important;
        transform: none !important;
    }
}
</style>
CSS;
    }
}
