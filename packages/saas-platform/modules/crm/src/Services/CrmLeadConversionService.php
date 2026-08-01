<?php

namespace SaaSPlatform\Modules\Crm\Services;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Crm\Models\CrmCompany;
use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmLead;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;

/**
 * Phase 19 — Part 2/4: the Lead→Contact conversion event. Runs inside a
 * transaction since it can create up to three rows (Contact, optionally
 * Company, optionally Opportunity) and must never leave a Lead marked
 * "converted" without its converted_*_id columns actually pointing
 * somewhere real.
 */
class CrmLeadConversionService
{
    /**
     * @param  array{create_company?: bool, create_opportunity?: bool, opportunity_name?: string, opportunity_amount?: int, pipeline_id?: int, stage_id?: int}  $options
     */
    public function convert(CrmLead $lead, array $options = []): CrmContact
    {
        if ($lead->isConverted()) {
            throw new \RuntimeException("Lead #{$lead->id} is already converted.");
        }

        return DB::transaction(function () use ($lead, $options) {
            $company = null;
            if (!empty($options['create_company']) && !empty($lead->company_name)) {
                $company = CrmCompany::create([
                    'site_id' => $lead->site_id,
                    'name' => $lead->company_name,
                    'owner_user_id' => $lead->owner_user_id,
                ]);
            }

            $contact = CrmContact::create([
                'site_id' => $lead->site_id,
                'company_id' => $company?->id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'owner_user_id' => $lead->owner_user_id,
                'lifecycle_stage' => CrmContact::STAGE_PROSPECT,
            ]);

            $opportunity = null;
            if (!empty($options['create_opportunity'])) {
                $pipeline = isset($options['pipeline_id'])
                    ? CrmPipeline::findOrFail($options['pipeline_id'])
                    : CrmPipeline::where('site_id', $lead->site_id)->where('is_default', true)->firstOrFail();

                $stage = isset($options['stage_id'])
                    ? $pipeline->stages()->findOrFail($options['stage_id'])
                    : $pipeline->stages()->where('type', 'open')->orderBy('position')->firstOrFail();

                $opportunity = CrmOpportunity::create([
                    'site_id' => $lead->site_id,
                    'name' => $options['opportunity_name'] ?? "Deal — {$contact->full_name}",
                    'contact_id' => $contact->id,
                    'company_id' => $company?->id,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'amount' => $options['opportunity_amount'] ?? 0,
                    'owner_user_id' => $lead->owner_user_id,
                ]);
            }

            $lead->update([
                'status' => CrmLead::STATUS_CONVERTED,
                'converted_contact_id' => $contact->id,
                'converted_company_id' => $company?->id,
                'converted_opportunity_id' => $opportunity?->id,
                'converted_at' => now(),
            ]);

            return $contact;
        });
    }
}
