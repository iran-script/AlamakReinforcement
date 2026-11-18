import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/data/api_client.dart';
import '../cases/case_material_totals.dart';

typedef RiserDetailsLoader = Future<Map<String, dynamic>> Function(int riserId);

class RiserDetailsSheet extends StatefulWidget {
  const RiserDetailsSheet({super.key, required this.riserId, required this.loadDetails, required this.onCreateCase, required this.onOpenCase, this.onShowMap, this.syncPending = false});
  final int riserId; final RiserDetailsLoader loadDetails; final ValueChanged<int> onCreateCase; final ValueChanged<int> onOpenCase; final VoidCallback? onShowMap; final bool syncPending;
  @override State<RiserDetailsSheet> createState() => _RiserDetailsSheetState();
}
class _RiserDetailsSheetState extends State<RiserDetailsSheet> {
  late Future<Map<String, dynamic>> future;
  @override void initState() { super.initState(); future = widget.loadDetails(widget.riserId); }
  void retry() { final next = widget.loadDetails(widget.riserId); setState(() { future = next; }); }
  @override Widget build(BuildContext context) => Directionality(textDirection: TextDirection.rtl, child: SafeArea(child: SizedBox(height: MediaQuery.sizeOf(context).height * .92, child: FutureBuilder<Map<String, dynamic>>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.hasError) return _Error(retry: retry, error: snapshot.error);
      if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
      return _Content(data: snapshot.data!, sheet: widget);
    },
  ))));
}

class _Content extends StatelessWidget {
  const _Content({required this.data, required this.sheet}); final Map<String, dynamic> data; final RiserDetailsSheet sheet;
  @override Widget build(BuildContext context) {
    final riser = _map(data['riser']); final cases = _maps(data['cases']);
    final current = cases.firstOrNull;
    final currentOperations = _maps(current?['operations']);
    final operations = currentOperations;
    final materialTotals = aggregateCaseMaterials(currentOperations);
    return Scaffold(
      appBar: AppBar(leading: IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close)), title: const Text('جزئیات علمک'), actions: [IconButton(onPressed: sheet.onShowMap, icon: const Icon(Icons.location_on_outlined))]),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 110), children: [
        if (cases.isNotEmpty) const Text('پرونده قابل ادامه:'),
        ...cases.map((c) => ListTile(
          dense: true,
          title: Text('پرونده ${c['id']}'),
          subtitle: Text(_status(c['status'])),
          onTap: () => sheet.onOpenCase(_int(c['id'])),
        )),
        _Header(riser, sheet.syncPending),
        if (sheet.syncPending) const _Banner('\u062b\u0628\u062a \u0645\u062d\u0644\u06cc \u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc', Icons.cloud_upload_outlined),
        if (current?['status'] == 'returned') _Banner('پرونده برای اصلاح برگشته است: ${current?['return_reason'] ?? current?['returned_reason'] ?? 'دلیل برگشت در پرونده ثبت شده است.'}', Icons.assignment_return_outlined),
        _Summary(riser, cases, operations),
        _MaterialsSummary(materialTotals),
        const SizedBox(height: 16), const _Heading('تصاویر قبل و بعد', 2), const SizedBox(height: 8), _Photos(cases, sheet.onOpenCase),
        const SizedBox(height: 16), _Heading('عملیات تعمیراتی', operations.length), const SizedBox(height: 8),
        if (operations.isEmpty) const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('هنوز عملیاتی ثبت نشده است.'))),
        ...operations.asMap().entries.map((e) => _Operation(e.key, e.value)),
        const SizedBox(height: 16),
        _Workflow(cases, sheet.onOpenCase),
        const SizedBox(height: 8), _Extra(riser, cases),
      ]),
      bottomNavigationBar: _Actions(cases, current, sheet.syncPending, () => sheet.onCreateCase(sheet.riserId), sheet.onOpenCase, sheet.onShowMap),
    );
  }
}

class _Header extends StatelessWidget { const _Header(this.riser, this.pending); final Map<String, dynamic> riser; final bool pending; @override Widget build(BuildContext context) => Card(color: Theme.of(context).colorScheme.primaryContainer, child: ListTile(leading: CircleAvatar(child: Icon(pending ? Icons.cloud_upload_outlined : Icons.cell_tower_outlined)), title: Text('${riser['r_giscode'] ?? riser['giscode'] ?? '—'}', style: const TextStyle(fontWeight: FontWeight.bold)), subtitle: const Text('کد واقعی GIS'), trailing: _Pill(riser['status']))); }
class _Summary extends StatelessWidget { const _Summary(this.riser, this.cases, this.operations); final Map<String, dynamic> riser; final List<Map<String, dynamic>> cases; final List<Map<String, dynamic>> operations; @override Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Wrap(spacing: 16, runSpacing: 14, children: [_Metric(Icons.assignment_outlined, 'پرونده', cases.isEmpty ? 'ندارد' : _status(cases.first['status'])), _Metric(Icons.handyman_outlined, 'عملیات', '${operations.length} مورد'), _Metric(Icons.update_outlined, 'آخرین تغییر', cases.isEmpty ? 'ثبت نشده' : '${cases.first['submitted_at'] ?? cases.first['identified_at'] ?? 'ثبت نشده'}'.split('T').first), if (riser['zone'] != null) _Metric(Icons.domain_outlined, 'ناحیه', '${riser['zone']}')]))); }
class _Metric extends StatelessWidget { const _Metric(this.icon, this.label, this.value); final IconData icon; final String label; final String value; @override Widget build(BuildContext context) => SizedBox(width: 140, child: Row(children: [Icon(icon, size: 19), const SizedBox(width: 7), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: Theme.of(context).textTheme.labelSmall), Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold))]))])); }

class _Photos extends StatelessWidget { const _Photos(this.cases, this.openCase); final List<Map<String, dynamic>> cases; final ValueChanged<int> openCase; @override Widget build(BuildContext context) { final c = cases.firstOrNull; final p = _maps(c?['photos']); return Row(children: [Expanded(child: _Photo('قبل از تعمیر', p.where((x) => x['type'] == 'before').firstOrNull)), const SizedBox(width: 10), Expanded(child: _Photo('بعد از تعمیر', p.where((x) => x['type'] == 'after').firstOrNull)), if (c != null) IconButton(onPressed: () => openCase(_int(c['id'])), icon: const Icon(Icons.edit_outlined))]); } }
class _Photo extends StatelessWidget { const _Photo(this.title, this.photo); final String title; final Map<String, dynamic>? photo; @override Widget build(BuildContext context) => Container(height: 112, decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(14)), child: Stack(children: [Center(child: photo?['url'] == null ? const Icon(Icons.add_a_photo_outlined) : Image.network(photo!['url'].toString(), fit: BoxFit.cover, width: double.infinity, height: double.infinity, errorBuilder: (_, _, _) => const Icon(Icons.broken_image_outlined))), Align(alignment: Alignment.bottomCenter, child: Container(width: double.infinity, color: Colors.black54, padding: const EdgeInsets.all(6), child: Text(title, style: const TextStyle(color: Colors.white))))])); }
class _Operation extends StatelessWidget {
  const _Operation(this.index, this.operation);
  final int index;
  final Map<String, dynamic> operation;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: CircleAvatar(child: Text('${index + 1}')),
      title: Text(operation['category']?.toString() ?? 'عملیات تعمیراتی'),
      subtitle: Text('${_maps(operation['materials']).length} مصالح • ${_operationStatus(operation['status'])}'),
      trailing: const Icon(Icons.chevron_left),
      onTap: () => showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (_) => Directionality(
          textDirection: TextDirection.rtl,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: _OperationDetails(operation),
          ),
        ),
      ),
    ),
  );
}

class _OperationDetails extends StatelessWidget {
  const _OperationDetails(this.operation);
  final Map<String, dynamic> operation;

  @override
  Widget build(BuildContext context) {
    final materials = _maps(operation['materials']);
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(operation['category']?.toString() ?? 'نوع عملیات', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: 8),
          Text('وضعیت: ${_operationStatus(operation['status'])}'),
          if (operation['description']?.toString().trim().isNotEmpty == true) ...[
            const SizedBox(height: 8),
            Text(operation['description'].toString()),
          ],
          if (operation['date'] != null) ...[
            const SizedBox(height: 8),
            Text('تاریخ ثبت: ${operation['date'].toString().split('T').first}'),
          ],
          const SizedBox(height: 16),
          Text(materials.isEmpty ? 'برای این عملیات مصالحی ثبت نشده است' : 'مصالح مصرفی', style: const TextStyle(fontWeight: FontWeight.bold)),
          ...materials.map((material) => ListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: Text('${material['title'] ?? 'مصالح بدون عنوان'}'),
            trailing: Text('${material['qty'] ?? '-'} ${material['unit'] ?? ''}'),
          )),
        ],
      ),
    );
  }
}

class _MaterialsSummary extends StatelessWidget {
  const _MaterialsSummary(this.materials);
  final List<Map<String, Object?>> materials;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('جمع کل مصالح مصرفی', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          if (materials.isEmpty) const Text('برای پرونده جاری مصالحی ثبت نشده است'),
          ...materials.map((material) => ListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: Text('${material['title'] ?? 'مصالح بدون عنوان'}'),
            subtitle: Text('واحد: ${material['unit'] ?? '-'}'),
            trailing: Text('${material['qty']}'),
          )),
        ],
      ),
    ),
  );
}
class _Workflow extends StatelessWidget { const _Workflow(this.cases, this.openCase); final List<Map<String, dynamic>> cases; final ValueChanged<int> openCase; @override Widget build(BuildContext context) => Card(child: ExpansionTile(title: const Text('گردش‌کار پرونده'), children: [if (cases.isNotEmpty) const ListTile(title: Text('ظ¾ط±ظˆظ†ط¯ظ‡ ظ‚ط§ط¨ظ„ ط§ط¯ط§ظ…ظ‡ را انتخاب کنید:')), if (cases.isEmpty) const ListTile(title: Text('پرونده‌ای ثبت نشده است.')), ...cases.map((c) => ListTile(title: Text('پرونده ${c['id']}'), subtitle: Text(c['status'] == 'returned' ? 'بازگشت برای اصلاح: ${c['return_reason'] ?? c['returned_reason'] ?? 'دلیل ثبت‌شده در پرونده'}' : _workflow(c['status'])), trailing: _Pill(c['status']), onTap: () => openCase(_int(c['id']))))])); }
class _Extra extends StatelessWidget { const _Extra(this.riser, this.cases); final Map<String, dynamic> riser; final List<Map<String, dynamic>> cases; @override Widget build(BuildContext context) => Card(child: ExpansionTile(title: const Text('اطلاعات تکمیلی'), children: [ListTile(title: const Text('شناسه داخلی'), subtitle: Text('${riser['id'] ?? '—'}')), ListTile(title: const Text('مختصات'), subtitle: Text(riser['latitude'] == null ? '—' : '${riser['latitude']}، ${riser['longitude']}')), if (cases.isNotEmpty) ListTile(title: const Text('توضیح پرونده'), subtitle: Text('${cases.first['description'] ?? '—'}'))])); }

class _Actions extends StatelessWidget { const _Actions(this.cases, this.current, this.pending, this.create, this.openCase, this.map); final List<Map<String, dynamic>> cases; final Map<String, dynamic>? current; final bool pending; final VoidCallback create; final ValueChanged<int> openCase; final VoidCallback? map; @override Widget build(BuildContext context) { final locked = current != null && {'submitted', 'completed'}.contains(current!['status']); final edit = current != null && !locked && {'open', 'in_progress', 'returned'}.contains(current!['status']); final id = current == null ? 0 : _int(current!['id']); VoidCallback? action; if (!pending) action = current == null ? create : () => openCase(id); return SafeArea(child: Material(elevation: 8, child: Padding(padding: const EdgeInsets.all(10), child: Row(children: [Expanded(child: FilledButton(onPressed: action, child: Text(current == null ? 'ثبت تعمیرات' : edit ? 'ویرایش تعمیرات' : 'مشاهده پرونده'))), const SizedBox(width: 8), IconButton.filledTonal(onPressed: cases.isEmpty ? null : () => openCase(_int(cases.first['id'])), icon: const Icon(Icons.history)), IconButton.filledTonal(onPressed: map, icon: const Icon(Icons.map_outlined))])))); } }
class _Heading extends StatelessWidget { const _Heading(this.text, this.count); final String text; final int count; @override Widget build(BuildContext context) => Row(children: [Expanded(child: Text(text, style: const TextStyle(fontWeight: FontWeight.bold))), Chip(label: Text('$count'))]); }
class _Banner extends StatelessWidget { const _Banner(this.text, this.icon); final String text; final IconData icon; @override Widget build(BuildContext context) => Card(color: Theme.of(context).colorScheme.errorContainer, child: ListTile(leading: Icon(icon), title: Text(text))); }
class _Pill extends StatelessWidget { const _Pill(this.value); final Object? value; @override Widget build(BuildContext context) => Chip(label: Text(_status(value))); }
class _Error extends StatelessWidget { const _Error({required this.retry, required this.error}); final VoidCallback retry; final Object? error; @override Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Text(error is DioException ? (ApiClient.messageFrom(error) ?? 'دریافت اطلاعات ناموفق بود.') : 'دریافت اطلاعات ناموفق بود.'), const SizedBox(height: 12), FilledButton(onPressed: retry, child: const Text('\u062a\u0644\u0627\u0634 \u0645\u062c\u062f\u062f'))])); }
Map<String, dynamic> _map(Object? x) => x is Map ? Map<String, dynamic>.from(x) : <String, dynamic>{};
List<Map<String, dynamic>> _maps(Object? x) => x is List ? x.whereType<Map>().map(_map).toList() : <Map<String, dynamic>>[];
int _int(Object? x) => int.tryParse('$x') ?? 0;
String _status(Object? x) => switch ('$x') { 'open' => 'باز', 'in_progress' => 'در حال تعمیر', 'returned' => 'بازگشتی', 'submitted' => 'در انتظار بررسی رئیس', 'completed' => 'تأیید نهایی شده', _ => 'نامشخص' };
String _operationStatus(Object? x) => switch ('$x') { 'completed' => 'تکمیل‌شده', 'submitted' => 'ارسال‌شده', 'returned' => 'بازگشتی', _ => 'در حال انجام' };
String _workflow(Object? x) => switch ('$x') { 'submitted' => 'در انتظار بررسی رئیس', 'completed' => 'تأیید نهایی شده', 'returned' => 'نیازمند اصلاح', 'in_progress' => 'در حال تعمیر', 'open' => 'نیازمند تعمیر', _ => 'وضعیت نامشخص' };
