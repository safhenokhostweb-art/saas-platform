{{-- Phase 3A — Persian-First Section Library foundation.
    Canonical composition packages (sections + header/footer compositions)
    built ONLY from registered canonical widget types, inserted through the
    Phase 2C canonical tree operations (ONE undoable history operation per
    insert, full structural validation at save). Every node stays
    independently editable after insertion — packages are documents, never
    monolithic HTML. RTL-native by design: logical start/end alignment and
    block/inline flow — no physical left/right anywhere. --}}
<script>
(function (window) {
    'use strict';
    if (window.CanvasSectionLibrary) return;

    // Canonical widget node shortcut: props always cloned at insert time so
    // package defaults can never alias live editor state.
    const W = (type, props, children) => ({
        type,
        props: props || {},
        ...(children && children.length ? { children } : {}),
    });

    // ── Persian/RTL-native representative sections (Step 12) ────────────
    // Quality over quantity: 4 sections that prove decomposition, logical
    // direction, and the new 3A basic widgets working together.
    const SECTIONS = [
        {
            key: 'hero_persian',
            name: 'قهرمان — فروشگاه خود را بسازید',
            description: 'عنوان بزرگ، توضیح، نشان تأکید و دکمه اقدام — چیدمان ستونی با فاصله‌گذاری منطقی.',
            categoryLabel: 'قهرمان',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('flex_container', { label: 'قهرمان', direction: 'column', gap: 'lg', alignItems: 'stretch', justifyContent: 'flex-start' }, [
                        W('badge', { text: 'جدید', style: 'primary' }),
                        W('heading', { level: 1, text: 'فروشگاه خود را حرفه‌ای بسازید' }),
                        W('text', { content: 'با سازندهٔ سایت کانواس، صفحهٔ اصلی خود را در چند دقیقه طراحی کنید؛ کاملاً راست‌چین و فارسی.' }),
                        W('button', { text: 'شروع کنید', url: '#', style: 'primary' }),
                        W('spacer', { height: 24 }),
                    ]),
                ]),
            ],
        },
        {
            // Phase 3B — professional Persian hero family instance. Composed
            // ONLY from registered canonical types (4-level nesting:
            // hero_section → hero_container → hero_content_wrapper → leaves
            // + hero_actions); every text string is a replaceable prop —
            // nothing hardcoded non-editable. Insertion goes through the
            // canonical tree operations (one undoable op, id regen).
            key: 'hero_pro_persian',
            name: 'قهرمان حرفه‌ای — فارسی',
            description: 'سکشن قهرمان تجاری: پس‌زمینه گرادیان، عنوان بزرگ، توضیح و دو دکمه اقدام — تماماً قابل ویرایش و RTL بومی.',
            categoryLabel: 'قهرمان',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('hero_section', { text_align: 'start', v_align: 'center' }, [
                    W('hero_container', {}, [
                        W('hero_content_wrapper', {}, [
                            W('badge', { text: 'فروشگاه شما، هوشمندتر از همیشه', style: 'accent' }),
                            W('heading', { level: 1, text: 'فروشگاه خود را حرفه‌ای بسازید' }),
                            W('text', { content: 'با سازندهٔ سایت کانواس، صفحهٔ اصلی خود را در چند دقیقه طراحی کنید؛ کاملاً راست‌چین و فارسی.' }),
                            W('hero_actions', {}, [
                                W('button', { text: 'شروع کنید', url: '#', style: 'primary' }),
                                W('button', { text: 'مشاهده دمو', url: '#', style: 'secondary' }),
                            ]),
                        ]),
                    ]),
                ]),
            ],
        },
        {
            key: 'features_persian',
            name: 'ویژگی‌ها — سه مزیت کلیدی',
            description: 'سه جعبهٔ آیکون در چیدمان ردیفی با شکست خودکار — تراز منطقی ابتدا.',
            categoryLabel: 'ویژگی‌ها',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('heading', { level: 2, text: 'چرا ما؟' }),
                    W('spacer', { height: 16 }),
                    W('flex_container', { label: 'ویژگی‌ها', direction: 'row', gap: 'md', alignItems: 'stretch', justifyContent: 'flex-start', wrap: true }, [
                        W('icon_box', { icon: '★', title: 'کیفیت برتر', text: 'محصولات منتخب با ضمانت اصالت کالا.', align: 'start', style: 'card' }),
                        W('icon_box', { icon: '⚡', title: 'ارسال سریع', text: 'ارسال به سراسر کشور در کمترین زمان.', align: 'start', style: 'card' }),
                        W('icon_box', { icon: '☎', title: 'پشتیبانی واقعی', text: 'پاسخ‌گویی توسط تیم پشتیبانی، هر روز هفته.', align: 'start', style: 'card' }),
                    ]),
                ]),
            ],
        },
        {
            key: 'about_cta_persian',
            name: 'درباره ما + اقدام',
            description: 'تصویر و متن در دو ستون منطقی با دکمهٔ اقدام در پایان.',
            categoryLabel: 'درباره ما',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('flex_container', { label: 'درباره ما', direction: 'row', gap: 'lg', alignItems: 'center', justifyContent: 'flex-start', wrap: true }, [
                        W('image_box', { url: '', title: 'داستان ما', text: 'از یک فروشگاه کوچک تا یک برند قابل اعتماد.', ratio: '4/3', align: 'start', style: 'card' }),
                        W('flex_container', { label: 'متن', direction: 'column', gap: 'md', alignItems: 'stretch', justifyContent: 'flex-start' }, [
                            W('heading', { level: 2, text: 'ما همین‌جا شروع کردیم' }),
                            W('text', { content: 'باور داریم خرید آنلاین باید ساده، سریع و قابل اعتماد باشد.' }),
                            W('button', { text: 'بیشتر بدانید', url: '#', style: 'secondary' }),
                        ]),
                    ]),
                ]),
            ],
        },
        {
            key: 'contact_persian',
            name: 'تماس با ما',
            description: 'فهرست تیک‌دار تعهدات، متن تماس و دکمه — چیدمان ستونی.',
            categoryLabel: 'تماس',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('heading', { level: 2, text: 'در تماس باشیم' }),
                    W('spacer', { height: 12 }),
                    W('list', { items: [{ text: 'پاسخ حداکثر تا ۲۴ ساعت' }, { text: 'مشاورهٔ رایگان انتخاب محصول' }, { text: 'هماهنگی سفارش‌های سازمانی' }], style: 'check' }),
                    W('spacer', { height: 12 }),
                    W('button', { text: 'ارسال پیام', url: '#', style: 'primary' }),
                ]),
            ],
        },
        {
            // Phase 3C / Milestone 1 — every feature stays an independently
            // editable canonical icon_box child; no JSON/repeater payload.
            key: 'features_showcase_persian',
            name: 'ویترین ویژگی‌های تجاری — فارسی',
            description: 'عنوان و سه کارت ویژگیٔ مستقل در گرید واکنش‌گرا و راست‌چین.',
            categoryLabel: 'ویژگی‌ها',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('heading', { level: 2, text: 'چرا مشتریان ما را انتخاب می‌کنند؟' }),
                    W('features_grid', { columns: '3', style: 'cards', title: '', subtitle: '' }, [
                        W('icon_box', { icon: '✓', title: 'تضمین کیفیت', text: 'محصولات منتخب با ضمانت اصالت و کیفیت.', align: 'start', style: 'card' }),
                        W('icon_box', { icon: '⚡', title: 'ارسال سریع', text: 'تحویل سریع و قابل پیگیری به سراسر کشور.', align: 'start', style: 'card' }),
                        W('icon_box', { icon: '☎', title: 'پشتیبانی همراه', text: 'پاسخ‌گویی شفاف پیش و پس از خرید.', align: 'start', style: 'card' }),
                    ]),
                ]),
            ],
        },
        {
            // Phase 3C / Milestone 1 — compositional stats and trust proof.
            key: 'stats_trust_persian',
            name: 'آمار و اعتماد — فارسی',
            description: 'سه شاخص تجاری و چهار نشان اعتماد مستقل با چیدمان منطقی RTL/LTR.',
            categoryLabel: 'اعتماد',
            direction: 'rtl',
            locale: 'fa',
            nodes: [
                W('section', { padding: 'md', background: 'transparent', container: true }, [
                    W('stats_row', { title: 'اعتماد شما، سرمایه ماست', background: '#f8fafc', background_image: '', dividers: true }, [
                        W('stat_item', { value: '۱۰', suffix: '+', label: 'سال تجربه', emphasis: false }),
                        W('stat_item', { value: '۵۰۰۰', suffix: '+', label: 'مشتری راضی', emphasis: true }),
                        W('stat_item', { value: '۹۸', suffix: '٪', label: 'رضایت از خرید', emphasis: false }),
                    ]),
                    W('trust_badges_row', { columns: '4', gap: 20, background: '#ffffff' }, [
                        W('trust_item', { icon: '✓', label: 'ضمانت اصالت', description: 'تضمین اصالت همه کالاها', url: '', style: 'plain' }),
                        W('trust_item', { icon: '↺', label: 'بازگشت آسان', description: 'فرایند شفاف بازگشت کالا', url: '', style: 'plain' }),
                        W('trust_item', { icon: '⚡', label: 'ارسال سریع', description: 'ارسال سریع و قابل پیگیری', url: '', style: 'plain' }),
                        W('trust_item', { icon: '☎', label: 'پشتیبانی', description: 'پاسخ‌گویی در تمام هفته', url: '', style: 'plain' }),
                    ]),
                ]),
            ],
        },
    ];

    // ── Header/Footer compositions (Steps 15–17) ────────────────────────
    // Same Canvas, same tree operations, same Inspector — a separate zone
    // target, not a separate builder engine. One Persian/RTL commercial
    // style + one neutral style capable of LTR.
    const COMPOSITIONS = {
        header: [
            {
                key: 'header_persian',
                name: 'هدر فروشگاهی فارسی',
                description: 'لوگو، منو، جستجو، حساب و سبد خرید — هدر کامل راست‌چین.',
                categoryLabel: 'هدر',
                direction: 'rtl',
                locale: 'fa',
                nodes: [
                    W('nav_header', {
                        logo_text: 'فروشگاه من',
                        logo_url: '/',
                        nav_links: [
                            { label: 'خانه', url: '/' },
                            { label: 'فروشگاه', url: '/shop' },
                            { label: 'وبلاگ', url: '/blog' },
                            { label: 'تماس', url: '/contact' },
                        ],
                        sticky: true,
                        cta_text: 'ثبت‌نام',
                        cta_url: '/register',
                        show_search: true,
                        show_cart: true,
                        show_account: true,
                    }),
                ],
            },
            {
                key: 'header_minimal',
                name: 'هدر مینیمال (چندزبانه)',
                description: 'لوگو و منوی ساده — بدون جهت‌محوری، مناسب LTR و RTL.',
                categoryLabel: 'هدر',
                direction: 'rtl',
                locale: 'fa',
                nodes: [
                    W('nav_header', {
                        logo_text: 'My Store',
                        logo_url: '/',
                        nav_links: [
                            { label: 'Home', url: '/' },
                            { label: 'Shop', url: '/shop' },
                            { label: 'Contact', url: '/contact' },
                        ],
                    }),
                ],
            },
            {
                key: 'header_mobile_preset',
                name: 'هدر استاندارد موبایل',
                description: 'لوگوی متناسب، همبرگری تعاملی، کشوی منو، بنر تبلیغاتی و لینک‌های دسترسی سریع — بهینه برای نمایشگرهای کوچک.',
                categoryLabel: 'هدر',
                direction: 'rtl',
                locale: 'fa',
                nodes: [
                    W('nav_header', {
                        logo_text: 'فروشگاه من',
                        logo_url: '/',
                        mobile_logo_url: '',
                        sticky: true,
                        search_mode: 'icon',
                        show_search: true,
                        cta_text: 'تماس با ما',
                        cta_url: 'tel:+982112345678',
                        cta_glow_enabled: true,
                        cta_glow_color: '#c2410c',
                        phone: '+98 21 1234 5678',
                        phone_glow_enabled: true,
                        phone_glow_color: '#22c55e',
                        social_links: [
                            { label: 'ایتا', url: 'https://eitaa.com', network: 'eitaa' },
                            { label: 'بله', url: 'https://bale.ai', network: 'bale' },
                            { label: 'روبیکا', url: 'https://rubika.ir', network: 'rubika' },
                            { label: 'سروش', url: 'https://soroush.ir', network: 'soroush' },
                        ],
                        social_style: 'brand',
                        social_show_mobile: true,
                        show_cart: true,
                        show_account: true,
                        header_order: ['logo', 'search', 'social', 'phone', 'cta', 'cart', 'account', 'menu', 'burger'],
                    }),
                ],
            },
        ],
        footer: [
            {
                key: 'footer_persian',
                name: 'فوتر فروشگاهی فارسی',
                description: 'لوگو، سه ستون لینک، شبکه‌های اجتماعی و کپی‌رایت.',
                categoryLabel: 'فوتر',
                direction: 'rtl',
                locale: 'fa',
                nodes: [
                    W('nav_footer', {
                        logo_text: 'فروشگاه من',
                        tagline: 'خرید مطمئن، ارسال سریع',
                        columns: [
                            { title: 'فروشگاه', links: [{ label: 'محصولات', url: '/shop' }, { label: 'تخفیف‌ها', url: '/sale' }] },
                            { title: 'پشتیبانی', links: [{ label: 'سوالات متداول', url: '/faq' }, { label: 'تماس با ما', url: '/contact' }] },
                            { title: 'قوانین', links: [{ label: 'شرایط استفاده', url: '/terms' }, { label: 'حریم خصوصی', url: '/privacy' }] },
                        ],
                        social_links: [{ label: 'Instagram', url: 'https://instagram.com' }],
                        copyright: '© همهٔ حقوق محفوظ است.',
                    }),
                ],
            },
            {
                key: 'footer_minimal',
                name: 'فوتر مینیمال (چندزبانه)',
                description: 'کپی‌رایت و یک ستون لینک — مناسب هر زبانی.',
                categoryLabel: 'فوتر',
                direction: 'rtl',
                locale: 'fa',
                nodes: [
                    W('nav_footer', {
                        logo_text: 'My Store',
                        columns: [
                            { title: 'Company', links: [{ label: 'About', url: '/about' }, { label: 'Contact', url: '/contact' }] },
                        ],
                        copyright: '© All rights reserved.',
                    }),
                ],
            },
        ],
    };

    const widgetCount = (nodes) => nodes.reduce(
        (sum, node) => sum + 1 + (Array.isArray(node.children) ? widgetCount(node.children) : 0), 0);

    /**
     * Phase 3A — the library boundary. Holds canonical packages and inserts
     * them through the EDITOR's canonical tree operations; it never mutates
     * document state directly.
     */
    class CanvasSectionLibrary {
        constructor(sections, compositions) {
            this.sections = sections;
            this.compositions = compositions;
        }

        /** Items for a zone target (section|header|footer), optionally filtered. */
        list(kind, term) {
            const source = kind === 'header' ? this.compositions.header
                : kind === 'footer' ? this.compositions.footer
                : this.sections;
            const q = (term || '').trim().toLowerCase();
            return source
                .map((item) => ({ ...item, widgets: widgetCount(item.nodes) }))
                .filter((item) => !q
                    || item.name.toLowerCase().includes(q)
                    || (item.categoryLabel || '').toLowerCase().includes(q));
        }

        get(key) {
            for (const item of [...this.sections, ...this.compositions.header, ...this.compositions.footer]) {
                if (item.key === key) return item;
            }
            return null;
        }

        /**
         * Canonical insertion (Step 11): availability gate (fail closed on
         * unknown widget types — same invariant as SectionPackage::import)
         * → id regeneration (never collide with existing nodes) → ONE
         * treeOperations.insertNode → editor history + dirty + reload.
         */
        insert(editor, key, parentId, index) {
            const template = this.get(key);
            if (!template) throw new Error('Section not found: ' + key);
            const schema = editor.treeOperations.schema || {};
            const check = (nodes) => {
                for (const node of nodes) {
                    if (!schema[node.type]) {
                        throw new Error('Widget type not available: ' + node.type);
                    }
                    if (node.children) check(node.children);
                }
            };
            check(template.nodes);

            const fresh = (nodes) => nodes.map((node) => {
                const clean = {
                    id: 'blk_' + Math.random().toString(16).slice(2, 10) + Date.now().toString(16).slice(-4),
                    type: node.type,
                    props: JSON.parse(JSON.stringify(node.props || {})),
                };
                if (node.style) clean.style = JSON.parse(JSON.stringify(node.style));
                if (node.responsive) clean.responsive = JSON.parse(JSON.stringify(node.responsive));
                if (node.children) clean.children = fresh(node.children);
                return clean;
            });
            const blocks = fresh(template.nodes);
            const root = blocks[0];

            const targetIndex = (index === undefined || index === null || index < 0)
                ? editor.blocks.length : index;
            const operation = editor.treeOperations.insertNode(editor.blocks, root, parentId || 'page', targetIndex);
            editor.recordStructuralHistory('insert', operation, null, root, editor.documentState.selectedNodeId, root.id);
            editor.afterStructuralChange(operation, root.id);
            return root;
        }
    }

    window.CanvasSectionLibrary = CanvasSectionLibrary;
    window.CANVAS_SECTION_LIBRARY = new CanvasSectionLibrary(SECTIONS, COMPOSITIONS);
})(window);
</script>
