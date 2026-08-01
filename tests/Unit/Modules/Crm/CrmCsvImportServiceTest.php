<?php

namespace Tests\Unit\Modules\Crm;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Services\CrmCsvImportService;
use Tests\Concerns\MakesCrmTestSites;
use Tests\TestCase;

class CrmCsvImportServiceTest extends TestCase
{
    use MakesCrmTestSites;
    use RefreshDatabase;

    private function putCsv(string $contents): string
    {
        Storage::fake('local');
        $path = 'crm-imports/test.csv';
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    public function test_preview_flags_valid_duplicate_and_invalid_rows(): void
    {
        $site = $this->makeCrmTestSite();
        CrmContact::create(['site_id' => $site->id, 'first_name' => 'Existing', 'email' => 'dup@example.com']);

        $csv = "first_name,last_name,email,phone,job_title,company_name\n"
            . "Ali,Rezaei,ali@example.com,0912,Manager,Acme\n"
            . "Dup,User,dup@example.com,,,\n"
            . ",Empty,bad@example.com,,,\n";

        $path = $this->putCsv($csv);
        $result = (new CrmCsvImportService())->preview(CrmCsvImportService::ENTITY_CONTACT, $path, $site->id);

        $this->assertSame('valid', $result['rows'][0]['status']);
        $this->assertSame('duplicate', $result['rows'][1]['status']);
        $this->assertSame('invalid', $result['rows'][2]['status']); // empty first_name
    }

    public function test_preview_rejects_invalid_email(): void
    {
        $site = $this->makeCrmTestSite();
        $csv = "first_name,last_name,email,phone,job_title,company_name\nAli,,not-an-email,,,\n";

        $path = $this->putCsv($csv);
        $result = (new CrmCsvImportService())->preview(CrmCsvImportService::ENTITY_CONTACT, $path, $site->id);

        $this->assertSame('invalid', $result['rows'][0]['status']);
    }

    public function test_commit_only_creates_valid_rows_and_skips_the_rest(): void
    {
        $site = $this->makeCrmTestSite();
        CrmContact::create(['site_id' => $site->id, 'first_name' => 'Existing', 'email' => 'dup@example.com']);

        $csv = "first_name,last_name,email,phone,job_title,company_name\n"
            . "Ali,Rezaei,ali@example.com,0912,Manager,Acme\n"
            . "Dup,User,dup@example.com,,,\n"
            . ",Empty,bad@example.com,,,\n";

        $path = $this->putCsv($csv);
        $counts = (new CrmCsvImportService())->commit(CrmCsvImportService::ENTITY_CONTACT, $path, $site->id);

        $this->assertSame(1, $counts['created']);
        $this->assertSame(1, $counts['skipped_duplicate']);
        $this->assertSame(1, $counts['skipped_invalid']);
        $this->assertDatabaseHas('crm_contacts', ['site_id' => $site->id, 'email' => 'ali@example.com']);
        $this->assertDatabaseHas('crm_companies', ['site_id' => $site->id, 'name' => 'Acme']);
    }

    public function test_malformed_row_with_wrong_column_count_is_skipped_not_crashed(): void
    {
        $site = $this->makeCrmTestSite();
        $csv = "first_name,last_name,email\nAli,TooMany,ali@example.com,extra,columns\n";

        $path = $this->putCsv($csv);
        $counts = (new CrmCsvImportService())->commit(CrmCsvImportService::ENTITY_CONTACT, $path, $site->id);

        $this->assertSame(0, $counts['created']);
        $this->assertSame(1, $counts['skipped_invalid']);
    }

    public function test_company_import_prevents_duplicate_by_name(): void
    {
        $site = $this->makeCrmTestSite();
        $csv = "name,domain,industry,phone\nAcme,acme.com,Tech,0912\n";
        $path = $this->putCsv($csv);

        (new CrmCsvImportService())->commit(CrmCsvImportService::ENTITY_COMPANY, $path, $site->id);
        $counts = (new CrmCsvImportService())->commit(CrmCsvImportService::ENTITY_COMPANY, $this->putCsv($csv), $site->id);

        $this->assertSame(0, $counts['created']);
        $this->assertSame(1, $counts['skipped_duplicate']);
    }
}
