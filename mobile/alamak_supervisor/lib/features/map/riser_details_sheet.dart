import 'dart:async';

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../core/data/api_client.dart';

typedef RiserDetailsLoader = Future<Map<String, dynamic>> Function(int riserId);

class RiserDetailsSheet extends StatefulWidget {
  const RiserDetailsSheet({super.key, required this.riserId, required this.loadDetails, required this.onCreateCase, required this.onOpenCase, this.syncPending = false});
  final int riserId;
  final RiserDetailsLoader loadDetails;
  final ValueChanged<int> onCreateCase;
  final ValueChanged<int> onOpenCase;
  final bool syncPending;

  @override
  State<RiserDetailsSheet> createState() => _RiserDetailsSheetState();
}

class _RiserDetailsSheetState extends State<RiserDetailsSheet> {
  late Future<Map<String, dynamic>> _request;

  @override
  void initState() {
    super.initState();
    _request = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    return widget.loadDetails(widget.riserId);
  }

  void _retry() {
    final next = _load();
    setState(() { _request = next; });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.fromLTRB(20, 8, 20, MediaQuery.viewInsetsOf(context).bottom + 20),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .55,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _request,
          builder: (context, snapshot) {
            if (snapshot.hasError) { final error = snapshot.error; final errorText = error is DioException ? (ApiClient.messageFrom(error) ?? '\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0639\u0644\u0645\u06a9 \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.') : '\u062f\u0631\u06cc\u0627\u0641\u062a \u0627\u0637\u0644\u0627\u0639\u0627\u062a \u0639\u0644\u0645\u06a9 \u0646\u0627\u0645\u0648\u0641\u0642 \u0628\u0648\u062f.'; return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(errorText, textDirection: TextDirection.rtl, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _retry, child: const Text('\u062a\u0644\u0627\u0634 \u0645\u062c\u062f\u062f')),
            ])); }
            if (!snapshot.hasData) { return const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              CircularProgressIndicator(), SizedBox(height: 16),
              Text('\u062f\u0631 \u062d\u0627\u0644 \u062f\u0631\u06cc\u0627\u0641\u062a \u062c\u0632\u0626\u06cc\u0627\u062a \u0639\u0644\u0645\u06a9...', textDirection: TextDirection.rtl),
              SizedBox(height: 12), LinearProgressIndicator(),
            ])); }
            final body = snapshot.data!;
            final riser = Map<String, dynamic>.from(body['riser'] as Map);
            final cases = List<Map<String, dynamic>>.from((body['cases'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
            final active = cases.where((c) => ['open', 'in_progress', 'submitted', 'returned'].contains(c['status'])).toList();
            final operations = body['operations'] as List? ?? const [];
            return ListView(children: [
              Text(riser['r_giscode']?.toString() ?? riser['giscode']?.toString() ?? '\u0639\u0644\u0645\u06a9', textDirection: TextDirection.rtl, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
              Text('\u0634\u0646\u0627\u0633\u0647: ${riser['id'] ?? widget.riserId}', textDirection: TextDirection.rtl),
              Text('\u0648\u0636\u0639\u06cc\u062a: ${riser['status'] ?? '\u0646\u0627\u0645\u0634\u062e\u0635'}', textDirection: TextDirection.rtl),
              if (riser['latitude'] != null && riser['longitude'] != null) Text('\u0645\u0648\u0642\u0639\u06cc\u062a: ${riser['latitude']}\u060c ${riser['longitude']}', textDirection: TextDirection.rtl),
              Text('\u0639\u0645\u0644\u06cc\u0627\u062a \u0642\u0628\u0644\u06cc: ${operations.length}  \u2022  \u067e\u0631\u0648\u0646\u062f\u0647\u200c\u0647\u0627: ${cases.length}', textDirection: TextDirection.rtl),
              if (widget.syncPending) const Card(color: Color(0xfffff4d6), child: ListTile(leading: Icon(Icons.cloud_upload_outlined, color: Colors.orange), title: Text('\u062b\u0628\u062a \u0645\u062d\u0644\u06cc \u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u0647\u0645\u06af\u0627\u0645\u200c\u0633\u0627\u0632\u06cc', textDirection: TextDirection.rtl), subtitle: Text('\u0648\u0636\u0639\u06cc\u062a \u0646\u0647\u0627\u06cc\u06cc \u0633\u0631\u0648\u0631 \u067e\u0633 \u0627\u0632 \u0627\u0631\u0633\u0627\u0644 \u0645\u0648\u0641\u0642 \u0645\u0634\u062e\u0635 \u0645\u06cc\u200c\u0634\u0648\u062f.', textDirection: TextDirection.rtl))),
              if (active.isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text('\u067e\u0631\u0648\u0646\u062f\u0647 \u0642\u0627\u0628\u0644 \u0627\u062f\u0627\u0645\u0647 \u0631\u0627 \u0627\u0646\u062a\u062e\u0627\u0628 \u06a9\u0646\u06cc\u062f:', textDirection: TextDirection.rtl),
                for (final item in active) OutlinedButton.icon(
                  onPressed: () => widget.onOpenCase(int.parse('${item['id']}')),
                  icon: const Icon(Icons.handyman_outlined),
                  label: Text('\u067e\u0631\u0648\u0646\u062f\u0647 ${item['id']} \u2014 ${_caseStatus(item['status'])}'),
                ),
              ],
              const SizedBox(height: 12),
              if (active.isEmpty && !widget.syncPending) FilledButton.icon(onPressed: () => widget.onCreateCase(widget.riserId), icon: const Icon(Icons.add_location_alt_outlined), label: const Text('\u062b\u0628\u062a \u0646\u06cc\u0627\u0632 \u0628\u0647 \u062a\u0639\u0645\u06cc\u0631')),
              if (cases.isNotEmpty) OutlinedButton.icon(onPressed: () async {
                final selected = await showModalBottomSheet<int>(context: context, showDragHandle: true, builder: (pickerContext) => SafeArea(child: ListView(shrinkWrap: true, children: [
                  for (final item in cases) ListTile(title: Text('\u067e\u0631\u0648\u0646\u062f\u0647 ${item['id']} \u2014 ${_caseStatus(item['status'])}'), subtitle: Text(item['description']?.toString() ?? ''), onTap: () => Navigator.pop(pickerContext, int.parse('${item['id']}'))),
                ])));
                if (selected != null && mounted) widget.onOpenCase(selected);
              }, icon: const Icon(Icons.history), label: const Text('\u0645\u0634\u0627\u0647\u062f\u0647 \u0633\u0648\u0627\u0628\u0642')),
            ]);
          },
        ),
      ),
    ),
  );
}

String _caseStatus(Object? value) => switch (value) {
  'open' => '\u0646\u06cc\u0627\u0632\u0645\u0646\u062f \u062a\u0639\u0645\u06cc\u0631',
  'in_progress' => '\u062f\u0631 \u062d\u0627\u0644 \u062a\u0639\u0645\u06cc\u0631',
  'submitted' => '\u062f\u0631 \u0627\u0646\u062a\u0638\u0627\u0631 \u062a\u0623\u06cc\u06cc\u062f',
  'returned' => '\u0628\u0631\u06af\u0634\u062a\u06cc',
  'completed' => '\u062a\u0623\u06cc\u06cc\u062f\u0634\u062f\u0647',
  _ => value?.toString() ?? '\u0646\u0627\u0645\u0634\u062e\u0635',
};
