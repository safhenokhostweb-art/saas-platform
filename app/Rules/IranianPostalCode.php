<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianPostalCode implements ValidationRule
{
    /**
     * دو رقم اول کدپستی → استان و شهر (بر اساس کد رهسپاری پست ایران - مرجع mobilebnk و پست)
     * در پنج رقم اول از اعداد ۰ و ۲ استفاده نمی‌شود.
     */
    protected static array $regionByPrefix = [
        '11' => ['province' => 'تهران', 'city' => 'تهران'],
        '13' => ['province' => 'تهران', 'city' => 'تهران'],
        '14' => ['province' => 'تهران', 'city' => 'تهران'],
        '15' => ['province' => 'تهران', 'city' => 'تهران'],
        '16' => ['province' => 'تهران', 'city' => 'تهران'],
        '17' => ['province' => 'تهران', 'city' => 'تهران'],
        '18' => ['province' => 'تهران', 'city' => 'تهران'],
        '19' => ['province' => 'تهران', 'city' => 'تهران'],
        '31' => ['province' => 'اصفهان', 'city' => 'اصفهان'],
        '33' => ['province' => 'اصفهان', 'city' => 'اصفهان'],
        '34' => ['province' => 'قزوین', 'city' => 'قزوین'],
        '35' => ['province' => 'سمنان', 'city' => 'سمنان'],
        '36' => ['province' => 'سمنان', 'city' => 'شاهرود'],
        '37' => ['province' => 'قم', 'city' => 'قم'],
        '38' => ['province' => 'مرکزی', 'city' => 'اراک'],
        '39' => ['province' => 'مرکزی', 'city' => 'ساوه'],
        '41' => ['province' => 'گیلان', 'city' => 'رشت'],
        '43' => ['province' => 'گیلان', 'city' => 'فومن'],
        '44' => ['province' => 'گیلان', 'city' => 'لاهیجان'],
        '45' => ['province' => 'زنجان', 'city' => 'زنجان'],
        '46' => ['province' => 'مازندران', 'city' => 'آمل'],
        '47' => ['province' => 'مازندران', 'city' => 'بابل'],
        '48' => ['province' => 'مازندران', 'city' => 'ساری'],
        '49' => ['province' => 'گلستان', 'city' => 'گرگان'],
        '51' => ['province' => 'آذربایجان شرقی', 'city' => 'تبریز'],
        '53' => ['province' => 'آذربایجان شرقی', 'city' => 'میانه'],
        '54' => ['province' => 'آذربایجان شرقی', 'city' => 'مرند'],
        '55' => ['province' => 'آذربایجان شرقی', 'city' => 'مراغه'],
        '56' => ['province' => 'اردبیل', 'city' => 'اردبیل'],
        '57' => ['province' => 'آذربایجان غربی', 'city' => 'ارومیه'],
        '58' => ['province' => 'آذربایجان غربی', 'city' => 'خوی'],
        '59' => ['province' => 'آذربایجان غربی', 'city' => 'مهاباد'],
        '61' => ['province' => 'خوزستان', 'city' => 'اهواز'],
        '63' => ['province' => 'خوزستان', 'city' => 'رامهرمز'],
        '64' => ['province' => 'خوزستان', 'city' => 'دزفول'],
        '65' => ['province' => 'همدان', 'city' => 'همدان'],
        '66' => ['province' => 'کردستان', 'city' => 'سنندج'],
        '67' => ['province' => 'کرمانشاه', 'city' => 'کرمانشاه'],
        '68' => ['province' => 'لرستان', 'city' => 'خرم‌آباد'],
        '69' => ['province' => 'ایلام', 'city' => 'ایلام'],
        '71' => ['province' => 'فارس', 'city' => 'شیراز'],
        '73' => ['province' => 'فارس', 'city' => 'کازرون'],
        '74' => ['province' => 'فارس', 'city' => 'جهرم'],
        '75' => ['province' => 'بوشهر', 'city' => 'بوشهر'],
        '76' => ['province' => 'کرمان', 'city' => 'کرمان'],
        '77' => ['province' => 'کرمان', 'city' => 'رفسنجان'],
        '78' => ['province' => 'کرمان', 'city' => 'سیرجان'],
        '79' => ['province' => 'هرمزگان', 'city' => 'بندرعباس'],
        '81' => ['province' => 'اصفهان', 'city' => 'اصفهان'],
        '83' => ['province' => 'اصفهان', 'city' => 'نائین'],
        '84' => ['province' => 'اصفهان', 'city' => 'خمینی‌شهر'],
        '85' => ['province' => 'اصفهان', 'city' => 'نجف‌آباد'],
        '86' => ['province' => 'اصفهان', 'city' => 'شهرضا'],
        '87' => ['province' => 'اصفهان', 'city' => 'کاشان'],
        '88' => ['province' => 'چهارمحال و بختیاری', 'city' => 'شهرکرد'],
        '89' => ['province' => 'یزد', 'city' => 'یزد'],
        '91' => ['province' => 'خراسان رضوی', 'city' => 'مشهد'],
        '93' => ['province' => 'خراسان رضوی', 'city' => 'نیشابور'],
        '94' => ['province' => 'خراسان شمالی', 'city' => 'بجنورد'],
        '95' => ['province' => 'خراسان رضوی', 'city' => 'تربت حیدریه'],
        '96' => ['province' => 'خراسان رضوی', 'city' => 'سبزوار'],
        '97' => ['province' => 'خراسان جنوبی', 'city' => 'بیرجند'],
        '98' => ['province' => 'سیستان و بلوچستان', 'city' => 'زاهدان'],
        '99' => ['province' => 'سیستان و بلوچستان', 'city' => 'ایرانشهر'],
        '26' => ['province' => 'قزوین', 'city' => 'قزوین'],
        '27' => ['province' => 'قزوین', 'city' => 'قزوین'],
        '28' => ['province' => 'البرز', 'city' => 'کرج'],
        '29' => ['province' => 'البرز', 'city' => 'کرج'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clean = self::normalize($value);

        if ($clean === null) {
            $fail('کد پستی باید دقیقاً ۱۰ رقم باشد (فقط اعداد). مثال: ۱۳۱۴۵۶۷۸۹۰');
            return;
        }

        $firstFive = substr($clean, 0, 5);
        for ($i = 0; $i < 5; $i++) {
            $d = $firstFive[$i];
            if ($d === '0' || $d === '2') {
                $fail('در پنج رقم اول کد پستی از اعداد ۰ و ۲ استفاده نمی‌شود (طبق استاندارد پست ایران).');
                return;
            }
        }

        if (count(array_unique(str_split($clean))) === 1) {
            $fail('کد پستی معتبر نیست؛ همهٔ ارقام نمی‌توانند یکسان باشند.');
            return;
        }

        $prefix = substr($clean, 0, 2);
        if (!isset(self::$regionByPrefix[$prefix])) {
            $fail('کد پستی معتبر نیست؛ دو رقم اول با هیچ منطقهٔ شناخته‌شده‌ای مطابقت ندارد.');
        }
    }

    /**
     * نرمال‌سازی و استخراج ۱۰ رقم خالص؛ در صورت نامعتبر بودن null برمی‌گرداند.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $persian = ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'];
        $clean = strtr($value, $persian);
        $clean = preg_replace('/[^0-9]/', '', $clean);
        return strlen($clean) === 10 ? $clean : null;
    }

    /**
     * تشخیص استان بر اساس دو رقم اول کدپستی.
     */
    public static function getRegion(?string $postalCode): ?string
    {
        $info = self::getRegionInfo($postalCode);
        return $info ? $info['province'] : null;
    }

    /**
     * تشخیص استان و شهر بر اساس دو رقم اول کدپستی.
     * خروجی: ['province' => '...', 'city' => '...'] یا null
     */
    public static function getRegionInfo(?string $postalCode): ?array
    {
        $clean = self::normalize($postalCode);
        if ($clean === null) {
            return null;
        }
        $prefix = substr($clean, 0, 2);
        return self::$regionByPrefix[$prefix] ?? null;
    }

    /**
     * متن خوانا برای نمایش: استان و شهر
     */
    public static function getRegionLabel(?string $postalCode): ?string
    {
        $info = self::getRegionInfo($postalCode);
        if (!$info) {
            return null;
        }
        return 'استان ' . $info['province'] . '، شهر ' . $info['city'];
    }

    /**
     * لیست دو رقم اول → استان و شهر (برای استفاده در فرانت)
     */
    public static function getRegionMap(): array
    {
        return self::$regionByPrefix;
    }
}
