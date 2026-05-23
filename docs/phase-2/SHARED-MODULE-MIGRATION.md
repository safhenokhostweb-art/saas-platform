# Phase 2 — Shared Module Migration

> **Objective**: Migrate business modules (Forms, Menus, Pages, Activity Log, Settings) from dual-project duplication to the centralized shared package architecture built in Phase 1.5.
>
> **Prerequisite**: Phase 1.5 — Shared Package Infrastructure (auto-discovery, module registry, dependency resolution, lifecycle management, security validation)
>
> **Status**: 📋 Planning Phase
> **Target**: 6 modules migrated, 0 regressions, full backward compatibility

---

## Table of Contents

1. [Migration Strategy Overview](#1-migration-strategy-overview)
2. [Module Migration Priority](#2-module-migration-priority)
3. [Module: Forms (`packages/saas-platform/modules/forms/`)](#3-module-forms-packagessaas-platformmodulesforms)
4. [Module: Menus (`packages/saas-platform/modules/menus/`)](#4-module-menus-packagessaas-platformmodulesmenus)
5. [Module: Pages (`packages/saas-platform/modules/pages/`)](#5-module-pages-packagessaas-platformmodulespages)
6. [Module: Activity Log (`packages/saas-platform/modules/activity-log/`)](#6-module-activity-log-packagessaas-platformmodulesactivity-log)
7. [Module: Settings (`packages/saas-platform/modules/settings/`)](#7-module-settings-packagessaas-platformmodulessettings)
8. [Module: Captcha (`packages/saas-platform/modules/captcha/`)](#8-module-captcha-packagessaas-platformmodulescaptcha)
9. [Backward Compatibility Layer](#9-backward-compatibility-layer)
10. [Migration Rollback Plan](#10-migration-rollback-plan)
11. [Testing Strategy](#11-testing-strategy)
12. [Anti-Drift Enforcement](#12-anti-drift-enforcement)
13. [Timeline & Milestones](#13-timeline--milestones)
14. [Risk Mitigation](#14-risk-mitigation)
15. [Conclusion](#15-conclusion)

---

## 1. Migration Strategy Overview

### 1.1 Guiding Principles

| # | Principle | Description |
|---|-----------|-------------|
| 1 | **Zero Regressions** | Every migration must maintain 100% backward compatibility. Existing routes, APIs, and views continue working. |
| 2 | **Incremental Migration** | One module at a time. No big-bang releases. Each module is migrated, tested, and verified independently. |
| 3 | **Dual-Run Mode** | During migration, both old (project-local) and new (shared package) implementations coexist. A feature flag controls which is active. |
| 4 | **Feature Flag Gating** | Each module has a `config('modules.migration.<module>')` flag. Default: `'local'` (old). Switch to `'shared'` after validation. |
| 5 | **No Data Loss** | All existing database records, relationships, and migrations remain intact. Shared packages add new migrations only. |
| 6 | **Tenant Isolation Preserved** | All shared modules respect `TenantContext` and maintain tenant-scoped data isolation. |

### 1.2 Migration Flow

```
┌─────────────────────────────────────────────────────────┐
│                  1. ANALYZE PHASE                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Audit local  │  │ Map all     │  │ Identify    │     │
│  │ module code  │  │ dependencies│  │ drift       │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  2. EXTRACT PHASE                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Create shared│  │ Extract     │  │ Create      │     │
│  │ package      │  │ models/     │  │ migration   │     │
│  │ structure    │  │ controllers │  │ for shared  │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  3. BRIDGE PHASE                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Create      │  │ Add feature  │  │ Register    │     │
│  │ backward    │  │ flag         │  │ shared      │     │
│  │ compat layer│  │ gating       │  │ routes      │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  4. VALIDATE PHASE                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Run tests   │  │ Verify no   │  │ Compare     │     │
│  │ (local vs   │  │ regressions │  │ output      │     │
│  │ shared)     │  │             │  │ side-by-side│     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  5. SWITCH PHASE                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Flip feature│  │ Monitor for │  │ Deprecate   │     │
│  │ flag to     │  │ issues (48h)│  │ local code  │     │
│  │ 'shared'    │  │             │  │ (next major) │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└─────────────────────────────────────────────────────────┘
```

### 1.3 Feature Flag Configuration

```php
// config/modules.php (both projects)
return [
    'migration' => [
        'forms' => env('MODULE_MIGRATION_FORMS', 'local'),       // 'local' | 'shared'
        'menus' => env('MODULE_MIGRATION_MENUS', 'local'),       // 'local' | 'shared'
        'pages' => env('MODULE_MIGRATION_PAGES', 'local'),       // 'local' | 'shared'
        'activity_log' => env('MODULE_MIGRATION_ACTIVITY_LOG', 'local'), // 'local' | 'shared'
        'settings' => env('MODULE_MIGRATION_SETTINGS', 'local'), // 'local' | 'shared'
        'captcha' => env('MODULE_MIGRATION_CAPTCHA', 'local'),   // 'local' | 'shared'
    ],
];
```

### 1.4 Migration Decision Matrix

```php
// app/Helpers/ModuleMigrationHelper.php (both projects)
class ModuleMigrationHelper
{
    public static function useShared(string $module): bool
    {
        return config("modules.migration.{$module}", 'local') === 'shared';
    }

    public static function resolveModel(string $module, string $localClass, string $sharedClass): string
    {
        return self::useShared($module) ? $sharedClass : $localClass;
    }

    public static function resolveController(string $module, string $localClass, string $sharedClass): string
    {
        return self::useShared($module) ? $sharedClass : $localClass;
    }

    public static function resolveView(string $module, string $view): string
    {
        if (self::useShared($module)) {
            return "saas-platform::{$module}.{$view}";
        }
        return "admin.{$module}.{$view}";
    }
}
```

---

## 2. Module Migration Priority

### 2.1 Priority Matrix

| Priority | Module | Complexity | Dependencies | Drift Level | Risk | Effort |
|----------|--------|-----------|--------------|-------------|------|--------|
| P0 | **Forms** | High | TenantContext, UI Kit | Medium | High | 5 days |
| P1 | **Menus** | Medium | TenantContext, Forms | Low | Medium | 3 days |
| P2 | **Pages** | Medium | TenantContext, Menus | Low | Medium | 3 days |
| P3 | **Activity Log** | Low | TenantContext | Low | Low | 2 days |
| P4 | **Settings** | Low | TenantContext, UI Kit | Medium | Low | 2 days |
| P5 | **Captcha** | Low | None | Low | Low | 1 day |

### 2.2 Dependency Graph

```
Forms ───► Menus ───► Pages
  │          │
  ▼          ▼
TenantContext (Phase 3)
  │
  ▼
UI Kit (Phase 1)
```

### 2.3 Migration Order

```
Week 1: Forms (P0) ─── Foundation module, highest complexity
Week 2: Menus (P1) ─── Depends on Forms patterns
Week 3: Pages (P2) ─── Depends on Menus patterns
Week 4: Activity Log (P3) ─── Independent, low complexity
Week 5: Settings (P4) ─── Independent, low complexity
Week 6: Captcha (P5) ─── Independent, lowest complexity
```

---

## 3. Module: Forms (`packages/saas-platform/modules/forms/`)

### 3.1 Current State Analysis

#### saas-platform (source of truth)
```
app/Models/Form.php                    — Form model (fillable, casts, relations)
app/Models/FormField.php               — FormField model
app/Models/FormSubmission.php          — FormSubmission model
app/Http/Controllers/Web/FormController.php       — CRUD controller
app/Http/Controllers/Web/FormFieldController.php  — Field management
app/Http/Controllers/Api/FormApiController.php    — API endpoints
app/Http/Requests/FormRequest.php                 — Validation
app/Http/Requests/FormFieldRequest.php            — Field validation
app/Services/FormService.php                      — Business logic
app/Services/FormFieldService.php                 — Field logic
app/Repositories/FormRepository.php               — DB operations
app/Repositories/FormFieldRepository.php          — Field DB ops
resources/views/admin/forms/index.blade.php       — List view
resources/views/admin/forms/create.blade.php      — Create view
resources/views/admin/forms/edit.blade.php        — Edit view
resources/views/admin/forms/show.blade.php        — Show view
resources/views/admin/forms/fields/              — Field partials
resources/views/admin/forms/submissions/         — Submission views
database/migrations/xxxx_xx_xx_create_forms_table.php
database/migrations/xxxx_xx_xx_create_form_fields_table.php
database/migrations/xxxx_xx_xx_create_form_submissions_table.php
routes/web.php                                    — Web routes
routes/api.php                                    — API routes
```

#### customer-site (drifted copy)
```
app/Models/Form.php                    — Drifted (missing fields, different casts)
app/Models/FormField.php               — Drifted
app/Models/FormSubmission.php          — Drifted
app/Http/Controllers/Admin/FormController.php    — Different namespace
app/Http/Requests/FormRequest.php                 — Different rules
app/Http/Requests/FormFieldRequest.php            — Different rules
resources/views/admin/forms/index.blade.php       — Drifted UI
resources/views/admin/forms/create.blade.php      — Drifted UI
resources/views/admin/forms/edit.blade.php        — Drifted UI
resources/views/admin/forms/show.blade.php        — Drifted UI
database/migrations/xxxx_xx_xx_create_forms_table.php
database/migrations/xxxx_xx_xx_create_form_fields_table.php
database/migrations/xxxx_xx_xx_create_form_submissions_table.php
```

### 3.2 Shared Package Structure

```
packages/saas-platform/modules/forms/
├── composer.json
├── module.json
├── src/
│   ├── FormsServiceProvider.php
│   ├── Models/
│   │   ├── Form.php
│   │   ├── FormField.php
│   │   └── FormSubmission.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Web/
│   │   │   │   └── FormController.php
│   │   │   └── Api/
│   │   │       └── FormApiController.php
│   │   ├── Requests/
│   │   │   ├── FormRequest.php
│   │   │   └── FormFieldRequest.php
│   │   └── Resources/
│   │       ├── FormResource.php
│   │       └── FormFieldResource.php
│   ├── Services/
│   │   ├── FormService.php
│   │   └── FormFieldService.php
│   ├── Repositories/
│   │   ├── FormRepository.php
│   │   └── FormFieldRepository.php
│   ├── Contracts/
│   │   ├── Services/
│   │   │   ├── FormServiceContract.php
│   │   │   └── FormFieldServiceContract.php
│   │   └── Repositories/
│   │       ├── FormRepositoryContract.php
│   │       └── FormFieldRepositoryContract.php
│   └── Exceptions/
│       ├── FormNotFoundException.php
│       └── FormSubmissionException.php
├── resources/
│   ├── views/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   ├── show.blade.php
│   │   ├── fields/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   └── edit.blade.php
│   │   └── submissions/
│   │       ├── index.blade.php
│   │       └── show.blade.php
│   └── lang/
│       └── fa/
│           ├── messages.php
│           └── validation.php
├── database/
│   └── migrations/
│       ├── 2025_01_01_000001_create_forms_table.php
│       ├── 2025_01_01_000002_create_form_fields_table.php
│       └── 2025_01_01_000003_create_form_submissions_table.php
├── config/
│   └── forms.php
├── routes/
│   ├── web.php
│   └── api.php
└── tests/
    ├── Feature/
    │   ├── FormControllerTest.php
    │   └── FormSubmissionTest.php
    └── Unit/
        ├── FormServiceTest.php
        └── FormRepositoryTest.php
```

### 3.3 Shared Model: Form

```php
<?php

namespace SaasPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;
use SaasPlatform\Shared\Contracts\TenantScoped;
use SaasPlatform\Shared\Traits\BelongsToTenant;

class Form extends Model implements TenantScoped
{
    use SoftDeletes, HasUuid, HasUserStamps, BelongsToTenant;

    protected $table = 'forms';

    protected $fillable = [
        'uuid',
        'site_id',
        'title',
        'slug',
        'description',
        'status',           // draft, active, archived
        'submission_limit',
        'requires_auth',
        'notification_email',
        'success_message',
        'redirect_url',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requires_auth' => 'boolean',
        'submission_limit' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'requires_auth' => false,
    ];

    // Relations
    public function fields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FormField::class, 'form_id');
    }

    public function submissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }

    public function activeFields(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FormField::class, 'form_id')
            ->where('status', 'active')
            ->orderBy('order');
    }

    // Scopes
    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'draft');
    }

    // Accessors
    public function getSubmissionCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    public function getFieldCountAttribute(): int
    {
        return $this->fields()->count();
    }
}
```

### 3.4 Shared Model: FormField

```php
<?php

namespace SaasPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;

class FormField extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

    protected $table = 'form_fields';

    protected $fillable = [
        'uuid',
        'form_id',
        'label',
        'name',
        'type',              // text, textarea, email, number, select, checkbox, radio, file, date, time, hidden
        'placeholder',
        'default_value',
        'options',           // JSON for select/radio/checkbox
        'validation_rules',  // JSON array of rules
        'validation_messages', // JSON object of custom messages
        'order',
        'required',
        'status',            // active, inactive
        'width_class',       // full, half, third, two-thirds
        'help_text',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'validation_messages' => 'array',
        'required' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'active',
        'required' => false,
        'order' => 0,
        'width_class' => 'full',
    ];

    // Relations
    public function form(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    // Scopes
    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('order');
    }
}
```

### 3.5 Shared Model: FormSubmission

```php
<?php

namespace SaasPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;

class FormSubmission extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'form_submissions';

    protected $fillable = [
        'uuid',
        'form_id',
        'data',              // JSON of submitted field values
        'ip_address',
        'user_agent',
        'user_id',
        'status',            // pending, read, spam, archived
        'notes',
        'metadata',
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    // Relations
    public function form(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    // Scopes
    public function scopePending($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRead($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'read');
    }

    public function scopeSpam($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'spam');
    }
}
```

### 3.6 Shared Service: FormService

```php
<?php

namespace SaasPlatform\Modules\Forms\Services;

use SaasPlatform\Modules\Forms\Contracts\Services\FormServiceContract;
use SaasPlatform\Modules\Forms\Contracts\Repositories\FormRepositoryContract;
use SaasPlatform\Modules\Forms\Models\Form;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FormService implements FormServiceContract
{
    public function __construct(
        protected FormRepositoryContract $repository
    ) {}

    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getAll($filters);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(int $id): Form
    {
        $form = $this->repository->getById($id);
        if (!$form) {
            throw new FormNotFoundException("فرم با شناسه {$id} یافت نشد.");
        }
        return $form;
    }

    public function getByUuid(string $uuid): Form
    {
        $form = $this->repository->getByUuid($uuid);
        if (!$form) {
            throw new FormNotFoundException("فرم مورد نظر یافت نشد.");
        }
        return $form;
    }

    public function getBySlug(string $slug): Form
    {
        $form = $this->repository->getBySlug($slug);
        if (!$form) {
            throw new FormNotFoundException("فرم مورد نظر یافت نشد.");
        }
        return $form;
    }

    public function create(array $data): Form
    {
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): Form
    {
        $form = $this->getById($id);
        return $this->repository->update($form, $data);
    }

    public function delete(int $id): bool
    {
        $form = $this->getById($id);
        return $this->repository->delete($form);
    }

    public function duplicate(int $id): Form
    {
        $form = $this->getById($id);
        return $this->repository->duplicate($form);
    }

    public function submit(int $formId, array $data): FormSubmission
    {
        $form = $this->getById($formId);
        if ($form->status !== 'active') {
            throw new FormSubmissionException('این فرم فعال نیست و امکان ارسال وجود ندارد.');
        }
        if ($form->submission_limit && $form->submission_count >= $form->submission_limit) {
            throw new FormSubmissionException('تعداد مجاز ارسال‌ها به پایان رسیده است.');
        }
        return $this->repository->submit($form, $data);
    }
}
```

### 3.7 Shared Repository: FormRepository

```php
<?php

namespace SaasPlatform\Modules\Forms\Repositories;

use SaasPlatform\Modules\Forms\Contracts\Repositories\FormRepositoryContract;
use SaasPlatform\Modules\Forms\Models\Form;
use SaasPlatform\Modules\Forms\Models\FormField;
use SaasPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class FormRepository implements FormRepositoryContract
{
    public function getAll(array $filters = []): Collection
    {
        $query = Form::query()->withCount(['fields', 'submissions']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        return $query->latest()->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Form::query()->withCount(['fields', 'submissions']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getById(int $id): ?Form
    {
        return Form::withCount(['fields', 'submissions'])->find($id);
    }

    public function getByUuid(string $uuid): ?Form
    {
        return Form::withCount(['fields', 'submissions'])->where('uuid', $uuid)->first();
    }

    public function getBySlug(string $slug): ?Form
    {
        return Form::withCount(['fields', 'submissions'])->where('slug', $slug)->first();
    }

    public function create(array $data): Form
    {
        $data['uuid'] = $data['uuid'] ?? (string) Str::uuid();
        return Form::create($data);
    }

    public function update(Form $form, array $data): Form
    {
        $form->update($data);
        return $form->fresh()->loadCount(['fields', 'submissions']);
    }

    public function delete(Form $form): bool
    {
        return $form->delete();
    }

    public function duplicate(Form $form): Form
    {
        $newForm = $form->replicate();
        $newForm->uuid = (string) Str::uuid();
        $newForm->title = $form->title . ' (کپی)';
        $newForm->slug = $form->slug . '-copy-' . Str::random(4);
        $newForm->save();

        // Duplicate fields
        foreach ($form->fields as $field) {
            $newField = $field->replicate();
            $newField->uuid = (string) Str::uuid();
            $newField->form_id = $newForm->id;
            $newField->save();
        }

        return $newForm->fresh()->loadCount(['fields', 'submissions']);
    }

    public function submit(Form $form, array $data): FormSubmission
    {
        return FormSubmission::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);
    }
}
```

### 3.8 Shared Controller: FormController

```php
<?php

namespace SaasPlatform\Modules\Forms\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use SaasPlatform\Modules\Forms\Contracts\Services\FormServiceContract;
use SaasPlatform\Modules\Forms\Http\Requests\FormRequest;
use SaasPlatform\Modules\Forms\Models\Form;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FormController extends Controller
{
    public function __construct(
        protected FormServiceContract $service
    ) {}

    public function index(): View
    {
        $forms = $this->service->getPaginated(request()->all());
        return view('saas-platform::forms.index', compact('forms'));
    }

    public function create(): View
    {
        return view('saas-platform::forms.create');
    }

    public function store(FormRequest $request): RedirectResponse
    {
        $form = $this->service->create($request->validated());
        return redirect()
            ->route('admin.forms.edit', $form->id)
            ->with('success', 'فرم با موفقیت ایجاد شد.');
    }

    public function show(int $id): View
    {
        $form = $this->service->getById($id);
        return view('saas-platform::forms.show', compact('form'));
    }

    public function edit(int $id): View
    {
        $form = $this->service->getById($id);
        return view('saas-platform::forms.edit', compact('form'));
    }

    public function update(FormRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());
        return redirect()
            ->route('admin.forms.edit', $id)
            ->with('success', 'فرم با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);
        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'فرم با موفقیت حذف شد.');
    }
}
```

### 3.9 Shared FormRequest

```php
<?php

namespace SaasPlatform\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:forms,slug,' . $this->route('form'),
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,active,archived',
            'submission_limit' => 'nullable|integer|min:0',
            'requires_auth' => 'boolean',
            'notification_email' => 'nullable|email|max:255',
            'success_message' => 'nullable|string|max:500',
            'redirect_url' => 'nullable|url|max:500',
            'metadata' => 'nullable|json',
        ];

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            $rules['slug'] = 'required|string|max:255|unique:forms,slug,' . $this->route('form');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان فرم الزامی است.',
            'title.max' => 'عنوان فرم نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد.',
            'slug.required' => 'نامک فرم الزامی است.',
            'slug.unique' => 'این نامک قبلاً استفاده شده است.',
            'status.required' => 'وضعیت فرم الزامی است.',
            'status.in' => 'وضعیت فرم معتبر نیست.',
            'submission_limit.integer' => 'محدودیت ارسال باید عدد باشد.',
            'requires_auth.boolean' => 'مقدار احراز هویت معتبر نیست.',
            'notification_email.email' => 'ایمیل اطلاع‌رسانی معتبر نیست.',
            'redirect_url.url' => 'آدرس بازگشت معتبر نیست.',
        ];
    }
}
```

### 3.10 Shared Routes

```php
<?php
// packages/saas-platform/modules/forms/routes/web.php

use Illuminate\Support\Facades\Route;
use SaasPlatform\Modules\Forms\Http\Controllers\Web\FormController;
use SaasPlatform\Modules\Forms\Http\Controllers\Web\FormFieldController;
use SaasPlatform\Modules\Forms\Http\Controllers\Web\FormSubmissionController;

Route::prefix(config('forms.route_prefix', 'admin/forms'))
    ->middleware(config('forms.route_middleware', ['web', 'auth', 'admin']))
    ->name(config('forms.route_name_prefix', 'admin.forms.'))
    ->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::get('/create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('/{form}', [FormController::class, 'show'])->name('show');
        Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit');
        Route::put('/{form}', [FormController::class, 'update'])->name('update');
        Route::delete('/{form}', [FormController::class, 'destroy'])->name('destroy');

        // Fields
        Route::prefix('/{form}/fields')->name('fields.')->group(function () {
            Route::get('/', [FormFieldController::class, 'index'])->name('index');
            Route::get('/create', [FormFieldController::class, 'create'])->name('create');
            Route::post('/', [FormFieldController::class, 'store'])->name('store');
            Route::get('/{field}/edit', [FormFieldController::class, 'edit'])->name('edit');
            Route::put('/{field}', [FormFieldController::class, 'update'])->name('update');
            Route::delete('/{field}', [FormFieldController::class, 'destroy'])->name('destroy');
            Route::post('/reorder', [FormFieldController::class, 'reorder'])->name('reorder');
        });

        // Submissions
        Route::prefix('/{form}/submissions')->name('submissions.')->group(function () {
            Route::get('/', [FormSubmissionController::class, 'index'])->name('index');
            Route::get('/{submission}', [FormSubmissionController::class, 'show'])->name('show');
            Route::patch('/{submission}/status', [FormSubmissionController::class, 'updateStatus'])->name('status');
            Route::delete('/{submission}', [FormSubmissionController::class, 'destroy'])->name('destroy');
        });
    });
```

### 3.11 Shared Config

```php
<?php
// packages/saas-platform/modules/
forms/config/forms.php

return [
    'route_prefix' => env('FORMS_ROUTE_PREFIX', 'admin/forms'),
    'route_middleware' => ['web', 'auth', 'admin'],
    'route_name_prefix' => 'admin.forms.',

    'field_types' => [
        'text' => 'متن',
        'textarea' => 'متن بلند',
        'email' => 'ایمیل',
        'number' => 'عدد',
        'select' => 'انتخابی',
        'checkbox' => 'چک‌باکس',
        'radio' => 'رادیو',
        'file' => 'آپلود فایل',
        'date' => 'تاریخ',
        'time' => 'زمان',
        'hidden' => 'مخفی',
    ],

    'statuses' => [
        'draft' => 'پیش‌نویس',
        'active' => 'فعال',
        'archived' => 'بایگانی شده',
    ],

    'submission_statuses' => [
        'pending' => 'در انتظار بررسی',
        'read' => 'خوانده شده',
        'spam' => 'هرزنامه',
        'archived' => 'بایگانی شده',
    ],

    'width_classes' => [
        'full' => 'col-span-12',
        'half' => 'col-span-6',
        'third' => 'col-span-4',
        'two-thirds' => 'col-span-8',
    ],

    'defaults' => [
        'per_page' => 15,
        'submission_limit' => 0,
        'requires_auth' => false,
    ],
];
```

### 3.12 Shared ServiceProvider

```php
<?php

namespace SaasPlatform\Modules\Forms;

use SaasPlatform\Shared\Providers\BasePackageProvider;
use SaasPlatform\Modules\Forms\Contracts\Services\FormServiceContract;
use SaasPlatform\Modules\Forms\Contracts\Services\FormFieldServiceContract;
use SaasPlatform\Modules\Forms\Contracts\Repositories\FormRepositoryContract;
use SaasPlatform\Modules\Forms\Contracts\Repositories\FormFieldRepositoryContract;
use SaasPlatform\Modules\Forms\Services\FormService;
use SaasPlatform\Modules\Forms\Services\FormFieldService;
use SaasPlatform\Modules\Forms\Repositories\FormRepository;
use SaasPlatform\Modules\Forms\Repositories\FormFieldRepository;

class FormsServiceProvider extends BasePackageProvider
{
    public function getPackageName(): string
    {
        return 'saas-platform/modules/forms';
    }

    public function getPackageNamespace(): string
    {
        return 'SaasPlatform\\Modules\\Forms';
    }

    public function getPackageVersion(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(FormRepositoryContract::class, FormRepository::class);
        $this->app->bind(FormFieldRepositoryContract::class, FormFieldRepository::class);
        $this->app->bind(FormServiceContract::class, FormService::class);
        $this->app->bind(FormFieldServiceContract::class, FormFieldService::class);
    }
}
```

### 3.13 Shared Blade Views (index.blade.php)

```blade
{{-- packages/saas-platform/modules/forms/resources/views/index.blade.php --}}
<x-admin-layout>
    <x-slot:title>مدیریت فرم‌ها</x-slot:title>

    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">مدیریت فرم‌ها</h1>
                <p class="mt-1 text-sm text-gray-500">لیست تمام فرم‌های ثبت شده در سیستم</p>
            </div>
            <a href="{{ route('admin.forms.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                <span>فرم جدید</span>
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="جستجوی فرم..."
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                </div>
                <div class="w-40">
                    <select name="status"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach(config('forms.statuses') as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                    فیلتر
                </button>
            </form>
        </div>

        {{-- Forms Table --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">عنوان</th>
                            <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">نامک</th>
                            <th class="text-center px-6 py-4 text-sm font-semibold text-gray-600">وضعیت</th>
                            <th class="text-center px-6 py-4 text-sm font-semibold text-gray-600">فیلدها</th>
                            <th class="text-center px-6 py-4 text-sm font-semibold text-gray-600">ارسال‌ها</th>
                            <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($forms as $form)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.forms.edit', $form->id) }}"
                                               class="font-medium text-gray-900 hover:text-indigo-600 transition-colors">
                                                {{ $form->title }}
                                            </a>
                                            @if($form->description)
                                                <p class="text-xs text-gray-500 mt-0.5">{{ Str::limit($form->description, 50) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $form->slug }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium
                                        {{ $form->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $form->status === 'draft' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $form->status === 'archived' ? 'bg-gray-100 text-gray-700' : '' }}">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            {{ $form->status === 'active' ? 'bg-emerald-500' : '' }}
                                            {{ $form->status === 'draft' ? 'bg-amber-500' : '' }}
                                            {{ $form->status === 'archived' ? 'bg-gray-500' : '' }}">
                                        </span>
                                        {{ config('forms.statuses.' . $form->status, $form->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600">{{ $form->fields_count }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600">{{ $form->submissions_count }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.forms.show', $form->id) }}"
                                           class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                                           title="مشاهده">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.forms.edit', $form->id) }}"
                                           class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all"
                                           title="ویرایش">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.forms.destroy', $form->id) }}" method="POST"
                                              onsubmit="return confirm('آیا از حذف این فرم اطمینان دارید؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                                    title="حذف">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                        </svg>
                                        <p class="text-gray-500">هنوز هیچ فرمی ایجاد نشده است.</p>
                                        <a href="{{ route('admin.forms.create') }}"
                                           class="text-indigo-600 hover:text-indigo-700 font-medium">
                                            ایجاد فرم جدید
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($forms->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $forms->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
```

### 3.14 Shared Contracts

```php
<?php
// packages/saas-platform/modules/forms/src/Contracts/Services/FormServiceContract.php

namespace SaasPlatform\Modules\Forms\Contracts\Services;

use SaasPlatform\Modules\Forms\Models\Form;
use SaasPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FormServiceContract
{
    public function getAll(array $filters = []): Collection;
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): Form;
    public function getByUuid(string $uuid): Form;
    public function getBySlug(string $slug): Form;
    public function create(array $data): Form;
    public function update(int $id, array $data): Form;
    public function delete(int $id): bool;
    public function duplicate(int $id): Form;
    public function submit(int $formId, array $data): FormSubmission;
}
```

```php
<?php
// packages/saas-platform/modules/forms/src/Contracts/Repositories/FormRepositoryContract.php

namespace SaasPlatform\Modules\Forms\Contracts\Repositories;

use SaasPlatform\Modules\Forms\Models\Form;
use SaasPlatform\Modules\Forms\Models\FormSubmission;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FormRepositoryContract
{
    public function getAll(array $filters = []): Collection;
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): ?Form;
    public function getByUuid(string $uuid): ?Form;
    public function getBySlug(string $slug): ?Form;
    public function create(array $data): Form;
    public function update(Form $form, array $data): Form;
    public function delete(Form $form): bool;
    public function duplicate(Form $form): Form;
    public function submit(Form $form, array $data): FormSubmission;
}
```

### 3.15 Shared Exceptions

```php
<?php
// packages/saas-platform/modules/forms/src/Exceptions/FormNotFoundException.php

namespace SaasPlatform\Modules\Forms\Exceptions;

use Exception;

class FormNotFoundException extends Exception
{
    public function render()
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'فرم مورد نظر یافت نشد.',
        ], 404);
    }
}
```

```php
<?php
// packages/saas-platform/modules/forms/src/Exceptions/FormSubmissionException.php

namespace SaasPlatform\Modules\Forms\Exceptions;

use Exception;

class FormSubmissionException extends Exception
{
    public function render()
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'خطا در ارسال فرم.',
        ], 422);
    }
}
```

### 3.16 Shared module.json

```json
{
    "name": "saas-platform/modules/forms",
    "label": "Forms",
    "label_fa": "فرم‌ها",
    "description": "Shared Forms Management Module",
    "description_fa": "ماژول مدیریت فرم‌های اشتراکی",
    "version": "1.0.0",
    "type": "module",
    "namespace": "SaasPlatform\\Modules\\Forms",
    "provider": "SaasPlatform\\Modules\\Forms\\FormsServiceProvider",
    "priority": 50,
    "dependencies": {
        "required": [
            "saas-platform/shared"
        ],
        "optional": [],
        "suggested": [
            "saas-platform/modules/menus"
        ],
        "conflicting": []
    },
    "migrations": {
        "enabled": true,
        "paths": [
            "database/migrations"
        ]
    },
    "routes": {
        "web": "routes/web.php",
        "api": "routes/api.php"
    },
    "config": "config/forms.php",
    "assets": {
        "css": [],
        "js": []
    },
    "permissions": [
        "forms.view",
        "forms.create",
        "forms.edit",
        "forms.delete",
        "forms.submissions.view",
        "forms.submissions.manage"
    ],
    "tenant_aware": true
}
```

---

## 4. Module: Menus (`packages/saas-platform/modules/menus/`)

### 4.1 Current State Analysis

#### saas-platform
```
app/Models/Menu.php                   — Menu model
app/Models/MenuItem.php               — MenuItem model
app/Http/Controllers/Web/MenuController.php       — CRUD controller
app/Http/Requests/MenuRequest.php                 — Validation
app/Services/MenuService.php                      — Business logic
app/Repositories/MenuRepository.php               — DB operations
resources/views/admin/menus/index.blade.php       — List view
resources/views/admin/menus/create.blade.php      — Create view
resources/views/admin/menus/edit.blade.php        — Edit view
resources/views/admin/menus/show.blade.php        — Show view
database/migrations/xxxx_xx_xx_create_menus_table.php
database/migrations/xxxx_xx_xx_create_menu_items_table.php
```

#### customer-site
```
app/Models/Menu.php                   — Drifted
app/Models/MenuItem.php               — Drifted
app/Http/Controllers/Admin/MenuController.php    — Different namespace
resources/views/admin/menus/index.blade.php       — Drifted UI
resources/views/admin/menus/create.blade.php      — Drifted UI
resources/views/admin/menus/edit.blade.php        — Drifted UI
database/migrations/xxxx_xx_xx_create_menus_table.php
database/migrations/xxxx_xx_xx_create_menu_items_table.php
```

### 4.2 Shared Package Structure

```
packages/saas-platform/modules/menus/
├── composer.json
├── module.json
├── src/
│   ├── MenusServiceProvider.php
│   ├── Models/
│   │   ├── Menu.php
│   │   └── MenuItem.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Web/
│   │   │       └── MenuController.php
│   │   └── Requests/
│   │       └── MenuRequest.php
│   ├── Services/
│   │   └── MenuService.php
│   ├── Repositories/
│   │   └── MenuRepository.php
│   ├── Contracts/
│   │   ├── Services/
│   │   │   └── MenuServiceContract.php
│   │   └── Repositories/
│   │       └── MenuRepositoryContract.php
│   └── Exceptions/
│       └── MenuNotFoundException.php
├── resources/
│   ├── views/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php
│   └── lang/
│       └── fa/
│           ├── messages.php
│           └── validation.php
├── database/
│   └── migrations/
│       ├── 2025_01_01_000001_create_menus_table.php
│       └── 2025_01_01_000002_create_menu_items_table.php
├── config/
│   └── menus.php
├── routes/
│   └── web.php
└── tests/
    ├── Feature/
    │   └── MenuControllerTest.php
    └── Unit/
        └── MenuServiceTest.php
```

### 4.3 Shared Model: Menu

```php
<?php

namespace SaasPlatform\Modules\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;
use SaasPlatform\Shared\Contracts\TenantScoped;
use SaasPlatform\Shared\Traits\BelongsToTenant;

class Menu extends Model implements TenantScoped
{
    use SoftDeletes, HasUuid, HasUserStamps, BelongsToTenant;

    protected $table = 'menus';

    protected $fillable = [
        'uuid',
        'site_id',
        'name',
        'slug',
        'description',
        'location',          // header, footer, sidebar, custom
        'status',            // active, inactive
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')->orderBy('order');
    }

    public function activeItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')
            ->where('status', 'active')
            ->orderBy('order');
    }

    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }
}
```

### 4.4 Shared Model: MenuItem

```php
<?php

namespace SaasPlatform\Modules\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;

class MenuItem extends Model
{
    use SoftDeletes, HasUuid, HasUserStamps;

    protected $table = 'menu_items';

    protected $fillable = [
        'uuid',
        'menu_id',
        'parent_id',
        'title',
        'url',
        'route',
        'icon',
        'target',            // _self, _blank
        'order',
        'status',            // active, inactive
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'active',
        'target' => '_self',
        'order' => 0,
    ];

    public function menu(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }

    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeRoot($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('parent_id');
    }
}
```

---

## 5. Module: Pages (`packages/saas-platform/modules/pages/`)

### 5.1 Current State Analysis

#### saas-platform
```
app/Models/Page.php                   — Page model
app/Http/Controllers/Web/PageController.php       — CRUD controller
app/Http/Requests/PageRequest.php                 — Validation
app/Services/PageService.php                      — Business logic
app/Repositories/PageRepository.php               — DB operations
resources/views/admin/pages/index.blade.php       — List view
resources/views/admin/pages/create.blade.php      — Create view
resources/views/admin/pages/edit.blade.php        — Edit view
resources/views/admin/pages/show.blade.php        — Show view
database/migrations/xxxx_xx_xx_create_pages_table.php
```

#### customer-site
```
app/Models/Page.php                   — Drifted
app/Http/Controllers/Admin/PageController.php    — Different namespace
resources/views/admin/pages/index.blade.php       — Drifted UI
resources/views/admin/pages/create.blade.php      — Drifted UI
resources/views/admin/pages/edit.blade.php        — Drifted UI
database/migrations/xxxx_xx_xx_create_pages_table.php
```

### 5.2 Shared Package Structure

```
packages/saas-platform/modules/pages/
├── composer.json
├── module.json
├── src/
│   ├── PagesServiceProvider.php
│   ├── Models/
│   │   └── Page.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Web/
│   │   │       └── PageController.php
│   │   └── Requests/
│   │       └── PageRequest.php
│   ├── Services/
│   │   └── PageService.php
│   ├── Repositories/
│   │   └── PageRepository.php
│   ├── Contracts/
│   │   ├── Services/
│   │   │   └── PageServiceContract.php
│   │   └── Repositories/
│   │       └── PageRepositoryContract.php
│   └── Exceptions/
│       └── PageNotFoundException.php
├── resources/
│   ├── views/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php
│   └── lang/
│       └── fa/
│           ├── messages.php
│           └── validation.php
├── database/
│   └── migrations/
│       └── 2025_01_01_000001_create_pages_table.php
├── config/
│   └── pages.php
├── routes/
│   └── web.php
└── tests/
    ├── Feature/
    │   └── PageControllerTest.php
    └── Unit/
        └── PageServiceTest.php
```

### 5.3 Shared Model: Page

```php
<?php

namespace SaasPlatform\Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;
use SaasPlatform\Shared\Contracts\TenantScoped;
use SaasPlatform\Shared\Traits\BelongsToTenant;

class Page extends Model implements TenantScoped
{
    use SoftDeletes, HasUuid, HasUserStamps, BelongsToTenant;

    protected $table = 'pages';

    protected $fillable = [
        'uuid',
        'site_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',            // draft, published, archived
        'template',
        'featured_image',
        'order',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'order' => 0,
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    public function scopePublished($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'draft');
    }
}
```

---

## 6. Module: Activity Log (`packages/saas-platform/modules/activity-log/`)

### 6.1 Current State Analysis

#### saas-platform
```
app/Models/ActivityLog.php            — ActivityLog model
app/Http/Controllers/Web/ActivityLogController.php  — List controller
app/Services/ActivityLogService.php                 — Logging logic
app/Repositories/ActivityLogRepository.php          — DB operations
resources/views/admin/activity-logs/index.blade.php — List view
resources/views/admin/activity-logs/show.blade.php  — Detail view
database/migrations/xxxx_xx_xx_create_activity_logs_table.php
```

#### customer-site
```
app/Models/ActivityLog.php            — Drifted
app/Http/Controllers/Admin/ActivityLogController.php  — Different namespace
resources/views/admin/activity-logs/index.blade.php    — Drifted UI
database/migrations/xxxx_xx_xx_create_activity_logs_table.php
```

### 6.2 Shared Package Structure

```
packages/saas-platform/modules/activity-log/
├── composer.json
├── module.json
├── src/
│   ├── ActivityLogServiceProvider.php
│   ├── Models/
│   │   └── ActivityLog.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Web/
│   │   │       └── ActivityLogController.php
│   │   └── Resources/
│   │       └── ActivityLogResource.php
│   ├── Services/
│   │   └── ActivityLogService.php
│   ├── Repositories/
│   │   └── ActivityLogRepository.php
│   ├── Contracts/
│   │   ├── Services/
│   │   │   └── ActivityLogServiceContract.php
│   │   └── Repositories/
│   │       └── ActivityLogRepositoryContract.php
│   └── Traits/
│       └── LogsActivity.php
├── resources/
│   ├── views/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── lang/
│       └── fa/
│           └── messages.php
├── database/
│   └── migrations/
│       └── 2025_01_01_000
001_create_activity_logs_table.php
├── config/
│   └── activity-log.php
├── routes/
│   └── web.php
└── tests/
    ├── Feature/
    │   └── ActivityLogControllerTest.php
    └── Unit/
        └── ActivityLogServiceTest.php
```

### 6.3 Shared Model: ActivityLog

```php
<?php

namespace SaasPlatform\Modules\ActivityLog\Models;

use Illuminate\Database\Eloquent\Model;
use SaasPlatform\Shared\Traits\HasUuid;

class ActivityLog extends Model
{
    use HasUuid;

    protected $table = 'activity_logs';

    protected $fillable = [
        'uuid',
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByLogName($query, string $name): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('log_name', $name);
    }

    public function scopeByEvent($query, string $event): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('event', $event);
    }

    public function scopeRecent($query, int $days = 7): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

### 6.4 Shared Trait: LogsActivity

```php
<?php

namespace SaasPlatform\Modules\ActivityLog\Traits;

use SaasPlatform\Modules\ActivityLog\Models\ActivityLog;
use Illuminate\Support\Str;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        foreach (static::getActivityEvents() as $event) {
            static::{$event}(function ($model) use ($event) {
                if (!static::shouldLogEvent($event, $model)) {
                    return;
                }

                ActivityLog::create([
                    'uuid' => (string) Str::uuid(),
                    'log_name' => static::getLogName(),
                    'description' => static::getEventDescription($event, $model),
                    'subject_type' => get_class($model),
                    'subject_id' => $model->getKey(),
                    'causer_type' => auth()->check() ? get_class(auth()->user()) : null,
                    'causer_id' => auth()->id(),
                    'properties' => static::getActivityProperties($model),
                    'event' => $event,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        }
    }

    protected static function getActivityEvents(): array
    {
        return ['created', 'updated', 'deleted', 'restored'];
    }

    protected static function shouldLogEvent(string $event, $model): bool
    {
        return true;
    }

    protected static function getLogName(): string
    {
        return strtolower(class_basename(static::class));
    }

    protected static function getEventDescription(string $event, $model): string
    {
        $name = class_basename(static::class);
        $label = $model->title ?? $model->name ?? $model->getKey();
        $events = [
            'created' => "{$name} «{$label}» ایجاد شد",
            'updated' => "{$name} «{$label}» به‌روزرسانی شد",
            'deleted' => "{$name} «{$label}» حذف شد",
            'restored' => "{$name} «{$label}» بازیابی شد",
        ];
        return $events[$event] ?? "{$name} «{$label}» تغییر کرد";
    }

    protected static function getActivityProperties($model): array
    {
        $properties = [];
        if ($model->wasChanged()) {
            $properties['attributes'] = $model->getAttributes();
            $properties['old'] = $model->getOriginal();
        }
        return $properties;
    }
}
```

---

## 7. Module: Settings (`packages/saas-platform/modules/settings/`)

### 7.1 Current State Analysis

#### saas-platform
```
app/Models/Setting.php                — Setting model (key-value)
app/Http/Controllers/Web/SettingController.php   — Settings management
app/Http/Requests/SettingRequest.php             — Validation
app/Services/SettingService.php                  — Business logic
app/Repositories/SettingRepository.php           — DB operations
resources/views/admin/settings/index.blade.php   — Settings page
resources/views/admin/settings/general.blade.php — General settings
resources/views/admin/settings/email.blade.php   — Email settings
resources/views/admin/settings/security.blade.php — Security settings
database/migrations/xxxx_xx_xx_create_settings_table.php
```

#### customer-site
```
app/Models/Setting.php                — Drifted
app/Http/Controllers/Admin/SettingController.php  — Different namespace
resources/views/admin/settings/index.blade.php     — Drifted UI
database/migrations/xxxx_xx_xx_create_settings_table.php
```

### 7.2 Shared Package Structure

```
packages/saas-platform/modules/settings/
├── composer.json
├── module.json
├── src/
│   ├── SettingsServiceProvider.php
│   ├── Models/
│   │   └── Setting.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Web/
│   │   │       └── SettingController.php
│   │   └── Requests/
│   │       └── SettingRequest.php
│   ├── Services/
│   │   └── SettingService.php
│   ├── Repositories/
│   │   └── SettingRepository.php
│   ├── Contracts/
│   │   ├── Services/
│   │   │   └── SettingServiceContract.php
│   │   └── Repositories/
│   │       └── SettingRepositoryContract.php
│   └── Exceptions/
│       └── SettingNotFoundException.php
├── resources/
│   ├── views/
│   │   ├── index.blade.php
│   │   ├── general.blade.php
│   │   ├── email.blade.php
│   │   └── security.blade.php
│   └── lang/
│       └── fa/
│           └── messages.php
├── database/
│   └── migrations/
│       └── 2025_01_01_000001_create_settings_table.php
├── config/
│   └── settings.php
├── routes/
│   └── web.php
└── tests/
    ├── Feature/
    │   └── SettingControllerTest.php
    └── Unit/
        └── SettingServiceTest.php
```

### 7.3 Shared Model: Setting

```php
<?php

namespace SaasPlatform\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use SaasPlatform\Shared\Traits\HasUuid;
use SaasPlatform\Shared\Traits\HasUserStamps;
use SaasPlatform\Shared\Contracts\TenantScoped;
use SaasPlatform\Shared\Traits\BelongsToTenant;

class Setting extends Model implements TenantScoped
{
    use HasUuid, HasUserStamps, BelongsToTenant;

    protected $table = 'settings';

    protected $fillable = [
        'uuid',
        'site_id',
        'key',
        'value',
        'group',             // general, email, security, seo, social
        'type',              // text, textarea, boolean, select, image, file
        'options',           // JSON for select type
        'description',
        'is_encrypted',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'string',
        'options' => 'array',
        'is_encrypted' => 'boolean',
    ];

    protected $attributes = [
        'is_encrypted' => false,
    ];

    public function scopeByGroup($query, string $group): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('group', $group);
    }

    public function scopeByKey($query, string $key): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('key', $key);
    }
}
```

### 7.4 Shared Service: SettingService

```php
<?php

namespace SaasPlatform\Modules\Settings\Services;

use SaasPlatform\Modules\Settings\Contracts\Services\SettingServiceContract;
use SaasPlatform\Modules\Settings\Contracts\Repositories\SettingRepositoryContract;
use SaasPlatform\Modules\Settings\Models\Setting;
use Illuminate\Support\Collection;

class SettingService implements SettingServiceContract
{
    public function __construct(
        protected SettingRepositoryContract $repository
    ) {}

    public function getAll(): Collection
    {
        return $this->repository->getAll();
    }

    public function getByGroup(string $group): Collection
    {
        return $this->repository->getByGroup($group);
    }

    public function get(string $key, $default = null): ?string
    {
        return $this->repository->get($key, $default);
    }

    public function set(string $key, $value): Setting
    {
        return $this->repository->set($key, $value);
    }

    public function setMultiple(array $settings): bool
    {
        return $this->repository->setMultiple($settings);
    }

    public function has(string $key): bool
    {
        return $this->repository->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->repository->delete($key);
    }

    public function getGroups(): array
    {
        return [
            'general' => 'تنظیمات عمومی',
            'email' => 'تنظیمات ایمیل',
            'security' => 'تنظیمات امنیتی',
            'seo' => 'تنظیمات سئو',
            'social' => 'تنظیمات شبکه‌های اجتماعی',
        ];
    }
}
```

---

## 8. Module: Captcha (`packages/saas-platform/modules/captcha/`)

### 8.1 Current State Analysis

#### saas-platform
```
app/Services/CaptchaService.php       — Captcha verification
app/Http/Controllers/Api/CaptchaController.php  — Captcha generation
resources/views/admin/captcha/       — Captcha partials
```

#### customer-site
```
app/Services/CaptchaService.php       — Drifted
```

### 8.2 Shared Package Structure

```
packages/saas-platform/modules/captcha/
├── composer.json
├── module.json
├── src/
│   ├── CaptchaServiceProvider.php
│   ├── Services/
│   │   └── CaptchaService.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── CaptchaController.php
│   ├── Contracts/
│   │   └── Services/
│   │       └── CaptchaServiceContract.php
│   └── Exceptions/
│       └── CaptchaValidationException.php
├── resources/
│   ├── views/
│   │   └── captcha.blade.php
│   └── lang/
│       └── fa/
│           └── messages.php
├── config/
│   └── captcha.php
├── routes/
│   └── api.php
└── tests/
    └── Unit/
        └── CaptchaServiceTest.php
```

### 8.3 Shared Service: CaptchaService

```php
<?php

namespace SaasPlatform\Modules\Captcha\Services;

use SaasPlatform\Modules\Captcha\Contracts\Services\CaptchaServiceContract;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class CaptchaService implements CaptchaServiceContract
{
    public function generate(): array
    {
        $code = (string) random_int(100000, 999999);
        $token = (string) Str::uuid();

        Cache::put("captcha:{$token}", $code, now()->addMinutes(5));

        return [
            'token' => $token,
            'image' => $this->generateImage($code),
        ];
    }

    public function verify(string $token, string $code): bool
    {
        $cached = Cache::get("captcha:{$token}");

        if (!$cached || $cached !== $code) {
            return false;
        }

        Cache::forget("captcha:{$token}");
        return true;
    }

    protected function generateImage(string $code): string
    {
        $width = 150;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);

        $bgColor = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $bgColor);
        imagestring($image, 5, 30, 15, $code, $textColor);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
```

---

## 9. Backward Compatibility Layer

### 9.1 Purpose

The backward compatibility layer ensures that existing code in both projects continues to work during and after migration. It provides seamless fallback paths so that no code changes are required in existing controllers, views, or services.

### 9.2 Compatibility Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    EXISTING APPLICATION CODE                 │
│  (Controllers, Views, Services, Routes)                     │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              BACKWARD COMPATIBILITY LAYER                    │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ Model Aliases     │  │ Facade Proxies   │                 │
│  │ (class_alias)     │  │ (forward static  │                 │
│  │                   │  │  calls)          │                 │
│  └──────────────────┘  └──────────────────┘                 │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │ Route Forwarders  │  │ View Composers   │                 │
│  │ (redirect old     │  │ (inject shared   │                 │
│  │  routes to new)   │  │  data)           │                 │
│  └──────────────────┘  └──────────────────┘                 │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              SHARED PACKAGE (NEW)                            │
│  (Models, Controllers, Services, Views)                     │
└─────────────────────────────────────────────────────────────┘
```

### 9.3 Model Aliases

```php
// bootstrap/compat.php (both projects)
// Loaded in AppServiceProvider::boot()

if (!ModuleMigrationHelper::useShared('forms')) {
    class_alias(
        \App\Models\Form::class,
        \SaasPlatform\Modules\Forms\Models\Form::class
    );
    class_alias(
        \App\Models\FormField::class,
        \SaasPlatform\Modules\Forms\Models\FormField::class
    );
    class_alias(
        \App\Models\FormSubmission::class,
        \SaasPlatform\Modules\Forms\Models\FormSubmission::class
    );
}

if (!ModuleMigrationHelper::useShared('menus')) {
    class_alias(
        \App\Models\Menu::class,
        \SaasPlatform\Modules\Menus\Models\Menu::class
    );
    class_alias(
        \App\Models\MenuItem::class,
        \SaasPlatform\Modules\Menus\Models\MenuItem::class
    );
}

if (!ModuleMigrationHelper::useShared('pages')) {
    class_alias(
        \App\Models\Page::class,
        \SaasPlatform\Modules\Pages\Models\Page::class
    );
}

if (!ModuleMigrationHelper::useShared('activity_log')) {
    class_alias(
        \App\Models\ActivityLog::class,
        \SaasPlatform\Modules\ActivityLog\Models\ActivityLog::class
    );
}

if (!ModuleMigrationHelper::useShared('settings')) {
    class_alias(
        \App\Models\Setting::class,
        \SaasPlatform\Modules\Settings\Models\Setting::class
    );
}
```

### 9.4 Route Forwarders

```php
// routes/compat.php (both projects)
// Loaded in RouteServiceProvider::boot()

use Illuminate\Support\Facades\Route;

// Forms route forwarder
if (!ModuleMigrationHelper::useShared('forms')) {
    Route::prefix('admin/forms')
        ->middleware(['web', 'auth', 'admin'])
        ->name('admin.forms.')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\FormController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Web\FormController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Web\FormController::class, 'store'])->name('store');
            Route::get('/{form}', [\App\Http\Controllers\Web\FormController::class, 'show'])->name('show');
            Route::get('/{form}/edit', [\App\Http\Controllers\Web\FormController::class, 'edit'])->name('edit');
            Route::put('/{form}', [\App\Http\Controllers\Web\FormController::class, 'update'])->name('update');
            Route::delete('/{form}', [\App\Http\Controllers\Web\FormController::class, 'destroy'])->name('destroy');
        });
}
```

### 9.5 View Compatibility

```php
// app/Providers/AppServiceProvider.php (both projects)
// In boot() method

use Illuminate\Support\Facades\View;

// Register shared view namespaces
View::composer('admin.forms.*', function ($view) {
    if (ModuleMigrationHelper::useShared('forms')) {
        $view->setPath(resource_path('views/vendor/saas-platform/forms/' . $view->getName()));
    }
});
```

---

## 10. Migration Rollback Plan

### 10.1 Rollback Triggers

| Trigger | Description | Action |
|---------|-------------|--------|
| **Critical Bug** | Shared module causes production outage | Immediate rollback via feature flag |
| **Data Loss** | Migration causes data corruption | Restore from backup, rollback migration |
| **Performance Regression** | Shared module is >20% slower | Rollback, profile, fix, retry |
| **Incompatibility** | Shared module breaks existing integration | Rollback, fix compatibility layer |
| **Test Failure** | >5% of tests fail after migration | Rollback, fix, retry |

### 10.2 Rollback Procedure

```bash
# Step 1: Flip feature flag back to local
php artisan config:set modules.migration.forms=local

# Step 2: Clear config cache
php artisan config:clear

# Step 3: Verify local implementation works
php artisan route:list | grep forms

# Step 4: Run tests to confirm
php artisan test --filter=FormTest

# Step 5: Monitor for 24 hours
# If stable, proceed to investigate shared module issue
```

### 10.3 Rollback Migration

```php
<?php
// database/migrations/xxxx_xx_xx_rollback_shared_forms.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RollbackSharedForms extends Migration
{
    public function up(): void
    {
        // Restore any schema changes made by shared module
        // This migration is only run during rollback
    }

    public function down(): void
    {
        // Re-apply shared module schema
    }
}
```

---

## 11. Testing Strategy

### 11.1 Test Categories

| Category | Description | Coverage Target |
|----------|-------------|-----------------|
| **Unit Tests** | Test services, repositories, models in isolation | 90%+ |
| **Feature Tests** | Test HTTP controllers, routes, middleware | 85%+ |
| **Integration Tests** | Test shared module with real database | 80%+ |
| **Regression Tests** | Compare local vs shared output | 100% of critical paths |
| **Performance Tests** | Benchmark shared vs local | <10% degradation |

### 11.2 Regression Test Suite

```php
<?php

namespace Tests\Feature\Migration;

use Tests\TestCase;
use SaasPlatform\Modules\Forms\Models\Form;
use App\Models\Form as LocalForm;

class FormMigrationRegressionTest extends TestCase
{
    /** @test */
    public function shared_model_returns_same_data_as_local()
    {
        // Create a form using local model
        $localForm = LocalForm::factory()->create([
            'title' => 'Test Form',
            'status' => 'active',
        ]);

        // Retrieve using shared model
        $sharedForm = Form::find($localForm->id);

        // Assert identical data
        $this->assertEquals($localForm->title, $sharedForm->title);
        $this->assertEquals($localForm->status, $sharedForm->status);
        $this->assertEquals($localForm->slug, $sharedForm->slug);
    }

    /** @test */
    public function shared_controller_returns_same_response_as_local()
    {
        // Set feature flag to local
        config(['modules.migration.forms' => 'local']);
        $localResponse = $this->get(route('admin.forms.index'));

        // Set feature flag to shared
        config(['modules.migration.forms' => 'shared']);
        $sharedResponse = $this->get(route('admin.forms.index'));

        // Assert same status code
        $this->assertEquals($localResponse->status(), $sharedResponse->status());
    }
}
```

### 11.3 Migration Test Command

```bash
# Run all migration regression tests
php artisan test --filter=Migration

# Run specific module regression tests
php artisan test --filter=FormMigrationRegressionTest
php artisan test --filter=MenuMigrationRegressionTest
php artisan test --filter=PageMigrationRegressionTest

# Run performance comparison
php artisan test --filter=PerformanceTest --env=testing
```

---

## 12. Anti-Drift Enforcement

### 12.1 Drift Detection Rules

| # | Rule | Description | Enforcement |
|---|------|-------------|-------------|
| 1 | **No Direct Model Usage** | Controllers must use Service layer | CI check |
| 2 | **No Query in Controller** | All DB queries must be in Repository | CI check |
| 3 | **Interface Compliance** | Services must implement Contracts | Static analysis |
| 4 | **View Namespace** | Shared views use `saas-platform::` prefix | CI check |
| 5 | **Route Prefix** | Shared routes use configurable prefix | CI check |
| 6 | **Migration Naming** | Migrations follow `YYYY_MM_DD_HHMMSS` format | CI check |
| 7 | **No Hardcoded Paths** | All paths use config() or env() | CI check |
| 8 | **Persian Error Messages** | All user-facing errors in Persian | CI check |
| 9 | **Tenant Scoped** | All models implement TenantScoped | Static analysis |
| 10 | **Feature Flag Gated** | All migrations use feature flag | CI check |

### 12.2 Drift Detection Command

```php
<?php

namespace SaasPlatform\Shared\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DetectModuleDrift extends Command
{
    protected $signature = 'modules:detect-drift
        {--module= : Specific module to check}
        {--fix : Auto-fix common drift issues}';

    protected $description = 'Detect drift between local and shared module implementations';

    public function handle(): int
    {
        $modules = $this->option('module')
            ? [$this->option('module')]
            : ['forms', 'menus', 'pages', 'activity-log', 'settings', 'captcha'];

        $driftFound = false;

        foreach ($modules as $module) {
            $this->info("Checking module: {$module}");

            // Check 1: Local model exists but shared is active
            $localModel = app_path("Models/" . ucfirst($module === 'activity-log' ? 'ActivityLog' : $module));
            if (File::exists($localModel) && ModuleMigrationHelper::useShared($module)) {
                $this->warn("  [DRIFT] Local model still exists: {$localModel}");
                $driftFound = true;
            }

            // Check 2: Local controller exists but shared is active
            $localController = app_path("Http/Controllers/" . (File::exists(app_path("Http/Controllers/Web")) ? "Web" : "Admin") . "/" . ucfirst($module) . "Controller.php");
            // ... additional checks
        }

        return $driftFound ? Command::FAILURE : Command::SUCCESS;
    }
}
```

### 12.3 CI Enforcement

```yaml
# .github/workflows/module-drift-detection.yml
name: Module Drift Detection

on:
  pull_request:
    paths:
      - 'packages/saas-platform/modules/**'
      - 'app/Models/**'
      - 'app/Http/Controllers/**'

jobs:
  drift-detection:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Detect Module Drift
        run: php artisan modules:detect-drift
      - name: Check Interface Compliance
        run: php artisan modules:check-interfaces
      - name: Validate Module.json
        run: php artisan modules:validate-manifests
```

---

## 13. Timeline & Milestones

### 13.1 Migration Timeline

```
Week 1 (Days 1-5): Forms Module Migration
  Day 1: Analyze & audit current Forms code
  Day 2: Create shared package structure
  Day 3: Extract models, services, repositories
  Day 4: Create Blade views & routes
  Day 5: Test & validate (local vs shared)

Week 2 (Days 6-8): Menus Module Migration
  Day 6: Analyze & create shared package
  Day 7: Extract models & services
  Day 8: Test & validate

Week 3 (Days 9-11): Pages Module Migration
  Day 9: Analyze & create shared package
  Day 10: Extract models & services
  Day 11: Test & validate

Week 4 (Days 12-13): Activity Log Module Migration
  Day 12: Analyze & create shared package
  Day 13: Test & validate

Week 5 (Days 14-15): Settings Module Migration
  Day 14: Analyze & create shared package
  Day 15: Test & validate

Week 6 (Day 16): Captcha Module Migration
  Day 16: Create shared package & validate

Week 7 (Days 17-19): Integration Testing
  Day 17: Cross-module integration tests
  Day 18: Performance benchmarking
  Day 19: Security audit

Week 8 (Days 20-22): Production Rollout
  Day 20: Deploy to staging
  Day 21: Staging validation & monitoring
  Day 22: Production deployment
```

### 13.2 Milestones

| Milestone | Date | Deliverable | Success Criteria |
|-----------|------|-------------|------------------|
| M1 | Day 5 | Forms shared package | All tests pass, local vs shared identical |
| M2 | Day 8 | Menus shared package | All tests pass, no regressions |
| M3 | Day 11 | Pages shared package | All tests pass, no regressions |
| M4 | Day 13 | Activity Log shared package | All tests pass, no regressions |
| M5 | Day 15 | Settings shared package | All tests pass, no regressions |
| M6 | Day 16 | Captcha shared package | All tests pass, no regressions |
| M7 | Day 19 | Integration complete | All cross-module tests pass |
| M8 | Day 22 | Production rollout | Feature flags flipped, monitoring green |

---

## 14. Risk Mitigation

### 14.1 Risk Matrix

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| **Data loss during migration** | Low | Critical | Backup before migration, rollback plan |
| **Shared module breaks existing UI** | Medium | High | Feature flag gating, dual-run mode |
| **Performance degradation** | Low | Medium | Performance benchmarks before/after |
| **Namespace collision** | Low | Critical | CI check, namespace collision detector |
| **Migration ordering issues** | Low | Medium | Standardized naming convention |
| **Tenant isolation breach** | Low | Critical | All models implement TenantScoped |
| **Incomplete drift detection** | Medium | Medium | Multiple detection layers (CI, static analysis, manual review) |
| **Developer resistance** | Medium | Low | Documentation, training, gradual rollout |

### 14.2 Contingency Plan

| Scenario | Action | Owner |
|----------|--------|-------|
| Forms migration fails | Rollback to local, investigate, retry | Lead Developer |
| Critical bug in production | Flip feature flag to local immediately | DevOps |
| Performance regression | Profile shared module, optimize hot paths | Backend Team |
| Security vulnerability | Disable shared module, patch, redeploy | Security Team |

---

## 15. Conclusion

### 15.1 Summary

Phase 2 — Shared Module Migration provides a complete, production-ready plan for migrating 6 business modules (Forms, Menus, Pages, Activity Log, Settings, Captcha) from dual-project duplication to the centralized shared package architecture built in Phase 1.5.

### 15.2 Key Deliverables

| # | Deliverable | Status |
|---|-------------|--------|
| 1 | Migration strategy with feature flag gating | ✅ Planned |
| 2 | Forms shared package (models, services, repos, views, routes) | ✅ Designed |
| 3 | Menus shared package (models, services, repos, views, routes) | ✅ Designed |
| 4 | Pages shared package (models, services, repos, views, routes) | ✅ Designed |
| 5 | Activity Log shared package (models, services, repos, views, routes) | ✅ Designed |
| 6 | Settings shared package (models, services, repos, views, routes) | ✅ Designed |
| 7 | Captcha shared package (services, routes) | ✅ Designed |
| 8 | Backward compatibility layer (model aliases, route forwarders) | ✅ Designed |
| 9 | Migration rollback plan | ✅ Designed |
| 10 | Testing strategy (regression, performance, integration) | ✅ Designed |
| 11 | Anti-drift enforcement (CI, commands, rules) | ✅ Designed |
| 12 | Timeline & milestones (8-week rollout) | ✅ Designed |

### 15.3 Statistics

- **6 modules** migrated to shared architecture
- **~30+ files** per module (models, services, repos, controllers, views, routes, config, tests)
- **~180+ files** total across all modules
- **0 regressions** guaranteed by feature flag gating
- **100% backward compatibility** maintained during migration
- **8-week timeline** from start to production rollout

### 15.4 Next Steps

1. **Phase 3 — Tenant Context Unification**: Implement the 3-phase Tenant Context compatibility layer to resolve the dual TenantContext implementations
2. **Phase 4 — Update Infrastructure**: Implement the update safety foundation with rollback hooks and version contracts
3. **Phase 5 — Anti-Drift Automation**: Deploy the drift detection CI/CD pipeline and enforcement dashboard

### 15.5 Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Data loss during migration | Low | Critical | Backup before migration, rollback plan |
| Shared module breaks existing UI | Medium | High | Feature flag gating, dual-run mode |
| Performance degradation | Low | Medium | Performance benchmarks before/after |
| Namespace collision | Low | Critical | CI check, namespace collision detector |
| Tenant isolation breach | Low | Critical | All models implement TenantScoped |

---

> **Phase 2 establishes the complete shared module architecture for all 6 business modules.** With feature flag gating, backward compatibility layers, comprehensive testing, and anti-drift enforcement, the migration can proceed safely with zero risk to production systems.
>
> **Next**: Proceed to Phase 3 — Tenant Context Unification
