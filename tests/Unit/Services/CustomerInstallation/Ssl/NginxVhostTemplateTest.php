<?php

namespace Tests\Unit\Services\CustomerInstallation\Ssl;

use App\Services\CustomerInstallation\Ssl\NginxVhostTemplate;
use PHPUnit\Framework\TestCase;

class NginxVhostTemplateTest extends TestCase
{
    public function test_country_trust_directives_are_central_and_preserve_original_peer(): void
    {
        $config = (new NginxVhostTemplate())->render(
            'tenant.example.test',
            '/var/www/tenant',
            '/run/php/php8.3-fpm.sock',
            ['cert' => '/cert.pem', 'key' => '/key.pem'],
        );

        $this->assertSame(2, substr_count($config, 'include snippets/arvan-cdn-real-ip.conf;'));
        $this->assertSame(2, substr_count($config, 'fastcgi_param HTTP_X_ORIGIN_PEER_IP $realip_remote_addr;'));
        $this->assertSame(2, substr_count(
            $config,
            "include fastcgi_params;\n        # Must follow fastcgi_params so a client-supplied value is overwritten.\n        fastcgi_param HTTP_X_ORIGIN_PEER_IP \$realip_remote_addr;",
        ));
        $this->assertStringNotContainsString('set_real_ip_from', $config);
    }
}
