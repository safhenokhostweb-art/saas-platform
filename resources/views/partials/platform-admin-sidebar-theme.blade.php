@php
    $t = \App\Helpers\PlatformSettings::allTheme();
    $defaults = \App\Helpers\PlatformSettings::THEME_KEYS;
    $bgTop = $t['theme_sidebar_bg'] ?? $defaults['theme_sidebar_bg'];
    $bgBottom = $t['theme_sidebar_bg_bottom'] ?? $defaults['theme_sidebar_bg_bottom'];
    $text = $t['theme_sidebar_text'] ?? $defaults['theme_sidebar_text'];
    $muted = $t['theme_sidebar_muted'] ?? $defaults['theme_sidebar_muted'];
    $accent = $t['theme_sidebar_accent'] ?? $defaults['theme_sidebar_accent'];
    $border = $t['theme_sidebar_border'] ?? $defaults['theme_sidebar_border'];
@endphp
<style id="platform-admin-sidebar-theme">
#admin-sidebar {
  --platform-sidebar-bg-top: {{ $bgTop }};
  --platform-sidebar-bg-bottom: {{ $bgBottom }};
  --platform-sidebar-text: {{ $text }};
  --platform-sidebar-muted: {{ $muted }};
  --platform-sidebar-accent: {{ $accent }};
  --platform-sidebar-border: {{ $border }};
  --platform-sidebar-hover: color-mix(in srgb, var(--platform-sidebar-accent) 15%, transparent);
  --platform-sidebar-active: color-mix(in srgb, var(--platform-sidebar-accent) 28%, transparent);
}

/* ─── پس‌زمینه سایدبار ─── */
#admin-sidebar .admin-sidebar-scroll-area {
  background: linear-gradient(165deg, var(--platform-sidebar-bg-top) 0%, var(--platform-sidebar-bg-bottom) 100%) !important;
  border-left: 1px solid color-mix(in srgb, var(--platform-sidebar-border) 55%, transparent) !important;
  box-shadow: -4px 0 24px color-mix(in srgb, var(--platform-sidebar-bg-bottom) 50%, transparent) !important;
}

/* ─── هدر / لوگو ─── */
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center.shrink-0 {
  background: color-mix(in srgb, var(--platform-sidebar-bg-bottom) 78%, rgba(0,0,0,0.85) 22%) !important;
  border-bottom-color: color-mix(in srgb, var(--platform-sidebar-border) 45%, transparent) !important;
}
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center.shrink-0 .sidebar-text {
  color: var(--platform-sidebar-text) !important;
}
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center.shrink-0 svg {
  color: var(--platform-sidebar-accent) !important;
}

/* ═══════════════════════════════════════════
   رنگ‌بندی گروه‌ها — هر گروه رنگ اختصاصی دارد
   ═══════════════════════════════════════════ */
#admin-sidebar .admin-sidebar-group { --grp-color: var(--platform-sidebar-accent); }
#admin-sidebar .admin-sidebar-group:nth-child(1)  { --grp-color: var(--ui-indigo);       }
#admin-sidebar .admin-sidebar-group:nth-child(2)  { --grp-color: var(--ui-purple);        }
#admin-sidebar .admin-sidebar-group:nth-child(3)  { --grp-color: var(--ui-info);          }
#admin-sidebar .admin-sidebar-group:nth-child(4)  { --grp-color: var(--ui-success);       }
#admin-sidebar .admin-sidebar-group:nth-child(5)  { --grp-color: var(--ui-danger);        }
#admin-sidebar .admin-sidebar-group:nth-child(6)  { --grp-color: var(--ui-warning);       }
#admin-sidebar .admin-sidebar-group:nth-child(7)  { --grp-color: var(--ui-info-hover);    }
#admin-sidebar .admin-sidebar-group:nth-child(8)  { --grp-color: var(--ui-neutral, #94a3b8); }
#admin-sidebar .admin-sidebar-group:nth-child(9)  { --grp-color: var(--ui-warning-hover); }
#admin-sidebar .admin-sidebar-group:nth-child(10) { --grp-color: var(--ui-purple-hover);  }

/* ─── گروه (wrapper) ─── */
#admin-sidebar .admin-sidebar-group {
  margin-bottom: 0;
}

/* ─── لیست آیتم‌ها (items مستقیم، بدون wrapper mb-4) ─── */
#admin-sidebar .admin-sidebar-item-list {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0.15rem 0 0.3rem 0;
}
#admin-sidebar .sidebar-group-items.is-collapsed {
  display: none !important;
}

/* ─── دکمه گروه (toggle) — طراحی جدید ─── */
#admin-sidebar .sidebar-group-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  border: none;
  border-right: 3px solid color-mix(in srgb, var(--grp-color) 60%, transparent);
  background: color-mix(in srgb, var(--grp-color) 8%, transparent);
  padding: 0.35rem 0.75rem 0.35rem 0.625rem;
  margin: 0.3rem 0 0.08rem 0;
  cursor: pointer;
  text-align: right;
  transition: background 0.18s ease, border-color 0.18s ease;
  border-radius: 0 0.5rem 0.5rem 0;
  color: inherit;
}
#admin-sidebar .sidebar-group-toggle:hover {
  background: color-mix(in srgb, var(--grp-color) 16%, transparent) !important;
  border-right-color: var(--grp-color) !important;
}
#admin-sidebar .sidebar-group-toggle[aria-expanded="true"] {
  background: color-mix(in srgb, var(--grp-color) 12%, transparent) !important;
  border-right-color: var(--grp-color) !important;
}

/* ─── لیبل گروه — بزرگ‌تر، خوانا، بدون uppercase ─── */
#admin-sidebar .sidebar-group-label {
  display: block;
  flex: 1;
  font-size: 0.82rem !important;
  font-weight: 700;
  letter-spacing: 0.01em;
  text-transform: none !important;
  color: var(--grp-color) !important;
  border-bottom: none !important;
  padding: 0 !important;
  margin: 0;
  line-height: 1.4;
}

/* ─── فلش گروه ─── */
#admin-sidebar .sidebar-group-caret {
  width: 0.9rem;
  height: 0.9rem;
  min-width: 0.9rem;
  flex-shrink: 0;
  color: color-mix(in srgb, var(--grp-color) 70%, transparent);
  transition: transform 0.22s ease, color 0.15s ease;
}
#admin-sidebar .sidebar-group-caret.is-open {
  transform: rotate(180deg);
  color: var(--grp-color);
}

/* ─── سایدبار جمع‌شده (فقط آیکن) ─── */
#admin-sidebar[data-collapsed="true"] .sidebar-group-toggle {
  display: none;
}
#admin-sidebar[data-collapsed="true"] .sidebar-group-items {
  display: flex !important;
  flex-direction: column;
  gap: 3px;
}
#admin-sidebar[data-collapsed="true"] .admin-sidebar-group {
  margin-bottom: 0.35rem;
}

/* ─── لینک‌های ناوبری ─── */
#admin-sidebar .sidebar-link {
  color: var(--platform-sidebar-text) !important;
  padding: 0.42rem 0.875rem !important;
  gap: 0.6rem;
  align-items: center;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
  border-radius: 0.5rem !important;
}
#admin-sidebar .sidebar-link:hover {
  background: color-mix(in srgb, var(--grp-color) 15%, transparent) !important;
  color: var(--platform-sidebar-text) !important;
  transform: none !important;
}
#admin-sidebar .sidebar-link.active {
  background: color-mix(in srgb, var(--grp-color) 25%, transparent) !important;
  color: var(--platform-sidebar-text) !important;
  font-weight: 600 !important;
  box-shadow: inset -3px 0 0 var(--grp-color) !important;
}
#admin-sidebar .sidebar-link svg {
  width: 1.1rem;
  height: 1.1rem;
  min-width: 1.1rem;
  flex-shrink: 0;
  color: color-mix(in srgb, var(--grp-color) 55%, var(--platform-sidebar-muted)) !important;
}
#admin-sidebar .sidebar-link:hover svg,
#admin-sidebar .sidebar-link.active svg {
  color: var(--grp-color) !important;
}

/* ─── جداکننده پایین سایدبار ─── */
#admin-sidebar .sidebar-bottom-section {
  border-top: 1px solid color-mix(in srgb, var(--platform-sidebar-border) 50%, transparent);
  padding-top: 0.5rem;
  margin-top: 0.5rem;
}

/* ─── دکمه collapse داخل سایدبار ─── */
#admin-sidebar .sidebar-collapse-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.38rem 0.875rem;
  background: transparent;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  color: color-mix(in srgb, var(--platform-sidebar-muted) 70%, transparent);
  font-size: 0.78rem;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  text-align: right;
  margin-bottom: 0.35rem;
}
#admin-sidebar .sidebar-collapse-btn:hover {
  background: color-mix(in srgb, var(--platform-sidebar-muted) 15%, transparent);
  color: var(--platform-sidebar-text);
}
#admin-sidebar[data-collapsed="true"] .sidebar-collapse-btn {
  justify-content: center;
  padding: 0.38rem;
}
#admin-sidebar[data-collapsed="true"] .sidebar-collapse-btn .sidebar-collapse-text {
  display: none;
}

/* ─── خروج ─── */
#admin-sidebar .pt-2.mt-2.border-t,
#admin-sidebar .sidebar-bottom-section {
  border-top-color: color-mix(in srgb, var(--platform-sidebar-border) 50%, transparent) !important;
}
</style>
