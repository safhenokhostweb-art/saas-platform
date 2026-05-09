<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
(function() {
    var blockLabels = {
        heading: 'عنوان',
        paragraph: 'پاراگراف',
        rich_text: 'متن غنی',
        image: 'تصویر',
        gallery: 'گالری تصویر',
        media_text: 'تصویر و متن',
        hero: 'بخش قهرمان (Hero)',
        features: 'ویژگی‌ها (۳ ستون)',
        cta: 'دکمه دعوت (CTA)',
        columns: 'دو ستون',
        list: 'لیست',
        quote: 'نقل‌قول',
        code: 'کد',
        divider: 'جداکننده',
        spacer: 'فاصله',
        html: 'HTML'
    };
    var defaultBlock = {
        heading: { level: 2, text: '' },
        paragraph: { text: '' },
        rich_text: { html: '' },
        image: { url: '', alt: '', caption: '', align: 'center' },
        gallery: { title: 'گالری تصویر', items: [] },
        media_text: { url: '', alt: '', align: 'left', text: '' },
        hero: { title: '', subtitle: '', button_text: '', button_url: '', bg_color: '#4f46e5' },
        features: { title: '', f1_title: '', f1_text: '', f2_title: '', f2_text: '', f3_title: '', f3_text: '' },
        cta: { title: '', subtitle: '', button_text: '', button_url: '', bg_color: '#6366f1' },
        columns: { col1: '', col2: '' },
        list: { ordered: false, items: [''] },
        quote: { text: '', author: '' },
        code: { code: '', language: 'text' },
        divider: {},
        spacer: { size: 'medium' },
        html: { html: '' }
    };

    var container = document.getElementById('page-builder-blocks');
    var hiddenInput = document.getElementById('page-content-json');
    var pageForm = document.getElementById('page-form');
    var uploadImageUrl = pageForm ? pageForm.getAttribute('data-upload-image-url') : '';
    if (!container || !hiddenInput) return;

    var blocks = [];
    try {
        var parsed = JSON.parse(hiddenInput.value || '{}');
        blocks = Array.isArray(parsed.blocks) ? parsed.blocks : [];
    } catch (e) {
        blocks = [];
    }

    function saveToInput() {
        hiddenInput.value = JSON.stringify({ blocks: blocks });
    }

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderBlock(block, index) {
        var type = block.type || 'paragraph';
        var data = block.data || {};
        var id = 'block-' + index;
        var label = blockLabels[type] || type;

        var wrap = document.createElement('div');
        wrap.className = 'page-builder-block border border-gray-200 rounded-lg bg-white p-3 flex gap-2';
        wrap.dataset.index = index;
        wrap.id = id;

        var handle = document.createElement('span');
        handle.className = 'cursor-grab text-gray-400 select-none self-start pt-1';
        handle.innerHTML = '⋮⋮';
        handle.setAttribute('title', 'کشیدن برای جابجایی');

        var body = document.createElement('div');
        body.className = 'flex-1 min-w-0';

        var header = document.createElement('div');
        header.className = 'flex items-center justify-between mb-2';
        header.innerHTML = '<span class="text-xs font-semibold text-gray-500">' + escapeHtml(label) + '</span>';
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'text-red-600 hover:text-red-700 text-xs';
        delBtn.textContent = 'حذف';
        delBtn.onclick = function() { removeBlock(index); };
        header.appendChild(delBtn);
        body.appendChild(header);

        var content = document.createElement('div');
        content.className = 'block-editor';

        switch (type) {
            case 'heading':
                content.innerHTML = '<select class="form-select w-24 inline-block mb-1"><option value="1"' + (data.level == 1 ? ' selected' : '') + '>H1</option><option value="2"' + (data.level == 1 ? '' : (data.level == 3 ? '' : ' selected')) + '>H2</option><option value="3"' + (data.level == 3 ? ' selected' : '') + '>H3</option></select>' +
                    '<input type="text" class="form-control mt-1" placeholder="متن عنوان" value="' + escapeHtml(data.text || '') + '">';
                break;
            case 'paragraph':
                content.innerHTML = '<textarea class="form-control" rows="3" placeholder="متن پاراگراف">' + escapeHtml(data.text || '') + '</textarea>';
                break;
            case 'rich_text':
                content.innerHTML = '<textarea id="rich-editor-' + index + '" class="rich-editor-target" rows="8" style="min-height:200px;width:100%">' + escapeHtml(data.html || '') + '</textarea>';
                break;
            case 'image':
                var fileInputId = 'page-image-upload-' + index;
                var alignVal = data.align || 'center';
                content.innerHTML = '<div class="flex gap-2 mb-1 flex-wrap items-center">' +
                    '<input type="text" class="form-control flex-1 min-w-[200px] image-url-input" placeholder="آدرس تصویر یا مرور و آپلود" value="' + escapeHtml(data.url || '') + '">' +
                    '<input type="file" id="' + fileInputId + '" accept="image/*" class="page-image-upload-input absolute opacity-0 w-0 h-0 pointer-events-none">' +
                    '<label for="' + fileInputId + '" class="page-image-browse-btn px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-sm font-medium whitespace-nowrap cursor-pointer mb-0">مرور و آپلود</label>' +
                    '</div>' +
                    '<div class="flex gap-2 mb-1"><label class="text-xs text-gray-500">تراز:</label><select class="form-select w-32 image-align-select"><option value="left"' + (alignVal === 'left' ? ' selected' : '') + '>چپ</option><option value="right"' + (alignVal === 'right' ? ' selected' : '') + '>راست</option><option value="center"' + (alignVal === 'center' ? ' selected' : '') + '>وسط</option><option value="full"' + (alignVal === 'full' ? ' selected' : '') + '>تمام عرض</option></select></div>' +
                    '<input type="text" class="form-control mb-1" placeholder="متن جایگزین (alt)" value="' + escapeHtml(data.alt || '') + '">' +
                    '<input type="text" class="form-control" placeholder="زیرنویس (اختیاری)" value="' + escapeHtml(data.caption || '') + '">';
                break;
            case 'gallery':
                var items = Array.isArray(data.items) ? data.items : [];
                var galHtml = '<input type="text" class="form-control mb-2" placeholder="عنوان گالری (اختیاری)" value="' + escapeHtml(data.title || '') + '">' +
                    '<div class="gallery-items space-y-2">';
                items.forEach(function(it, i) {
                    var typ = (it && it.type) || 'image';
                    galHtml += '<div class="gallery-item flex flex-wrap gap-2 items-center border p-2 rounded bg-gray-50">' +
                        '<select class="gallery-item-type form-select w-24"><option value="image"' + (typ === 'image' ? ' selected' : '') + '>تصویر</option><option value="video"' + (typ === 'video' ? ' selected' : '') + '>فیلم</option></select>' +
                        '<input type="text" class="gallery-item-url form-control flex-1 min-w-[120px]" placeholder="آدرس" value="' + escapeHtml(it && it.url ? it.url : '') + '">' +
                        '<input type="file" class="gallery-item-file hidden" accept="image/*,video/*">' +
                        '<button type="button" class="gallery-browse-btn px-2 py-1 text-xs border rounded bg-white">مرور</button>' +
                        '<input type="text" class="gallery-item-caption form-control w-32" placeholder="زیرنویس" value="' + escapeHtml(it && it.caption ? it.caption : '') + '">' +
                        '<button type="button" class="gallery-remove-item text-red-600 text-xs">حذف</button></div>';
                });
                galHtml += '</div><button type="button" class="gallery-add-item mt-1 text-sm text-blue-600">+ افزودن تصویر/فیلم</button>';
                content.innerHTML = galHtml;
                break;
            case 'media_text':
                var mtFileId = 'page-media-text-upload-' + index;
                var mtAlign = data.align || 'left';
                content.innerHTML = '<div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">' +
                    '<div><label class="text-xs text-gray-500">تصویر</label><input type="text" class="form-control media-text-url" placeholder="آدرس تصویر" value="' + escapeHtml(data.url || '') + '">' +
                    '<input type="file" id="' + mtFileId + '" accept="image/*" class="page-image-upload-input absolute opacity-0 w-0 h-0">' +
                    '<label for="' + mtFileId + '" class="inline-block mt-1 px-2 py-1 text-xs border rounded cursor-pointer">مرور و آپلود</label></div>' +
                    '<div><label class="text-xs text-gray-500">تراز تصویر</label><select class="form-select media-text-align"><option value="left"' + (mtAlign === 'left' ? ' selected' : '') + '>چپ</option><option value="right"' + (mtAlign === 'right' ? ' selected' : '') + '>راست</option></select></div></div>' +
                    '<textarea class="form-control" rows="4" placeholder="متن کنار تصویر">' + escapeHtml(data.text || '') + '</textarea>';
                break;
            case 'hero':
                content.innerHTML = '<input type="text" class="form-control mb-1" placeholder="عنوان اصلی" value="' + escapeHtml(data.title || '') + '">' +
                    '<textarea class="form-control mb-1" rows="2" placeholder="زیرعنوان">' + escapeHtml(data.subtitle || '') + '</textarea>' +
                    '<input type="text" class="form-control mb-1" placeholder="متن دکمه" value="' + escapeHtml(data.button_text || '') + '">' +
                    '<input type="text" class="form-control mb-1" placeholder="لینک دکمه" value="' + escapeHtml(data.button_url || '') + '">' +
                    '<label class="text-xs text-gray-500 mt-1">رنگ پس‌زمینه</label><input type="text" class="form-control" placeholder="#4f46e5" value="' + escapeHtml(data.bg_color || '#4f46e5') + '">';
                break;
            case 'features':
                content.innerHTML = '<input type="text" class="form-control mb-2" placeholder="عنوان بخش" value="' + escapeHtml(data.title || '') + '">' +
                    '<div class="grid grid-cols-1 md:grid-cols-3 gap-2">' +
                    '<div class="border rounded p-2"><input type="text" class="form-control mb-1 text-sm" placeholder="ویژگی ۱ عنوان" value="' + escapeHtml(data.f1_title || '') + '"><textarea class="form-control text-sm" rows="2" placeholder="متن">' + escapeHtml(data.f1_text || '') + '</textarea></div>' +
                    '<div class="border rounded p-2"><input type="text" class="form-control mb-1 text-sm" placeholder="ویژگی ۲ عنوان" value="' + escapeHtml(data.f2_title || '') + '"><textarea class="form-control text-sm" rows="2" placeholder="متن">' + escapeHtml(data.f2_text || '') + '</textarea></div>' +
                    '<div class="border rounded p-2"><input type="text" class="form-control mb-1 text-sm" placeholder="ویژگی ۳ عنوان" value="' + escapeHtml(data.f3_title || '') + '"><textarea class="form-control text-sm" rows="2" placeholder="متن">' + escapeHtml(data.f3_text || '') + '</textarea></div>' +
                    '</div>';
                break;
            case 'cta':
                content.innerHTML = '<input type="text" class="form-control mb-1" placeholder="عنوان" value="' + escapeHtml(data.title || '') + '">' +
                    '<textarea class="form-control mb-1" rows="2" placeholder="زیرعنوان">' + escapeHtml(data.subtitle || '') + '</textarea>' +
                    '<input type="text" class="form-control mb-1" placeholder="متن دکمه" value="' + escapeHtml(data.button_text || '') + '">' +
                    '<input type="text" class="form-control mb-1" placeholder="لینک دکمه" value="' + escapeHtml(data.button_url || '') + '">' +
                    '<label class="text-xs text-gray-500">رنگ پس‌زمینه</label><input type="text" class="form-control" placeholder="#6366f1" value="' + escapeHtml(data.bg_color || '#6366f1') + '">';
                break;
            case 'columns':
                content.innerHTML = '<textarea class="form-control mb-1" rows="3" placeholder="ستون چپ">' + escapeHtml(data.col1 || '') + '</textarea>' +
                    '<textarea class="form-control" rows="3" placeholder="ستون راست">' + escapeHtml(data.col2 || '') + '</textarea>';
                break;
            case 'list':
                var items = Array.isArray(data.items) ? data.items : [''];
                var listHtml = '<label class="flex items-center gap-2 mb-2"><input type="checkbox" class="form-check-input" ' + (data.ordered ? 'checked' : '') + '> لیست شماره‌دار</label>';
                listHtml += '<div class="list-items space-y-1">';
                for (var i = 0; i < items.length; i++) {
                    listHtml += '<div class="flex gap-1"><input type="text" class="form-control flex-1 list-item" placeholder="آیتم ' + (i+1) + '" value="' + escapeHtml(items[i] || '') + '"><button type="button" class="list-remove-item text-red-600 text-sm">−</button></div>';
                }
                listHtml += '</div><button type="button" class="list-add-item mt-1 text-sm text-blue-600">+ افزودن آیتم</button>';
                content.innerHTML = listHtml;
                break;
            case 'quote':
                content.innerHTML = '<textarea class="form-control mb-1" rows="2" placeholder="متن نقل‌قول">' + escapeHtml(data.text || '') + '</textarea>' +
                    '<input type="text" class="form-control" placeholder="نویسنده (اختیاری)" value="' + escapeHtml(data.author || '') + '">';
                break;
            case 'code':
                content.innerHTML = '<input type="text" class="form-control mb-1" placeholder="زبان (مثلاً php, js)" value="' + escapeHtml(data.language || '') + '">' +
                    '<textarea class="form-control font-mono text-sm" rows="4" placeholder="کد">' + escapeHtml(data.code || '') + '</textarea>';
                break;
            case 'divider':
                content.innerHTML = '<p class="text-gray-400 text-sm">خط افقی جداکننده</p>';
                break;
            case 'spacer':
                content.innerHTML = '<select class="form-select w-40"><option value="small"' + (data.size === 'small' ? ' selected' : '') + '>کوچک</option><option value="medium"' + (data.size !== 'small' && data.size !== 'large' ? ' selected' : '') + '>متوسط</option><option value="large"' + (data.size === 'large' ? ' selected' : '') + '>بزرگ</option></select>';
                break;
            case 'html':
                content.innerHTML = '<textarea class="form-control font-mono text-sm" rows="4" placeholder="HTML خام">' + escapeHtml(data.html || '') + '</textarea>';
                break;
            default:
                content.textContent = type;
        }
        body.appendChild(content);
        wrap.appendChild(handle);
        wrap.appendChild(body);
        return wrap;
    }

    function collectBlockData(blockEl) {
        var index = parseInt(blockEl.dataset.index, 10);
        var block = blocks[index];
        if (!block) return;
        var type = block.type || 'paragraph';
        var data = block.data || {};
        var editor = blockEl.querySelector('.block-editor');
        if (!editor) return;

        switch (type) {
            case 'heading':
                var sel = editor.querySelector('select');
                var inp = editor.querySelector('input[type="text"]');
                blocks[index].data = { level: parseInt(sel && sel.value ? sel.value : 2, 10), text: inp ? inp.value : '' };
                break;
            case 'paragraph':
                var ta = editor.querySelector('textarea');
                blocks[index].data = { text: ta ? ta.value : '' };
                break;
            case 'rich_text':
                var richId = 'rich-editor-' + index;
                var ed = typeof tinymce !== 'undefined' ? tinymce.get(richId) : null;
                blocks[index].data = { html: ed ? ed.getContent() : (blockEl.querySelector('#rich-editor-' + index) && blockEl.querySelector('#rich-editor-' + index).value) || '' };
                break;
            case 'image':
                var urlInp = editor.querySelector('.image-url-input');
                var alignSel = editor.querySelector('.image-align-select');
                var textInps = editor.querySelectorAll('.block-editor input[type="text"]');
                blocks[index].data = {
                    url: urlInp ? urlInp.value : '',
                    alt: textInps[1] ? textInps[1].value : '',
                    caption: textInps[2] ? textInps[2].value : '',
                    align: alignSel ? alignSel.value : 'center'
                };
                break;
            case 'gallery':
                var galTitle = editor.querySelector('.form-control');
                var items = [];
                editor.querySelectorAll('.gallery-item').forEach(function(row) {
                    var typ = row.querySelector('.gallery-item-type');
                    var urlInp = row.querySelector('.gallery-item-url');
                    var cap = row.querySelector('.gallery-item-caption');
                    items.push({ type: typ ? typ.value : 'image', url: urlInp ? urlInp.value : '', caption: cap ? cap.value : '' });
                });
                blocks[index].data = { title: galTitle ? galTitle.value : '', items: items };
                break;
            case 'media_text':
                var mtUrl = editor.querySelector('.media-text-url');
                var mtAlign = editor.querySelector('.media-text-align');
                var mtText = editor.querySelector('textarea');
                blocks[index].data = {
                    url: mtUrl ? mtUrl.value : '',
                    align: mtAlign ? mtAlign.value : 'left',
                    text: mtText ? mtText.value : ''
                };
                break;
            case 'hero':
                var hi = blockEl.querySelectorAll('.block-editor input, .block-editor textarea');
                blocks[index].data = {
                    title: hi[0] ? hi[0].value : '', subtitle: hi[1] ? hi[1].value : '',
                    button_text: hi[2] ? hi[2].value : '', button_url: hi[3] ? hi[3].value : '',
                    bg_color: hi[4] ? hi[4].value : '#4f46e5'
                };
                break;
            case 'features':
                var fi = blockEl.querySelectorAll('.block-editor input, .block-editor textarea');
                blocks[index].data = {
                    title: fi[0] ? fi[0].value : '',
                    f1_title: fi[1] ? fi[1].value : '', f1_text: fi[2] ? fi[2].value : '',
                    f2_title: fi[3] ? fi[3].value : '', f2_text: fi[4] ? fi[4].value : '',
                    f3_title: fi[5] ? fi[5].value : '', f3_text: fi[6] ? fi[6].value : ''
                };
                break;
            case 'cta':
                var ci = blockEl.querySelectorAll('.block-editor input, .block-editor textarea');
                blocks[index].data = {
                    title: ci[0] ? ci[0].value : '', subtitle: ci[1] ? ci[1].value : '',
                    button_text: ci[2] ? ci[2].value : '', button_url: ci[3] ? ci[3].value : '',
                    bg_color: ci[4] ? ci[4].value : '#6366f1'
                };
                break;
            case 'columns':
                var cols = blockEl.querySelectorAll('.block-editor textarea');
                blocks[index].data = { col1: cols[0] ? cols[0].value : '', col2: cols[1] ? cols[1].value : '' };
                break;
            case 'list':
                var items = [];
                editor.querySelectorAll('.list-item').forEach(function(inp) { items.push(inp.value); });
                var ord = editor.querySelector('input[type="checkbox"]');
                blocks[index].data = { ordered: ord ? ord.checked : false, items: items };
                break;
            case 'quote':
                var qta = editor.querySelector('textarea');
                var qinp = editor.querySelector('input');
                blocks[index].data = { text: qta ? qta.value : '', author: qinp ? qinp.value : '' };
                break;
            case 'code':
                var lang = editor.querySelector('input');
                var codeTa = editor.querySelector('textarea');
                blocks[index].data = { code: codeTa ? codeTa.value : '', language: lang ? lang.value : 'text' };
                break;
            case 'spacer':
                var sizeSel = editor.querySelector('select');
                blocks[index].data = { size: sizeSel ? sizeSel.value : 'medium' };
                break;
            case 'html':
                var htmlTa = editor.querySelector('textarea');
                blocks[index].data = { html: htmlTa ? htmlTa.value : '' };
                break;
        }
    }

    var uploadImageUrlForTiny = uploadImageUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';

    function saveRichContentBeforeRender() {
        container.querySelectorAll('.page-builder-block').forEach(function(el) {
            var idx = parseInt(el.dataset.index, 10);
            if (isNaN(idx) || !blocks[idx] || blocks[idx].type !== 'rich_text') return;
            var id = 'rich-editor-' + idx;
            var ed = typeof tinymce !== 'undefined' ? tinymce.get(id) : null;
            if (ed) {
                blocks[idx].data.html = ed.getContent();
                try { ed.remove(); } catch (e) {}
            }
        });
    }

    function getTinyMCEConfig(editorId) {
        return {
            selector: '#' + editorId,
            base_url: '{{ asset('vendor/tinymce') }}',
            suffix: '.min',
            height: 280,
            directionality: 'rtl',
            language: 'fa',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount blockquote',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent blockquote | link image media table | charmap | removeformat | code fullscreen',
            block_formats: 'پاراگراف=p; عنوان 1=h1; عنوان 2=h2; عنوان 3=h3; عنوان 4=h4; پیش‌فرض=pre',
            content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 14px; }',
            promotion: false,
            branding: false,
            images_upload_url: uploadImageUrlForTiny || '',
            images_upload_credentials: true,
            automatic_uploads: true,
            file_picker_types: 'image',
            images_upload_handler: function(blobInfo, progress) {
                return new Promise(function(resolve, reject) {
                    if (!uploadImageUrlForTiny) {
                        var url = blobInfo.blobUri();
                        resolve(url);
                        return;
                    }
                    var fd = new FormData();
                    fd.append('image', blobInfo.blob(), blobInfo.filename());
                    fd.append('_token', csrfToken);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', uploadImageUrlForTiny);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    xhr.onload = function() {
                        if (xhr.status < 200 || xhr.status >= 300) { reject('خطا در آپلود'); return; }
                        try {
                            var data = JSON.parse(xhr.responseText);
                            resolve(data && data.url ? data.url : '');
                        } catch (e) { reject('پاسخ نامعتبر'); }
                    };
                    xhr.onerror = function() { reject('خطای شبکه'); };
                    xhr.send(fd);
                });
            }
        };
    }

    function initTinyMCEForRichTextBlocks() {
        if (typeof tinymce === 'undefined') return;
        blocks.forEach(function(block, i) {
            if (block.type !== 'rich_text') return;
            var id = 'rich-editor-' + i;
            var el = document.getElementById(id);
            if (!el || el.getAttribute('data-tinymce-inited')) return;
            el.setAttribute('data-tinymce-inited', '1');
            var config = getTinyMCEConfig(id);
            config.selector = '#' + id;
            tinymce.init(config);
        });
    }

    function renderAll() {
        saveRichContentBeforeRender();
        container.innerHTML = '';
        blocks.forEach(function(block, i) {
            container.appendChild(renderBlock(block, i));
        });
        saveToInput();
        setTimeout(initTinyMCEForRichTextBlocks, 100);
    }

    function addBlock(type) {
        syncAllFromDom();
        var data = defaultBlock[type] !== undefined ? JSON.parse(JSON.stringify(defaultBlock[type])) : {};
        blocks.push({ type: type, data: data });
        renderAll();
    }

    function removeBlock(index) {
        syncAllFromDom();
        blocks.splice(index, 1);
        renderAll();
    }

    function syncAllFromDom() {
        container.querySelectorAll('.page-builder-block').forEach(function(el) {
            collectBlockData(el);
        });
        saveToInput();
    }

    document.getElementById('page-form').addEventListener('submit', function() {
        container.querySelectorAll('.page-builder-block').forEach(function(el) {
            collectBlockData(el);
        });
        saveToInput();
    });

    document.querySelectorAll('.page-builder-add').forEach(function(btn) {
        btn.addEventListener('click', function() {
            addBlock(this.getAttribute('data-type'));
        });
    });

    if (typeof Sortable !== 'undefined') {
        new Sortable(container, {
            animation: 150,
            handle: '.cursor-grab',
            onEnd: function() {
                syncAllFromDom();
                var newOrder = [];
                container.querySelectorAll('.page-builder-block').forEach(function(el) {
                    var idx = parseInt(el.dataset.index, 10);
                    if (!isNaN(idx) && blocks[idx]) newOrder.push(blocks[idx]);
                });
                blocks = newOrder;
                renderAll();
            }
        });
    }
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('page-image-browse-btn') || e.target.closest('.page-image-browse-btn')) {
            return;
        }
        if (e.target.classList.contains('gallery-add-item')) {
            e.preventDefault();
            var blockEl = e.target.closest('.page-builder-block');
            if (!blockEl) return;
            collectBlockData(blockEl);
            var idx = parseInt(blockEl.dataset.index, 10);
            if (!blocks[idx].data.items) blocks[idx].data.items = [];
            blocks[idx].data.items.push({ type: 'image', url: '', caption: '' });
            renderAll();
        } else if (e.target.classList.contains('gallery-remove-item')) {
            e.preventDefault();
            var blockEl = e.target.closest('.page-builder-block');
            if (!blockEl) return;
            collectBlockData(blockEl);
            var idx = parseInt(blockEl.dataset.index, 10);
            var row = e.target.closest('.gallery-item');
            if (!row || !row.parentElement) return;
            var itemIdx = Array.from(row.parentElement.querySelectorAll('.gallery-item')).indexOf(row);
            if (blocks[idx].data.items && blocks[idx].data.items.length > 0) {
                blocks[idx].data.items.splice(itemIdx, 1);
                renderAll();
            }
        } else if (e.target.classList.contains('gallery-browse-btn')) {
            e.preventDefault();
            var row = e.target.closest('.gallery-item');
            var fileInput = row ? row.querySelector('.gallery-item-file') : null;
            if (fileInput) fileInput.click();
        } else if (e.target.classList.contains('list-add-item')) {
            e.preventDefault();
            var blockEl = e.target.closest('.page-builder-block');
            if (!blockEl) return;
            collectBlockData(blockEl);
            var idx = parseInt(blockEl.dataset.index, 10);
            if (!blocks[idx].data.items) blocks[idx].data.items = [''];
            blocks[idx].data.items.push('');
            renderAll();
        } else if (e.target.classList.contains('list-remove-item')) {
            e.preventDefault();
            var blockEl = e.target.closest('.page-builder-block');
            if (!blockEl) return;
            collectBlockData(blockEl);
            var idx = parseInt(blockEl.dataset.index, 10);
            var row = e.target.closest('.flex.gap-1');
            if (!row || !row.parentElement) return;
            var itemIdx = Array.from(row.parentElement.children).indexOf(row);
            if (blocks[idx].data.items && blocks[idx].data.items.length > 1) {
                blocks[idx].data.items.splice(itemIdx, 1);
                renderAll();
            }
        }
    });

    container.addEventListener('change', function(e) {
        var isGalleryFile = e.target.classList.contains('gallery-item-file');
        var isPageImage = e.target.classList.contains('page-image-upload-input');
        if (!isPageImage && !isGalleryFile) return;
        var file = e.target.files && e.target.files[0];
        if (!file || !uploadImageUrl) return;
        var urlInput = null;
        var btn = null;
        if (isGalleryFile) {
            var row = e.target.closest('.gallery-item');
            urlInput = row ? row.querySelector('.gallery-item-url') : null;
            btn = row ? row.querySelector('.gallery-browse-btn') : null;
        } else {
            var block = e.target.closest('.page-builder-block');
            urlInput = block ? block.querySelector('.image-url-input') : null;
            btn = block ? block.querySelector('.page-image-browse-btn') : null;
            if (!urlInput) urlInput = block ? block.querySelector('.media-text-url') : null;
        }
        if (!urlInput) return;
        if (btn) { btn.disabled = true; btn.textContent = '…'; }
        var fd = new FormData();
        fd.append('image', file);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '');
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
        if (csrf && csrf.content) headers['X-CSRF-TOKEN'] = csrf.content;
        fetch(uploadImageUrl, { method: 'POST', body: fd, headers: headers })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.url) urlInput.value = data.url;
                e.target.value = '';
            })
            .catch(function() {})
            .finally(function() {
                if (btn) { btn.disabled = false; btn.textContent = isGalleryFile ? 'مرور' : 'مرور و آپلود'; }
            });
    });

    renderAll();
})();
</script>
