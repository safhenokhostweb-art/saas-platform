<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface;
use SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface;
use SaaSPlatform\Modules\Forms\Models\Form;

class FormService implements FormServiceInterface
{
    public function __construct(
        protected FormRepositoryInterface $repository,
        protected CaptchaServiceInterface $captchaService,
        protected SiteRepositoryInterface $siteRepository
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->getAll($filters);
    }

    public function getById(int $id): ?Form
    {
        return $this->repository->getById($id);
    }

    public function create(array $data): Form
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']) . '-' . time();
        }

        return $this->repository->create($data);
    }

    public function update(Form $form, array $data): Form
    {
        return $this->repository->update($form, $data);
    }

    public function delete(Form $form): bool
    {
        return $this->repository->delete($form);
    }

    public function getWithFields(int $id): ?Form
    {
        return $this->repository->getWithFields($id);
    }

    public function getPreviewData(Form $form): array
    {
        $form->load(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('position')]);

        $captcha = ['enabled' => false, 'type' => null, 'hcaptcha_site_key' => '', 'persian_code' => '', 'normal_text' => ''];

        if (config('forms.captcha_enabled', true) && $this->captchaService->isEnabled()) {
            $cfg = $this->captchaService->getConfigForSite(null);
            if ($cfg['enabled']) {
                $captcha['enabled'] = true;
                $captcha['type']    = $cfg['type'];
                $captcha['hcaptcha_site_key'] = $cfg['hcaptcha_site_key'];
                if ($cfg['type'] === CaptchaServiceInterface::TYPE_PERSIAN_NUMBER) {
                    $captcha['persian_code'] = $this->captchaService->generatePersianNumber(4);
                } elseif ($cfg['type'] === CaptchaServiceInterface::TYPE_NORMAL) {
                    $gen = $this->captchaService->generateNormal();
                    $captcha['normal_text'] = $gen['text'];
                }
            }
        }

        $firstSite = $form->sites()->first() ?? $this->siteRepository->allForDisplay()->first();

        return compact('form', 'captcha', 'firstSite');
    }
}
