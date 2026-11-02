import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Returns the selected material quantities when confirmed; null means canceled.
Future<Map<int, String>?> showMaterialPicker(
  BuildContext context, {
  required Future<Map<String, dynamic>> Function() loadReferences,
  Map<int, String> initialQuantities = const {},
}) => showModalBottomSheet<Map<int, String>>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  showDragHandle: true,
  builder: (_) => MaterialPickerSheet(
    loadReferences: loadReferences,
    initialQuantities: initialQuantities,
  ),
);

class MaterialPickerSheet extends StatefulWidget {
  const MaterialPickerSheet({
    super.key,
    required this.loadReferences,
    this.initialQuantities = const {},
  });

  final Future<Map<String, dynamic>> Function() loadReferences;
  final Map<int, String> initialQuantities;

  @override
  State<MaterialPickerSheet> createState() => _MaterialPickerSheetState();
}

class _MaterialPickerSheetState extends State<MaterialPickerSheet> {
  final _search = TextEditingController();
  final Map<int, TextEditingController> _quantities = {};
  List<Map<String, dynamic>> _materials = [];
  List<Map<String, dynamic>> _categories = [];
  String? _error;
  bool _loading = true;
  int? _categoryId;

  @override
  void initState() {
    super.initState();
    for (final entry in widget.initialQuantities.entries) {
      _quantities[entry.key] = TextEditingController(text: entry.value);
    }
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    for (final controller in _quantities.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final references = await widget.loadReferences();
      final materials = (references['materials'] as List? ?? [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where((item) => int.tryParse('${item['id']}') != null)
          .toList(growable: false);
      final categoriesById = <int, Map<String, dynamic>>{};
      for (final material in materials) {
        final category = material['category'];
        if (category is Map) {
          final map = Map<String, dynamic>.from(category);
          final id = int.tryParse(
            '${map['id'] ?? material['material_category_id']}',
          );
          if (id != null) {
            categoriesById.putIfAbsent(id, () => map..['id'] = id);
          }
        }
      }
      if (!mounted) return;
      setState(() {
        _materials = materials;
        _categories = categoriesById.values.toList()
          ..sort((a, b) => '${a['title']}'.compareTo('${b['title']}'));
        _loading = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'دریافت فهرست مصالح ناموفق بود.';
        });
      }
    }
  }

  static String _normalize(String value) => value
      .replaceAll('ي', 'ی')
      .replaceAll('ى', 'ی')
      .replaceAll('ك', 'ک')
      .replaceAll('\u200c', ' ')
      .replaceAll(RegExp(r'[\u064b-\u065f\u0670]'), '')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim()
      .toLowerCase();

  static String _normalizeAmount(String value) => value
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

  int? _materialCategoryId(Map<String, dynamic> material) {
    final category = material['category'];
    if (category is Map) return int.tryParse('${category['id']}');
    return int.tryParse('${material['material_category_id'] ?? ''}');
  }

  List<Map<String, dynamic>> get _visibleMaterials {
    final query = _normalize(_search.text);
    return _materials
        .where((material) {
          final categoryMatches =
              _categoryId == null ||
              _materialCategoryId(material) == _categoryId;
          final textMatches =
              query.isEmpty ||
              _normalize('${material['title'] ?? ''} ${material['code'] ?? ''}')
                  .contains(query);
          return categoryMatches && textMatches;
        })
        .toList(growable: false);
  }

  void _toggle(Map<String, dynamic> material, bool selected) {
    final id = int.parse('${material['id']}');
    setState(() {
      if (selected) {
        _quantities.putIfAbsent(id, () => TextEditingController());
      } else {
        _quantities.remove(id)?.dispose();
      }
    });
  }

  void _confirm() {
    for (final entry in _quantities.entries) {
      final value = entry.value.text.trim();
      final amount = double.tryParse(_normalizeAmount(value));
      if (amount == null || !amount.isFinite || amount <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'برای هر مصالح انتخاب‌شده، مقدار معتبر و بیشتر از صفر وارد کنید.',
            ),
          ),
        );
        return;
      }
    }
    Navigator.of(context).pop(
      Map<int, String>.unmodifiable({
        for (final entry in _quantities.entries)
          entry.key: entry.value.text.trim(),
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    final height = MediaQuery.sizeOf(context).height;
    final visible = _visibleMaterials;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Padding(
        padding: EdgeInsets.only(bottom: bottomInset),
        child: SizedBox(
          height: height * .88,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'انتخاب مصالح مصرفی',
                            style: TextStyle(
                              fontSize: 19,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        Text('${_quantities.length} انتخاب'),
                      ],
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      key: const Key('material-search'),
                      controller: _search,
                      onChanged: (_) => setState(() {}),
                      textDirection: TextDirection.rtl,
                      decoration: const InputDecoration(
                        prefixIcon: Icon(Icons.search),
                        hintText: 'جست‌وجو براساس نام یا کد مصالح',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                    ),
                    if (_categories.isNotEmpty) ...[
                      const SizedBox(height: 9),
                      SizedBox(
                        height: 42,
                        child: ListView(
                          scrollDirection: Axis.horizontal,
                          children: [
                            Padding(
                              padding: const EdgeInsetsDirectional.only(end: 7),
                              child: ChoiceChip(
                                label: const Text('همه دسته‌ها'),
                                selected: _categoryId == null,
                                onSelected: (_) =>
                                    setState(() => _categoryId = null),
                              ),
                            ),
                            ..._categories.map((category) {
                              final id = int.tryParse('${category['id']}');
                              if (id == null) return const SizedBox.shrink();
                              return Padding(
                                padding: const EdgeInsetsDirectional.only(
                                  end: 7,
                                ),
                                child: ChoiceChip(
                                  label: Text(
                                    '${category['title'] ?? category['code'] ?? 'دسته‌بندی'}',
                                  ),
                                  selected: _categoryId == id,
                                  onSelected: (_) => setState(
                                    () => _categoryId = _categoryId == id
                                        ? null
                                        : id,
                                  ),
                                ),
                              );
                            }),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 8),
                            TextButton.icon(
                              onPressed: _load,
                              icon: const Icon(Icons.refresh),
                              label: const Text('تلاش دوباره'),
                            ),
                          ],
                        ),
                      )
                    : visible.isEmpty
                    ? const Center(
                        child: Text('مصالحی مطابق جست‌وجو پیدا نشد.'),
                      )
                    : ListView.builder(
                        key: const Key('material-list'),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        itemCount: visible.length,
                        itemBuilder: (context, index) {
                          final material = visible[index];
                          final id = int.parse('${material['id']}');
                          final controller = _quantities[id];
                          return Card(
                            margin: const EdgeInsets.symmetric(vertical: 4),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 5),
                              child: Column(
                                children: [
                                  CheckboxListTile(
                                    key: Key('material-option-$id'),
                                    value: controller != null,
                                    onChanged: (value) =>
                                        _toggle(material, value ?? false),
                                    title: Text(
                                      '${material['title'] ?? 'بدون عنوان'}',
                                    ),
                                    subtitle: Text(
                                      [
                                        if ('${material['code'] ?? ''}'
                                            .isNotEmpty)
                                          'کد: ${material['code']}',
                                        if ('${material['unit'] ?? ''}'
                                            .isNotEmpty)
                                          'واحد: ${material['unit']}',
                                      ].join('  •  '),
                                    ),
                                    controlAffinity:
                                        ListTileControlAffinity.leading,
                                    dense: true,
                                  ),
                                  if (controller != null)
                                    Padding(
                                      padding:
                                          const EdgeInsetsDirectional.fromSTEB(
                                            16,
                                            0,
                                            56,
                                            10,
                                          ),
                                      child: TextField(
                                        key: Key('material-quantity-$id'),
                                        controller: controller,
                                        keyboardType:
                                            const TextInputType.numberWithOptions(
                                              decimal: true,
                                              signed: false,
                                            ),
                                        inputFormatters: [
                                          FilteringTextInputFormatter.allow(
                                            RegExp(r'[0-9٠-٩۰-۹.,]'),
                                          ),
                                        ],
                                        decoration: InputDecoration(
                                          labelText: 'مقدار مصرف',
                                          suffixText:
                                              '${material['unit'] ?? ''}',
                                          border: const OutlineInputBorder(),
                                          isDense: true,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      key: const Key('material-confirm'),
                      onPressed: _loading || _error != null ? null : _confirm,
                      icon: const Icon(Icons.check),
                      label: Text('تأیید انتخاب (${_quantities.length})'),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
