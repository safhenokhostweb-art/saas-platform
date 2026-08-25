<?php

namespace Tests\Unit;

use App\Models\Theme;
use PHPUnit\Framework\TestCase;

/**
 * Template Builder commercial expansion — 'business' and 'services' were
 * genuinely missing from the theme category taxonomy (verified against the
 * customer's requested category list: business, corporate, ecommerce,
 * personal-brand, services, education, restaurant, medical, real estate,
 * portfolio). 'ecommerce' and 'personal-brand' were NOT added as new
 * categories — they already exist under this taxonomy's existing terms
 * ('store' and 'personal' respectively); adding near-duplicate categories
 * would fragment the catalog instead of extending it.
 */
class ThemeCategoryLabelTest extends TestCase
{
    public function test_business_category_constant_and_label_exist(): void
    {
        $this->assertSame('business', Theme::CATEGORY_BUSINESS);
        $this->assertSame('کسب‌وکار', Theme::categoryLabel(Theme::CATEGORY_BUSINESS));
    }

    public function test_services_category_constant_and_label_exist(): void
    {
        $this->assertSame('services', Theme::CATEGORY_SERVICES);
        $this->assertSame('خدمات', Theme::categoryLabel(Theme::CATEGORY_SERVICES));
    }

    public function test_existing_categories_are_unaffected(): void
    {
        $this->assertSame('شرکتی', Theme::categoryLabel(Theme::CATEGORY_CORPORATE));
        $this->assertSame('فروشگاه', Theme::categoryLabel(Theme::CATEGORY_STORE));
        $this->assertSame('شخصی', Theme::categoryLabel(Theme::CATEGORY_PERSONAL));
    }

    public function test_unknown_category_falls_back_to_the_raw_value(): void
    {
        $this->assertSame('not-a-real-category', Theme::categoryLabel('not-a-real-category'));
    }
}
