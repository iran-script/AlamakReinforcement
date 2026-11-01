import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:uuid/uuid.dart';

import '../../core/data/api_client.dart';
import '../auth/session_controller.dart';
import 'case_repository.dart';

class CasesPage extends ConsumerStatefulWidget {
  const CasesPage({super.key});
  @override
  ConsumerState<CasesPage> createState() => _CasesPageState();
}

class _CasesPageState extends ConsumerState<CasesPage> {
  Future<Map<String, dynamic>>? _request;
  List<Map<String, Object?>> _drafts = [];
  String? _status;

  @override
  void initState() { super.initState(); _load(); }
  void _load() {
    final session = ref.read(sessionProvider);
    _request = session.api!.dio.get('v1/supervisor/cases', queryParameters: {if (_status != null) 'status': _status}).then((r) => Map<String, dynamic>.from(r.data as Map));
    CaseRepository(session).pending().then((value) { if (mounted) setState(() => _drafts = value); });
  }

  Future<void> _sync() async {
    final session = ref.read(sessionProvider);
    try {
      await CaseRepository(session).syncPending();
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('عملیات با موفقیت انجام شد.'))); setState(_load); }
    } catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.messageFrom(error) ?? 'Request failed')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('پرونده‌های تعمیر'), actions: [
        IconButton(tooltip: 'همگام‌سازی', onPressed: _drafts.isEmpty ? null : _sync, icon: const Icon(Icons.sync)),
        PopupMenuButton<String?>(tooltip: 'همه وضعیت‌ها', onSelected: (value) => setState(() { _status = value; _load(); }), itemBuilder: (_) => const [
          PopupMenuItem(value: null, child: Text('اطلاعات پرونده')),
          PopupMenuItem(value: 'open', child: Text('باز')),
          PopupMenuItem(value: 'submitted', child: Text('ارسال‌شده')),
          PopupMenuItem(value: 'returned', child: Text('برگشتی')),
          PopupMenuItem(value: 'completed', child: Text('تکمیل‌شده')),
        ], child: const Padding(padding: EdgeInsets.all(12), child: Icon(Icons.filter_list))),
      ]),
      body: RefreshIndicator(onRefresh: () async { await session.refreshUser(); setState(_load); await _request; }, child: FutureBuilder<Map<String, dynamic>>(future: _request, builder: (context, snapshot) {
        if (snapshot.hasError && _drafts.isEmpty) return ListView(children: [const SizedBox(height: 180), Center(child: Text('دریافت اطلاعات ناموفق بود.'))]);
        if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
        final paginator = snapshot.data!;
        final cases = List<Map<String, dynamic>>.from((paginator['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
        return ListView(padding: const EdgeInsets.all(14), children: [
          if (session.offline) const _InfoBanner(text: 'حالت آفلاین فعال است. ثبت نهایی پس از تأیید سرور انجام می‌شود.'),
          if (_drafts.isNotEmpty) ...[
            _SectionTitle(title: 'همگام‌سازی', trailing: TextButton.icon(onPressed: _sync, icon: const Icon(Icons.sync), label: const Text('همگام‌سازی'))),
            ..._drafts.map((draft) => Card(child: ListTile(leading: const Icon(Icons.cloud_upload_outlined, color: Colors.orange), title: Text(draft['kind'] == 'case' ? 'اطلاعات پرونده' : 'اطلاعات پرونده'), subtitle: Text('${draft['created_at']}'), trailing: const Icon(Icons.pending_outlined)))),
            const SizedBox(height: 8),
          ],
          _SectionTitle(title: 'اطلاعات پرونده', trailing: Text('${cases.length} مورد')),
          if (cases.isEmpty) const _EmptyState(title: 'پرونده‌ای یافت نشد.', message: 'پرونده‌ای یافت نشد.'),
          ...cases.map((item) => _CaseTile(item: item, onTap: () => context.push('/case/${item['id']}'))),
        ]);
      })),
    );
  }
}

class NewCasePage extends ConsumerStatefulWidget {
  const NewCasePage({super.key, required this.riserId});
  final int riserId;
  @override
  ConsumerState<NewCasePage> createState() => _NewCasePageState();
}

class _NewCasePageState extends ConsumerState<NewCasePage> {
  final _description = TextEditingController();
  final _picker = ImagePicker();
  final List<XFile> _photos = [];
  List<Map<String, dynamic>> _categories = [];
  String _priority = '\u{06a9}\u{0645}';
  int? _categoryId;
  DateTime _date = DateTime.now();
  bool _loading = false;

  @override
  void initState() { super.initState(); _loadReferences(); }
  Future<void> _loadReferences() async {
    try {
      final result = await ref.read(sessionProvider).api!.dio.get('v1/supervisor/references');
      if (mounted) setState(() => _categories = List<Map<String, dynamic>>.from((result.data['operation_categories'] as List).map((e) => Map<String, dynamic>.from(e as Map))));
    } catch (_) { /* Optional category can remain unset while offline. */ }
  }

  @override
  void dispose() { _description.dispose(); super.dispose(); }

  Future<void> _pickPhotos() async {
    final selected = await _picker.pickMultiImage(imageQuality: 78, maxWidth: 1800);
    if (selected.isNotEmpty && mounted) setState(() => _photos.addAll(selected.take(20 - _photos.length)));
  }

  Future<void> _submit() async {
    final session = ref.read(sessionProvider);
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    if (userId == null) return;
    setState(() => _loading = true);
    final payload = <String, dynamic>{
      'client_uuid': const Uuid().v4(), 'riser_id': widget.riserId,
      if (_categoryId != null) 'operation_category_id': _categoryId,
      'priority': _priority, 'description': _description.text.trim(),
      'identified_at': _date.toIso8601String(),
    };
    try {
      await session.api!.dio.post('v1/supervisor/cases', data: CaseRepository(session).formData(payload, _photos.map((e) => e.path).toList(), 'photos_before'));
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('پرونده با موفقیت ثبت شد.'))); context.go('/app/cases'); }
    } on DioException catch (error) {
      if (error.response == null && kReleaseMode) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ثبت آفلاین در نسخه عملیاتی غیرفعال است. اتصال به سرور داخلی را بررسی کنید.')));
      } else if (error.response == null) {
        await CaseRepository(session).savePending(kind: 'case', userId: userId, payload: payload, filePaths: _photos.map((e) => e.path).toList());
        if (mounted) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('پرونده با موفقیت ثبت شد.'))); context.go('/app/cases'); }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.messageFrom(error) ?? 'Request failed')));
      }
    } finally { if (mounted) setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('پرونده‌های تعمیر')),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      Card(child: ListTile(leading: const Icon(Icons.location_on_outlined), title: Text('اطلاعات علمک و محدوده مجاز'), subtitle: const Text('اطلاعات علمک و محدوده مجاز'))),
      const SizedBox(height: 12),
      DropdownButtonFormField<String>(initialValue: _priority, decoration: const InputDecoration(labelText: 'اولویت خرابی'), items: const [DropdownMenuItem(value: '\u{06a9}\u{0645}', child: Text('\u{06a9}\u{0645}')), DropdownMenuItem(value: '\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}', child: Text('\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}')), DropdownMenuItem(value: '\u{0632}\u{06cc}\u{0627}\u{062f}', child: Text('\u{0632}\u{06cc}\u{0627}\u{062f}')), DropdownMenuItem(value: '\u{0641}\u{0648}\u{0631}\u{06cc}', child: Text('\u{0641}\u{0648}\u{0631}\u{06cc}'))], onChanged: (value) => setState(() => _priority = value ?? '\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}')),
      const SizedBox(height: 12),
      DropdownButtonFormField<int?>(initialValue: _categoryId, decoration: const InputDecoration(labelText: 'نوع عملیات'), items: [const DropdownMenuItem<int?>(value: null, child: Text('انتخاب نشده')), ..._categories.map((row) => DropdownMenuItem<int?>(value: row['id'] as int, child: Text(row['title'].toString())))], onChanged: (value) => setState(() => _categoryId = value)),
      const SizedBox(height: 12),
      TextField(controller: _description, minLines: 3, maxLines: 6, decoration: const InputDecoration(labelText: 'شرح خرابی', alignLabelWithHint: true)),
      const SizedBox(height: 12),
      OutlinedButton.icon(onPressed: () async { final date = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime(2020), lastDate: DateTime.now()); if (date != null) setState(() => _date = date); }, icon: const Icon(Icons.calendar_today), label: Text('تاریخ شناسایی: ${_date.toLocal().toString().split(' ').first}')),
      const SizedBox(height: 12),
      OutlinedButton.icon(onPressed: _pickPhotos, icon: const Icon(Icons.add_a_photo_outlined), label: Text('افزودن تصویر')),
      if (_photos.isNotEmpty) SizedBox(height: 92, child: ListView(scrollDirection: Axis.horizontal, children: _photos.map((photo) => Padding(padding: const EdgeInsetsDirectional.only(end: 8), child: Image.file(File(photo.path), width: 92, height: 92, fit: BoxFit.cover))).toList())),
      const SizedBox(height: 18),
      FilledButton.icon(onPressed: _loading ? null : _submit, icon: _loading ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send), label: Text(_loading ? 'در حال ارسال…' : 'ارسال گزارش')),
    ]),
  );
}

class CaseDetailPage extends ConsumerStatefulWidget {
  const CaseDetailPage({super.key, required this.caseId});
  final int caseId;
  @override
  ConsumerState<CaseDetailPage> createState() => _CaseDetailPageState();
}

class _CaseDetailPageState extends ConsumerState<CaseDetailPage> {
  Future<Map<String, dynamic>>? _request;
  @override
  void initState() { super.initState(); _load(); }
  void _load() { _request = ref.read(sessionProvider).api!.dio.get('v1/supervisor/cases/${widget.caseId}').then((r) => Map<String, dynamic>.from(r.data['case'] as Map)); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('پرونده‌های تعمیر')),
    body: FutureBuilder<Map<String, dynamic>>(future: _request, builder: (context, snapshot) {
      if (snapshot.hasError) return Center(child: Text('دریافت اطلاعات ناموفق بود.'));
      if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
      final item = snapshot.data!;
      final operation = item['operation'] as Map?;
      final photos = List<Map<String, dynamic>>.from((item['photos'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
      return ListView(padding: const EdgeInsets.all(16), children: [
        _InfoBanner(text: 'اولویت خرابی'),
        const SizedBox(height: 10),
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(item['description']?.toString().isNotEmpty == true ? item['description'].toString() : 'بدون شرح', style: const TextStyle(height: 1.6)),
          const SizedBox(height: 8), Text('تاریخ شناسایی: ${item['identified_at'] ?? '—'}'),
          Text('گروه اجرایی'),
          Text('ارسال به رئیس ناحیه: ${item['submitted_at'] ?? 'هنوز ارسال نشده'}'),
        ]))),
        const SizedBox(height: 12),
        if (photos.isNotEmpty) ...[
          const _SectionTitle(title: 'اطلاعات پرونده'),
          SizedBox(height: 110, child: ListView(scrollDirection: Axis.horizontal, children: photos.map((photo) => Padding(padding: const EdgeInsetsDirectional.only(end: 8), child: ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(photo['url'].toString(), width: 110, height: 110, fit: BoxFit.cover, headers: {'Authorization': 'Bearer ${ref.read(sessionProvider).token}'}, errorBuilder: (_, _, _) => const SizedBox(width: 110, child: Icon(Icons.broken_image_outlined)))))).toList())),
        ],
        if (operation == null && ['open', 'in_progress', 'returned'].contains(item['status'])) ...[
          const SizedBox(height: 14), FilledButton.icon(onPressed: () => _showCompleteForm(context), icon: const Icon(Icons.handyman_outlined), label: const Text('اطلاعات پرونده')),
        ] else if (operation != null) ...[
          const _SectionTitle(title: 'اطلاعات پرونده'),
          Card(child: ListTile(leading: const Icon(Icons.build_outlined), title: Text(operation['operation_category']?['title']?.toString() ?? 'تاریخ تعمیر'), subtitle: Text('تاریخ تعمیر'))),
        ],
      ]);
    }),
  );

  Future<void> _showCompleteForm(BuildContext context) async {
    await showModalBottomSheet<void>(context: context, isScrollControlled: true, showDragHandle: true, builder: (_) => CompleteOperationSheet(caseId: widget.caseId, onSaved: () { Navigator.of(context).pop(); setState(_load); }));
  }
}

class CompleteOperationSheet extends ConsumerStatefulWidget {
  const CompleteOperationSheet({super.key, required this.caseId, required this.onSaved});
  final int caseId; final VoidCallback onSaved;
  @override
  ConsumerState<CompleteOperationSheet> createState() => _CompleteOperationSheetState();
}

class _CompleteOperationSheetState extends ConsumerState<CompleteOperationSheet> {
  final _description = TextEditingController();
  final _picker = ImagePicker();
  final Map<int, TextEditingController> _quantities = {};
  List<Map<String, dynamic>> _materials = [];
  List<Map<String, dynamic>> _categories = [];
  final List<XFile> _photos = [];
  int? _categoryId;
  DateTime _date = DateTime.now();
  bool _busy = false;

  @override
  void initState() { super.initState(); _loadReferences(); }
  Future<void> _loadReferences() async {
    try {
      final r = await ref.read(sessionProvider).api!.dio.get('v1/supervisor/references');
      if (mounted) setState(() { _materials = List<Map<String, dynamic>>.from((r.data['materials'] as List).map((e) => Map<String, dynamic>.from(e as Map))); _categories = List<Map<String, dynamic>>.from((r.data['operation_categories'] as List).map((e) => Map<String, dynamic>.from(e as Map))); });
    } catch (_) { }
  }
  @override
  void dispose() { _description.dispose(); for (final c in _quantities.values) { c.dispose(); } super.dispose(); }

  Future<void> _submit() async {
    final session = ref.read(sessionProvider); final userId = int.tryParse('${session.user?['id'] ?? ''}'); if (userId == null) return;
    setState(() => _busy = true);
    final uuid = const Uuid().v4();
    final materials = <String, String>{};
    for (final entry in _quantities.entries) { final qty = double.tryParse(entry.value.text); if (qty != null && qty > 0) materials['materials[${entry.key}]'] = entry.value.text; }
    final payload = <String, dynamic>{'client_uuid': uuid, 'operation_date': _date.toIso8601String().split('T').first, if (_categoryId != null) 'operation_category_id': _categoryId, 'description': _description.text.trim(), ...materials};
    try {
      await session.api!.dio.post('v1/supervisor/cases/${widget.caseId}/operation', data: CaseRepository(session).formData(payload, _photos.map((e) => e.path).toList(), 'photos_after'));
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('پرونده با موفقیت ثبت شد.'))); widget.onSaved(); }
    } on DioException catch (error) {
      if (error.response == null && kReleaseMode) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ثبت آفلاین در نسخه عملیاتی غیرفعال است. اتصال به سرور داخلی را بررسی کنید.')));
      } else if (error.response == null) {
        await CaseRepository(session).savePending(kind: 'operation', userId: userId, payload: {'case_id': widget.caseId, ...payload}, filePaths: _photos.map((e) => e.path).toList());
        if (mounted) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('پرونده با موفقیت ثبت شد.'))); widget.onSaved(); }
      } else if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.messageFrom(error) ?? 'Request failed'))); }
    } finally { if (mounted) setState(() => _busy = false); }
  }

  @override
  Widget build(BuildContext context) => Padding(padding: EdgeInsets.fromLTRB(18, 8, 18, MediaQuery.viewInsetsOf(context).bottom + 20), child: SizedBox(height: MediaQuery.sizeOf(context).height * .82, child: ListView(children: [
    Text('اطلاعات پرونده', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
    const SizedBox(height: 14),
    OutlinedButton.icon(onPressed: () async { final date = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime(2020), lastDate: DateTime.now()); if (date != null) setState(() => _date = date); }, icon: const Icon(Icons.calendar_today), label: Text('همگام‌سازی')),
    DropdownButtonFormField<int?>(initialValue: _categoryId, decoration: const InputDecoration(labelText: 'نوع عملیات'), items: [const DropdownMenuItem<int?>(value: null, child: Text('نوع عملیات')), ..._categories.map((c) => DropdownMenuItem<int?>(value: c['id'] as int, child: Text(c['title'].toString())))], onChanged: (value) => setState(() => _categoryId = value)),
    const SizedBox(height: 12), TextField(controller: _description, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'شرح خرابی یا تعمیر')),
    const SizedBox(height: 16), const _SectionTitle(title: 'مصالح مصرف‌شده (مقدار و واحد)'),
    ..._materials.map((material) { final id = material['id'] as int; final controller = _quantities.putIfAbsent(id, TextEditingController.new); return Padding(padding: const EdgeInsets.only(bottom: 8), child: Row(children: [Expanded(child: Text('${material['title']}  (${material['unit']})')), const SizedBox(width: 10), SizedBox(width: 110, child: TextField(controller: controller, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'مقدار')))])); }),
    OutlinedButton.icon(onPressed: () async { final files = await _picker.pickMultiImage(imageQuality: 78); if (mounted) setState(() => _photos.addAll(files.take(20 - _photos.length))); }, icon: const Icon(Icons.add_a_photo_outlined), label: Text('همگام‌سازی')),
    if (_photos.isNotEmpty) SizedBox(height: 90, child: ListView(scrollDirection: Axis.horizontal, children: _photos.map((file) => Padding(padding: const EdgeInsetsDirectional.only(end: 8), child: Image.file(File(file.path), width: 90, height: 90, fit: BoxFit.cover))).toList())),
    const SizedBox(height: 16), FilledButton.icon(onPressed: _busy ? null : _submit, icon: const Icon(Icons.send), label: Text(_busy ? 'در حال ارسال…' : 'ارسال برای بررسی')),
  ])));
}

class _CaseTile extends StatelessWidget {
  const _CaseTile({required this.item, required this.onTap}); final Map<String, dynamic> item; final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(margin: const EdgeInsets.only(bottom: 9), child: ListTile(onTap: onTap, leading: CircleAvatar(backgroundColor: _statusColor(item['status']).withValues(alpha: .14), child: Icon(Icons.build_outlined, color: _statusColor(item['status']))), title: Text(item['riser']?['r_giscode']?.toString() ?? 'اطلاعات علمک و محدوده مجاز'), subtitle: Text('${_statusLabel(item['status'])}  •  ${item['identified_at'] ?? ''}', maxLines: 2), trailing: const Icon(Icons.chevron_left)));
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, this.trailing}); final String title; final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Row(children: [Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.w800))), ?trailing]));
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner({required this.text}); final String text;
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Theme.of(context).colorScheme.secondaryContainer, borderRadius: BorderRadius.circular(12)), child: Text(text, style: const TextStyle(height: 1.5)));
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.title, required this.message}); final String title; final String message;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 36), child: Column(children: [const Icon(Icons.inbox_outlined, size: 38, color: Colors.black38), const SizedBox(height: 8), Text(title, style: const TextStyle(fontWeight: FontWeight.bold)), Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54))]));
}

String _statusLabel(Object? value) => switch (value) {
  'open' => 'باز', 'in_progress' => 'در حال انجام', 'submitted' => 'ارسال‌شده برای بررسی',
  'returned' => 'برگشتی', 'completed' => 'برگشتی', _ => 'نامشخص',
};
Color _statusColor(Object? value) => switch (value) {
  'open' => const Color(0xffb7791f), 'in_progress' => const Color(0xff2563eb),
  'submitted' => const Color(0xff6955b5), 'returned' => const Color(0xffc34747),
  'completed' => const Color(0xff18856e), _ => const Color(0xff607d8b),
};
