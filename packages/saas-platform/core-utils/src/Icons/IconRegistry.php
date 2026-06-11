<?php

namespace SaaSPlatform\CoreUtils\Icons;

/**
 * SaaS Platform Duotone Icon Registry
 *
 * Unique duotone icon set: background fill (opacity 0.12) + 2px stroke foreground.
 * All icons: 24×24 viewBox, stroke-linecap="round", stroke-linejoin="round".
 * Usage: <x-core-ui::icon name="home" class="w-5 h-5 text-blue-600" />
 *
 * Categories: Navigation · Actions · Users · Education · Commerce ·
 *             Communication · Files · Analytics · Status · Infrastructure
 */
class IconRegistry
{
    private static array $icons = [

        // ══════════════════════════════════════════════════════════════════
        // NAVIGATION
        // ══════════════════════════════════════════════════════════════════

        'home' => '<path fill="currentColor" fill-opacity="0.12" d="M4 11.5V20a.5.5 0 0 0 .5.5H9v-4.5h6V20.5h4.5a.5.5 0 0 0 .5-.5v-8.5L12 4.5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 12 12 4l9 8v7a2 2 0 0 1-2 2h-4v-5H9v5H5a2 2 0 0 1-2-2v-7Z"/>',

        'dashboard' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="3" width="7" height="9" rx="2"/><rect fill="currentColor" fill-opacity="0.12" x="14" y="3" width="7" height="4" rx="2"/><rect fill="currentColor" fill-opacity="0.12" x="3" y="16" width="7" height="5" rx="2"/><rect fill="currentColor" fill-opacity="0.12" x="14" y="11" width="7" height="10" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="7" height="9" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="14" y="3" width="7" height="4" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="16" width="7" height="5" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="14" y="11" width="7" height="10" rx="2"/>',

        'menu' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/>',

        'search' => '<circle fill="currentColor" fill-opacity="0.12" cx="10.5" cy="10.5" r="6.5"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="10.5" cy="10.5" r="6.5"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="15.5" y1="15.5" x2="21" y2="21"/>',

        'filter' => '<path fill="currentColor" fill-opacity="0.12" d="M3 5h18L14 12.5V19l-4-2V12.5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 5h18L14 13v6l-4-2v-4Z"/>',

        'close' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M5 5 19 19M19 5 5 19"/>',

        'chevron-right' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 5 16 12 9 19"/>',

        'chevron-left' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 5 8 12 15 19"/>',

        'chevron-up' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 15 12 9 6 15"/>',

        'chevron-down' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9 12 15 18 9"/>',

        'arrow-right' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/>',

        'arrow-left' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m5 5-5-5 5-5"/>',

        'arrow-up' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-5 5 5-5 5 5"/>',

        'arrow-down' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-5-5 5 5 5-5"/>',

        'external-link' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6m4-5h6v6m-11 5L21 3"/>',

        'grid' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="3" width="7" height="7" rx="1.5"/><rect fill="currentColor" fill-opacity="0.12" x="14" y="3" width="7" height="7" rx="1.5"/><rect fill="currentColor" fill-opacity="0.12" x="3" y="14" width="7" height="7" rx="1.5"/><rect fill="currentColor" fill-opacity="0.12" x="14" y="14" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="14" y="3" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="14" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="14" y="14" width="7" height="7" rx="1.5"/>',

        'list' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',

        'sort' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 9h8M3 15h5m7-9v12m-4-4 4 4 4-4"/>',

        'sidebar' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="3" width="5" height="18" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="18" height="18" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 3v18"/>',

        // ══════════════════════════════════════════════════════════════════
        // ACTIONS
        // ══════════════════════════════════════════════════════════════════

        'plus' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 5v14M5 12h14"/>',

        'plus-circle' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 8v8M8 12h8"/>',

        'minus' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M5 12h14"/>',

        'edit' => '<path fill="currentColor" fill-opacity="0.12" d="M17.5 3.5 20.5 6.5 8 19H5v-3Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M17.5 3.5 20.5 6.5 8 19H5v-3Zm0 0 3 3"/>',

        'edit-box' => '<path fill="currentColor" fill-opacity="0.12" d="M15.5 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-9.5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>',

        'trash' => '<path fill="currentColor" fill-opacity="0.12" d="M6 7h12l-1 13.1A1.5 1.5 0 0 1 15.5 21h-7A1.5 1.5 0 0 1 7 20.1Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m1 0-1 13.1A1.5 1.5 0 0 1 15.5 21h-7A1.5 1.5 0 0 1 7 19.1L6 6"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M10 11v5M14 11v5"/>',

        'copy' => '<rect fill="currentColor" fill-opacity="0.12" x="9" y="9" width="12" height="12" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="9" y="9" width="12" height="12" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',

        'save' => '<path fill="currentColor" fill-opacity="0.12" d="M5 2h11l5 5v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm3 19v-7h8v7ZM7 2v5h8V2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Zm-8 0v-7h6v7M7 3v5h7"/>',

        'download' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="15" width="18" height="6" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3v11m-4-4 4 4 4-4M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/>',

        'upload' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="15" width="18" height="6" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 15V4m-4 4 4-4 4 4M3 17v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/>',

        'refresh' => '<circle fill="currentColor" fill-opacity="0.08" cx="12" cy="12" r="9"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="1,4 1,10 7,10"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="23,20 23,14 17,14"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/>',

        'settings' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',

        'dots-horizontal' => '<circle fill="currentColor" cx="5" cy="12" r="1.5"/><circle fill="currentColor" cx="12" cy="12" r="1.5"/><circle fill="currentColor" cx="19" cy="12" r="1.5"/>',

        'dots-vertical' => '<circle fill="currentColor" cx="12" cy="5" r="1.5"/><circle fill="currentColor" cx="12" cy="12" r="1.5"/><circle fill="currentColor" cx="12" cy="19" r="1.5"/>',

        'check' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17 4 12"/>',

        'eye' => '<path fill="currentColor" fill-opacity="0.12" d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="3"/>',

        'eye-off' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="1" y1="1" x2="23" y2="23"/>',

        'lock' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="11" width="18" height="11" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M7 11V7a5 5 0 0 1 10 0v4"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="11" width="18" height="11" rx="2"/><circle fill="currentColor" cx="12" cy="16" r="1.5"/>',

        'unlock' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="11" width="18" height="11" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M7 11V7a5 5 0 0 1 9.9-1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="11" width="18" height="11" rx="2"/><circle fill="currentColor" cx="12" cy="16" r="1.5"/>',

        'share' => '<circle fill="currentColor" fill-opacity="0.12" cx="18" cy="5" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="6" cy="12" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="18" cy="19" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="18" cy="5" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="6" cy="12" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="18" cy="19" r="3"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',

        'link' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',

        // ══════════════════════════════════════════════════════════════════
        // USERS & AUTH
        // ══════════════════════════════════════════════════════════════════

        'user' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="7" r="4"/><path fill="currentColor" fill-opacity="0.12" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="7" r="4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>',

        'users' => '<path fill="currentColor" fill-opacity="0.12" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="9" cy="7" r="4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',

        'user-plus' => '<path fill="currentColor" fill-opacity="0.12" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="9" cy="7" r="4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M20 8v6m-3-3h6"/>',

        'user-check' => '<path fill="currentColor" fill-opacity="0.12" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="9" cy="7" r="4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m16 11 2 2 4-4"/>',

        'login' => '<path fill="currentColor" fill-opacity="0.12" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 17 15 12 10 7"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="15" y1="12" x2="3" y2="12"/>',

        'logout' => '<path fill="currentColor" fill-opacity="0.12" d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m16 17 5-5-5-5"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="21" y1="12" x2="9" y2="12"/>',

        'shield' => '<path fill="currentColor" fill-opacity="0.12" d="M12 22S4 18 4 12V5l8-3 8 3v7c0 6-8 10-8 10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 22S4 18 4 12V5l8-3 8 3v7c0 6-8 10-8 10Z"/>',

        'shield-check' => '<path fill="currentColor" fill-opacity="0.12" d="M12 22S4 18 4 12V5l8-3 8 3v7c0 6-8 10-8 10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 22S4 18 4 12V5l8-3 8 3v7c0 6-8 10-8 10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>',

        'key' => '<circle fill="currentColor" fill-opacity="0.12" cx="7.5" cy="15.5" r="5.5"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="7.5" cy="15.5" r="4.5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m11.26 11.74 8.24-8.24m0 0 2 2-2.5 2.5-2-2m-3 4 1.5 1.5"/>',

        'crown' => '<path fill="currentColor" fill-opacity="0.12" d="M2 18h20L19.5 8 15 13 12 3 9 13 4.5 8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m2 19 17.5-1L22 7l-5 5-5-9-5 9-5-5 2.5 12Zm0 0h20"/>',

        'profile' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="2" width="20" height="20" rx="3"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="2" width="20" height="20" rx="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="10" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M6 21v-1a6 6 0 0 1 12 0v1"/>',

        // ══════════════════════════════════════════════════════════════════
        // EDUCATION
        // ══════════════════════════════════════════════════════════════════

        'book' => '<path fill="currentColor" fill-opacity="0.12" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z"/>',

        'book-open' => '<path fill="currentColor" fill-opacity="0.12" d="M2 3h9v18H2Zm11 0h9v18H13Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 3h9v18H2V3Zm11 0h9v18H13V3Z"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="12" y1="3" x2="12" y2="21"/>',

        'graduation' => '<path fill="currentColor" fill-opacity="0.12" d="M12 3 2 9l10 6 10-6-10-6Zm-6 8v5a8 8 0 0 0 12 0v-5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3 2 9l10 6 10-6-10-6Zm6 5v6a6 6 0 0 1-12 0v-6"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="2" y1="9" x2="2" y2="14"/>',

        'courses' => '<path fill="currentColor" fill-opacity="0.12" d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4Zm0 5h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 13h8M8 17h5"/>',

        'lesson' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path fill="currentColor" fill-opacity="0.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M10 8.5 16 12 10 15.5Z"/>',

        'video-lesson' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="5" width="15" height="14" rx="2"/><path fill="currentColor" fill-opacity="0.12" d="M17 9 22 6.5v11L17 15Z"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="5" width="15" height="14" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m17 9 5-2.5v11L17 15"/>',

        'quiz' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><circle fill="currentColor" cx="12" cy="17" r="1.25"/>',

        'certificate' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="9" r="6"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 9 2 2 4-4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8.21 13.89 7 23l5-3 5 3-1.21-9.12"/>',

        'star' => '<path fill="currentColor" fill-opacity="0.2" d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/>',

        'assignment' => '<path fill="currentColor" fill-opacity="0.12" d="M9 2h6v3H9ZM5 3h14a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 2h6v4H9V2ZM5 4h14a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9 11h6M9 15h4"/>',

        'classroom' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="3" width="20" height="14" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="3" width="20" height="14" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 21h8M12 17v4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M6 8h12M6 11.5h8"/>',

        'learning-path' => '<circle fill="currentColor" fill-opacity="0.12" cx="5" cy="6" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="19" cy="18" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="5" cy="6" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="19" cy="18" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7.5 8.5 9.5 10m5 4 2 2"/>',

        'progress' => '<rect fill="currentColor" fill-opacity="0.1" x="2" y="10" width="20" height="5" rx="2.5"/><rect fill="currentColor" fill-opacity="0.45" x="2" y="10" width="14" height="5" rx="2.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="10" width="20" height="5" rx="2.5"/>',

        'walkthrough' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="10"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 16v-4M12 8h.01"/><circle fill="currentColor" cx="12" cy="8" r="1.25"/>',

        // ══════════════════════════════════════════════════════════════════
        // COMMERCE & SUBSCRIPTION
        // ══════════════════════════════════════════════════════════════════

        'cart' => '<path fill="currentColor" fill-opacity="0.12" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6ZM3 6h18"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M16 10a4 4 0 0 1-8 0"/>',

        'shopping-bag' => '<path fill="currentColor" fill-opacity="0.12" d="M6 2h12l2 5v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 2h12l2 5v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M16 10a4 4 0 0 1-8 0"/>',

        'tag' => '<path fill="currentColor" fill-opacity="0.12" d="M20.59 13.41L13.42 20.6A2 2 0 0 1 11 20.6L3.4 13A2 2 0 0 1 2.8 11L3.8 4A2 2 0 0 1 5.5 2.2l7-1A2 2 0 0 1 14.1 1.8l7.59 7.6a2 2 0 0 1-.1 4Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20.59 13.41-7.17 7.17a2 2 0 0 0-2.83 0L3.42 17.59A2 2 0 0 0 3.42 20.41l.17.17A2 2 0 0 0 6.41 20.41L13.41 13.41A2 2 0 0 0 13.41 10.59L3.41 .59A2 2 0 0 0 .59 .59v0"/><circle fill="currentColor" cx="7.5" cy="7.5" r="1.5"/>',

        'price-tag' => '<path fill="currentColor" fill-opacity="0.12" d="M12.83 2H20a2 2 0 0 1 2 2v7.17a2 2 0 0 1-.59 1.42L13 21a2 2 0 0 1-2.83 0L3 13.83a2 2 0 0 1 0-2.83L11.42 2.6A2 2 0 0 1 12.83 2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12.83 2H20a2 2 0 0 1 2 2v7.17a2 2 0 0 1-.59 1.42L13 21a2 2 0 0 1-2.83 0L3 13.83a2 2 0 0 1 0-2.83L11.42 2.6A2 2 0 0 1 12.83 2Z"/><circle fill="currentColor" stroke="currentColor" stroke-width="1" cx="16.5" cy="7.5" r="1.5"/>',

        'credit-card' => '<rect fill="currentColor" fill-opacity="0.12" x="1" y="4" width="22" height="16" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="1" y="4" width="22" height="16" rx="2"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="1" y1="10" x2="23" y2="10"/>',

        'receipt' => '<path fill="currentColor" fill-opacity="0.12" d="M4 2h16v20l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 2h16v20l-3-2-2 2-2-2-2 2-2-2-3 2V2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 10h8M8 14h5"/>',

        'invoice' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 11v6m-2-3h4"/>',

        'coupon' => '<path fill="currentColor" fill-opacity="0.12" d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v2a2 2 0 0 1 0 4v2a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 0-4Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 9a2 2 0 0 0 0 4v3a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2Z"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 2" x1="9" y1="5" x2="9" y2="19"/>',

        'subscription' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="10"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7 18s1.5-2 5-2 5 2 5 2"/>',

        'plan' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="7" width="20" height="14" rx="2"/><rect fill="currentColor" fill-opacity="0.12" x="4" y="4" width="16" height="3" rx="1"/><rect fill="currentColor" fill-opacity="0.08" x="6" y="2" width="12" height="2" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="7" width="20" height="14" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="4" y="4" width="16" height="3" rx="1"/>',

        'wallet' => '<path fill="currentColor" fill-opacity="0.12" d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 9a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M16 13h.01"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 5V4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v1"/>',

        'order' => '<path fill="currentColor" fill-opacity="0.12" d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="18" height="18" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9 9h6M9 13h6M9 17h4"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 3v4h6V3"/>',

        'store' => '<path fill="currentColor" fill-opacity="0.12" d="M2 7h20l-2 13H4ZM5 7l2-5h10l2 5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 7h20l-2 13H4L2 7Zm3 0L7 2h10l2 5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 7v13M7 7l1.5 7M17 7l-1.5 7"/>',

        'package' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2 2 7v10l10 5 10-5V7Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 22 2 17V7l10-5 10 5v10l-10 5Zm0 0V12m0 0L2 7m10 5 10-5"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="7" y1="9.5" x2="17" y2="4.5"/>',

        'referral' => '<circle fill="currentColor" fill-opacity="0.12" cx="18" cy="5" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="6" cy="12" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="18" cy="19" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="18" cy="5" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="6" cy="12" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="18" cy="19" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m8.59 13.51 6.82 3.98M15.41 6.51 8.59 10.49"/>',

        // ══════════════════════════════════════════════════════════════════
        // COMMUNICATION
        // ══════════════════════════════════════════════════════════════════

        'bell' => '<path fill="currentColor" fill-opacity="0.12" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M13.73 21a2 2 0 0 1-3.46 0"/>',

        'bell-ring' => '<path fill="currentColor" fill-opacity="0.12" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M13.73 21a2 2 0 0 1-3.46 0m8.93-18.35A8.97 8.97 0 0 1 21 8M2.36 2.65A8.97 8.97 0 0 0 3 8"/>',

        'message' => '<path fill="currentColor" fill-opacity="0.12" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/>',

        'message-circle' => '<path fill="currentColor" fill-opacity="0.12" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/>',

        'sms' => '<rect fill="currentColor" fill-opacity="0.12" x="5" y="2" width="14" height="20" rx="3"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="5" y="2" width="14" height="20" rx="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9 10h6M9 14h4"/><circle fill="currentColor" cx="12" cy="19" r="1"/>',

        'megaphone' => '<path fill="currentColor" fill-opacity="0.12" d="M3 11v2h14l4-7v16l-4-7H3v2l-2-2v-5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 11v2H1v-2h2Zm0 0h14l4-6v16l-4-6H3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7 13v5"/>',

        'broadcast' => '<path fill="currentColor" fill-opacity="0.12" d="M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="15" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M5.64 10.64A9 9 0 0 0 3 17m18 0a9 9 0 0 0-2.64-6.36M8.46 8.46A6 6 0 0 0 6 14m12 0a6 6 0 0 0-2.46-5.54"/>',

        'announcement' => '<path fill="currentColor" fill-opacity="0.12" d="M21 3H3l7 9v6l4 2v-8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 3H3l7 9v6l4 2v-8l7-9Z"/>',

        'mail' => '<path fill="currentColor" fill-opacity="0.12" d="M22 6H2v14h20ZM2 6l10 7 10-7"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="6" width="20" height="14" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 6 12 13 22 6"/>',

        'phone' => '<path fill="currentColor" fill-opacity="0.12" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',

        // ══════════════════════════════════════════════════════════════════
        // FILES & MEDIA
        // ══════════════════════════════════════════════════════════════════

        'file' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/>',

        'file-text' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="16" y1="13" x2="8" y2="13"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="16" y1="17" x2="8" y2="17"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" points="10,9 9,9 8,9"/>',

        'image' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="3" width="18" height="18" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="18" height="18" rx="2"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="8.5" cy="8.5" r="1.5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 15 16 10 5 21"/>',

        'video' => '<path fill="currentColor" fill-opacity="0.12" d="M23 7 16 12 23 17V7ZM3 7h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="1" y="7" width="14" height="10" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m23 7-7 5 7 5V7Z"/>',

        'music' => '<path fill="currentColor" fill-opacity="0.12" d="M9 18V5l12-2v13"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l12-2v13"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="6" cy="18" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="18" cy="16" r="3"/>',

        'folder' => '<path fill="currentColor" fill-opacity="0.12" d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2v11Z"/>',

        'folder-open' => '<path fill="currentColor" fill-opacity="0.12" d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M2 9h20l-2 11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2L2 9ZM2 9V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v1"/>',

        'paperclip' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',

        'pdf' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/><path stroke="currentColor" fill="none" stroke-width="1.75" stroke-linecap="round" d="M7 13h1.5a1 1 0 0 1 0 2H7v2m4-4h1a1.5 1.5 0 0 1 0 3h-1v1m3-4h2m-1 0v4"/>',

        // ══════════════════════════════════════════════════════════════════
        // ANALYTICS & REPORTS
        // ══════════════════════════════════════════════════════════════════

        'bar-chart' => '<path fill="currentColor" fill-opacity="0.12" d="M4 20h3V9H4Zm7 0h3V4h-3Zm7 0h3V13h-3Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 20h3V9H4v11Zm7 0h3V4h-3v16Zm7 0h3V13h-3v7Z"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="2" y1="20" x2="22" y2="20"/>',

        'line-chart' => '<path fill="currentColor" fill-opacity="0.12" d="M3 20 7 10l4 5 4-8 5 8H3Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="3,20 7,10 11,15 15,7 20,15"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="3" y1="20" x2="21" y2="20"/>',

        'pie-chart' => '<path fill="currentColor" fill-opacity="0.12" d="M21.21 15.89A10 10 0 1 1 8 2.83Z"/><path fill="currentColor" fill-opacity="0.25" d="M22 12A10 10 0 0 0 12 2v10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21.21 15.89A10 10 0 1 1 8 2.83m14 9.17A10 10 0 0 0 12 2v10h10Z"/>',

        'trending-up' => '<path fill="currentColor" fill-opacity="0.12" d="M3 20 8 12l4 4 5-7 5 3V20H3Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="15,7 22,7 22,14"/>',

        'trending-down' => '<path fill="currentColor" fill-opacity="0.12" d="M3 7 8 14l4-4 5 7 5-3V7H3Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22,17 13.5,8.5 8.5,13.5 2,7"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="15,17 22,17 22,10"/>',

        'analytics' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="14" width="4" height="7" rx="1"/><rect fill="currentColor" fill-opacity="0.12" x="9" y="9" width="4" height="12" rx="1"/><rect fill="currentColor" fill-opacity="0.12" x="16" y="4" width="4" height="17" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="14" width="4" height="7" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="9" y="9" width="4" height="12" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="16" y="4" width="4" height="17" rx="1"/>',

        'report' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="8,14 10,12 12,14 16,10"/>',

        'activity' => '<polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22,12 18,12 15,21 9,3 6,12 2,12"/>',

        'stats' => '<path fill="currentColor" fill-opacity="0.12" d="M2 20h20v-2H2Zm2-4 4-5 4 3 4-8 4 5v5H4Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 16 8 11l4 3 4-8 4 5"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="2" y1="20" x2="22" y2="20"/>',

        // ══════════════════════════════════════════════════════════════════
        // STATUS & FEEDBACK
        // ══════════════════════════════════════════════════════════════════

        'check-circle' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>',

        'x-circle' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="m15 9-6 6M9 9l6 6"/>',

        'alert-triangle' => '<path fill="currentColor" fill-opacity="0.12" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="12" y1="9" x2="12" y2="13"/><circle fill="currentColor" cx="12" cy="17" r="1.25"/>',

        'info-circle' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="12" y1="16" x2="12" y2="12"/><circle fill="currentColor" cx="12" cy="8" r="1.25"/>',

        'loader' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/>',

        'clock' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/>',

        'calendar' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="4" width="18" height="17" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="4" width="18" height="17" rx="2"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="16" y1="2" x2="16" y2="6"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="8" y1="2" x2="8" y2="6"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="3" y1="10" x2="21" y2="10"/>',

        'badge-check' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2L9.5 4.5H6V8L3.5 10.5 6 13v3.5h3.5L12 19l2.5-2.5H18V13l2.5-2.5L18 8V4.5h-3.5Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2 9 4.5H5.5V8L3 10.5 5.5 13v3.5H9L12 19l3-2.5h3.5V13l2.5-2.5L18.5 8V4.5H15Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>',

        'flag' => '<path fill="currentColor" fill-opacity="0.12" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1v12Zm0 0v6"/>',

        // ══════════════════════════════════════════════════════════════════
        // INFRASTRUCTURE & TECH
        // ══════════════════════════════════════════════════════════════════

        'server' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="2" width="20" height="8" rx="2"/><rect fill="currentColor" fill-opacity="0.12" x="2" y="14" width="20" height="8" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="2" width="20" height="8" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="14" width="20" height="8" rx="2"/><circle fill="currentColor" cx="7" cy="6" r="1.5"/><circle fill="currentColor" cx="7" cy="18" r="1.5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M11 6h4M11 18h4"/>',

        'database' => '<ellipse fill="currentColor" fill-opacity="0.12" cx="12" cy="5" rx="9" ry="3"/><path fill="currentColor" fill-opacity="0.12" d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><ellipse stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="5" rx="9" ry="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M21 12c0 1.66-4.03 3-9 3s-9-1.34-9-3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/>',

        'cloud' => '<path fill="currentColor" fill-opacity="0.12" d="M18 10a6 6 0 0 0-11.47-2.13A4 4 0 0 0 6 16h13a3 3 0 0 0-.5-5.96A6 6 0 0 0 18 10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 10a6 6 0 0 0-11.47-2.13A4 4 0 1 0 6 16h13a3 3 0 0 0 0-6 3 3 0 0 0-.5.04"/>',

        'cloud-upload' => '<path fill="currentColor" fill-opacity="0.12" d="M18 10a6 6 0 0 0-11.47-2.13A4 4 0 0 0 6 16h13a3 3 0 0 0-.5-5.96A6 6 0 0 0 18 10Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 10a6 6 0 0 0-11.47-2.13A4 4 0 1 0 6 16h13a3 3 0 0 0 0-6 3 3 0 0 0-.5.04"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m-3-3 3-3 3 3"/>',

        'backup' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="10"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 7v4l3 3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M5.63 5.63a8 8 0 0 1 0 12.74m12.74 0a8 8 0 0 1 0-12.74"/>',

        'rocket' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2S7 7 7 13l5 3 5-3c0-6-5-11-5-11Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2S7 6.5 7 13l5 3 5-3c0-6.5-5-11-5-11Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7 13c-1.5 1-2.5 2-2 4l4-1m9-3c1.5 1 2.5 2 2 4l-4-1"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="10" r="2"/>',

        'monitor' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="3" width="20" height="14" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="3" width="20" height="14" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 21h8M12 17v4"/>',

        'terminal' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="3" width="20" height="18" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="3" width="20" height="18" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 4 3.5-4 3.5m6 0h4"/>',

        'code' => '<path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',

        'api' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="3" width="20" height="18" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="3" width="20" height="18" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7 8h10M7 12h7M7 16h5"/>',

        'webhook' => '<circle fill="currentColor" fill-opacity="0.12" cx="5" cy="12" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="19" cy="5" r="3"/><circle fill="currentColor" fill-opacity="0.12" cx="19" cy="19" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="5" cy="12" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="19" cy="5" r="3"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="19" cy="19" r="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="m8 12 8-6m0 12-8-4"/>',

        'log' => '<path fill="currentColor" fill-opacity="0.12" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14,2 14,8 20,8"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 12h8M8 15h5M8 18h3"/>',

        'deploy' => '<path fill="currentColor" fill-opacity="0.12" d="M12 3 2 12h3v8h14v-8h3Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 12 12 3l9 9h-3v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7H3Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9 21v-6h6v6"/>',

        'cpu' => '<rect fill="currentColor" fill-opacity="0.12" x="7" y="7" width="10" height="10" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="7" y="7" width="10" height="10" rx="1"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="2" width="20" height="20" rx="3"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 2v5M12 17v5M2 12h5M17 12h5"/>',

        // ══════════════════════════════════════════════════════════════════
        // GENERAL / MISC
        // ══════════════════════════════════════════════════════════════════

        'globe' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/>',

        'map-pin' => '<path fill="currentColor" fill-opacity="0.12" d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Z"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="9" r="2.5"/>',

        'sun' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="5"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>',

        'moon' => '<path fill="currentColor" fill-opacity="0.12" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',

        'help' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><circle fill="currentColor" cx="12" cy="17" r="1.25"/>',

        'spark' => '<path fill="currentColor" fill-opacity="0.2" d="m12 2 2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m12 2 2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6L12 2Z"/>',

        'zap' => '<path fill="currentColor" fill-opacity="0.15" d="M13 2 3 14h9l-1 8 10-12h-9Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h9l-1 8 10-12h-9L13 2Z"/>',

        'ai' => '<circle fill="currentColor" fill-opacity="0.12" cx="12" cy="12" r="9"/><circle stroke="currentColor" fill="none" stroke-width="2" cx="12" cy="12" r="9"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M9 9h.01M15 9h.01"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M8 14s1.5 2 4 2 4-2 4-2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M7 8c-1-1-2-1-3 0m13 0c1-1 2-1 3 0M7 16c-1 1-2 1-3 2m13-2c1 1 2 1 3 2"/>',

        'puzzle' => '<path fill="currentColor" fill-opacity="0.12" d="M4 7h3a1 1 0 0 0 1-1V4a2 2 0 1 1 4 0v2a1 1 0 0 0 1 1h3v3a1 1 0 0 1-1 1h-2a2 2 0 1 0 0 4h2a1 1 0 0 1 1 1v3H7v-3a1 1 0 0 1 1-1h2a2 2 0 1 0 0-4H8a1 1 0 0 1-1-1V7Z"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 7h3a1 1 0 0 0 1-1V4a2 2 0 1 1 4 0v2a1 1 0 0 0 1 1h3v3a1 1 0 0 1-1 1h-2a2 2 0 1 0 0 4h2a1 1 0 0 1 1 1v3H7v-3a1 1 0 0 1 1-1h2a2 2 0 1 0 0-4H8a1 1 0 0 1-1-1V7Z"/>',

        'tenant' => '<rect fill="currentColor" fill-opacity="0.12" x="2" y="7" width="20" height="14" rx="2"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="2" y="7" width="20" height="14" rx="2"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2m-2 5h12m-6 0v5"/>',

        'module' => '<rect fill="currentColor" fill-opacity="0.12" x="3" y="3" width="7" height="7" rx="1.5"/><rect fill="currentColor" fill-opacity="0.12" x="14" y="3" width="7" height="7" rx="1.5"/><rect fill="currentColor" fill-opacity="0.12" x="3" y="14" width="7" height="7" rx="1.5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" d="M14 14h7v7h-7z"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="3" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="14" y="3" width="7" height="7" rx="1.5"/><rect stroke="currentColor" fill="none" stroke-width="2" stroke-linejoin="round" x="3" y="14" width="7" height="7" rx="1.5"/><path stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" d="M17.5 14 17.5 21m-3.5-3.5h7"/>',

    ];

    public static function get(string $name): ?string
    {
        return self::$icons[$name] ?? null;
    }

    public static function exists(string $name): bool
    {
        return isset(self::$icons[$name]);
    }

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::$icons);
    }
}
