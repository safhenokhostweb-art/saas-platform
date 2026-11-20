<?php

namespace Tests\Unit\CustomerDeletion\Guards;

use App\Services\CustomerDeletion\Guards\DatabaseNameGuard;
use App\Services\CustomerDeletion\Guards\DomainGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** ADR-090 — database / user / certificate-name guards (need config, hence Tests\TestCase). */
class DatabaseAndDomainGuardTest extends TestCase
{
    public function test_customer_database_and_user_names_pass(): void
    {
        $this->assertSame('cust_db_42', DatabaseNameGuard::assertDatabase('cust_db_42'));
        $this->assertSame('u_deadbeef', DatabaseNameGuard::assertUser('u_deadbeef'));
    }

    public static function protectedDatabases(): array
    {
        return [
            'mysql'              => ['mysql'],
            'sys'                => ['sys'],
            'information_schema' => ['information_schema'],
            'performance_schema' => ['PERFORMANCE_SCHEMA'],
            'backtick injection' => ['cust`; DROP DATABASE mysql; --'],
            'space'              => ['cust db'],
            'empty'              => [''],
            'too long'           => [str_repeat('a', 65)],
        ];
    }

    #[DataProvider('protectedDatabases')]
    public function test_system_or_malformed_databases_are_refused(string $name): void
    {
        $this->expectException(PurgeGuardViolation::class);
        DatabaseNameGuard::assertDatabase($name);
    }

    public function test_the_platforms_own_database_and_user_are_refused(): void
    {
        config(['database.connections.mysql.database' => 'saas_platform', 'database.connections.mysql.username' => 'saas_app']);

        foreach ([fn () => DatabaseNameGuard::assertDatabase('saas_platform'), fn () => DatabaseNameGuard::assertUser('saas_app'), fn () => DatabaseNameGuard::assertUser('root')] as $call) {
            try {
                $call();
                $this->fail('protected name accepted');
            } catch (PurgeGuardViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_certificate_names_pass_for_customer_domains(): void
    {
        config(['app.url' => 'https://safhenokhost.ir']);

        $this->assertSame('shop.example.ir', DomainGuard::assertCertificateName('Shop.Example.IR '));
        $this->assertSame('shop.safhenokhost.ir', DomainGuard::assertCertificateName('shop.safhenokhost.ir'));
    }

    public static function badCertificateNames(): array
    {
        return [
            'platform host'     => ['safhenokhost.ir'],
            'platform www'      => ['www.safhenokhost.ir'],
            'wildcard'          => ['*.example.ir'],
            'ip'                => ['87.107.12.81'],
            'single label'      => ['localhost'],
            'traversal'         => ['../../etc'],
            'shell'             => ['example.ir;rm'],
            'empty'             => [''],
        ];
    }

    #[DataProvider('badCertificateNames')]
    public function test_platform_wildcard_ip_or_malformed_names_are_refused(string $name): void
    {
        config(['app.url' => 'https://safhenokhost.ir']);

        $this->expectException(PurgeGuardViolation::class);
        DomainGuard::assertCertificateName($name);
    }
}
