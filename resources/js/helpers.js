/**
 * تبدیل اعداد انگلیسی (0-9) و عربی (٠-٩) به فارسی در زمان رندر.
 * روی دیتای خام تغییری ایجاد نمی‌کند.
 * از این تابع فقط برای نمایش استفاده کنید، نه برای data attributes یا value input.
 *
 * @param {string|number|null} value - مقدار ورودی
 * @returns {string}
 */
export function toPersianNumbers(value) {
    if (value === null || value === undefined || value === '') {
        return String(value ?? '');
    }

    const input = String(value);

    // تبدیل یک به یک کاراکترها
    const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    const englishDigits = '0123456789';
    const arabicDigits = '٠١٢٣٤٥٦٧٨٩';

    let result = '';
    for (let i = 0; i < input.length; i++) {
        const char = input[i];
        const engIndex = englishDigits.indexOf(char);
        const arbIndex = arabicDigits.indexOf(char);
        if (engIndex !== -1) {
            result += persianDigits[engIndex];
        } else if (arbIndex !== -1) {
            result += persianDigits[arbIndex];
        } else {
            result += char;
        }
    }

    return result;
}

/**
 * تابع کمکی برای استفاده در قالب‌های Vue/JSX.
 * فقط برای نمایش متن، نه attributes.
 *
 * @example
 * <span>{{ toPersianNumbers(item.price) }}</span>
 */
export default toPersianNumbers;