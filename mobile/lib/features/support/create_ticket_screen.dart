import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/client_api_service.dart';
import '../../shared/providers/auth_provider.dart';

// ─── Form state ───────────────────────────────────────────────────────────────

class CreateTicketState {
  const CreateTicketState({
    this.isSubmitting = false,
    this.error,
    this.success = false,
  });

  final bool isSubmitting;
  final String? error;
  final bool success;
}

class CreateTicketNotifier extends StateNotifier<CreateTicketState> {
  CreateTicketNotifier(this._ref) : super(const CreateTicketState());

  final Ref _ref;

  Future<TicketDetail?> submit({
    required String subject,
    required String description,
    String? customerMobile,
    String? customerName,
    String priority = 'normal',
    String? category,
  }) async {
    state = const CreateTicketState(isSubmitting: true);

    try {
      final api    = _ref.read(clientApiServiceProvider);
      final user   = _ref.read(authStateProvider).user;

      final ticket = await api.createTicket(
        subject:        subject,
        description:    description,
        customerMobile: customerMobile ?? user?.mobile ?? '',
        customerName:   customerName   ?? user?.name,
        priority:       priority,
        category:       category,
      );
      state = const CreateTicketState(success: true);
      return ticket;
    } catch (e) {
      state = CreateTicketState(error: e.toString());
      return null;
    }
  }
}

final createTicketProvider = StateNotifierProvider.autoDispose<CreateTicketNotifier, CreateTicketState>(
  (ref) => CreateTicketNotifier(ref),
);

// ─── Screen ───────────────────────────────────────────────────────────────────

class CreateTicketScreen extends ConsumerStatefulWidget {
  const CreateTicketScreen({super.key});

  @override
  ConsumerState<CreateTicketScreen> createState() => _CreateTicketScreenState();
}

class _CreateTicketScreenState extends ConsumerState<CreateTicketScreen> {
  final _formKey       = GlobalKey<FormState>();
  final _subjectCtrl   = TextEditingController();
  final _descCtrl      = TextEditingController();
  final _mobileCtrl    = TextEditingController();
  final _nameCtrl      = TextEditingController();
  final _categoryCtrl  = TextEditingController();

  String _priority = 'normal';

  @override
  void dispose() {
    _subjectCtrl.dispose();
    _descCtrl.dispose();
    _mobileCtrl.dispose();
    _nameCtrl.dispose();
    _categoryCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final user        = ref.read(authStateProvider).user;
    final isSiteAdmin = user?.isSiteAdminUser ?? false;

    final ticket = await ref.read(createTicketProvider.notifier).submit(
      subject:        _subjectCtrl.text.trim(),
      description:    _descCtrl.text.trim(),
      customerMobile: isSiteAdmin ? (_mobileCtrl.text.trim().isEmpty ? null : _mobileCtrl.text.trim()) : null,
      customerName:   _nameCtrl.text.trim().isEmpty ? null : _nameCtrl.text.trim(),
      priority:       _priority,
      category:       _categoryCtrl.text.trim().isEmpty ? null : _categoryCtrl.text.trim(),
    );

    if (mounted && ticket != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تیکت با موفقیت ثبت شد.'), backgroundColor: Colors.green),
      );
      if (context.canPop()) {
        context.pop();
      } else {
        context.go('/support');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final formState = ref.watch(createTicketProvider);
    final isSiteAdmin = ref.watch(authStateProvider).user?.isSiteAdminUser ?? false;

    return Scaffold(
      appBar: AppBar(title: const Text('تیکت جدید')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (formState.error != null)
                _ErrorBanner(error: formState.error!),

              // ── موضوع ────────────────────────────────────────────────
              TextFormField(
                controller: _subjectCtrl,
                decoration: const InputDecoration(
                  labelText: 'موضوع *',
                  prefixIcon: Icon(Icons.title),
                ),
                textInputAction: TextInputAction.next,
                validator: (v) => (v == null || v.trim().isEmpty) ? 'موضوع الزامی است' : null,
              ),
              const SizedBox(height: 16),

              // ── شرح مشکل ─────────────────────────────────────────────
              TextFormField(
                controller: _descCtrl,
                decoration: const InputDecoration(
                  labelText: 'شرح مشکل *',
                  prefixIcon: Icon(Icons.description),
                  alignLabelWithHint: true,
                ),
                maxLines: 5,
                textInputAction: TextInputAction.newline,
                validator: (v) => (v == null || v.trim().isEmpty) ? 'شرح مشکل الزامی است' : null,
              ),
              const SizedBox(height: 16),

              // ── موبایل مشتری (فقط ادمین سایت) ───────────────────────
              if (isSiteAdmin) ...[
                TextFormField(
                  controller: _mobileCtrl,
                  decoration: const InputDecoration(
                    labelText: 'موبایل مشتری *',
                    prefixIcon: Icon(Icons.phone),
                    hintText: '09xxxxxxxxx',
                  ),
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) return 'موبایل الزامی است';
                    if (!RegExp(r'^09[0-9]{9}$').hasMatch(v.trim())) return 'فرمت موبایل نادرست است';
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(
                    labelText: 'نام مشتری (اختیاری)',
                    prefixIcon: Icon(Icons.person),
                  ),
                  textInputAction: TextInputAction.next,
                ),
                const SizedBox(height: 16),
              ],

              // ── اولویت ───────────────────────────────────────────────
              _PrioritySelector(
                value: _priority,
                onChanged: (v) => setState(() => _priority = v),
              ),
              const SizedBox(height: 16),

              // ── دسته‌بندی ─────────────────────────────────────────────
              TextFormField(
                controller: _categoryCtrl,
                decoration: const InputDecoration(
                  labelText: 'دسته‌بندی (اختیاری)',
                  prefixIcon: Icon(Icons.category),
                  hintText: 'مثال: فنی، مالی، عمومی',
                ),
                textInputAction: TextInputAction.done,
              ),
              const SizedBox(height: 32),

              // ── ثبت ───────────────────────────────────────────────────
              FilledButton.icon(
                onPressed: formState.isSubmitting ? null : _submit,
                icon: formState.isSubmitting
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.send),
                label: Text(formState.isSubmitting ? 'در حال ثبت...' : 'ثبت تیکت'),
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Priority selector ────────────────────────────────────────────────────────

class _PrioritySelector extends StatelessWidget {
  const _PrioritySelector({required this.value, required this.onChanged});

  final String value;
  final void Function(String) onChanged;

  @override
  Widget build(BuildContext context) {
    const options = [
      ('پایین', 'low',    Colors.grey),
      ('عادی',  'normal', Colors.orange),
      ('بالا',  'high',   Colors.red),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('اولویت', style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        SegmentedButton<String>(
          segments: options.map((opt) {
            final (label, val, color) = opt;
            return ButtonSegment<String>(
              value: val,
              label: Text(label),
              icon: Icon(Icons.circle, size: 10, color: color),
            );
          }).toList(),
          selected: {value},
          onSelectionChanged: (s) => onChanged(s.first),
        ),
      ],
    );
  }
}

// ─── Error banner ─────────────────────────────────────────────────────────────

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.error});
  final String error;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.red.shade200),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline, color: Colors.red.shade700),
          const SizedBox(width: 8),
          Expanded(child: Text(error, style: TextStyle(color: Colors.red.shade700))),
        ],
      ),
    );
  }
}
