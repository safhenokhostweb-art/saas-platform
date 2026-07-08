# LOCAL FIX REPORT
Date: 2026-06-21

## Fix 1 — customer-site: Missing Migrations (APPLIED)
**Error**: `SQLSTATE[42S02]: Table 'customer_site.homepage_videos' doesn't exist`
**Cause**: 12 migrations created since last local sync were never run locally
**Fix**: `php artisan migrate --force` run from customer-site directory
**Migrations Applied** (all succeeded):
- 2026_06_13_100000_create_homepage_videos_table
- 2026_06_13_200000_create_translations_tables
- 2026_06_13_300000_create_installment_tables
- 2026_06_13_400000_create_access_list_tables
- 2026_06_13_500000_create_analytics_page_views_table
- 2026_06_14_100000_create_admin_notifications_table
- 2026_06_14_200000_add_builder_content_to_posts_and_products
- 2026_06_15_100000_create_crm_tables
- 2026_06_15_200000_add_protection_to_pages_table
- 2026_06_15_200000_create_inventory_tables
- 2026_06_15_200001_create_page_revisions_table
- 2026_06_19_300001_create_site_theme_templates_table
**Status**: ✅ RESOLVED

## Fix 2 — tb-ai-studio: Missing Hosts Entry (MANUAL REQUIRED)
**Error**: `The remote name could not be resolved: 'tb-ai-studio.test'`
**Cause**: Apache vhost exists but no Windows hosts file entry for the domain
**Fix**: Add the following line to `C:\Windows\System32\drivers\etc\hosts` as Administrator:
```
127.0.0.1      tb-ai-studio.test   #laragon magic!
```
**Options** (choose one):
1. Open Notepad as Administrator → open hosts file → add line → save
2. Run Laragon as Administrator → it will auto-update hosts on next restart
3. Run in elevated PowerShell:
   ```powershell
   Add-Content -Path "C:\Windows\System32\drivers\etc\hosts" -Value "127.0.0.1      tb-ai-studio.test   #laragon magic!"
   ```
**Status**: ⚠️ PENDING — requires manual admin action
