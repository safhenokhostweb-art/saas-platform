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
/* همان الگوی customer-site: hover/active با شفافیت روی accent (نه پس‌زمینهٔ جدا) */
#admin-sidebar {
  --platform-sidebar-bg-top: {{ $bgTop }};
  --platform-sidebar-bg-bottom: {{ $bgBottom }};
  --platform-sidebar-text: {{ $text }};
  --platform-sidebar-muted: {{ $muted }};
  --platform-sidebar-accent: {{ $accent }};
  --platform-sidebar-border: {{ $border }};
  --platform-sidebar-hover: color-mix(in srgb, var(--platform-sidebar-accent) 20%, transparent);
  --platform-sidebar-active: color-mix(in srgb, var(--platform-sidebar-accent) 32%, transparent);
}
#admin-sidebar .admin-sidebar-scroll-area {
  background: linear-gradient(165deg, var(--platform-sidebar-bg-top) 0%, var(--platform-sidebar-bg-bottom) 100%) !important;
  border-left: 1px solid color-mix(in srgb, var(--platform-sidebar-border) 55%, transparent) !important;
  box-shadow: -4px 0 24px color-mix(in srgb, var(--platform-sidebar-bg-bottom) 50%, transparent) !important;
}
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center.flex-shrink-0 {
  background: color-mix(in srgb, var(--platform-sidebar-bg-bottom) 78%, rgba(0,0,0,0.85) 22%) !important;
  border-bottom-color: color-mix(in srgb, var(--platform-sidebar-border) 45%, transparent) !important;
}
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center .text-slate-100,
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center .sidebar-text {
  color: var(--platform-sidebar-text) !important;
}
#admin-sidebar .admin-sidebar-scroll-area > div.flex.items-center svg {
  color: var(--platform-sidebar-accent) !important;
}
/* گروه‌های منو — جمع‌شونده (مثل customer-site) */
#admin-sidebar .admin-sidebar-group {
  margin-bottom: 0.2rem;
}
#admin-sidebar .admin-sidebar-group:last-of-type {
  margin-bottom: 0;
}
/* حذف mb-4 از x-core-ui::sidebar-group wrapper */
#admin-sidebar .admin-sidebar-item-list > div {
  margin-bottom: 0 !important;
}
#admin-sidebar .sidebar-group-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border: none;
  background: transparent;
  padding: 0;
  margin: 0 0 0.1rem 0;
  cursor: pointer;
  color: inherit;
  text-align: right;
  transition: background 0.15s ease, color 0.15s ease;
}
#admin-sidebar .sidebar-group-toggle:hover {
  background: var(--platform-sidebar-hover) !important;
  border-radius: 0.5rem;
}
#admin-sidebar .sidebar-group-label {
  display: block;
  flex: 1;
  padding: 0.18rem 0.65rem 0.22rem;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--platform-sidebar-muted) !important;
  border-bottom: 1px solid var(--platform-sidebar-border) !important;
  margin-bottom: 0;
}
#admin-sidebar .sidebar-group-toggle[aria-expanded="true"] .sidebar-group-label {
  color: var(--platform-sidebar-accent) !important;
  border-bottom-color: color-mix(in srgb, var(--platform-sidebar-accent) 35%, transparent) !important;
}
#admin-sidebar .sidebar-group-caret {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  color: var(--platform-sidebar-muted);
  transition: transform 0.15s ease, color 0.15s ease;
}
#admin-sidebar .sidebar-group-caret.is-open {
  transform: rotate(180deg);
  color: var(--platform-sidebar-accent);
}
#admin-sidebar .sidebar-group-items.is-collapsed {
  display: none;
}
#admin-sidebar .admin-sidebar-item-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
/* نوار کناری جمع‌شده (فقط آیکن): دکمهٔ گروه مخفی، همهٔ لینک‌ها نمایش */
#admin-sidebar[data-collapsed="true"] .sidebar-group-toggle {
  display: none;
}
#admin-sidebar[data-collapsed="true"] .sidebar-group-items {
  display: flex !important;
  flex-direction: column;
  gap: 4px;
}
#admin-sidebar[data-collapsed="true"] .admin-sidebar-group {
  margin-bottom: 0.5rem;
}
#admin-sidebar .sidebar-link {
  color: var(--platform-sidebar-text) !important;
  padding: 0.45rem 0.875rem !important;
  gap: 0.6rem;
  align-items: center;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}
#admin-sidebar .sidebar-link:hover {
  background: var(--platform-sidebar-hover) !important;
  color: var(--platform-sidebar-text) !important;
}
#admin-sidebar .sidebar-link.active {
  background: var(--platform-sidebar-active) !important;
  color: var(--platform-sidebar-text) !important;
  font-weight: 600 !important;
  box-shadow: inset -3px 0 0 var(--platform-sidebar-accent) !important;
}
#admin-sidebar .sidebar-link svg {
  width: 1.25rem;
  height: 1.25rem;
  min-width: 1.25rem;
  flex-shrink: 0;
  color: var(--platform-sidebar-muted) !important;
}
#admin-sidebar .sidebar-link:hover svg,
#admin-sidebar .sidebar-link.active svg {
  color: var(--platform-sidebar-accent) !important;
}
#admin-sidebar .pt-4.mt-4.border-t {
  border-top: 1px solid var(--platform-sidebar-border) !important;
}

/* ═══ رنگ‌بندی گروه‌های منو — از Design Token Authority ═══ */
#admin-sidebar .admin-sidebar-group { --grp-color: var(--platform-sidebar-accent); }

/* ۱ داشبورد → accent تم */
#admin-sidebar .admin-sidebar-group:nth-child(1)  { --grp-color: var(--ui-indigo);       }
/* ۲ مشتریان / کاربران → secondary */
#admin-sidebar .admin-sidebar-group:nth-child(2)  { --grp-color: var(--ui-purple);        }
/* ۳ سایت‌ها / پروژه‌ها → info */
#admin-sidebar .admin-sidebar-group:nth-child(3)  { --grp-color: var(--ui-info);          }
/* ۴ فروش / صورتحساب → success */
#admin-sidebar .admin-sidebar-group:nth-child(4)  { --grp-color: var(--ui-success);       }
/* ۵ پشتیبانی / تیکت‌ها → danger */
#admin-sidebar .admin-sidebar-group:nth-child(5)  { --grp-color: var(--ui-danger);        }
/* ۶ فرم‌ها / برگه‌ها → warning */
#admin-sidebar .admin-sidebar-group:nth-child(6)  { --grp-color: var(--ui-warning);       }
/* ۷ محتوا / مقالات → info-hover */
#admin-sidebar .admin-sidebar-group:nth-child(7)  { --grp-color: var(--ui-info-hover);    }
/* ۸ تنظیمات سیستم → neutral */
#admin-sidebar .admin-sidebar-group:nth-child(8)  { --grp-color: var(--ui-neutral);       }
/* ۹ بازاریابی → warning-hover */
#admin-sidebar .admin-sidebar-group:nth-child(9)  { --grp-color: var(--ui-warning-hover); }
/* ۱۰ گزارش‌ها / آمار → purple-hover */
#admin-sidebar .admin-sidebar-group:nth-child(10) { --grp-color: var(--ui-purple-hover);  }

#admin-sidebar .admin-sidebar-group .sidebar-group-label {
  color: var(--grp-color) !important;
  border-bottom-color: color-mix(in srgb, var(--grp-color) 30%, transparent) !important;
}
#admin-sidebar .admin-sidebar-group .sidebar-group-caret {
  color: var(--grp-color) !important;
  opacity: 0.7;
}
#admin-sidebar .admin-sidebar-group .sidebar-link:hover {
  background: color-mix(in srgb, var(--grp-color) 18%, transparent) !important;
  color: var(--platform-sidebar-text) !important;
}
#admin-sidebar .admin-sidebar-group .sidebar-link.active {
  background: color-mix(in srgb, var(--grp-color) 28%, transparent) !important;
  color: var(--platform-sidebar-text) !important;
  box-shadow: inset -3px 0 0 var(--grp-color) !important;
}
#admin-sidebar .admin-sidebar-group .sidebar-link:hover svg,
#admin-sidebar .admin-sidebar-group .sidebar-link.active svg {
  color: var(--grp-color) !important;
}
</style>
