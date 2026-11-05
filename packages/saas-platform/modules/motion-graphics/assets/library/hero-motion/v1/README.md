# Corporate Hero Motion — موشن قهرمان سازمانی

داشبورد هوشمند سازمان با نمودار رشد، کارت امنیت و اعتماد، پایداری سرویس و پشتیبانی شبانه‌روزی برای صفحه نخست سایت‌های سازمانی و شرکتی.

- Slug: `hero-motion`, library version `v1`, engine `svg-js` (CSS-animated SVG, no script).
- License tier: free (included with the motion-graphics module license).
- Canvas: 960×540 (`viewBox="0 0 960 540"`), scales to any width; RTL layout, Persian digits.
- Light and dark: `prefers-color-scheme` swaps the `--asset-*` tokens in `<style id="asset-theme">`.
- Motion: an intro that plays once, then ambient loops. Only `transform`, `opacity` and stroke-dash properties animate (no filters, no SMIL). `prefers-reduced-motion: reduce` stops every animation and leaves the complete final frame.
- Delivery: served by the platform at `/motion/hero-motion/v1/animation.svg` (MotionLibraryAssetController) straight from this directory; there is no other copy. Change the art only by adding a new version directory; never edit a published version in place.
- `preview.svg` is the same artwork with animation disabled.
