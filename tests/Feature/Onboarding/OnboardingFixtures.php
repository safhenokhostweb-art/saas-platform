<?php

namespace Tests\Feature\Onboarding;

use App\Models\Customer;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Models\Module;
use App\Models\Role;
use App\Models\Site;
use App\Models\Theme;
use App\Models\User;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Support\Facades\Hash;

/** Shared fixtures for the ADR-088 onboarding tests. */
trait OnboardingFixtures
{
    protected const ROOT = 'safhenokhost.test';

    protected function admin(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'), 'is_admin' => true, 'is_active' => true,
        ]);
        if ($role = Role::where('slug', Role::SUPER_ADMIN)->first()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    protected function platformRootDomain(string $root = self::ROOT): void
    {
        DnsProviderSetting::create(['provider' => 'arvan', 'root_domain' => $root, 'is_active' => true]);
    }

    protected function server(string $type = 'fake'): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label' => 'Server ' . $type, 'type' => $type, 'host' => '10.0.0.' . random_int(2, 250),
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
    }

    protected function customer(string $mobile = '09121112233', string $name = 'مشتری موجود'): Customer
    {
        return Customer::create(['name' => $name, 'mobile' => $mobile]);
    }

    protected function site(Customer $customer, string $domain): Site
    {
        return Site::create(['customer_id' => $customer->id, 'title' => 'Site ' . $domain, 'domain' => $domain, 'is_active' => true]);
    }

    protected function eligibleTheme(string $slug = 'solar-industry'): Theme
    {
        $theme = Theme::create([
            'name' => 'Theme ' . $slug, 'slug' => $slug, 'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL, 'category' => Theme::CATEGORY_INDUSTRIAL,
            'status' => Theme::STATUS_DRAFT, 'is_marketplace' => true, 'license_type' => Theme::LICENSE_FREE,
            'global_styles' => app(ThemeBuilderService::class)->defaultGlobalStyles(),
            'header_blocks' => [], 'footer_blocks' => [],
            'pages' => ['home' => [
                'title' => 'صفحه اصلی', 'slug' => '', 'is_home' => true,
                'blocks' => [['id' => 'h', 'type' => 'heading', 'props' => ['text' => $slug, 'level' => 1]]],
            ]],
        ]);
        app(ThemeBuilderService::class)->publish($theme, '1.0.0', 'onboarding test');

        return $theme->fresh();
    }

    protected function draftTheme(string $slug = 'draft-theme'): Theme
    {
        return Theme::create(['name' => 'Draft', 'slug' => $slug, 'status' => Theme::STATUS_DRAFT]);
    }

    protected function solarKit(): Module
    {
        return Module::query()->where('slug', 'solar_industry_kit')->firstOrFail();
    }

    /** A valid wizard submission; override any key. */
    protected function submission(CustomerRemoteServer $server, array $overrides = []): array
    {
        return array_merge([
            'mobile'            => '۰۹۳۵۱۲۳۴۵۶۷',
            'customer_name'     => 'سارا محمدی',
            'company_name'      => 'خورشید پارس',
            'site_title'        => 'خورشید انرژی پارس',
            'domain_mode'       => 'subdomain',
            'domain'            => 'khorshid',
            'license_type'      => 'professional',
            'business_vertical' => '',
            'kit_module_ids'    => [],
            'base_modules'      => ['forms'],
            'theme_id'          => '',
            'remote_server_id'  => $server->id,
            'ssl_enabled'       => 1,
            'backup_policy'     => 'daily',
        ], $overrides);
    }
}
