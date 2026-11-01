import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session_controller.dart';

class DashboardPage extends ConsumerStatefulWidget {
  const DashboardPage({super.key});
  @override
  ConsumerState<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends ConsumerState<DashboardPage> {
  Future<Map<String, dynamic>>? _request;
  @override
  void initState() { super.initState(); _load(); }
  void _load() { _request = ref.read(sessionProvider).api!.dio.get('v1/supervisor/dashboard').then((r) => Map<String, dynamic>.from(r.data as Map)); }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('داشبورد ناظر'), actions: [
        Padding(padding: const EdgeInsetsDirectional.only(end: 14), child: Icon(session.offline ? Icons.cloud_off : Icons.cloud_done, color: session.offline ? Colors.orange : Colors.green)),
      ]),
      body: RefreshIndicator(onRefresh: () async { await session.refreshUser(); setState(_load); await _request; }, child: ListView(padding: const EdgeInsets.all(16), children: [
        Text('داشبورد ناظر', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text('داشبورد ناظر', style: const TextStyle(color: Colors.black54)),
        const SizedBox(height: 18),
        FutureBuilder<Map<String, dynamic>>(future: _request, builder: (context, snapshot) {
          if (snapshot.hasError) return _OfflineCard(message: snapshot.error is DioException ? 'حالت آفلاین: داده‌ها ممکن است به‌روز نباشند.' : 'حالت آفلاین: داده‌ها ممکن است به‌روز نباشند.');
          if (!snapshot.hasData) return const _LoadingCard();
          final data = snapshot.data!;
          return GridView.count(shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), crossAxisCount: MediaQuery.sizeOf(context).width > 600 ? 4 : 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.5, children: [
            _MetricCard(title: 'پرونده‌های باز', value: data['open'], icon: Icons.build_circle_outlined, color: const Color(0xffb7791f)),
            _MetricCard(title: 'ارسال‌شده به رئیس ناحیه', value: data['submitted'], icon: Icons.outbox_outlined, color: const Color(0xff6955b5)),
            _MetricCard(title: 'برگشتی', value: data['returned'], icon: Icons.assignment_return_outlined, color: const Color(0xffc34747)),
            _MetricCard(title: 'تکمیل‌شده', value: data['completed'], icon: Icons.task_alt, color: const Color(0xff18856e)),
          ]);
        }),
        const SizedBox(height: 18),
        Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('داشبورد ناظر', style: TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          const Text('داشبورد ناظر', style: TextStyle(height: 1.65, color: Colors.black54)),
          if (session.offline) const Padding(padding: EdgeInsets.only(top: 10), child: Text('حالت آفلاین: داده‌ها ممکن است به‌روز نباشند.', style: TextStyle(color: Colors.deepOrange))),
        ]))),
      ])),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.title, required this.value, required this.icon, required this.color});
  final String title; final Object? value; final IconData icon; final Color color;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
    Icon(icon, color: color), Text('${value ?? 0}', style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800)), Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: Colors.black54)),
  ])));
}

class _LoadingCard extends StatelessWidget {
  const _LoadingCard();
  @override
  Widget build(BuildContext context) => const Card(child: SizedBox(height: 160, child: Center(child: CircularProgressIndicator())));
}

class _OfflineCard extends StatelessWidget {
  const _OfflineCard({required this.message}); final String message;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(18), child: Text(message, style: const TextStyle(height: 1.6))));
}
