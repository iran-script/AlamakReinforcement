import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:uuid/uuid.dart';

import '../../core/data/api_client.dart';
import '../auth/session_controller.dart';
import 'case_repository.dart';
import 'image_source_sheet.dart';
import 'material_picker_sheet.dart';

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
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    final session = ref.read(sessionProvider);
    _request = session.api!.dio
        .get(
          'v1/supervisor/cases',
          queryParameters: {if (_status != null) 'status': _status},
        )
        .then((r) => Map<String, dynamic>.from(r.data as Map));
    CaseRepository(session).pending().then((value) {
      if (mounted) setState(() => _drafts = value);
    });
  }

  Future<void> _sync() async {
    final session = ref.read(sessionProvider);
    try {
      await CaseRepository(session).syncPending();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('عملیات با موفقیت انجام شد.')),
        );
        setState(_load);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ApiClient.messageFrom(error) ?? 'Request failed'),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('پرونده‌های تعمیر'),
        actions: [
          IconButton(
            tooltip: 'همگام‌سازی',
            onPressed: _drafts.isEmpty ? null : _sync,
            icon: const Icon(Icons.sync),
          ),
          PopupMenuButton<String?>(
            tooltip: 'همه وضعیت‌ها',
            onSelected: (value) => setState(() {
              _status = value;
              _load();
            }),
            itemBuilder: (_) => const [
              PopupMenuItem(value: null, child: Text('اطلاعات پرونده')),
              PopupMenuItem(value: 'open', child: Text('باز')),
              PopupMenuItem(value: 'submitted', child: Text('ارسال‌شده')),
              PopupMenuItem(value: 'returned', child: Text('برگشتی')),
              PopupMenuItem(value: 'completed', child: Text('تکمیل‌شده')),
            ],
            child: const Padding(
              padding: EdgeInsets.all(12),
              child: Icon(Icons.filter_list),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await session.refreshUser();
          setState(_load);
          await _request;
        },
        child: FutureBuilder<Map<String, dynamic>>(
          future: _request,
          builder: (context, snapshot) {
            if (snapshot.hasError && _drafts.isEmpty) {
              return ListView(
                children: [
                  const SizedBox(height: 180),
                  Center(child: Text('دریافت اطلاعات ناموفق بود.')),
                ],
              );
            }
            if (!snapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final paginator = snapshot.data!;
            final cases = List<Map<String, dynamic>>.from(
              (paginator['data'] as List? ?? []).map(
                (e) => Map<String, dynamic>.from(e as Map),
              ),
            );
            return ListView(
              padding: const EdgeInsets.all(14),
              children: [
                if (session.offline)
                  const _InfoBanner(
                    text: 'حالت آفلاین فعال است. ثبت نهایی پس از تأیید سرور انجام می‌شود.',
                  ),
                if (_drafts.isNotEmpty) ...[
                  _SectionTitle(
                    title: 'همگام‌سازی',
                    trailing: TextButton.icon(
                      onPressed: _sync,
                      icon: const Icon(Icons.sync),
                      label: const Text('همگام‌سازی'),
                    ),
                  ),
                  ..._drafts.map(
                    (draft) => Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.cloud_upload_outlined,
                          color: Colors.orange,
                        ),
                        title: Text(
                          draft['kind'] == 'case'
                              ? 'اطلاعات پرونده'
                              : 'اطلاعات پرونده',
                        ),
                        subtitle: Text('${draft['created_at']}'),
                        trailing: const Icon(Icons.pending_outlined),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                ],
                _SectionTitle(
                  title: 'اطلاعات پرونده',
                  trailing: Text('${cases.length} مورد'),
                ),
                if (cases.isEmpty)
                  const _EmptyState(
                    title: 'پرونده‌ای یافت نشد.',
                    message: 'پرونده‌ای یافت نشد.',
                  ),
                ...cases.map(
                  (item) => _CaseTile(
                    item: item,
                    onTap: () => context.push('/case/${item['id']}'),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class NewCasePage extends ConsumerStatefulWidget {
  const NewCasePage({
    super.key,
    required this.riserId,
    this.returnToMap = false,
  });
  final int riserId;
  final bool returnToMap;
  @override
  ConsumerState<NewCasePage> createState() => _NewCasePageState();
}

class _NewCasePageState extends ConsumerState<NewCasePage> {
  final _description = TextEditingController();
  final _picker = ImagePicker();
  final List<XFile> _photos = [];
  String _priority = '\u{06a9}\u{0645}';
  final DateTime _date = DateTime.now();
  bool _loading = false;
  String? _clientUuid;

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickPhotos() async {
    try {
      final remaining = 20 - _photos.length;
      if (remaining <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('حداکثر ۲۰ تصویر قابل افزودن است.')),
        );
        return;
      }
      final selected = await chooseImages(
        context,
        camera: () => _picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 78,
          maxWidth: 1800,
        ),
        gallery: () => _picker.pickMultiImage(imageQuality: 78, maxWidth: 1800),
      );
      if (!mounted || selected.isEmpty) return;
      final accepted = <XFile>[];
      for (final file in selected) {
        if (_photos.any((photo) => photo.path == file.path) ||
            accepted.any((photo) => photo.path == file.path)) {
          continue;
        }
        if (await File(file.path).length() > 10 * 1024 * 1024) continue;
        accepted.add(file);
        if (accepted.length >= remaining) break;
      }
      if (mounted && accepted.isNotEmpty) {
        setState(() => _photos.addAll(accepted));
      }
      if (mounted && accepted.length < selected.length) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'برخی تصاویر تکراری، بیش از حد مجاز یا بزرگ‌تر از ۱۰ مگابایت بودند.',
            ),
          ),
        );
      }
    } on PlatformException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error.code.contains('permission')
                  ? 'اجازه دسترسی به دوربین یا تصاویر داده نشد.'
                  : 'انتخاب تصویر انجام نشد؛ دوباره تلاش کنید.',
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'خواندن تصویر انجام نشد؛ تصویر یا مجوزها را بررسی کنید.',
            ),
          ),
        );
      }
    }
  }

  Future<void> _submit() async {
    if (_loading) return;
    final session = ref.read(sessionProvider);
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    if (userId == null) return;
    setState(() => _loading = true);
    final payload = <String, dynamic>{
      'client_uuid': _clientUuid ??= const Uuid().v4(),
      'riser_id': widget.riserId,
      'priority': _priority,
      'description': _description.text.trim(),
      'identified_at': _date.toIso8601String(),
    };
    try {
      await session.api!.dio.post(
        'v1/supervisor/cases',
        data: CaseRepository(session).formData(
          payload,
          _photos.map((e) => e.path).toList(),
          'photos_before',
        ),
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('پرونده با موفقیت در سرور ثبت شد.')),
        );
        if (widget.returnToMap && context.canPop()) {
          context.pop(true);
        } else {
          context.go('/app/cases');
        }
      }
    } on DioException catch (error) {
      if (error.response == null) {
        await CaseRepository(session).savePending(
          kind: 'case',
          userId: userId,
          payload: payload,
          filePaths: _photos.map((e) => e.path).toList(),
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'پرونده ثبت نهایی نشده و برای همگام‌سازی در صف آفلاین ذخیره شد.',
              ),
            ),
          );
          if (widget.returnToMap && context.canPop()) {
            context.pop({'pending_riser_id': widget.riserId});
          } else {
            context.go('/app/cases');
          }
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ApiClient.messageFrom(error) ?? 'Request failed'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('ثبت خرابی علمک')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: ListTile(
            leading: const Icon(Icons.location_on_outlined),
            title: Text('\u0639\u0644\u0645\u06a9 ${widget.riserId}'),
            subtitle: Text(
              '\u0634\u0646\u0627\u0633\u0647 \u0639\u0644\u0645\u06a9: ${widget.riserId}',
            ),
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _priority,
          decoration: const InputDecoration(labelText: 'اولویت خرابی'),
          items: const [
            DropdownMenuItem(
              value: '\u{06a9}\u{0645}',
              child: Text('\u{06a9}\u{0645}'),
            ),
            DropdownMenuItem(
              value: '\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}',
              child: Text('\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}'),
            ),
            DropdownMenuItem(
              value: '\u{0632}\u{06cc}\u{0627}\u{062f}',
              child: Text('\u{0632}\u{06cc}\u{0627}\u{062f}'),
            ),
            DropdownMenuItem(
              value: '\u{0641}\u{0648}\u{0631}\u{06cc}',
              child: Text('\u{0641}\u{0648}\u{0631}\u{06cc}'),
            ),
          ],
          onChanged: (value) => setState(
            () =>
                _priority = value ?? '\u{0645}\u{062a}\u{0648}\u{0633}\u{0637}',
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _description,
          minLines: 3,
          maxLines: 6,
          decoration: const InputDecoration(
            labelText: 'شرح خرابی',
            alignLabelWithHint: true,
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _pickPhotos,
          icon: const Icon(Icons.add_a_photo_outlined),
          label: Text('افزودن تصویر'),
        ),
        if (_photos.isNotEmpty)
          SizedBox(
            height: 100,
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: List.generate(
                _photos.length,
                (index) => Padding(
                  padding: const EdgeInsetsDirectional.only(end: 8),
                  child: Stack(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.file(
                          File(_photos[index].path),
                          width: 92,
                          height: 92,
                          fit: BoxFit.cover,
                        ),
                      ),
                      PositionedDirectional(
                        top: 2,
                        end: 2,
                        child: IconButton.filledTonal(
                          visualDensity: VisualDensity.compact,
                          onPressed: () =>
                              setState(() => _photos.removeAt(index)),
                          icon: const Icon(Icons.close, size: 18),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        const SizedBox(height: 18),
        FilledButton.icon(
          onPressed: _loading ? null : _submit,
          icon: _loading
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.send),
          label: Text(
            _loading
                ? '\u062f\u0631 \u062d\u0627\u0644 \u062b\u0628\u062a...'
                : '\u062b\u0628\u062a \u062e\u0631\u0627\u0628\u06cc',
          ),
        ),
      ],
    ),
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
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _request = ref
        .read(sessionProvider)
        .api!
        .dio
        .get('v1/supervisor/cases/${widget.caseId}')
        .then((r) => Map<String, dynamic>.from(r.data['case'] as Map));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('پرونده‌های تعمیر')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _request,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(child: Text('دریافت اطلاعات ناموفق بود.'));
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final item = snapshot.data!;
        final operation = item['operation'] as Map?;
        final photos = List<Map<String, dynamic>>.from(
          (item['photos'] as List? ?? []).map(
            (e) => Map<String, dynamic>.from(e as Map),
          ),
        );
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _InfoBanner(text: 'اولویت خرابی'),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item['description']?.toString().isNotEmpty == true
                          ? item['description'].toString()
                          : 'بدون شرح',
                      style: const TextStyle(height: 1.6),
                    ),
                    const SizedBox(height: 8),
                    Text('تاریخ شناسایی: ${item['identified_at'] ?? '—'}'),
                    Text('گروه اجرایی'),
                    Text(
                      'ارسال به رئیس ناحیه: ${item['submitted_at'] ?? 'هنوز ارسال نشده'}',
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (photos.isNotEmpty) ...[
              const _SectionTitle(title: 'اطلاعات پرونده'),
              SizedBox(
                height: 110,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  children: photos
                      .map(
                        (photo) => Padding(
                          padding: const EdgeInsetsDirectional.only(end: 8),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: Image.network(
                              photo['url'].toString(),
                              width: 110,
                              height: 110,
                              fit: BoxFit.cover,
                              headers: {
                                'Authorization':
                                    'Bearer ${ref.read(sessionProvider).token}',
                              },
                              errorBuilder: (_, _, _) => const SizedBox(
                                width: 110,
                                child: Icon(Icons.broken_image_outlined),
                              ),
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
            ],
            if (operation == null &&
                [
                  'open',
                  'in_progress',
                  'returned',
                ].contains(item['status'])) ...[
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: () => _showCompleteForm(context),
                icon: const Icon(Icons.handyman_outlined),
                label: const Text('اطلاعات پرونده'),
              ),
            ] else if (operation != null) ...[
              const _SectionTitle(title: 'اطلاعات پرونده'),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.build_outlined),
                  title: Text(
                    operation['operation_category']?['title']?.toString() ??
                        'تاریخ تعمیر',
                  ),
                  subtitle: Text('تاریخ تعمیر'),
                ),
              ),
            ],
          ],
        );
      },
    ),
  );

  Future<void> _showCompleteForm(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => CompleteOperationSheet(
        caseId: widget.caseId,
        onSaved: () {
          Navigator.of(context).pop();
          setState(_load);
        },
      ),
    );
  }
}

class CompleteOperationSheet extends ConsumerStatefulWidget {
  const CompleteOperationSheet({
    super.key,
    required this.caseId,
    required this.onSaved,
    this.draftMode = false,
    this.operation,
    this.riserId,
  });
  final int caseId;
  final VoidCallback onSaved;
  final bool draftMode;
  final Map<String, dynamic>? operation;
  final int? riserId;
  @override
  ConsumerState<CompleteOperationSheet> createState() =>
      _CompleteOperationSheetState();
}

class _CompleteOperationSheetState
    extends ConsumerState<CompleteOperationSheet> {
  final _description = TextEditingController();
  final _picker = ImagePicker();
  final Map<int, TextEditingController> _quantities = {};
  List<Map<String, dynamic>> _materials = [];
  List<Map<String, dynamic>> _categories = [];
  bool _referencesLoading = true;
  String? _referencesError;
  final List<XFile> _photos = [];
  int? _categoryId;
  DateTime _date = DateTime.now();
  bool _busy = false;
  String? _clientUuid;

  @override
  void initState() {
    super.initState();
    _loadReferences();
  }

  Future<void> _loadReferences() async {
    if (mounted) {
      setState(() {
        _referencesLoading = true;
        _referencesError = null;
      });
    }
    try {
      final r = await ref
          .read(sessionProvider)
          .api!
          .dio
          .get('v1/supervisor/references');
      if (mounted) {
        setState(() {
          _materials = List<Map<String, dynamic>>.from(
            (r.data['materials'] as List? ?? []).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          _categories = List<Map<String, dynamic>>.from(
            (r.data['operation_categories'] as List? ?? []).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          final operation = widget.operation;
          if (operation != null) {
            _categoryId = int.tryParse('${operation['operation_category_id']}');
            _description.text = operation['description']?.toString() ?? '';
            _date =
                DateTime.tryParse(
                  '${operation['date'] ?? operation['operation_date']}',
                ) ??
                _date;
            for (final material in List<Map<String, dynamic>>.from(
              (operation['materials'] as List? ?? []).map(
                (e) => Map<String, dynamic>.from(e as Map),
              ),
            )) {
              final id = int.tryParse('${material['id']}');
              if (id != null) {
                _quantities.putIfAbsent(id, TextEditingController.new).text =
                    '${material['qty']}';
              }
            }
          }
          _referencesLoading = false;
          _referencesError = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _referencesLoading = false;
          _referencesError = 'دریافت اطلاعات مصالح و عملیات ناموفق بود.';
        });
      }
    }
  }

  Future<Map<String, dynamic>> _referencesForPicker() async {
    if (_referencesError != null ||
        (_materials.isEmpty && _referencesLoading)) {
      final response = await ref
          .read(sessionProvider)
          .api!
          .dio
          .get('v1/supervisor/references');
      return Map<String, dynamic>.from(response.data as Map);
    }
    return {'materials': _materials};
  }

  Future<void> _selectMaterials() async {
    final initial = <int, String>{
      for (final entry in _quantities.entries) entry.key: entry.value.text,
    };
    final selected = await showMaterialPicker(
      context,
      loadReferences: _referencesForPicker,
      initialQuantities: initial,
    );
    if (!mounted || selected == null) return;
    setState(() {
      for (final id in _quantities.keys.toList()) {
        if (!selected.containsKey(id)) _quantities.remove(id)?.dispose();
      }
      for (final entry in selected.entries) {
        final controller = _quantities.putIfAbsent(
          entry.key,
          TextEditingController.new,
        );
        controller.text = entry.value;
      }
    });
  }

  @override
  void dispose() {
    _description.dispose();
    for (final c in _quantities.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickPhotos() async {
    try {
      final remaining = 20 - _photos.length;
      if (remaining <= 0) return;
      final selected = await chooseImages(
        context,
        camera: () => _picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 78,
          maxWidth: 1800,
        ),
        gallery: () => _picker.pickMultiImage(imageQuality: 78, maxWidth: 1800),
      );
      if (!mounted || selected.isEmpty) return;
      final accepted = <XFile>[];
      for (final file in selected) {
        if (_photos.any((photo) => photo.path == file.path) ||
            accepted.any((photo) => photo.path == file.path)) {
          continue;
        }
        if (await File(file.path).length() > 10 * 1024 * 1024) continue;
        accepted.add(file);
        if (accepted.length >= remaining) break;
      }
      if (mounted && accepted.isNotEmpty) {
        setState(() => _photos.addAll(accepted));
      }
    } on PlatformException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error.code.contains('permission')
                  ? '\u0627\u062c\u0627\u0632\u0647 \u062f\u0633\u062a\u0631\u0633\u06cc \u0628\u0647 \u062f\u0648\u0631\u0628\u06cc\u0646 \u06cc\u0627 \u062a\u0635\u0627\u0648\u06cc\u0631 \u062f\u0627\u062f\u0647 \u0646\u0634\u062f.'
                  : '\u0627\u0646\u062a\u062e\u0627\u0628 \u062a\u0635\u0648\u06cc\u0631 \u0627\u0646\u062c\u0627\u0645 \u0646\u0634\u062f.',
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              '\u062e\u0648\u0627\u0646\u062f\u0646 \u062a\u0635\u0648\u06cc\u0631 \u0627\u0646\u062c\u0627\u0645 \u0646\u0634\u062f.',
            ),
          ),
        );
      }
    }
  }

  Future<void> _submit() async {
    if (_busy) return;
    if (_categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('نوع عملیات را انتخاب کنید.')),
      );
      return;
    }
    final session = ref.read(sessionProvider);
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    if (userId == null) return;
    final invalidQuantity = _quantities.entries.any((entry) {
      final value = entry.value.text.trim();
      final quantity = double.tryParse(_normalizeAmount(value));
      return quantity == null || !quantity.isFinite || quantity <= 0;
    });
    if (invalidQuantity) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('مقدار مصالح باید عددی بزرگ‌تر از صفر باشد.'),
        ),
      );
      return;
    }
    setState(() => _busy = true);
    final uuid = _clientUuid ??= const Uuid().v4();
    final materials = <String, String>{};
    for (final entry in _quantities.entries) {
      final qty = double.tryParse(_normalizeAmount(entry.value.text));
      if (qty != null && qty.isFinite && qty > 0) {
        materials['materials[${entry.key}]'] = _normalizeAmount(
          entry.value.text,
        );
      }
    }
    final payload = <String, dynamic>{
      'client_uuid': uuid,
      'operation_date': _date.toIso8601String().split('T').first,
      'operation_category_id': _categoryId,
      'description': _description.text.trim(),
      ...materials,
    };
    try {
      if (widget.operation == null && widget.draftMode) {
        await session.api!.dio.post(
          'v1/supervisor/cases/${widget.caseId}/operations',
          data: CaseRepository(session).formData(
            payload,
            _photos.map((e) => e.path).toList(),
            'photos_after',
          ),
        );
      } else if (widget.operation != null && widget.draftMode) {
        payload.remove('client_uuid');
        payload['version'] = widget.operation!['updated_at'];
        await session.api!.dio.put(
          'v1/supervisor/cases/${widget.caseId}/operations/${widget.operation!['id']}',
          data: CaseRepository(session).formData(
            payload,
            _photos.map((e) => e.path).toList(),
            'photos_after',
          ),
        );
      } else {
        await session.api!.dio.post(
          'v1/supervisor/cases/${widget.caseId}/operation',
          data: CaseRepository(session).formData(
            payload,
            _photos.map((e) => e.path).toList(),
            'photos_after',
          ),
        );
      }
      if (mounted) {
        final message = widget.draftMode
            ? (widget.operation == null
                  ? 'عملیات به پرونده افزوده شد.'
                  : 'تغییرات عملیات ذخیره شد.')
            : 'گزارش تعمیر برای بررسی ارسال شد.';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
        widget.onSaved();
      }
    } on DioException catch (error) {
      if (error.response == null) {
        final pendingKind = widget.draftMode
            ? (widget.operation == null
                  ? 'operation_draft'
                  : 'operation_update')
            : 'operation';
        await CaseRepository(session).savePending(
          kind: pendingKind,
          userId: userId,
          payload: {
            'case_id': widget.caseId,
            if (widget.riserId != null) 'riser_id': widget.riserId,
            if (widget.operation != null)
              'operation_id': widget.operation!['id'],
            ...payload,
          },
          filePaths: _photos.map((e) => e.path).toList(),
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'ارتباط برقرار نشد؛ عملیات و تصاویر در صف آفلاین ذخیره شدند.',
              ),
            ),
          );
          widget.onSaved();
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(ApiClient.messageFrom(error) ?? 'Request failed'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      18,
      8,
      18,
      MediaQuery.viewInsetsOf(context).bottom + 20,
    ),
    child: SizedBox(
      height: MediaQuery.sizeOf(context).height * .82,
      child: ListView(
        children: [
          Text(
            'اطلاعات پرونده',
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: () async {
              final date = await showDatePicker(
                context: context,
                initialDate: _date,
                firstDate: DateTime(2020),
                lastDate: DateTime.now(),
              );
              if (date != null) setState(() => _date = date);
            },
            icon: const Icon(Icons.calendar_today),
            label: Text('همگام‌سازی'),
          ),
          DropdownButtonFormField<int?>(
            initialValue: _categoryId,
            decoration: const InputDecoration(labelText: 'نوع عملیات'),
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('نوع عملیات'),
              ),
              ..._categories.map(
                (c) => DropdownMenuItem<int?>(
                  value: c['id'] as int,
                  child: Text(c['title'].toString()),
                ),
              ),
            ],
            onChanged: (value) => setState(() => _categoryId = value),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            minLines: 2,
            maxLines: 4,
            decoration: const InputDecoration(labelText: 'شرح خرابی یا تعمیر'),
          ),
          const SizedBox(height: 16),
          _SectionTitle(title: 'مصالح مصرف‌شده (${_quantities.length})'),
          if (_referencesLoading) const LinearProgressIndicator(),
          if (_referencesError != null)
            Row(
              children: [
                Expanded(
                  child: Text(
                    _referencesError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _loadReferences,
                  child: const Text('تلاش دوباره'),
                ),
              ],
            ),
          OutlinedButton.icon(
            key: const Key('open-material-picker'),
            onPressed: _referencesLoading || _referencesError != null
                ? null
                : _selectMaterials,
            icon: const Icon(Icons.playlist_add),
            label: const Text('انتخاب مصالح'),
          ),
          if (_quantities.isEmpty &&
              !_referencesLoading &&
              _referencesError == null)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('هنوز مصالحی برای این عملیات انتخاب نشده است.'),
            ),
          ..._quantities.entries.map((entry) {
            final id = entry.key;
            final material = _materials
                .where((item) => int.tryParse('${item['id']}') == id)
                .firstOrNull;
            final title = material?['title']?.toString() ?? 'مصالح #$id';
            final unit = material?['unit']?.toString() ?? '';
            return Card(
              key: Key('selected-material-$id'),
              margin: const EdgeInsets.symmetric(vertical: 4),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          if (unit.isNotEmpty)
                            Text(
                              'واحد: $unit',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                        ],
                      ),
                    ),
                    SizedBox(
                      width: 112,
                      child: TextField(
                        controller: entry.value,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: 'مقدار',
                          suffixText: unit.isEmpty ? null : unit,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'حذف مصالح',
                      onPressed: () =>
                          setState(() => _quantities.remove(id)?.dispose()),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
            );
          }),
          OutlinedButton.icon(
            onPressed: _pickPhotos,
            icon: const Icon(Icons.add_a_photo_outlined),
            label: const Text(
              '\u0627\u0641\u0632\u0648\u062f\u0646 \u062a\u0635\u0648\u06cc\u0631',
            ),
          ),
          if (_photos.isNotEmpty)
            SizedBox(
              height: 90,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: List.generate(
                  _photos.length,
                  (index) => Padding(
                    padding: const EdgeInsetsDirectional.only(end: 8),
                    child: Stack(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Image.file(
                            File(_photos[index].path),
                            width: 88,
                            height: 88,
                            fit: BoxFit.cover,
                          ),
                        ),
                        PositionedDirectional(
                          top: 0,
                          end: 0,
                          child: IconButton.filledTonal(
                            visualDensity: VisualDensity.compact,
                            onPressed: () =>
                                setState(() => _photos.removeAt(index)),
                            icon: const Icon(Icons.close, size: 18),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _busy ? null : _submit,
            icon: const Icon(Icons.send),
            label: Text(
              _busy
                  ? 'در حال ارسال…'
                  : widget.draftMode
                  ? (widget.operation == null ? 'ثبت عملیات' : 'ذخیره تغییرات')
                  : 'ارسال برای بررسی',
            ),
          ),
        ],
      ),
    ),
  );
}

class _CaseTile extends StatelessWidget {
  const _CaseTile({required this.item, required this.onTap});
  final Map<String, dynamic> item;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 9),
    child: ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        backgroundColor: _statusColor(item['status']).withValues(alpha: .14),
        child: Icon(Icons.build_outlined, color: _statusColor(item['status'])),
      ),
      title: Text(
        item['riser']?['r_giscode']?.toString() ?? 'اطلاعات علمک و محدوده مجاز',
      ),
      subtitle: Text(
        '${_statusLabel(item['status'])}  •  ${item['identified_at'] ?? ''}',
        maxLines: 2,
      ),
      trailing: const Icon(Icons.chevron_left),
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, this.trailing});
  final String title;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
        ?trailing,
      ],
    ),
  );
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.secondaryContainer,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(text, style: const TextStyle(height: 1.5)),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.title, required this.message});
  final String title;
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 36),
    child: Column(
      children: [
        const Icon(Icons.inbox_outlined, size: 38, color: Colors.black38),
        const SizedBox(height: 8),
        Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.black54),
        ),
      ],
    ),
  );
}

String _statusLabel(Object? value) => switch (value) {
  'open' => 'باز',
  'in_progress' => 'در حال انجام',
  'submitted' => 'ارسال‌شده برای بررسی',
  'returned' => 'برگشتی',
  'completed' => 'برگشتی',
  _ => 'نامشخص',
};

String _normalizeAmount(String value) => value
    .replaceAll('۰', '0')
    .replaceAll('۱', '1')
    .replaceAll('۲', '2')
    .replaceAll('۳', '3')
    .replaceAll('۴', '4')
    .replaceAll('۵', '5')
    .replaceAll('۶', '6')
    .replaceAll('۷', '7')
    .replaceAll('۸', '8')
    .replaceAll('۹', '9')
    .replaceAll('٠', '0')
    .replaceAll('١', '1')
    .replaceAll('٢', '2')
    .replaceAll('٣', '3')
    .replaceAll('٤', '4')
    .replaceAll('٥', '5')
    .replaceAll('٦', '6')
    .replaceAll('٧', '7')
    .replaceAll('٨', '8')
    .replaceAll('٩', '9')
    .replaceAll('٫', '.')
    .replaceAll(',', '.');

Color _statusColor(Object? value) => switch (value) {
  'open' => const Color(0xffb7791f),
  'in_progress' => const Color(0xff2563eb),
  'submitted' => const Color(0xff6955b5),
  'returned' => const Color(0xffc34747),
  'completed' => const Color(0xff18856e),
  _ => const Color(0xff607d8b),
};
