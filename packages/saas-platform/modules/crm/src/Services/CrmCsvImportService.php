<?php

namespace SaaSPlatform\Modules\Crm\Services;

use Illuminate\Support\Facades\Storage;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;

/**
 * Phase 20 — Part 5: CSV Import for Contact/Company/Lead. Two-step:
 * parse() renders a Preview (nothing written to the DB yet); commit()
 * re-reads the SAME uploaded file (kept on a temp disk between the two
 * HTTP requests — see CrmImportExportController) and actually inserts
 * rows, skipping anything parse() already flagged as invalid or a
 * duplicate.
 */
class CrmCsvImportService
{
    public const ENTITY_CONTACT = 'contact';
    public const ENTITY_COMPANY = 'company';
    public const ENTITY_LEAD = 'lead';

    private const COLUMNS = [
        self::ENTITY_CONTACT => ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company_name'],
        self::ENTITY_COMPANY => ['name', 'domain', 'industry', 'phone'],
        self::ENTITY_LEAD => ['first_name', 'last_name', 'email', 'phone', 'company_name', 'source'],
    ];

    public function requiredColumns(string $entity): array
    {
        return self::COLUMNS[$entity] ?? [];
    }

    /**
     * @return array{header: string[], rows: array<int, array{data: array, status: string, reason: ?string}>}
     */
    public function preview(string $entity, string $diskPath, int $siteId): array
    {
        [$header, $rows] = $this->readCsv($diskPath);
        $requiredNameColumn = $entity === self::ENTITY_COMPANY ? 'name' : 'first_name';

        if (!in_array($requiredNameColumn, $header, true)) {
            return ['header' => $header, 'rows' => [], 'error' => "ستون الزامی «{$requiredNameColumn}» در فایل یافت نشد."];
        }

        $annotated = [];
        foreach ($rows as $row) {
            $data = $this->combineRow($header, $row);
            $annotated[] = $data === null
                ? ['data' => ['_raw' => implode(',', $row)], 'status' => 'invalid', 'reason' => 'تعداد ستون‌های این ردیف با هدر مطابقت ندارد.']
                : $this->annotateRow($entity, $data, $siteId);
        }

        return ['header' => $header, 'rows' => $annotated];
    }

    /**
     * @return array{created: int, skipped_duplicate: int, skipped_invalid: int}
     */
    public function commit(string $entity, string $diskPath, int $siteId): array
    {
        [$header, $rows] = $this->readCsv($diskPath);
        $counts = ['created' => 0, 'skipped_duplicate' => 0, 'skipped_invalid' => 0];

        foreach ($rows as $row) {
            $data = $this->combineRow($header, $row);
            if ($data === null) {
                $counts['skipped_invalid']++;

                continue;
            }

            $annotated = $this->annotateRow($entity, $data, $siteId);

            if ($annotated['status'] === 'invalid') {
                $counts['skipped_invalid']++;

                continue;
            }
            if ($annotated['status'] === 'duplicate') {
                $counts['skipped_duplicate']++;

                continue;
            }

            $this->createRow($entity, $annotated['data'], $siteId);
            $counts['created']++;
        }

        return $counts;
    }

    private function readCsv(string $diskPath): array
    {
        $contents = Storage::disk('local')->get($diskPath);
        $lines = array_filter(preg_split('/\r\n|\r|\n/', trim($contents)), fn ($l) => trim($l) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            return [[], []];
        }

        $header = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = array_map(fn ($line) => str_getcsv($line), $lines);

        return [$header, $rows];
    }

    /** array_combine() throws on mismatched lengths — a malformed CSV row must not crash the whole import. */
    private function combineRow(array $header, array $row): ?array
    {
        if (count($header) !== count($row)) {
            return null;
        }

        return array_combine($header, $row);
    }

    /** @return array{data: array, status: string, reason: ?string} */
    private function annotateRow(string $entity, array $data, int $siteId): array
    {
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

        $nameField = $entity === self::ENTITY_COMPANY ? 'name' : 'first_name';
        if (empty($data[$nameField])) {
            return ['data' => $data, 'status' => 'invalid', 'reason' => "«{$nameField}» خالی است."];
        }

        if (!empty($data['email'] ?? null) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['data' => $data, 'status' => 'invalid', 'reason' => 'ایمیل نامعتبر است.'];
        }

        $duplicate = match ($entity) {
            self::ENTITY_CONTACT => !empty($data['email'] ?? null) && CrmContact::where('site_id', $siteId)->where('email', $data['email'])->exists(),
            self::ENTITY_COMPANY => CrmCompany::where('site_id', $siteId)->where('name', $data['name'])->exists(),
            self::ENTITY_LEAD => !empty($data['email'] ?? null) && CrmLead::where('site_id', $siteId)->where('email', $data['email'])->exists(),
            default => false,
        };

        if ($duplicate) {
            return ['data' => $data, 'status' => 'duplicate', 'reason' => 'قبلاً با همین ایمیل/نام ثبت شده است.'];
        }

        return ['data' => $data, 'status' => 'valid', 'reason' => null];
    }

    private function createRow(string $entity, array $data, int $siteId): void
    {
        match ($entity) {
            self::ENTITY_CONTACT => CrmContact::create([
                'site_id' => $siteId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'company_id' => !empty($data['company_name'] ?? null)
                    ? CrmCompany::firstOrCreate(['site_id' => $siteId, 'name' => $data['company_name']])->id
                    : null,
            ]),
            self::ENTITY_COMPANY => CrmCompany::create([
                'site_id' => $siteId, 'name' => $data['name'],
                'domain' => $data['domain'] ?? null, 'industry' => $data['industry'] ?? null, 'phone' => $data['phone'] ?? null,
            ]),
            self::ENTITY_LEAD => CrmLead::create([
                'site_id' => $siteId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'source' => $data['source'] ?? 'csv_import',
            ]),
            default => null,
        };
    }
}
