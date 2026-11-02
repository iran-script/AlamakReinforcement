import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/data/api_client.dart';
import '../auth/session_controller.dart';
import 'case_repository.dart';
import 'case_pages.dart' show CompleteOperationSheet;
import 'case_material_totals.dart';

class RepairCasePage extends ConsumerStatefulWidget {
  const RepairCasePage({super.key, required this.caseId});
  final int caseId;

  @override
  ConsumerState<RepairCasePage> createState() => _RepairCasePageState();
}

class _RepairCasePageState extends ConsumerState<RepairCasePage> {
  Future<Map<String, dynamic>>? _request;
  Map<String, dynamic>? _cachedCase;
  List<Map<String, dynamic>> _pendingLocal = [];
  bool _offlineView = false;
  bool _submitting = false;

  @override
  void initState() { super.initState(); _load(); }

  void _load() {
    _request = _loadDetails();
  }

  Future<Map<String, dynamic>> _loadDetails() async {
    try {
      final response = await ref.read(sessionProvider).api!.dio.get('v1/supervisor/cases/${widget.caseId}');
      final item = Map<String, dynamic>.from(response.data['case'] as Map);
      _cachedCase = item;
      _offlineView = false;
      await _loadLocalDrafts();
      return item;
    } on DioException {
      if (_cachedCase == null) rethrow;
      _offlineView = true;
      await _loadLocalDrafts();
      return _cachedCase!;
    }
  }

  Future<void> _loadLocalDrafts() async {
    final rows = await CaseRepository(ref.read(sessionProvider)).pending();
    final pending = <Map<String, dynamic>>[];
    for (final row in rows) {
      if (!['operation_draft', 'operation_update'].contains(row['kind'])) continue;
      final payload = jsonDecode(row['payload']! as String) as Map;
      if ('${payload['case_id']}' == '${widget.caseId}') pending.add(Map<String, dynamic>.from(payload));
    }
    if (mounted) setState(() => _pendingLocal = pending);
  }

  Future<void> _submitForReview() async {
    if (_submitting) return;
    setState(() => _submitting = true);
    try {
      await ref.read(sessionProvider).api!.dio.post('v1/supervisor/cases/${widget.caseId}/submit');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('\u067e\u0631\u0648\u0646\u062f\u0647 \u0628\u0631\u0627\u06cc \u0628\u0631\u0631\u0633\u06cc \u0627\u0631\u0633\u0627\u0644 \u0634\u062f.')));
        setState(_load);
      }
    } on DioException catch (error) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiClient.messageFrom(error) ?? '\u0627\u0631\u0633\u0627\u0644 \u067e\u0631\u0648\u0646\u062f\u0647 \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.')));
    } finally { if (mounted) setState(() => _submitting = false); }
  }

  Future<void> _editOperation(Map<String, dynamic> operation) async {
    await showModalBottomSheet<void>(
      context: context, isScrollControlled: true, showDragHandle: true,
      builder: (sheetContext) => CompleteOperationSheet(
        caseId: widget.caseId, riserId: int.tryParse('${_cachedCase?['riser_id'] ?? ''}'), operation: operation, draftMode: true,
        onSaved: () { Navigator.pop(sheetContext); setState(_load); },
      ),
    );
  }

  Future<void> _addOperation() async {
    await showModalBottomSheet<void>(
      context: context, isScrollControlled: true, showDragHandle: true,
      builder: (sheetContext) => CompleteOperationSheet(
        caseId: widget.caseId, riserId: int.tryParse('${_cachedCase?['riser_id'] ?? ''}'), draftMode: true,
        onSaved: () { Navigator.pop(sheetContext); setState(_load); },
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Directionality(
    textDirection: TextDirection.rtl,
    child: Scaffold(
      appBar: AppBar(title: const Text('\u062a\u0639\u0645\u06cc\u0631\u0627\u062a \u0639\u0644\u0645\u06a9')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _request,
        builder: (context, snapshot) {
          if (snapshot.hasError) return _failure(snapshot.error);
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final item = snapshot.data!;
          final operations = List<Map<String, dynamic>>.from((item['operations'] as List? ?? const []).map((e) => Map<String, dynamic>.from(e as Map)));
          final photos = List<Map<String, dynamic>>.from((item['photos'] as List? ?? const []).map((e) => Map<String, dynamic>.from(e as Map)));
          final beforePhotos = photos.where((photo) => photo['type'] == 'before').toList();
          final canEdit = ['open', 'in_progress', 'returned'].contains(item['status']);
          return ListView(padding: const EdgeInsets.all(16), children: [
            if (_offlineView) const _InfoBanner('\u0627\u062a\u0635\u0627\u0644 \u0642\u0637\u0639 \u0627\u0633\u062a\u061b \u062f\u0627\u062f\u0647\u200c\u0647\u0627\u06cc \u0646\u0645\u0627\u06cc\u0634\u06cc \u0622\u062e\u0631\u06cc\u0646 \u062f\u0631\u06cc\u0627\u0641\u062a \u0634\u062f\u0647\u200c\u0627\u0646\u062f.'),
            ..._pendingLocal.map((draft) => Card(color: Colors.amber.shade50, child: ListTile(leading: const Icon(Icons.cloud_upload_outlined, color: Colors.orange), title: const Text('\u0639\u0645\u0644\u06cc\u0627\u062a \u062f\u0631 \u0635\u0641 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc'), subtitle: Text(draft['description']?.toString() ?? '\u062b\u0628\u062a \u0645\u062d\u0644\u06cc \u062a\u0627 \u0648\u0635\u0648\u0644 \u0627\u062a\u0635\u0627\u0644 \u062a\u0623\u06cc\u06cc\u062f \u0646\u0645\u06cc\u200c\u0634\u0648\u062f.')))),
            Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(item['riser']?['r_giscode']?.toString() ?? '\u0639\u0644\u0645\u06a9 ${item['riser_id'] ?? ''}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
              Text('\u0634\u0646\u0627\u0633\u0647 \u0639\u0644\u0645\u06a9: ${item['riser_id'] ?? ''}'),
              const SizedBox(height: 8),
              Chip(label: Text(_status(item['status'])), avatar: const Icon(Icons.assignment_outlined)),
              Text('\u0627\u0648\u0644\u0648\u06cc\u062a: ${item['priority'] ?? '\u0646\u0627\u0645\u0634\u062e\u0635'}'),
              Text(item['description']?.toString().isNotEmpty == true ? item['description'].toString() : '\u0634\u0631\u062d\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.'),
              if (item['identified_at'] != null) Text('\u062a\u0627\u0631\u06cc\u062e \u062b\u0628\u062a: ${item['identified_at']}'),
              if (item['submitted_at'] != null) Text('\u062a\u0627\u0631\u06cc\u062e \u0627\u0631\u0633\u0627\u0644: ${item['submitted_at']}'),
              if (item['closed_at'] != null) Text('\u062a\u0627\u0631\u06cc\u062e \u062a\u0623\u06cc\u06cc\u062f\u200c\u0646\u0647\u0627\u06cc\u06cc: ${item['closed_at']}'),
              if (item['reporter']?['name'] != null) Text('\u062b\u0628\u062a\u200c\u06a9\u0646\u0646\u062f\u0647: ${item['reporter']['name']}'),
              if (item['assigned_group']?['name'] != null) Text('\u06af\u0631\u0648\u0647 \u0645\u0633\u0626\u0648\u0644: ${item['assigned_group']['name']}'),
            ]))),
            if (beforePhotos.isNotEmpty) ...[
              const _SectionTitle('عکس‌های قبل از تعمیر'),
              _PhotoGallery(photos: beforePhotos, token: ref.watch(sessionProvider).token),
            ],
            const _SectionTitle('عملیات تعمیراتی'),
            if (operations.isEmpty) Card(child: ListTile(leading: const Icon(Icons.info_outline), title: const Text('\u0647\u0646\u0648\u0632 \u0639\u0645\u0644\u06cc\u0627\u062a\u06cc \u0628\u0631\u0627\u06cc \u0627\u06cc\u0646 \u067e\u0631\u0648\u0646\u062f\u0647 \u062b\u0628\u062a \u0646\u0634\u062f\u0647.'))),
            ...operations.map((operation) => Card(child: ExpansionTile(
              title: Text(operation['category']?.toString() ?? operation['operation_category']?['title']?.toString() ?? '\u0639\u0645\u0644\u06cc\u0627\u062a ${operation['id']}'),
              subtitle: Text('${_status(operation['status'])}  •  ${operation['date'] ?? operation['operation_date'] ?? ''}'),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              children: [
                Align(alignment: AlignmentDirectional.centerStart, child: Text(operation['description']?.toString() ?? '')),
                if (operation['created_by'] != null) Align(alignment: AlignmentDirectional.centerStart, child: Text('\u062b\u0628\u062a\u200c\u06a9\u0646\u0646\u062f\u0647 \u0639\u0645\u0644\u06cc\u0627\u062a: ${operation['created_by']}')),
                for (final reviewer in List<Map<String, dynamic>>.from((operation['reviewers'] as List? ?? const []).map((e) => Map<String, dynamic>.from(e as Map))))
                  ListTile(dense: true, leading: const Icon(Icons.fact_check_outlined), title: Text('${reviewer['name']} — ${_status(reviewer['status'])}'), subtitle: Text('${reviewer['comment'] ?? ''} ${reviewer['approved_at'] ?? ''}')),
                for (final material in List<Map<String, dynamic>>.from((operation['materials'] as List? ?? const []).map((e) => Map<String, dynamic>.from(e as Map))))
                  ListTile(dense: true, title: Text(material['title'].toString()), trailing: Text('${material['qty']} ${material['unit'] ?? ''}')),
                if ((operation['photos'] as List? ?? const []).isNotEmpty)
                  _PhotoGallery(photos: List<Map<String, dynamic>>.from((operation['photos'] as List).map((e) => Map<String, dynamic>.from(e as Map))), token: ref.watch(sessionProvider).token),
                if (canEdit && ['draft', 'need_revision'].contains(operation['status']))
                  Align(alignment: AlignmentDirectional.centerEnd, child: TextButton.icon(onPressed: () => _editOperation(operation), icon: const Icon(Icons.edit_outlined), label: const Text('\u0648\u06cc\u0631\u0627\u06cc\u0634 \u0639\u0645\u0644\u06cc\u0627\u062a'))),
              ],
            ))),
            if (operations.isNotEmpty) _MaterialSummary(operations: operations),
            if (canEdit) ...[
              const SizedBox(height: 10),
              OutlinedButton.icon(onPressed: _addOperation, icon: const Icon(Icons.add), label: const Text('\u0627\u0641\u0632\u0648\u062f\u0646 \u0639\u0645\u0644\u06cc\u0627\u062a')),
              if (operations.isNotEmpty) FilledButton.icon(onPressed: _submitting ? null : _submitForReview, icon: _submitting ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send), label: Text(_submitting ? '\u062f\u0631 \u062d\u0627\u0644 \u0627\u0631\u0633\u0627\u0644...' : '\u0627\u0631\u0633\u0627\u0644 \u0628\u0631\u0627\u06cc \u062a\u0623\u06cc\u06cc\u062f')),
            ],
            if (item['last_editor']?['name'] != null) Text('\u0622\u062e\u0631\u06cc\u0646 \u0648\u06cc\u0631\u0627\u06cc\u0634: ${item['last_editor']['name']}'),
          ]);
        },
      ),
    ),
  );

  Widget _failure(Object? error) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Text(error is DioException ? ApiClient.messageFrom(error) ?? '\u062f\u0631\u06cc\u0627\u0641\u062a \u067e\u0631\u0648\u0646\u062f\u0647 \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.' : '\u062f\u0631\u06cc\u0627\u0641\u062a \u067e\u0631\u0648\u0646\u062f\u0647 \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.'),
    const SizedBox(height: 10),
    FilledButton(onPressed: () => setState(_load), child: const Text('\u062a\u0644\u0627\u0634 \u0645\u062c\u062f\u062f')),
  ]));
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 10), child: Text(text, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)));
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Container(margin: const EdgeInsets.only(bottom: 12), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Theme.of(context).colorScheme.secondaryContainer, borderRadius: BorderRadius.circular(12)), child: Text(text, style: const TextStyle(height: 1.4)));
}

class _MaterialSummary extends StatelessWidget {
  const _MaterialSummary({required this.operations});
  final List<Map<String, dynamic>> operations;
  @override
  Widget build(BuildContext context) {
    final totals = aggregateCaseMaterials(operations);
    return Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('\u062c\u0645\u0639 \u06a9\u0644 \u0645\u0635\u0627\u0644\u062d \u0645\u0635\u0631\u0641\u06cc', style: TextStyle(fontWeight: FontWeight.bold)),
      for (final entry in totals) ListTile(dense: true, contentPadding: EdgeInsets.zero, title: Text('${entry['title']}'), trailing: Text('${entry['qty']} ${entry['unit']}')),
      if (totals.isEmpty) const Text('\u0645\u0635\u0644\u062d\u06cc \u062b\u0628\u062a \u0646\u0634\u062f\u0647 \u0627\u0633\u062a.'),
    ])));
  }
}

class _PhotoGallery extends StatelessWidget {
  const _PhotoGallery({required this.photos, required this.token});
  final List<Map<String, dynamic>> photos;
  final String? token;
  @override
  Widget build(BuildContext context) => SizedBox(height: 104, child: ListView(scrollDirection: Axis.horizontal, children: photos.map((photo) => Padding(
    padding: const EdgeInsetsDirectional.only(end: 8),
    child: ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.network(photo['url'].toString(), width: 104, height: 104, fit: BoxFit.cover, headers: {if (token != null) 'Authorization': 'Bearer $token'}, errorBuilder: (_, _, _) => const SizedBox(width: 104, child: Icon(Icons.broken_image_outlined)))),
  )).toList()));
}

String _status(Object? value) => switch (value) {
  'open' => '\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u062a\u0639\u0645\u06cc\u0631',
  'in_progress' => '\u062f\u0631 \u062d\u0627\u0644 \u062a\u0639\u0645\u06cc\u0631',
  'submitted' || 'submitOperation' => '\u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0623\u06cc\u06cc\u062f',
  'returned' || 'need_revision' => '\u0628\u0631\u06af\u0634\u062a\u06cc \u0628\u0631\u0627\u06cc \u0627\u0635\u0644\u0627\u062d',
  'completed' || 'approved' => '\u062a\u0623\u06cc\u06cc\u062f \u0634\u062f\u0647',
  'draft' => '\u067e\u06cc\u0634\u200c\u0646\u0648\u06cc\u0633',
  _ => value?.toString() ?? '\u0646\u0627\u0645\u0634\u062e\u0635',
};
