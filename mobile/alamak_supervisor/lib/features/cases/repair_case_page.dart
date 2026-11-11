import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:uuid/uuid.dart';

import '../../core/data/api_client.dart';
import '../auth/session_controller.dart';
import 'case_repository.dart';
import 'image_source_sheet.dart';
import 'material_picker_sheet.dart';
import 'operation_draft.dart';

class RepairCasePage extends ConsumerStatefulWidget {
  const RepairCasePage({super.key, required this.caseId});
  final int caseId;

  @override
  ConsumerState<RepairCasePage> createState() => _RepairCasePageState();
}

class _RepairCasePageState extends ConsumerState<RepairCasePage> {
  final _picker = ImagePicker();
  Future<void>? _loading;
  Map<String, dynamic>? _case;
  List<OperationDraft> _operations = [];
  final List<int> _deletedOperationIds = [];
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _materials = [];
  List<Map<String, dynamic>> _photos = [];
  XFile? _beforePhoto;
  XFile? _afterPhoto;
  int _activeIndex = 0;
  bool _dirty = false;
  bool _pendingLocal = false;
  bool _busy = false;
  String? _referencesError;

  @override
  void initState() {
    super.initState();
    _loading = _load();
  }

  Future<void> _load() async {
    final session = ref.read(sessionProvider);
    try {
      final responses = await Future.wait([
        session.api!.dio.get('v1/supervisor/cases/${widget.caseId}'),
        session.api!.dio.get('v1/supervisor/references'),
      ]);
      final item = Map<String, dynamic>.from(responses[0].data['case'] as Map);
      final references = Map<String, dynamic>.from(responses[1].data as Map);
      if (!mounted) return;
      setState(() {
        _case = item;
        _operations = List<Map<String, dynamic>>.from(
          (item['operations'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
        ).map(OperationDraft.fromJson).toList();
        _photos = List<Map<String, dynamic>>.from(
          (item['photos'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
        );
        _categories = List<Map<String, dynamic>>.from(
          (references['operation_categories'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
        );
        _materials = List<Map<String, dynamic>>.from(
          (references['materials'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
        );
        _dirty = false;
        _pendingLocal = false;
        _referencesError = null;
      });
      final pending = await CaseRepository(session).pending();
      final hasPending = pending.any((row) {
        if (row['kind'] != 'operations_batch') return false;
        final payload = row['payload'];
        if (payload is! String) return false;
        final decoded = Map<String, dynamic>.from(jsonDecode(payload) as Map);
        return '${decoded['case_id']}' == '${widget.caseId}';
      });
      if (mounted) setState(() => _pendingLocal = hasPending);
    } catch (error) {
      if (mounted) setState(() => _referencesError = ApiClient.messageFrom(error) ?? 'دریافت پرونده انجام نشد.');
      rethrow;
    }
  }

  bool get _locked => _case?['status'] == 'submitted' || _case?['status'] == 'completed';
  bool get _canEdit => !_locked && ['open', 'in_progress', 'returned'].contains(_case?['status']);

  void _markDirty() {
    if (!_dirty) setState(() => _dirty = true);
  }

  void _addOperation() {
    if (!_canEdit) return;
    setState(() {
      _operations.add(OperationDraft(clientUuid: const Uuid().v4()));
      _activeIndex = _operations.length - 1;
      _dirty = true;
    });
  }

  Future<void> _removeOperation(int index) async {
    if (!_canEdit) return;
    final operation = _operations[index];
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('حذف عملیات؟'),
        content: Text(operation.isPersisted ? 'این عملیات به‌صورت منطقی حذف می‌شود.' : 'این عملیات جدید از فرم حذف می‌شود.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('حذف')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      if (operation.id != null) _deletedOperationIds.add(operation.id!);
      _operations.removeAt(index);
      _activeIndex = _operations.isEmpty ? 0 : _activeIndex.clamp(0, _operations.length - 1);
      _dirty = true;
    });
  }

  Future<Map<String, dynamic>> _referencesForPicker() async => {'materials': _materials};

  Future<void> _selectMaterials(OperationDraft operation) async {
    final initial = {for (final item in operation.materials.values) item.id: item.quantity};
    final selected = await showMaterialPicker(context, loadReferences: _referencesForPicker, initialQuantities: initial);
    if (!mounted || selected == null) return;
    setState(() {
      operation.materials.removeWhere((id, _) => !selected.containsKey(id));
      for (final entry in selected.entries) {
        final reference = _materials.firstWhere(
          (material) => int.tryParse('${material['id']}') == entry.key,
          orElse: () => <String, dynamic>{},
        );
        operation.materials[entry.key] = MaterialDraft(
          id: entry.key,
          title: '${reference['title'] ?? 'مصالح #${entry.key}'}',
          unit: '${reference['unit'] ?? ''}',
          quantity: entry.value,
        );
      }
      _dirty = true;
    });
  }

  Future<void> _pickCasePhoto(String type) async {
    try {
      final selected = await chooseImages(
        context,
        camera: () => _picker.pickImage(source: ImageSource.camera, imageQuality: 82, maxWidth: 1800),
        gallery: () => _picker.pickMultiImage(imageQuality: 82, maxWidth: 1800),
      );
      if (!mounted || selected.isEmpty) return;
      setState(() {
        if (type == 'before') {
          _beforePhoto = selected.first;
        } else {
          _afterPhoto = selected.first;
        }
        _dirty = true;
      });
    } catch (_) {
      if (mounted) _showMessage('انتخاب تصویر انجام نشد.');
    }
  }

  Future<void> _save() async {
    if (_busy || !_canEdit) return;
    if (_operations.isEmpty && _deletedOperationIds.isEmpty) {
      _showMessage('حداقل یک عملیات معتبر اضافه کنید.');
      return;
    }
    for (final operation in _operations) {
      final error = operation.validate();
      if (error != null) {
        _showMessage(error);
        return;
      }
    }
    setState(() => _busy = true);
    try {
      final result = await CaseRepository(ref.read(sessionProvider)).saveOperations(
        caseId: widget.caseId,
        operations: _operations,
        deletedOperationIds: _deletedOperationIds,
        beforePhotoPath: _beforePhoto?.path,
        afterPhotoPath: _afterPhoto?.path,
      );
      if (!mounted) return;
      setState(() {
        _dirty = false;
        _pendingLocal = !result.serverSaved;
        _beforePhoto = null;
        _afterPhoto = null;
        if (result.serverSaved) _deletedOperationIds.clear();
      });
      _showMessage(result.serverSaved ? 'همه عملیات با موفقیت ذخیره شد.' : 'تغییرات فقط به‌صورت Pending Local ذخیره شد و هنوز روی سرور قطعی نیست.');
      if (result.serverSaved) await _reloadAfterSave();
    } on DioException catch (error) {
      if (mounted) _showMessage(_serverValidationMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reloadAfterSave() async {
    try {
      await _load();
    } catch (_) {
      // The save already succeeded; keep the editor state visible.
    }
  }

  Future<void> _submitForReview() async {
    if (_busy || !_canEdit) return;
    if (_dirty || _pendingLocal) {
      _showMessage('ابتدا تغییرات را در حالت آنلاین ذخیره کنید.');
      return;
    }
    if (_operations.isEmpty) {
      _showMessage('برای ارسال، پرونده باید حداقل یک عملیات داشته باشد.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(sessionProvider).api!.dio.post('v1/supervisor/cases/${widget.caseId}/submit');
      if (mounted) {
        _showMessage('پرونده برای تأیید رئیس ارسال شد.');
        await _reloadAfterSave();
      }
    } on DioException catch (error) {
      if (mounted) _showMessage(ApiClient.messageFrom(error) ?? 'ارسال پرونده انجام نشد.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showMessage(String message) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));

  String _serverValidationMessage(DioException error) {
    final response = error.response;
    final errors = response?.data is Map ? response!.data['errors'] : null;
    if (response?.statusCode == 422 && errors is Map && errors.isNotEmpty) {
      final firstKey = errors.keys.first.toString();
      final value = errors[firstKey];
      final detail = value is List && value.isNotEmpty ? value.first.toString() : value?.toString();
      final match = RegExp(r'operations\.(\d+)(?:\.materials\.(\d+))?').firstMatch(firstKey);
      if (match != null) {
        final operation = int.parse(match.group(1)!) + 1;
        final material = match.group(2) == null ? null : int.parse(match.group(2)!) + 1;
        return material == null
            ? 'عملیات $operation: ${detail ?? 'مقدار نامعتبر است.'}'
            : 'عملیات $operation، مصالح $material: ${detail ?? 'مقدار نامعتبر است.'}';
      }
      return detail ?? 'اطلاعات عملیات معتبر نیست.';
    }
    if (response?.statusCode == 409) return 'پرونده یا عملیات در جای دیگری تغییر کرده است؛ ابتدا تازه‌سازی کنید.';
    return ApiClient.messageFrom(error) ?? 'ذخیره عملیات انجام نشد.';
  }

  @override
  Widget build(BuildContext context) => Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          appBar: AppBar(
            title: Text('تعمیرات علمک ${_case?['riser']?['r_giscode'] ?? widget.caseId}'),
            actions: [IconButton(onPressed: () => setState(() => _loading = _load()), icon: const Icon(Icons.refresh))],
          ),
          body: FutureBuilder<void>(
            future: _loading,
            builder: (context, snapshot) {
              if (snapshot.hasError && _case == null) return _errorView(snapshot.error);
              if (_case == null) return const Center(child: CircularProgressIndicator());
              return _editor();
            },
          ),
          bottomNavigationBar: _case == null ? null : _actionBar(),
        ),
      );

  Widget _editor() {
    final status = '${_case!['status'] ?? ''}';
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 128),
      children: [
        if (_pendingLocal) const _InfoBanner('تغییرات در صف آفلاین هستند؛ ذخیره سرور هنوز تأیید نشده است.'),
        if (status == 'returned') _InfoBanner('پرونده برای اصلاح برگشته است: ${_case!['return_reason'] ?? _case!['returned_reason'] ?? 'دلیل برگشت در پرونده ثبت شده است.'}'),
        _headerCard(),
        const SizedBox(height: 12),
        _casePhotosCard(),
        const SizedBox(height: 18),
        Row(children: [const Expanded(child: Text('عملیات تعمیراتی', style: TextStyle(fontSize: 19, fontWeight: FontWeight.bold))), Chip(label: Text('${_operations.length} عملیات'))]),
        const SizedBox(height: 8),
        if (_operations.isEmpty) const _EmptyCard(text: 'هنوز عملیاتی برای این پرونده ثبت نشده است.'),
        for (var index = 0; index < _operations.length; index++) _operationCard(index, _operations[index]),
        const SizedBox(height: 10),
        if (_canEdit) OutlinedButton.icon(onPressed: _addOperation, icon: const Icon(Icons.add_circle_outline), label: const Text('افزودن عملیات جدید')),
        if (_referencesError != null) _InfoBanner(_referencesError!),
      ],
    );
  }

  Widget _headerCard() => Card(child: Padding(padding: const EdgeInsets.all(16), child: Wrap(spacing: 10, runSpacing: 8, children: [
        Text('کد علمک: ${_case!['riser']?['r_giscode'] ?? _case!['riser_id'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.bold)),
        Chip(label: Text(_status(_case!['status']))),
        Chip(avatar: const Icon(Icons.inventory_2_outlined, size: 18), label: Text('${_operations.fold<int>(0, (sum, op) => sum + op.materials.length)} مصالح')),
        Chip(avatar: Icon(ref.watch(sessionProvider).offline ? Icons.cloud_off : Icons.cloud_done, size: 18), label: Text(ref.watch(sessionProvider).offline ? 'آفلاین' : 'متصل')),
      ])));

  Widget _casePhotosCard() {
    final before = _photos.where((photo) => photo['type'] == 'before').firstOrNull;
    final after = _photos.where((photo) => photo['type'] == 'after').firstOrNull;
    return Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('تصاویر پرونده', style: TextStyle(fontWeight: FontWeight.bold)),
      const SizedBox(height: 10),
      Row(children: [Expanded(child: _casePhotoTile('before', 'قبل از تعمیر', before, _beforePhoto)), const SizedBox(width: 10), Expanded(child: _casePhotoTile('after', 'بعد از تعمیر', after, _afterPhoto))]),
    ])));
  }

  Widget _casePhotoTile(String type, String title, Map<String, dynamic>? existing, XFile? local) => InkWell(
        onTap: _canEdit ? () => _pickCasePhoto(type) : null,
        borderRadius: BorderRadius.circular(14),
        child: Container(height: 120, decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(14)), clipBehavior: Clip.antiAlias, child: Stack(fit: StackFit.expand, children: [
          if (local != null) Image.file(File(local.path), fit: BoxFit.cover)
          else if (existing?['url'] != null) Image.network('${existing!['url']}', fit: BoxFit.cover, headers: _authHeaders(), errorBuilder: (_, _, _) => const Icon(Icons.broken_image_outlined))
          else const Center(child: Icon(Icons.photo_outlined, size: 34)),
          Positioned(bottom: 0, left: 0, right: 0, child: ColoredBox(color: Colors.black54, child: Padding(padding: const EdgeInsets.all(6), child: Text(title, style: const TextStyle(color: Colors.white))))),
        ])));

  Widget _operationCard(int index, OperationDraft operation) {
    final category = _categories.firstWhere((item) => int.tryParse('${item['id']}') == operation.categoryId, orElse: () => <String, dynamic>{});
    final validation = operation.validate();
    final active = _activeIndex == index;
    return Card(margin: const EdgeInsets.only(bottom: 10), color: active ? Theme.of(context).colorScheme.primaryContainer.withValues(alpha: .45) : null, child: Column(children: [
      InkWell(onTap: () => setState(() => _activeIndex = index), borderRadius: BorderRadius.circular(18), child: Padding(padding: const EdgeInsets.fromLTRB(14, 12, 14, 8), child: Row(children: [
        CircleAvatar(child: Text('${index + 1}')), const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(category['title']?.toString() ?? (operation.categoryId == null ? 'نوع عملیات انتخاب نشده' : 'عملیات ${operation.categoryId}'), style: const TextStyle(fontWeight: FontWeight.bold)), Text('${operation.materials.length} مصالح • ${_status(operation.status)}')])),
        if (validation != null) Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error),
        if (_canEdit) IconButton(tooltip: 'حذف عملیات', onPressed: () => _removeOperation(index), icon: const Icon(Icons.delete_outline)),
      ]))),
      if (active) _operationDetails(operation),
    ]));
  }

  Widget _operationDetails(OperationDraft operation) => Padding(padding: const EdgeInsets.fromLTRB(14, 0, 14, 14), child: Column(children: [
    DropdownButtonFormField<int>(initialValue: operation.categoryId, decoration: const InputDecoration(labelText: 'نوع عملیات'), items: _categories.map((item) => DropdownMenuItem(value: int.tryParse('${item['id']}'), child: Text('${item['title']}'))).toList(), onChanged: _canEdit ? (value) { setState(() { operation.categoryId = value; _dirty = true; }); } : null),
    const SizedBox(height: 10),
    TextFormField(initialValue: operation.description, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'توضیحات'), onChanged: _canEdit ? (value) { operation.description = value; _markDirty(); } : null),
    const SizedBox(height: 10),
    Align(alignment: AlignmentDirectional.centerStart, child: OutlinedButton.icon(onPressed: _canEdit ? () => _selectMaterials(operation) : null, icon: const Icon(Icons.playlist_add), label: Text('انتخاب مصالح (${operation.materials.length})'))),
    for (final material in operation.materials.values) _materialRow(operation, material),
    if (operation.validate() != null) Align(alignment: AlignmentDirectional.centerStart, child: Padding(padding: const EdgeInsets.only(top: 8), child: Text(operation.validate()!, style: TextStyle(color: Theme.of(context).colorScheme.error)))),
  ]));

  Widget _materialRow(OperationDraft operation, MaterialDraft material) => Card(key: ValueKey('${operation.clientUuid}-${material.id}'), color: Theme.of(context).colorScheme.surface, child: Padding(padding: const EdgeInsets.all(8), child: Row(children: [
    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(material.title, style: const TextStyle(fontWeight: FontWeight.w600)), if (material.unit.isNotEmpty) Text('واحد: ${material.unit}', style: Theme.of(context).textTheme.bodySmall)])),
    SizedBox(width: 110, child: TextFormField(initialValue: material.quantity, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'مقدار', suffixText: material.unit.isEmpty ? null : material.unit), onChanged: _canEdit ? (value) { material.quantity = value; _markDirty(); } : null)),
    if (_canEdit) IconButton(onPressed: () => setState(() { operation.materials.remove(material.id); _dirty = true; }), icon: const Icon(Icons.close)),
  ])));

  Widget _actionBar() => SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 10), child: Row(children: [
    Expanded(child: FilledButton.icon(onPressed: _canEdit && !_busy ? _save : null, icon: const Icon(Icons.save_outlined), label: const Text('ذخیره تعمیرات'))), const SizedBox(width: 10),
    Expanded(child: OutlinedButton.icon(onPressed: _canEdit && !_busy ? _submitForReview : null, icon: const Icon(Icons.send_outlined), label: const Text('ارسال برای تأیید رئیس'))),
  ])));

  Widget _errorView(Object? error) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(ApiClient.messageFrom(error) ?? 'دریافت پرونده انجام نشد.'), const SizedBox(height: 12), FilledButton(onPressed: () => setState(() => _loading = _load()), child: const Text('تلاش مجدد'))])));

  Map<String, String> _authHeaders() => {if (ref.read(sessionProvider).token != null) 'Authorization': 'Bearer ${ref.read(sessionProvider).token}'};
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Container(margin: const EdgeInsets.only(bottom: 12), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Theme.of(context).colorScheme.secondaryContainer, borderRadius: BorderRadius.circular(14)), child: Text(text, style: const TextStyle(height: 1.4)));
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(text)));
}

String _status(Object? value) => switch ('$value') {
      'open' => 'باز',
      'in_progress' => 'در حال تعمیر',
      'submitted' => 'در انتظار تأیید رئیس',
      'returned' => 'برگشتی برای اصلاح',
      'completed' => 'تکمیل‌شده',
      'need_revision' => 'نیازمند اصلاح',
      'draft' => 'پیش‌نویس',
      _ => '${value ?? 'نامشخص'}',
    };
