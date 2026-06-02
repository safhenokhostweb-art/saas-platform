import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/client_api_service.dart';
import '../../core/config/app_config.dart';
import '../../core/security/secure_token_service.dart';
import '../../shared/providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey      = GlobalKey<FormState>();
  final _mobileCtrl   = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool  _obscure      = true;
  bool  _loading      = false;
  String? _error;
  late AnimationController _animCtrl;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _fadeAnim  = CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(begin: const Offset(0, 0.08), end: Offset.zero)
        .animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut));
    _animCtrl.forward();
    _initServerUrl();
  }

  Future<void> _initServerUrl() async {
    final svc = ref.read(secureTokenServiceProvider);
    final saved = await svc.read(SecureKey.baseUrl);
    // اگر آدرس ذخیره نشده یا اشتباه است، آدرس پیش‌فرض را تنظیم کن
    if (saved == null || saved.isEmpty || saved == 'http://customer-site.test') {
      await svc.write(SecureKey.baseUrl, AppConfig.defaultServerUrl);
      ref.read(clientApiServiceProvider).setBaseUrl(AppConfig.defaultServerUrl);
    } else {
      ref.read(clientApiServiceProvider).setBaseUrl(saved);
    }
  }

  @override
  void dispose() {
    _mobileCtrl.dispose();
    _passwordCtrl.dispose();
    _animCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() { _loading = true; _error = null; });

    try {
      await ref.read(authStateProvider.notifier).login(
        mobile:   _mobileCtrl.text.trim(),
        password: _passwordCtrl.text,
      );
      if (mounted) context.go('/conversations');
    } catch (e) {
      setState(() => _error = _toFarsiError(e.toString()));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _toFarsiError(String raw) {
    if (raw.contains('401') || raw.contains('Invalid') || raw.contains('credentials'))
      return 'شماره موبایل یا رمز عبور اشتباه است.';
    if (raw.contains('404'))
      return 'سرور پیدا نشد. لطفاً اتصال اینترنت را بررسی کنید.';
    if (raw.contains('422'))
      return 'اطلاعات وارد شده نادرست است.';
    if (raw.contains('500'))
      return 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.';
    if (raw.contains('SocketException') || raw.contains('connection'))
      return 'اتصال به سرور ممکن نیست. اتصال اینترنت را بررسی کنید.';
    if (raw.contains('timeout'))
      return 'زمان انتظار تمام شد. دوباره تلاش کنید.';
    return 'خطا در ورود. لطفاً دوباره تلاش کنید.';
  }

  @override
  Widget build(BuildContext context) {
    final theme  = Theme.of(context);
    final cs     = theme.colorScheme;
    final size   = MediaQuery.of(context).size;
    final isWide = size.width > 600;

    return Scaffold(
      backgroundColor: cs.surfaceContainerLowest,
      body: Center(
        child: FadeTransition(
          opacity: _fadeAnim,
          child: SlideTransition(
            position: _slideAnim,
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(
                horizontal: isWide ? 0 : 24,
                vertical: 32,
              ),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Directionality(
                  textDirection: TextDirection.rtl,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // ── لوگو + عنوان ──────────────────────────────────
                      _buildHeader(cs),
                      const SizedBox(height: 40),

                      // ── کارت ورود ────────────────────────────────────
                      Container(
                        decoration: BoxDecoration(
                          color: cs.surface,
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: cs.shadow.withValues(alpha: 0.06),
                              blurRadius: 24,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        padding: const EdgeInsets.all(28),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                'خوش آمدید',
                                style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'برای ادامه وارد شوید',
                                style: theme.textTheme.bodyMedium?.copyWith(color: cs.outline),
                              ),
                              const SizedBox(height: 28),

                              // موبایل
                              _buildField(
                                controller: _mobileCtrl,
                                label: 'شماره موبایل',
                                hint: '09xxxxxxxxx',
                                icon: Icons.phone_outlined,
                                type: TextInputType.phone,
                                action: TextInputAction.next,
                                validator: (v) {
                                  if (v == null || v.trim().isEmpty) return 'شماره موبایل را وارد کنید';
                                  if (!RegExp(r'^09[0-9]{9}$').hasMatch(v.trim())) return 'فرمت موبایل نادرست است (مثال: 09123456789)';
                                  return null;
                                },
                                ltr: true,
                              ),
                              const SizedBox(height: 16),

                              // رمز عبور
                              TextFormField(
                                controller:     _passwordCtrl,
                                obscureText:    _obscure,
                                textDirection:  TextDirection.ltr,
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: (_) => _submit(),
                                decoration: InputDecoration(
                                  labelText:   'رمز عبور',
                                  prefixIcon:  const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    icon: Icon(
                                      _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                                      color: cs.outline,
                                    ),
                                    onPressed: () => setState(() => _obscure = !_obscure),
                                  ),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                                ),
                                validator: (v) => (v == null || v.isEmpty) ? 'رمز عبور را وارد کنید' : null,
                              ),

                              // خطا
                              AnimatedSize(
                                duration: const Duration(milliseconds: 250),
                                child: _error != null
                                    ? Padding(
                                        padding: const EdgeInsets.only(top: 14),
                                        child: _ErrorCard(message: _error!),
                                      )
                                    : const SizedBox.shrink(),
                              ),

                              const SizedBox(height: 24),

                              // دکمه ورود
                              _SubmitButton(loading: _loading, onTap: _submit),
                            ],
                          ),
                        ),
                      ),

                      // ── تنظیمات پیشرفته (آدرس سرور) ─────────────────
                      const SizedBox(height: 20),
                      _ServerUrlHint(onTap: () => _showServerSheet(context)),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(ColorScheme cs) {
    return Column(
      children: [
        Container(
          width: 72,
          height: 72,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [cs.primary, cs.secondary],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: cs.primary.withValues(alpha: 0.3),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: const Icon(Icons.support_agent_rounded, color: Colors.white, size: 38),
        ),
        const SizedBox(height: 16),
        Text(AppConfig.appName, style: TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.bold,
          color: cs.onSurface,
          letterSpacing: 1,
        )),
        const SizedBox(height: 4),
        Text('سامانه پشتیبانی آنلاین', style: TextStyle(
          fontSize: 13,
          color: cs.outline,
        )),
      ],
    );
  }

  Widget _buildField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required IconData icon,
    required TextInputType type,
    required TextInputAction action,
    required String? Function(String?) validator,
    bool ltr = false,
  }) {
    return TextFormField(
      controller:      controller,
      keyboardType:    type,
      textInputAction: action,
      textDirection:   ltr ? TextDirection.ltr : TextDirection.rtl,
      decoration: InputDecoration(
        labelText:  label,
        hintText:   hint,
        prefixIcon: Icon(icon),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      ),
      validator: validator,
    );
  }

  void _showServerSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => _ServerUrlSheet(),
    );
  }
}

// ─── Submit Button ────────────────────────────────────────────────────────────

class _SubmitButton extends StatelessWidget {
  const _SubmitButton({required this.loading, required this.onTap});
  final bool loading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      height: 52,
      decoration: BoxDecoration(
        gradient: loading
            ? null
            : LinearGradient(colors: [cs.primary, cs.secondary]),
        color: loading ? cs.surfaceContainerHighest : null,
        borderRadius: BorderRadius.circular(14),
        boxShadow: loading ? [] : [
          BoxShadow(color: cs.primary.withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 6)),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: loading ? null : onTap,
          child: Center(
            child: loading
                ? SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2.5, color: cs.primary),
                  )
                : const Text(
                    'ورود به سامانه',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

// ─── Error card ───────────────────────────────────────────────────────────────

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.errorContainer.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Theme.of(context).colorScheme.error.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, size: 18,
              color: Theme.of(context).colorScheme.error),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message,
                style: TextStyle(fontSize: 13,
                    color: Theme.of(context).colorScheme.onErrorContainer)),
          ),
        ],
      ),
    );
  }
}

// ─── Server URL Hint (تنظیمات پیشرفته) ──────────────────────────────────────

class _ServerUrlHint extends ConsumerWidget {
  const _ServerUrlHint({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return TextButton.icon(
      onPressed: onTap,
      icon: const Icon(Icons.settings_outlined, size: 16),
      label: const Text('تنظیمات پیشرفته (آدرس سرور)', style: TextStyle(fontSize: 12)),
      style: TextButton.styleFrom(
        foregroundColor: Theme.of(context).colorScheme.outline,
      ),
    );
  }
}

// ─── Server URL Sheet ─────────────────────────────────────────────────────────

class _ServerUrlSheet extends ConsumerStatefulWidget {
  @override
  ConsumerState<_ServerUrlSheet> createState() => _ServerUrlSheetState();
}

class _ServerUrlSheetState extends ConsumerState<_ServerUrlSheet> {
  late TextEditingController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = TextEditingController(text: AppConfig.defaultServerUrl);
    _load();
  }

  Future<void> _load() async {
    final url = await ref.read(secureTokenServiceProvider).read(SecureKey.baseUrl);
    if (url != null && mounted) setState(() => _ctrl.text = url);
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20,
          MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(width: 40, height: 4,
                decoration: BoxDecoration(color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2))),
          ),
          const SizedBox(height: 16),
          const Text('آدرس سرور', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          const Text('این تنظیم توسط مدیر سیستم پیکربندی می‌شود.',
              style: TextStyle(fontSize: 12, color: Colors.grey)),
          const SizedBox(height: 16),
          TextField(
            controller: _ctrl,
            textDirection: TextDirection.ltr,
            decoration: InputDecoration(
              labelText: 'آدرس سرور',
              hintText: 'https://platform.example.com',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              prefixIcon: const Icon(Icons.dns_outlined),
            ),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(
              child: OutlinedButton(
                onPressed: () async {
                  final svc = ref.read(secureTokenServiceProvider);
                  await svc.write(SecureKey.baseUrl, AppConfig.defaultServerUrl);
                  ref.read(clientApiServiceProvider).setBaseUrl(AppConfig.defaultServerUrl);
                  setState(() => _ctrl.text = AppConfig.defaultServerUrl);
                  if (context.mounted) Navigator.pop(context);
                },
                child: const Text('بازنشانی'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
                onPressed: () async {
                  final url = _ctrl.text.trim();
                  if (url.isEmpty) return;
                  final svc = ref.read(secureTokenServiceProvider);
                  await svc.write(SecureKey.baseUrl, url);
                  ref.read(clientApiServiceProvider).setBaseUrl(url);
                  if (context.mounted) Navigator.pop(context);
                },
                child: const Text('ذخیره'),
              ),
            ),
          ]),
        ],
      ),
    );
  }
}
