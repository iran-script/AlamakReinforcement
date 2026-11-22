import 'package:flutter/material.dart';

import 'wms_map_repository.dart';

class WmsLayersSheet extends StatelessWidget {
  const WmsLayersSheet({
    required this.layers,
    required this.settings,
    required this.statuses,
    required this.loadingCatalog,
    required this.catalogError,
    required this.onRefresh,
    required this.onToggle,
    required this.onOpacity,
    required this.onRetry,
    super.key,
  });

  final List<WmsMapLayer> layers;
  final Map<int, WmsViewSetting> settings;
  final Map<int, WmsLayerStatus> statuses;
  final bool loadingCatalog;
  final String? catalogError;
  final Future<void> Function() onRefresh;
  final Future<void> Function(WmsMapLayer layer, bool visible) onToggle;
  final Future<void> Function(WmsMapLayer layer, double opacity) onOpacity;
  final Future<void> Function(WmsMapLayer layer) onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Directionality(
      textDirection: TextDirection.rtl,
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * .72,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 12, 8),
                child: Row(
                  children: [
                    const Icon(Icons.layers_outlined),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'لایه‌های نقشه',
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'بستن',
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.map_outlined),
                title: const Text('نقشه پایه'),
                subtitle: const Text('فعال'),
                trailing: const Icon(Icons.check_circle, color: Colors.green),
                dense: true,
              ),
              if (catalogError != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                  child: Material(
                    color: theme.colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(12),
                    child: ListTile(
                      leading: Icon(
                        Icons.cloud_off_outlined,
                        color: theme.colorScheme.onErrorContainer,
                      ),
                      title: Text(
                        catalogError!,
                        style: TextStyle(
                          color: theme.colorScheme.onErrorContainer,
                        ),
                      ),
                      trailing: IconButton(
                        tooltip: 'تلاش دوباره',
                        onPressed: loadingCatalog ? null : onRefresh,
                        icon: const Icon(Icons.refresh),
                      ),
                    ),
                  ),
                ),
              Expanded(
                child: loadingCatalog && layers.isEmpty
                    ? const Center(child: CircularProgressIndicator())
                    : layers.isEmpty
                    ? const Center(
                        child: Text(
                          'لایه فعال و مجازی برای این حساب وجود ندارد.',
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                        itemCount: layers.length,
                        itemBuilder: (context, index) =>
                            _layerCard(context, layers[index]),
                      ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton.tonalIcon(
                    onPressed: loadingCatalog ? null : onRefresh,
                    icon: loadingCatalog
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.refresh),
                    label: const Text('به‌روزرسانی فهرست و لایه‌ها'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _layerCard(BuildContext context, WmsMapLayer layer) {
    final theme = Theme.of(context);
    final setting =
        settings[layer.id] ??
        WmsViewSetting(visible: layer.defaultVisible, opacity: layer.opacity);
    final status = statuses[layer.id] ?? const WmsLayerStatus();
    final statusColor = status.loading
        ? theme.colorScheme.primary
        : (status.message == null ? Colors.green : theme.colorScheme.error);
    final statusIcon = status.loading
        ? Icons.sync
        : (status.message == null
              ? Icons.cloud_done_outlined
              : Icons.cloud_off_outlined);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
        child: Column(
          children: [
            Row(
              children: [
                Icon(statusIcon, color: statusColor),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        layer.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium,
                      ),
                      Text(
                        layer.serviceName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                Switch.adaptive(
                  value: setting.visible,
                  onChanged: (value) => onToggle(layer, value),
                ),
              ],
            ),
            if (status.message != null)
              Row(
                children: [
                  Expanded(
                    child: Text(
                      status.message!,
                      style: TextStyle(
                        color: theme.colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'تلاش دوباره',
                    onPressed: () => onRetry(layer),
                    icon: const Icon(Icons.refresh),
                  ),
                ],
              )
            else if (status.loading)
              const LinearProgressIndicator(minHeight: 2)
            else
              Row(
                children: [
                  const Text('شفافیت', style: TextStyle(fontSize: 12)),
                  Expanded(
                    child: Slider(
                      value: setting.opacity.clamp(0, 1).toDouble(),
                      min: 0,
                      max: 1,
                      divisions: 20,
                      label: '${(setting.opacity * 100).round()}٪',
                      onChanged: setting.visible
                          ? (value) => onOpacity(layer, value)
                          : null,
                    ),
                  ),
                  SizedBox(
                    width: 42,
                    child: Text(
                      '${(setting.opacity * 100).round()}٪',
                      textAlign: TextAlign.center,
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
