# پرامپت استاندارد برای تحلیل/تکمیل ماژول

این متن را در Cursor برای یک ماژول مشخص (همراه با نام مسیر یا کلید ماژول) استفاده کنید.

Analyze this module and compare it with its rule file in `.cursor/rules`.

Requirements:

- Detect all existing features and architecture.
- If the current implementation is more complete than the suggested structure, keep it unchanged.
- Only add missing enterprise-grade features.
- Never remove working code.
- Preserve backward compatibility.
- Add tests for new functionality.
- Update `docs/modules/<module>.md` and `docs/CHANGELOG.md` only if changes are made.
- If the module is already complete, make no code changes and report **"No changes required"**.

**جایگزین‌ها:** `<module>` = نام فایل/کلید ماژول (مثلاً `billing` برای `docs/modules/billing.md` و قانون متناظر در `.cursor/rules`).
