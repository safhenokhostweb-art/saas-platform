<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Acool HVAC — hand-crafted Canvas ThemePack
 * Pixel-perfect replica of elementorkits-1.nextdin.com/acool/
 * Uses new HVAC block types: nav_header, hvac_hero, client_logos,
 * about_section, service_cards, stats_row, trust_badges, project_cards,
 * contact_info, site_footer
 */
class AcoolHvacSeedCommand extends Command
{
    protected $signature = 'acool:seed {--fresh : Delete existing Acool theme and re-seed}';
    protected $description = 'Seed the Acool HVAC Canvas ThemePack into the marketplace';

    public function handle(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║  Acool HVAC — Canvas ThemePack v2.0                      ║');
        $this->line('║  Professional HVAC Service Theme                         ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->line('');

        if ($this->option('fresh')) {
            $t = Theme::where('slug', 'acool-hvac-service')->first();
            if ($t) {
                ThemeMarketplace::where('theme_id', $t->id)->delete();
                $t->delete();
                $this->info('Deleted existing Acool theme.');
            }
        }

        $pages = $this->buildPages();

        $theme = Theme::updateOrCreate(
            ['slug' => 'acool-hvac-service'],
            [
                'name'           => 'Acool – Air Conditioner & HVAC Repair Service',
                'description'    => 'Professional HVAC service theme with navy & sky-blue design. Features split hero, service cards, stats, project portfolio, testimonials and full footer. Perfect for AC repair and HVAC businesses.',
                'category'       => Theme::CATEGORY_INDUSTRIAL,
                'type'           => Theme::TYPE_FULL,
                'source'         => Theme::SOURCE_SEEDER,
                'status'         => Theme::STATUS_PUBLISHED,
                'is_marketplace' => true,
                'license_type'   => Theme::LICENSE_FREE,
                'marketplace_price' => 0,
                'header_blocks'  => [$this->navBlock()],
                'footer_blocks'  => [$this->footerBlock()],
                'pages'          => $pages,
                'global_styles'  => $this->globalStyles(),
                'published_at'   => now(),
            ]
        );

        ThemeMarketplace::updateOrCreate(
            ['theme_id' => $theme->id],
            [
                'marketplace_description' => 'A stunning navy & sky-blue professional theme for HVAC, air conditioning, heating and cooling service businesses. Includes 12 pages with hero section, services, projects, testimonials, FAQ, pricing, blog, and contact.',
                'is_featured'             => true,
                'design_score'            => 92,
                'commerce_score'          => 78,
                'performance_score'       => 90,
                'completeness_score'      => 98,
                'marketplace_score'       => 92,
                'is_new_release'          => true,
                'category_tags'           => ['hvac', 'air-conditioning', 'heating', 'cooling', 'industrial', 'service'],
                'preview_url'             => '',
            ]
        );

        $this->info('✓ Theme published:   ' . $theme->id . ' (acool-hvac-service)');
        $this->info('✓ Pages:             ' . count($pages));
        $this->line('');

        return self::SUCCESS;
    }

    // ── Global styles ─────────────────────────────────────────────────────

    private function globalStyles(): array
    {
        return [
            'colors' => [
                'primary'    => '#1e3a5f',
                'secondary'  => '#0ea5e9',
                'accent'     => '#f97316',
                'dark'       => '#0f172a',
                'light'      => '#f8fafc',
                'text'       => '#1f2937',
                'muted'      => '#6b7280',
            ],
            'fonts' => [
                'heading' => 'Inter, sans-serif',
                'body'    => 'Inter, sans-serif',
            ],
        ];
    }

    // ── Shared blocks ─────────────────────────────────────────────────────

    private function navBlock(): array
    {
        return [
            'id'   => 'nav-acool',
            'type' => 'nav_header',
            'props' => [
                'logo_text' => 'Acool HVAC',
                'phone'     => '+1 (555) 123-4567',
                'cta_text'  => 'Book Service',
                'cta_url'   => '/contact',
                'sticky'    => true,
                'links'     => [
                    ['label' => 'Home',     'url' => '/'],
                    ['label' => 'About',    'url' => '/about'],
                    ['label' => 'Services', 'url' => '/services'],
                    ['label' => 'Projects', 'url' => '/projects'],
                    ['label' => 'Blog',     'url' => '/blog'],
                    ['label' => 'Contact',  'url' => '/contact'],
                ],
            ],
        ];
    }

    private function footerBlock(): array
    {
        return [
            'id'   => 'footer-acool',
            'type' => 'site_footer',
            'props' => [
                'logo_text'  => 'Acool HVAC',
                'tagline'    => 'Professional air conditioning & HVAC repair services you can trust. Available 24/7 for all your heating and cooling needs.',
                'copyright'  => '© ' . date('Y') . ' Acool HVAC Services. All rights reserved.',
                'background' => '#0f172a',
                'links'      => [
                    ['label' => 'Privacy Policy',   'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                ],
                'social_links' => [
                    ['label' => 'Facebook',  'icon' => 'f',  'url' => '#'],
                    ['label' => 'Twitter',   'icon' => 't',  'url' => '#'],
                    ['label' => 'Instagram', 'icon' => 'in', 'url' => '#'],
                    ['label' => 'YouTube',   'icon' => '▶',  'url' => '#'],
                ],
                'columns' => [
                    [
                        'title' => 'Services',
                        'links' => [
                            ['label' => 'AC Repair',         'url' => '/services'],
                            ['label' => 'AC Installation',   'url' => '/services'],
                            ['label' => 'AC Maintenance',    'url' => '/services'],
                            ['label' => 'Heating Systems',   'url' => '/services'],
                            ['label' => 'Emergency Service', 'url' => '/services'],
                        ],
                    ],
                    [
                        'title' => 'Company',
                        'links' => [
                            ['label' => 'About Us',     'url' => '/about'],
                            ['label' => 'Our Projects', 'url' => '/projects'],
                            ['label' => 'Pricing',      'url' => '/pricing'],
                            ['label' => 'FAQ',          'url' => '/faq'],
                            ['label' => 'Contact',      'url' => '/contact'],
                        ],
                    ],
                    [
                        'title' => 'Contact Info',
                        'links' => [
                            ['label' => '📍 123 Cooling Ave, CA',  'url' => '#'],
                            ['label' => '📞 +1 (555) 123-4567',    'url' => 'tel:+15551234567'],
                            ['label' => '✉ info@acoolhvac.com',   'url' => 'mailto:info@acoolhvac.com'],
                            ['label' => '🕐 Mon–Sat 7am–8pm',     'url' => '#'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function ctaBlock(string $title = "Need HVAC Service? We're Available 24/7", string $subtitle = "Don't suffer in the heat or cold. Our certified technicians are ready to help right now."): array
    {
        return [
            'id'    => 'cta-' . Str::random(4),
            'type'  => 'cta',
            'props' => [
                'title'       => $title,
                'subtitle'    => $subtitle,
                'button_text' => 'Schedule Service Now',
                'button_url'  => '/contact',
                'background'  => 'primary',
            ],
            'style' => ['bgColor' => '#1e3a5f', 'textColor' => '#ffffff'],
        ];
    }

    // ── Page builder ──────────────────────────────────────────────────────

    private function buildPages(): array
    {
        return [
            'home'           => ['title' => 'Home',           'slug' => '',               'blocks' => $this->homeBlocks()],
            'about'          => ['title' => 'About',          'slug' => 'about',          'blocks' => $this->aboutBlocks()],
            'services'       => ['title' => 'Services',       'slug' => 'services',       'blocks' => $this->servicesBlocks()],
            'service_detail' => ['title' => 'Service Detail', 'slug' => 'service-detail', 'blocks' => $this->serviceDetailBlocks()],
            'projects'       => ['title' => 'Projects',       'slug' => 'projects',       'blocks' => $this->projectsBlocks()],
            'project_detail' => ['title' => 'Project Detail', 'slug' => 'project-detail', 'blocks' => $this->projectDetailBlocks()],
            'pricing'        => ['title' => 'Pricing',        'slug' => 'pricing',        'blocks' => $this->pricingBlocks()],
            'faq'            => ['title' => 'FAQ',            'slug' => 'faq',            'blocks' => $this->faqBlocks()],
            'blog'           => ['title' => 'Blog',           'slug' => 'blog',           'blocks' => $this->blogBlocks()],
            'contact'        => ['title' => 'Contact',        'slug' => 'contact',        'blocks' => $this->contactBlocks()],
            '404'            => ['title' => '404 Error',      'slug' => '404',            'blocks' => $this->errorBlocks()],
            'single_post'    => ['title' => 'Single Post',    'slug' => 'blog/{slug}',    'blocks' => $this->singlePostBlocks()],
        ];
    }

    // ── Home ─────────────────────────────────────────────────────────────

    private function homeBlocks(): array
    {
        return [
            [
                'id'   => 'home-hero',
                'type' => 'hvac_hero',
                'props' => [
                    'badge'              => 'Professional HVAC Services',
                    'title'              => 'Expert Air Conditioning & HVAC Repair Services',
                    'subtitle'           => 'Trusted by over 5,000 homeowners and businesses. Certified technicians, same-day service, and 100% satisfaction guarantee on every job.',
                    'btn_primary_text'   => 'Get Free Quote',
                    'btn_primary_url'    => '/contact',
                    'btn_secondary_text' => 'Our Services',
                    'btn_secondary_url'  => '/services',
                    'phone'              => '+1 (555) 123-4567',
                    'rating'             => '4.9',
                    'rating_label'       => '500+ Verified Reviews',
                ],
            ],

            [
                'id'   => 'home-partners',
                'type' => 'client_logos',
                'props' => [
                    'title' => 'Our Trusted Brand Partners',
                    'logos' => [
                        ['name' => 'Carrier'], ['name' => 'Trane'],      ['name' => 'Lennox'],
                        ['name' => 'Daikin'],  ['name' => 'Mitsubishi'], ['name' => 'Rheem'],
                        ['name' => 'York'],    ['name' => 'Goodman'],    ['name' => 'Honeywell'],
                    ],
                ],
            ],

            [
                'id'   => 'home-about',
                'type' => 'about_section',
                'props' => [
                    'badge'       => 'About Acool HVAC',
                    'title'       => 'Your Local HVAC Experts Since 2008',
                    'description' => 'We are a family-owned HVAC company with over 15 years of experience serving residential and commercial clients. Our EPA-certified technicians provide reliable, affordable, and efficient air conditioning and heating solutions.',
                    'features'    => [
                        ['label' => 'Licensed & EPA Certified Technicians'],
                        ['label' => 'Same-Day & Emergency Service Available'],
                        ['label' => 'Upfront Pricing — No Hidden Fees'],
                        ['label' => '100% Satisfaction Guarantee'],
                        ['label' => 'All Major Brands Serviced & Installed'],
                    ],
                    'btn_text' => 'Learn More About Us',
                    'btn_url'  => '/about',
                    'stats'    => [
                        ['value' => '15+', 'label' => 'Years Experience'],
                        ['value' => '5K+', 'label' => 'Happy Customers'],
                        ['value' => '98%', 'label' => 'Satisfaction Rate'],
                    ],
                ],
            ],

            [
                'id'   => 'home-services',
                'type' => 'service_cards',
                'props' => [
                    'title'    => 'Our HVAC Services',
                    'subtitle' => 'Comprehensive heating, cooling and ventilation solutions for homes and businesses',
                    'columns'  => 3,
                    'cards'    => [
                        ['icon' => '❄️', 'title' => 'AC Repair',              'description' => 'Fast and reliable repair for all makes and models. Most repairs completed same day.', 'url' => '/services'],
                        ['icon' => '🔧', 'title' => 'AC Installation',        'description' => 'Expert installation of new systems. We help you choose the right unit for your space.', 'url' => '/services'],
                        ['icon' => '🛡️', 'title' => 'Preventive Maintenance', 'description' => 'Annual tune-ups to maximize efficiency, prevent breakdowns, and extend system life.', 'url' => '/services'],
                        ['icon' => '🔥', 'title' => 'Heating Systems',        'description' => 'Furnace repair, heat pump installation, and heating maintenance for year-round comfort.', 'url' => '/services'],
                        ['icon' => '🏢', 'title' => 'Commercial HVAC',        'description' => 'Complete HVAC solutions for offices, retail stores, restaurants, and industrial facilities.', 'url' => '/services'],
                        ['icon' => '🚨', 'title' => 'Emergency Service',      'description' => '24/7 emergency HVAC service. When your system breaks down, we are just a call away.', 'url' => '/services'],
                    ],
                ],
            ],

            [
                'id'   => 'home-stats',
                'type' => 'stats_row',
                'props' => [
                    'title'      => 'Why Thousands Trust Acool HVAC',
                    'subtitle'   => 'Our numbers speak for themselves — quality, reliability and customer satisfaction',
                    'background' => '#1e3a5f',
                    'stats'      => [
                        ['icon' => '⏱️', 'value' => '24/7',   'label' => 'Emergency Support'],
                        ['icon' => '🎓', 'value' => '100%',   'label' => 'Certified Technicians'],
                        ['icon' => '⭐', 'value' => '4.9/5',  'label' => 'Customer Rating'],
                        ['icon' => '🏠', 'value' => '5,000+', 'label' => 'Homes Serviced'],
                    ],
                ],
            ],

            [
                'id'   => 'home-trust',
                'type' => 'trust_badges',
                'props' => [
                    'title'    => 'The Acool Advantage',
                    'subtitle' => 'What makes us different from other HVAC companies',
                    'columns'  => 3,
                    'items'    => [
                        ['icon' => '🏆', 'title' => 'Award-Winning Service',      'description' => 'Recognized as the top HVAC company in the region for 5 consecutive years.'],
                        ['icon' => '💰', 'title' => 'Best Price Guarantee',       'description' => 'We will match or beat any competitor written quote on identical services.'],
                        ['icon' => '🔒', 'title' => 'Fully Insured & Licensed',   'description' => 'All technicians are EPA certified, licensed, and fully insured for your protection.'],
                        ['icon' => '⚡', 'title' => 'Same-Day Service',           'description' => 'Most repairs completed same day. We understand your comfort cannot wait.'],
                        ['icon' => '🌱', 'title' => 'Energy-Efficient Solutions', 'description' => 'We recommend and install energy-efficient systems to reduce your utility bills.'],
                        ['icon' => '📞', 'title' => '24/7 Customer Support',      'description' => 'Our team is available around the clock to answer questions and schedule service.'],
                    ],
                ],
            ],

            [
                'id'   => 'home-projects',
                'type' => 'project_cards',
                'props' => [
                    'title'    => 'Our Recent Projects',
                    'subtitle' => 'Take a look at some of our completed HVAC installations and repairs',
                    'columns'  => 2,
                    'cards'    => [
                        ['category' => 'Residential', 'title' => 'Complete Home AC Replacement',     'description' => 'Replaced aging central AC with a high-efficiency Carrier unit for a 3,200 sq ft home in Riverside.', 'url' => '/projects'],
                        ['category' => 'Commercial',  'title' => 'Office Building HVAC Upgrade',     'description' => 'Designed and installed a new multi-zone HVAC system for a 15,000 sq ft commercial office space.', 'url' => '/projects'],
                        ['category' => 'Residential', 'title' => 'Ductless Mini-Split Installation', 'description' => 'Installed a 5-zone ductless Mitsubishi system for a historic home without existing ductwork.', 'url' => '/projects'],
                        ['category' => 'Commercial',  'title' => 'Restaurant Kitchen Ventilation',   'description' => 'Designed and installed commercial kitchen exhaust and makeup air system for a busy restaurant.', 'url' => '/projects'],
                    ],
                ],
            ],

            $this->ctaBlock(),

            ['id' => 'home-testimonials', 'type' => 'testimonials', 'props' => ['title' => 'What Our Customers Say', 'limit' => 5]],
            ['id' => 'home-blog',         'type' => 'blog_posts',   'props' => ['title' => 'Latest HVAC Tips & News', 'limit' => 3]],

            [
                'id'   => 'home-contact',
                'type' => 'contact_info',
                'props' => [
                    'title'      => 'Get In Touch',
                    'subtitle'   => "We're here to help — call, email, or stop by our office",
                    'background' => '#1e3a5f',
                    'items'      => [
                        ['icon' => '📍', 'label' => 'Address',        'value' => '123 Cooling Avenue, Los Angeles, CA 90001'],
                        ['icon' => '📞', 'label' => 'Phone',          'value' => '+1 (555) 123-4567'],
                        ['icon' => '✉️', 'label' => 'Email',          'value' => 'info@acoolhvac.com'],
                        ['icon' => '🕐', 'label' => 'Business Hours', 'value' => 'Mon–Sat: 7am–8pm, Sun: 9am–5pm'],
                    ],
                ],
            ],

        ];
    }

    // ── About ────────────────────────────────────────────────────────────

    private function aboutBlocks(): array
    {
        return [
            $this->pageHero('About Acool HVAC', 'Your trusted partner for all heating, ventilation, and air conditioning needs'),
            [
                'id'   => 'about-main',
                'type' => 'about_section',
                'props' => [
                    'badge'       => 'Our Story',
                    'title'       => 'Serving Our Community Since 2008',
                    'description' => 'Acool HVAC was founded with a simple mission: provide honest, high-quality HVAC services at fair prices. What started as a one-man operation has grown into a team of 25+ certified technicians serving thousands of homes and businesses across the greater Los Angeles area.',
                    'features'    => [
                        ['label' => 'Over 15 years serving the community'],
                        ['label' => '25+ EPA certified technicians on staff'],
                        ['label' => 'Authorized dealer for major HVAC brands'],
                        ['label' => 'A+ rating with the Better Business Bureau'],
                        ['label' => 'Financing options available (OAC)'],
                    ],
                    'btn_text' => 'Contact Our Team',
                    'btn_url'  => '/contact',
                    'stats'    => [
                        ['value' => '2008', 'label' => 'Founded'],
                        ['value' => '25+',  'label' => 'Technicians'],
                        ['value' => 'A+',   'label' => 'BBB Rating'],
                    ],
                ],
            ],
            [
                'id'   => 'about-stats',
                'type' => 'stats_row',
                'props' => [
                    'title'      => 'Our Track Record Speaks for Itself',
                    'subtitle'   => '15 years of delivering excellence in HVAC services',
                    'background' => '#1e3a5f',
                    'stats'      => [
                        ['icon' => '🏠', 'value' => '5,000+', 'label' => 'Homes & Businesses Served'],
                        ['icon' => '⭐', 'value' => '4.9/5',  'label' => 'Average Customer Rating'],
                        ['icon' => '🎓', 'value' => '25+',    'label' => 'Certified Technicians'],
                        ['icon' => '🏆', 'value' => '12',     'label' => 'Industry Awards'],
                    ],
                ],
            ],
            [
                'id'   => 'about-values',
                'type' => 'trust_badges',
                'props' => [
                    'title'    => 'Our Core Values',
                    'subtitle' => 'The principles that guide everything we do',
                    'columns'  => 3,
                    'items'    => [
                        ['icon' => '🤝', 'title' => 'Integrity',     'description' => 'Honest assessments and upfront pricing. No surprises, no hidden fees.'],
                        ['icon' => '💎', 'title' => 'Quality',       'description' => 'Only the best parts and manufacturer specifications on every job.'],
                        ['icon' => '⚡', 'title' => 'Reliability',   'description' => 'We show up on time, every time. Your time is valuable and we respect that.'],
                        ['icon' => '🎓', 'title' => 'Expertise',     'description' => 'Continuous training to stay current with the latest HVAC technology.'],
                        ['icon' => '🌱', 'title' => 'Sustainability', 'description' => 'Energy-efficient solutions to reduce environmental impact and lower utility costs.'],
                        ['icon' => '👨‍👩‍👧', 'title' => 'Community',  'description' => 'Locally owned and operated. We invest in our community and give back where we can.'],
                    ],
                ],
            ],
            $this->ctaBlock('Ready to Work With Us?', 'Join thousands of satisfied customers who trust Acool HVAC for all their heating and cooling needs.'),
        ];
    }

    // ── Services ─────────────────────────────────────────────────────────

    private function servicesBlocks(): array
    {
        return [
            $this->pageHero('Our HVAC Services', 'Comprehensive heating, cooling and ventilation solutions for residential and commercial clients'),
            [
                'id'   => 'services-main',
                'type' => 'service_cards',
                'props' => [
                    'title'    => 'Everything Your HVAC System Needs',
                    'subtitle' => 'From emergency repairs to new installations — we do it all',
                    'columns'  => 3,
                    'cards'    => [
                        ['icon' => '❄️', 'title' => 'AC Repair',               'description' => 'Fast diagnosis and repair for all central AC, window units, and mini-splits. Most repairs same day.', 'url' => '/service-detail'],
                        ['icon' => '🔧', 'title' => 'AC Installation',         'description' => 'Expert installation of new AC systems with the right unit selection for your home and budget.', 'url' => '/service-detail'],
                        ['icon' => '🛡️', 'title' => 'Preventive Maintenance',  'description' => 'Annual or bi-annual plans to maximize efficiency, prevent breakdowns, and extend system life.', 'url' => '/service-detail'],
                        ['icon' => '🔥', 'title' => 'Heating Systems',         'description' => 'Furnace installation, heat pump service, boiler repair, and radiant heating solutions.', 'url' => '/service-detail'],
                        ['icon' => '🌬️', 'title' => 'Indoor Air Quality',      'description' => 'Air purifiers, UV lights, humidifiers, and dehumidifiers for healthier indoor air.', 'url' => '/service-detail'],
                        ['icon' => '🏢', 'title' => 'Commercial HVAC',         'description' => 'RTU installation, chiller systems, commercial refrigeration, and building automation.', 'url' => '/service-detail'],
                        ['icon' => '💧', 'title' => 'Duct Services',           'description' => 'Duct cleaning, sealing, repair and replacement to improve airflow and efficiency.', 'url' => '/service-detail'],
                        ['icon' => '🌱', 'title' => 'Energy Efficiency Audits','description' => 'Comprehensive assessment with recommendations to lower your energy costs.', 'url' => '/service-detail'],
                        ['icon' => '🚨', 'title' => '24/7 Emergency Service',  'description' => 'HVAC emergencies do not wait. Our on-call technicians are ready when you need us most.', 'url' => '/service-detail'],
                    ],
                ],
            ],
            [
                'id'   => 'services-stats',
                'type' => 'stats_row',
                'props' => [
                    'title'      => 'Service You Can Count On',
                    'subtitle'   => 'Our commitment to excellence in every job we complete',
                    'background' => '#1e3a5f',
                    'stats'      => [
                        ['icon' => '⏰', 'value' => '2hr',  'label' => 'Average Response Time'],
                        ['icon' => '✅', 'value' => '95%',  'label' => 'Same-Day Completion'],
                        ['icon' => '🛡️', 'value' => '1yr',  'label' => 'Labor Warranty'],
                        ['icon' => '⭐', 'value' => '4.9',  'label' => 'Google Rating'],
                    ],
                ],
            ],
            $this->ctaBlock(),
        ];
    }

    // ── Service Detail ───────────────────────────────────────────────────

    private function serviceDetailBlocks(): array
    {
        return [
            $this->pageHero('AC Repair Service', 'Fast, reliable, and affordable AC repair — same day service available'),
            [
                'id'   => 'sd-about',
                'type' => 'about_section',
                'props' => [
                    'badge'       => 'AC Repair Experts',
                    'title'       => 'Professional AC Repair You Can Trust',
                    'description' => 'When your air conditioner breaks down, you need fast, reliable repair. Our EPA-certified technicians have repaired thousands of AC systems of all makes and models. We provide upfront pricing before starting any work.',
                    'features'    => [
                        ['label' => 'All major brands serviced'],
                        ['label' => 'Upfront pricing — no surprises'],
                        ['label' => 'Same-day service in most cases'],
                        ['label' => '1-year labor warranty on all repairs'],
                        ['label' => 'Fully stocked service vehicles'],
                    ],
                    'btn_text' => 'Schedule Repair',
                    'btn_url'  => '/contact',
                    'stats'    => [
                        ['value' => '2hr', 'label' => 'Avg Response Time'],
                        ['value' => '95%', 'label' => 'Same-Day Repair'],
                    ],
                ],
            ],
            [
                'id'   => 'sd-signs',
                'type' => 'trust_badges',
                'props' => [
                    'title'    => 'Signs Your AC Needs Repair',
                    'subtitle' => 'Do not wait until your system fails completely',
                    'columns'  => 3,
                    'items'    => [
                        ['icon' => '🌡️', 'title' => 'Warm Air',         'description' => 'AC running but not cooling? Could be a refrigerant leak or compressor issue.'],
                        ['icon' => '💧', 'title' => 'Water Leaks',       'description' => 'Moisture or ice around your unit indicates a drainage or refrigerant problem.'],
                        ['icon' => '🔊', 'title' => 'Unusual Noises',    'description' => 'Grinding, squealing or rattling sounds signal a mechanical problem.'],
                        ['icon' => '💨', 'title' => 'Weak Airflow',      'description' => 'Poor airflow often means a clogged filter, duct issues, or failing blower motor.'],
                        ['icon' => '💸', 'title' => 'High Energy Bills', 'description' => 'A sudden spike in utility bills indicates your AC is working harder than it should.'],
                        ['icon' => '🔄', 'title' => 'Frequent Cycling',  'description' => 'AC turning on and off rapidly (short cycling) can damage the compressor.'],
                    ],
                ],
            ],
            $this->ctaBlock('Ready to Schedule Your AC Repair?', 'Call us now or book online. Same-day service available in most service areas.'),
        ];
    }

    // ── Projects ─────────────────────────────────────────────────────────

    private function projectsBlocks(): array
    {
        return [
            $this->pageHero('Our Projects', 'A showcase of our completed HVAC installations and service projects'),
            [
                'id'   => 'projects-grid',
                'type' => 'project_cards',
                'props' => [
                    'title'    => 'Recent Completed Projects',
                    'subtitle' => 'Every project completed to the highest standards of quality and craftsmanship',
                    'columns'  => 2,
                    'cards'    => [
                        ['category' => 'Residential', 'title' => 'Complete AC Replacement — 3,200 sq ft Home',   'description' => 'Replaced a 15-year-old central AC system with a new 5-ton Carrier Infinity 26. New air handler and refrigerant lines included.', 'url' => '/project-detail'],
                        ['category' => 'Commercial',  'title' => 'Office Building HVAC Overhaul',                'description' => '20,000 sq ft office received all-new HVAC equipment including four rooftop units and complete ductwork replacement.', 'url' => '/project-detail'],
                        ['category' => 'Residential', 'title' => 'Ductless Mini-Split — 5 Zone System',          'description' => 'Historic home without ductwork. Installed 5-zone Mitsubishi Diamond Series for individual room temperature control.', 'url' => '/project-detail'],
                        ['category' => 'Commercial',  'title' => 'Restaurant Kitchen Ventilation',               'description' => 'Designed and installed commercial exhaust hood system with 6,000 CFM capacity and makeup air unit.', 'url' => '/project-detail'],
                        ['category' => 'Residential', 'title' => 'Heat Pump Installation',                       'description' => 'Replaced gas furnace with high-efficiency Lennox heat pump to qualify for federal tax credits and reduce utility costs.', 'url' => '/project-detail'],
                        ['category' => 'Commercial',  'title' => 'Hotel HVAC System Upgrade',                    'description' => 'Complete HVAC upgrade for 120-room boutique hotel including PTAC replacement and common area systems.', 'url' => '/project-detail'],
                    ],
                ],
            ],
            $this->ctaBlock('Have a Project in Mind?', 'Contact us for a free consultation and estimate.'),
        ];
    }

    // ── Project Detail ────────────────────────────────────────────────────

    private function projectDetailBlocks(): array
    {
        return [
            $this->pageHero('Project: Complete AC Replacement', 'Residential · Los Angeles, CA · Completed June 2024'),
            [
                'id'   => 'pd-details',
                'type' => 'about_section',
                'props' => [
                    'badge'       => 'Project Overview',
                    'title'       => '5-Ton Carrier Infinity 26 Installation',
                    'description' => 'This project involved the complete removal of a 15-year-old central AC system and installation of a new high-efficiency Carrier Infinity 26 system rated at 26 SEER. Included a new variable-speed air handler, all new refrigerant lines, programmable thermostat, and duct cleaning.',
                    'features'    => [
                        ['label' => 'System: Carrier Infinity 26, 5-ton, 26 SEER'],
                        ['label' => 'New variable-speed air handler installed'],
                        ['label' => 'All new refrigerant lines'],
                        ['label' => 'Smart thermostat with app control'],
                        ['label' => 'Complete ductwork inspection and sealing'],
                    ],
                    'btn_text' => 'Get a Similar Quote',
                    'btn_url'  => '/contact',
                    'stats'    => [
                        ['value' => '1 Day',   'label' => 'Installation Time'],
                        ['value' => '26 SEER', 'label' => 'Efficiency Rating'],
                        ['value' => '$1,200',  'label' => 'Annual Energy Savings'],
                    ],
                ],
            ],
            $this->ctaBlock(),
        ];
    }

    // ── Pricing ───────────────────────────────────────────────────────────

    private function pricingBlocks(): array
    {
        return [
            $this->pageHero('Transparent Pricing', "No hidden fees, no surprises. Here's what you can expect to pay."),
            [
                'id'   => 'pricing-plans',
                'type' => 'trust_badges',
                'props' => [
                    'title'    => 'Service Pricing Guide',
                    'subtitle' => 'Prices vary by system type and severity. Contact us for a firm quote.',
                    'columns'  => 3,
                    'items'    => [
                        ['icon' => '🔍', 'title' => 'Diagnostic Visit',      'description' => 'Starting at $89. Our technician diagnoses any HVAC issue and provides a written estimate before any work.'],
                        ['icon' => '❄️', 'title' => 'AC Repair',             'description' => 'Starting at $149. Includes diagnostic and most common repairs. Refrigerant additional if needed.'],
                        ['icon' => '🛡️', 'title' => 'Tune-Up & Maintenance', 'description' => 'Starting at $99 per system. Annual maintenance keeps your system efficient and prevents costly breakdowns.'],
                        ['icon' => '🔧', 'title' => 'AC Installation',        'description' => 'Starting at $3,500. Price depends on system size, efficiency rating, and installation complexity.'],
                        ['icon' => '💎', 'title' => 'Comfort Club Plan',      'description' => 'From $15/mo. Priority scheduling, two annual tune-ups, and 15% off all repairs.'],
                        ['icon' => '🚨', 'title' => 'Emergency Service',      'description' => 'Available 24/7. After-hours service call fee of $149 plus repair costs.'],
                    ],
                ],
            ],
            [
                'id'   => 'pricing-guarantee',
                'type' => 'stats_row',
                'props' => [
                    'title'      => 'Our Price Guarantee',
                    'subtitle'   => 'We stand behind our pricing with these commitments',
                    'background' => '#1e3a5f',
                    'stats'      => [
                        ['icon' => '📋', 'value' => 'Upfront', 'label' => 'Written Estimates'],
                        ['icon' => '💰', 'value' => 'Match',   'label' => 'Competitor Prices'],
                        ['icon' => '🚫', 'value' => 'Zero',    'label' => 'Hidden Fees'],
                        ['icon' => '✅', 'value' => '100%',    'label' => 'Satisfaction Guaranteed'],
                    ],
                ],
            ],
            $this->ctaBlock('Get Your Free Quote Today', 'Call or book online for a free in-home estimate. No pressure, no obligation.'),
        ];
    }

    // ── FAQ ───────────────────────────────────────────────────────────────

    private function faqBlocks(): array
    {
        return [
            $this->pageHero('Frequently Asked Questions', 'Answers to the most common HVAC questions we hear from our customers'),
            [
                'id'    => 'faq-main',
                'type'  => 'faq',
                'props' => [
                    'title' => 'Common HVAC Questions',
                    'items' => [
                        ['question' => 'How often should I change my AC filter?',          'answer' => 'Most standard 1-inch filters should be changed every 1-3 months. Thicker media filters (4-5 inch) can last 6-12 months.'],
                        ['question' => 'Why is my AC not cooling the house?',              'answer' => 'Common causes include a dirty filter, low refrigerant, frozen evaporator coil, faulty thermostat, or a failing compressor.'],
                        ['question' => 'How long should an AC system last?',               'answer' => 'A well-maintained central AC system typically lasts 15-20 years. If yours is over 15 years old and needs major repair, replacement may be more cost-effective.'],
                        ['question' => 'What is SEER and why does it matter?',             'answer' => 'SEER (Seasonal Energy Efficiency Ratio) measures AC efficiency. Higher SEER means lower operating costs. A 20+ SEER unit can reduce cooling costs by 40% vs a 10 SEER unit.'],
                        ['question' => 'Do you offer financing for new installations?',    'answer' => 'Yes! We offer 0% interest financing for qualified customers. Ask about our 12-month same-as-cash and 60-month low payment options.'],
                        ['question' => 'What areas do you serve?',                         'answer' => 'We serve the greater Los Angeles area including Riverside, San Bernardino, Orange County, and surrounding communities within 50 miles of downtown LA.'],
                    ],
                ],
            ],
            $this->ctaBlock('Still Have Questions?', 'Our friendly team is happy to answer any HVAC questions. Call us anytime!'),
        ];
    }

    // ── Blog ─────────────────────────────────────────────────────────────

    private function blogBlocks(): array
    {
        return [
            $this->pageHero('HVAC Tips & News', 'Expert advice and industry news to help you get the most from your HVAC system'),
            ['id' => 'blog-main', 'type' => 'blog_posts', 'props' => ['title' => 'Latest Articles', 'limit' => 9]],
        ];
    }

    // ── Contact ───────────────────────────────────────────────────────────

    private function contactBlocks(): array
    {
        return [
            $this->pageHero('Contact Us', "We're here to help. Reach out by phone, email, or visit our office."),
            [
                'id'   => 'contact-details',
                'type' => 'contact_info',
                'props' => [
                    'title'      => 'How to Reach Us',
                    'subtitle'   => 'Multiple ways to get in touch with our team',
                    'background' => '#1e3a5f',
                    'items'      => [
                        ['icon' => '📍', 'label' => 'Address',        'value' => '123 Cooling Avenue, Los Angeles, CA 90001'],
                        ['icon' => '📞', 'label' => 'Phone',          'value' => '+1 (555) 123-4567'],
                        ['icon' => '✉️', 'label' => 'Email',          'value' => 'info@acoolhvac.com'],
                        ['icon' => '🕐', 'label' => 'Hours',          'value' => 'Mon–Sat 7am–8pm | Sun 9am–5pm'],
                        ['icon' => '🚨', 'label' => 'Emergency Line', 'value' => '+1 (555) 911-HVAC (24/7)'],
                    ],
                ],
            ],
            [
                'id'   => 'contact-trust',
                'type' => 'trust_badges',
                'props' => [
                    'title'    => 'Why Contact Acool?',
                    'subtitle' => 'We make getting service as easy as possible',
                    'columns'  => 3,
                    'items'    => [
                        ['icon' => '⚡', 'title' => 'Fast Response',       'description' => 'We respond to all inquiries within 2 hours during business hours.'],
                        ['icon' => '💰', 'title' => 'Free Estimates',      'description' => 'All in-home consultations and estimates are completely free.'],
                        ['icon' => '📅', 'title' => 'Flexible Scheduling', 'description' => 'Morning, afternoon, or evening appointments available 6 days a week.'],
                    ],
                ],
            ],
        ];
    }

    // ── 404 ───────────────────────────────────────────────────────────────

    private function errorBlocks(): array
    {
        return [
            [
                'id'       => '404-content',
                'type'     => 'section',
                'props'    => ['background' => '#f8fafc', 'padding' => 'xl'],
                'children' => [
                    ['id' => Str::random(6), 'type' => 'heading', 'props' => ['text' => '404 — Page Not Found', 'level' => 1, '_style' => ['typography' => ['color' => '#1e3a5f', 'textAlign' => 'center']]]],
                    ['id' => Str::random(6), 'type' => 'text',    'props' => ['content' => '<p>Oops! The page you are looking for does not exist or has been moved.</p>', '_style' => ['typography' => ['textAlign' => 'center']]]],
                    ['id' => Str::random(6), 'type' => 'button',  'props' => ['text' => 'Go Back Home', 'url' => '/', 'style' => 'primary']],
                ],
            ],
        ];
    }

    // ── Single Post ───────────────────────────────────────────────────────

    private function singlePostBlocks(): array
    {
        return [
            [
                'id'       => 'post-header',
                'type'     => 'section',
                'props'    => ['background' => '#1e3a5f', 'padding' => 'lg'],
                'children' => [
                    ['id' => Str::random(6), 'type' => 'heading', 'props' => ['text' => 'Article Title', 'level' => 1, '_style' => ['typography' => ['color' => '#ffffff', 'textAlign' => 'center']]]],
                    ['id' => Str::random(6), 'type' => 'text',    'props' => ['content' => '<p>Posted by Acool HVAC | HVAC Tips</p>', '_style' => ['typography' => ['color' => 'rgba(255,255,255,0.6)', 'textAlign' => 'center']]]],
                ],
            ],
            [
                'id'       => 'post-content',
                'type'     => 'section',
                'props'    => ['background' => '#ffffff', 'padding' => 'xl'],
                'children' => [
                    ['id' => Str::random(6), 'type' => 'text', 'props' => ['content' => '<p>This is where the article content will appear. Blog posts are managed through the platform blog module and rendered dynamically here.</p>']],
                ],
            ],
            ['id' => 'post-related', 'type' => 'blog_posts', 'props' => ['title' => 'Related Articles', 'limit' => 3]],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function pageHero(string $title, string $subtitle): array
    {
        return [
            'id'       => 'page-hero-' . Str::random(4),
            'type'     => 'section',
            'props'    => ['background' => '#1e3a5f', 'padding' => 'xl'],
            'children' => [
                ['id' => Str::random(6), 'type' => 'heading', 'props' => ['text' => $title,    'level' => 1, '_style' => ['typography' => ['color' => '#ffffff', 'textAlign' => 'center']]]],
                ['id' => Str::random(6), 'type' => 'text',    'props' => ['content' => "<p>{$subtitle}</p>", '_style' => ['typography' => ['color' => 'rgba(255,255,255,0.75)', 'textAlign' => 'center']]]],
            ],
        ];
    }
}
