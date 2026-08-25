<div>
    <label class="form-label flex items-center justify-between">
        <span>دارایی‌های موشن الزامی</span>
        <button type="button" onclick="openMotionBrowser(this)" class="text-xs px-2.5 py-1 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition font-medium">🎬 انتخاب بصری موشن</button>
    </label>
    <input type="text" name="required_asset_ids" class="form-control text-xs" placeholder="با «انتخاب بصری موشن» پر می‌شود — یا شناسه را دستی وارد کنید" required
           oninput="motionPickerPreview(this)">
    <div class="motion-picker-preview mt-2 flex flex-wrap gap-2"></div>
</div>
<div>
    <label class="form-label flex items-center justify-between">
        <span>دارایی‌های موشن اختیاری</span>
        <button type="button" onclick="openMotionBrowser(this)" class="text-xs px-2.5 py-1 rounded-lg border border-indigo-300 text-indigo-600 hover:bg-indigo-50 transition font-medium">🎬 انتخاب بصری موشن</button>
    </label>
    <input type="text" name="optional_asset_ids" class="form-control text-xs" placeholder="با «انتخاب بصری موشن» پر می‌شود — یا شناسه را دستی وارد کنید"
           oninput="motionPickerPreview(this)">
    <div class="motion-picker-preview mt-2 flex flex-wrap gap-2"></div>
</div>

@once
{{-- Theme Builder Motion Picker — real visual browse modal, shared across
     every assignment-fields instance on the page (there can be many, one
     per template/theme card). Rendered once; openMotionBrowser() points it
     at whichever required/optional input the admin clicked "select" next
     to. Lists ONLY the published, global library via
     admin.motion-graphics.assets.browse (MotionAssetLibraryBrowseContract)
     — a customer-specific/bespoke asset can never appear here. --}}
<div id="motionBrowseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:1rem;width:100%;max-width:42rem;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-weight:700;font-size:.875rem;color:#1e293b;margin:0;">انتخاب موشن گرافیک — کتابخانه عمومی و منتشرشده</h3>
            <button type="button" onclick="closeMotionBrowser()" style="color:#94a3b8;font-size:1.25rem;line-height:1;background:none;border:none;cursor:pointer;">×</button>
        </div>
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;gap:.5rem;">
            <input id="motionBrowseSearch" type="text" placeholder="جستجو بر اساس نام..." class="form-control" style="flex:1;font-size:.8rem;" oninput="motionBrowseDebouncedSearch()">
            <input id="motionBrowseCategory" type="text" placeholder="دسته‌بندی (اختیاری)" class="form-control" style="max-width:9rem;font-size:.8rem;" oninput="motionBrowseDebouncedSearch()">
        </div>
        <div id="motionBrowseGrid" style="flex:1;overflow-y:auto;padding:1rem 1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">
            <p style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:.8rem;padding:2rem 0;">در حال بارگذاری...</p>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-top:1px solid #f1f5f9;">
            <span id="motionBrowsePageInfo" style="font-size:.75rem;color:#94a3b8;"></span>
            <div style="display:flex;gap:.4rem;">
                <button type="button" onclick="motionBrowseGoto(motionBrowseState.page - 1)" class="btn btn-sm" style="font-size:.75rem;">قبلی</button>
                <button type="button" onclick="motionBrowseGoto(motionBrowseState.page + 1)" class="btn btn-sm" style="font-size:.75rem;">بعدی</button>
                <button type="button" onclick="closeMotionBrowser()" class="btn btn-sm btn-primary" style="font-size:.75rem;">پایان انتخاب</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Theme Builder Motion Picker — real visual browse (Select -> modal ->
//    search/filter -> thumbnail/name/category -> click to add). Reuses the
//    exact same admin.motion-graphics.assets.browse endpoint / safe
//    MotionAssetPreview payload as the per-id live-preview cards below;
//    this only adds a way to discover ids without typing them. ──────────
const motionBrowseState = { targetInput: null, page: 1, siteId: {{ (int) $site->id }} };
let __motionBrowseDebounce = null;

function openMotionBrowser(buttonEl) {
    motionBrowseState.targetInput = buttonEl.closest('div').querySelector('input[type="text"]');
    motionBrowseState.page = 1;
    document.getElementById('motionBrowseSearch').value = '';
    document.getElementById('motionBrowseCategory').value = '';
    document.getElementById('motionBrowseModal').style.display = 'flex';
    motionBrowseFetch();
}

function closeMotionBrowser() {
    document.getElementById('motionBrowseModal').style.display = 'none';
}

function motionBrowseDebouncedSearch() {
    clearTimeout(__motionBrowseDebounce);
    __motionBrowseDebounce = setTimeout(() => { motionBrowseState.page = 1; motionBrowseFetch(); }, 350);
}

function motionBrowseGoto(page) {
    if (page < 1) return;
    motionBrowseState.page = page;
    motionBrowseFetch();
}

async function motionBrowseFetch() {
    const grid = document.getElementById('motionBrowseGrid');
    grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:.8rem;padding:2rem 0;">در حال بارگذاری...</p>';

    const search = document.getElementById('motionBrowseSearch').value.trim();
    const category = document.getElementById('motionBrowseCategory').value.trim();
    const params = new URLSearchParams({ page: motionBrowseState.page });
    if (search) params.set('search', search);
    if (category) params.set('category', category);

    try {
        const res = await fetch(`{{ url('admin/motion-graphics') }}/${motionBrowseState.siteId}/assets/browse?${params}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        motionBrowseRender(data);
    } catch (e) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#ef4444;font-size:.8rem;padding:2rem 0;">خطا در بارگذاری کتابخانه موشن.</p>';
    }
}

function motionBrowseRender(data) {
    const grid = document.getElementById('motionBrowseGrid');
    const info = document.getElementById('motionBrowsePageInfo');
    const items = data.items || [];

    info.textContent = data.total > 0
        ? `${data.total} دارایی — صفحه ${data.page}`
        : '';

    if (items.length === 0) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#94a3b8;font-size:.8rem;padding:2rem 0;">هیچ دارایی منتشرشده‌ای یافت نشد.</p>';
        return;
    }

    grid.innerHTML = items.map(a => {
        const thumb = a.thumbnail_reference
            ? `<img src="${a.thumbnail_reference}" alt="" style="width:2.75rem;height:2.75rem;border-radius:.5rem;object-fit:cover;background:#f1f5f9;" onerror="this.outerHTML='<span style=&quot;width:2.75rem;height:2.75rem;border-radius:.5rem;background:#eef2ff;color:#818cf8;display:flex;align-items:center;justify-content:center;font-size:1.1rem;&quot;>🎬</span>'">`
            : `<span style="width:2.75rem;height:2.75rem;border-radius:.5rem;background:#eef2ff;color:#818cf8;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">🎬</span>`;
        return `
            <button type="button" onclick="motionBrowseSelect('${a.asset_id}')"
                style="display:flex;align-items:center;gap:.6rem;text-align:right;padding:.6rem;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;cursor:pointer;transition:.15s;"
                onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='#e2e8f0'">
                ${thumb}
                <span style="min-width:0;">
                    <span style="display:block;font-size:.8rem;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${a.name || a.asset_id}</span>
                    <span style="display:block;font-size:.7rem;color:#94a3b8;">${a.category ? a.category + ' · ' : ''}${a.type || ''}</span>
                </span>
            </button>`;
    }).join('');
}

function motionBrowseSelect(assetId) {
    const input = motionBrowseState.targetInput;
    if (!input) return;
    const current = input.value.split(',').map(s => s.trim()).filter(Boolean);
    if (!current.includes(assetId)) {
        current.push(assetId);
        input.value = current.join(', ');
        motionPickerPreview(input);
    }
}

// Theme Builder Motion Picker (authoring): as the admin types/pastes asset
// ids, show a live visual card (thumbnail/name/category) resolved from the
// existing admin.motion-graphics.assets.preview endpoint — reuses
// MotionAssetReadContract + MotionRuntimeProjectionReadContract via
// MotionGraphicsAdminService::previewAsset(), no new read path.
let __motionPickerDebounce = null;
function motionPickerPreview(input) {
    clearTimeout(__motionPickerDebounce);
    __motionPickerDebounce = setTimeout(() => __motionPickerRender(input), 350);
}
async function __motionPickerRender(input) {
    const container = input.parentElement.querySelector('.motion-picker-preview');
    const ids = input.value.split(',').map(s => s.trim()).filter(Boolean);
    if (ids.length === 0) { container.innerHTML = ''; return; }

    container.innerHTML = ids.map(id => `<span class="text-xs text-gray-400" data-motion-card="${id}">در حال بارگذاری «${id}»...</span>`).join('');

    for (const id of ids) {
        try {
            const res = await fetch(`{{ url('admin/motion-graphics/'.$site->id.'/assets/preview') }}?asset_id=${encodeURIComponent(id)}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            const el = container.querySelector(`[data-motion-card="${CSS.escape(id)}"]`);
            if (!el) continue;

            if (!data.found) {
                el.outerHTML = `<span class="inline-flex items-center gap-1 text-xs bg-red-50 text-red-600 border border-red-200 rounded-lg px-2 py-1" data-motion-card="${id}">✕ «${id}» یافت نشد / منتشر نشده</span>`;
                continue;
            }

            const a = data.asset;
            const thumb = a.thumbnail_reference
                ? `<img src="${a.thumbnail_reference}" alt="" class="w-8 h-8 rounded object-cover bg-gray-100" onerror="this.style.display='none'">`
                : `<span class="w-8 h-8 rounded bg-indigo-50 flex items-center justify-center text-indigo-400 text-xs">؟</span>`;
            el.outerHTML = `
                <span class="inline-flex items-center gap-2 text-xs bg-white border border-gray-200 rounded-lg px-2 py-1.5" data-motion-card="${id}">
                    ${thumb}
                    <span class="flex flex-col leading-tight">
                        <span class="font-semibold text-gray-800">${a.name ? a.name : a.asset_id}</span>
                        <span class="text-gray-400">${a.category ? a.category + ' · ' : ''}${a.type}</span>
                    </span>
                </span>`;
        } catch (e) {
            const el = container.querySelector(`[data-motion-card="${CSS.escape(id)}"]`);
            if (el) el.outerHTML = `<span class="text-xs text-gray-400" data-motion-card="${id}">«${id}» — خطا در بارگذاری پیش‌نمایش</span>`;
        }
    }
}
</script>
@endonce
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="form-label">نوع دارایی</label>
        <input type="text" name="compatibility_asset_type" class="form-control" placeholder="lottie">
    </div>
    <div>
        <label class="form-label">بخش</label>
        <input type="text" name="compatibility_section" class="form-control" placeholder="hero">
    </div>
</div>
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="form-label">صنعت</label>
        <input type="text" name="compatibility_industry" class="form-control">
    </div>
    <label class="flex items-center gap-2 mt-6 text-sm text-gray-600">
        <input type="checkbox" name="compatibility_theme_tokens_required" value="1" class="form-check-input">
        نیاز به theme tokens
    </label>
</div>
