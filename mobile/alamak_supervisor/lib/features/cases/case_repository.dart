import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../auth/session_controller.dart';
import 'operation_draft.dart';

class BatchSaveResult {
  const BatchSaveResult({required this.serverSaved, this.pendingId});

  final bool serverSaved;
  final String? pendingId;
}

class CaseRepository {
  CaseRepository(this.session);
  final SessionController session;
  static const _uuid = Uuid();

  Future<String> savePending({required String kind, required int userId,
    required Map<String, dynamic> payload, required List<String> filePaths}) async {
    final id = _uuid.v4();
    final root = await getApplicationDocumentsDirectory();
    final directory = Directory(p.join(root.path, 'pending', '$userId', id));
    await directory.create(recursive: true);
    final storedFiles = <String>[];
    for (var i = 0; i < filePaths.length; i++) {
      final source = File(filePaths[i]);
      if (await source.exists()) {
        final extension = p.extension(source.path).toLowerCase();
        final target = p.join(directory.path, '$i$extension');
        await source.copy(target);
        storedFiles.add(target);
      }
    }
    await session.drafts.save(id: id, userId: userId, kind: kind,
      payload: payload, files: storedFiles);
    return id;
  }

  Future<void> syncPending() async {
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    final client = session.api;
    if (userId == null || client == null || session.token == null) throw StateError('برای ثبت یا همگام‌سازی، ابتدا به سرور وارد شوید.');
    await session.refreshUser();
    if (session.token == null || session.offline) throw StateError('برای ثبت یا همگام‌سازی، ابتدا به سرور وارد شوید.');
    final pending = await session.drafts.list(userId);
    for (final row in pending) {
      final payload = Map<String, dynamic>.from(jsonDecode(row['payload']! as String) as Map);
      final files = List<String>.from(jsonDecode(row['files']! as String) as List);
      final data = <String, dynamic>{...payload};
      final field = row['kind'] == 'case' ? 'photos_before' : 'photos_after';
      for (var index = 0; index < files.length; index++) {
        final file = File(files[index]);
        if (await file.exists()) {
          data['$field[$index]'] = await MultipartFile.fromFile(file.path, filename: p.basename(file.path));
        }
      }
      final form = FormData.fromMap(data);
      if (row['kind'] == 'case') {
        await client.dio.post('v1/supervisor/cases', data: form);
      } else if (row['kind'] == 'operations_batch') {
        final caseId = data.remove('case_id');
        final mode = data.remove('mode') == 'update' ? 'update' : 'create';
        final photoTypes = List<String>.from(data.remove('photo_types') as List? ?? const []);
        final current = await client.dio.get('v1/supervisor/cases/$caseId');
        final currentCase = Map<String, dynamic>.from(current.data['case'] as Map);
        if (['submitted', 'completed'].contains(currentCase['status'])) {
          throw StateError('پرونده پس از ایجاد صف آفلاین ارسال یا تکمیل شده و قابل همگام‌سازی نیست.');
        }
        final batchForm = _batchFormData(
          data,
          files,
          photoTypes,
        );
        if (mode == 'update') {
          await client.dio.put('v1/supervisor/cases/$caseId/operations/batch', data: batchForm);
        } else {
          await client.dio.post('v1/supervisor/cases/$caseId/operations/batch', data: batchForm);
        }
      } else if (row['kind'] == 'operation_draft') {
        await client.dio.post('v1/supervisor/cases/${data.remove('case_id')}/operations', data: form);
      } else if (row['kind'] == 'operation_update') {
        final caseId = data.remove('case_id');
        final operationId = data.remove('operation_id');
        await client.dio.put('v1/supervisor/cases/$caseId/operations/$operationId', data: form);
      } else {
        final caseId = data.remove('case_id');
        await client.dio.post('v1/supervisor/cases/$caseId/operation', data: form);
      }
      await session.drafts.delete(row['id']! as String, userId);
      for (final file in files) {
        try { await File(file).delete(); } catch (_) { }
      }
    }
  }

  Future<List<Map<String, Object?>>> pending() async {
    final id = int.tryParse('${session.user?['id'] ?? ''}');
    return id == null ? [] : session.drafts.list(id);
  }

  FormData formData(Map<String, dynamic> payload, List<String> paths, String photoField) {
    final fields = <String, dynamic>{...payload};
    for (var i = 0; i < paths.length; i++) {
      fields['$photoField[$i]'] = MultipartFile.fromFileSync(paths[i], filename: p.basename(paths[i]));
    }
    return FormData.fromMap(fields);
  }

  Future<BatchSaveResult> saveOperations({
    required int caseId,
    required List<OperationDraft> operations,
    required List<int> deletedOperationIds,
    String? beforePhotoPath,
    String? afterPhotoPath,
  }) async {
    final userId = int.tryParse('${session.user?['id'] ?? ''}');
    if (userId == null || session.api == null || session.token == null) {
      throw StateError('برای ذخیره ابتدا وارد سامانه شوید.');
    }

    final hasExisting = operations.any((operation) => operation.isPersisted) || deletedOperationIds.isNotEmpty;
    final mode = hasExisting ? 'update' : 'create';
    final batchUuid = const Uuid().v4();
    final payload = <String, dynamic>{
      'client_uuid': batchUuid,
      'operations': operations.map((operation) => operation.toPayload()).toList(),
      if (deletedOperationIds.isNotEmpty) 'deleted_operation_ids': deletedOperationIds,
    };
    final paths = <String>[];
    final photoTypes = <String>[];
    if (beforePhotoPath != null) {
      paths.add(beforePhotoPath);
      photoTypes.add('before');
    }
    if (afterPhotoPath != null) {
      paths.add(afterPhotoPath);
      photoTypes.add('after');
    }

    try {
      final form = _batchFormData(payload, paths, photoTypes);
      if (mode == 'update') {
        await session.api!.dio.put('v1/supervisor/cases/$caseId/operations/batch', data: form);
      } else {
        await session.api!.dio.post('v1/supervisor/cases/$caseId/operations/batch', data: form);
      }
      return const BatchSaveResult(serverSaved: true);
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      final pendingPayload = <String, dynamic>{
        'case_id': caseId,
        'mode': mode,
        'photo_types': photoTypes,
        ...payload,
      };
      final pendingId = await savePending(
        kind: 'operations_batch',
        userId: userId,
        payload: pendingPayload,
        filePaths: paths,
      );
      return BatchSaveResult(serverSaved: false, pendingId: pendingId);
    }
  }

  FormData _batchFormData(
    Map<String, dynamic> payload,
    List<String> filePaths,
    List<String> photoTypes,
  ) {
    final fields = <String, dynamic>{};
    if (payload['client_uuid'] != null) fields['client_uuid'] = '${payload['client_uuid']}';
    final operations = List<Map<String, dynamic>>.from(
      (payload['operations'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
    );
    for (var index = 0; index < operations.length; index++) {
      final operation = operations[index];
      for (final key in ['id', 'client_uuid', 'version', 'operation_category_id', 'operation_date', 'description']) {
        if (operation[key] != null) fields['operations[$index][$key]'] = '${operation[key]}';
      }
      final materials = List<Map<String, dynamic>>.from(
        (operation['materials'] as List? ?? const []).map((entry) => Map<String, dynamic>.from(entry as Map)),
      );
      for (var materialIndex = 0; materialIndex < materials.length; materialIndex++) {
        final material = materials[materialIndex];
        fields['operations[$index][materials][$materialIndex][material_id]'] = '${material['material_id']}';
        fields['operations[$index][materials][$materialIndex][qty]'] = '${material['qty']}';
      }
    }
    final deleted = List<Object?>.from(payload['deleted_operation_ids'] as List? ?? const []);
    for (var index = 0; index < deleted.length; index++) {
      fields['deleted_operation_ids[$index]'] = '${deleted[index]}';
    }
    for (var index = 0; index < filePaths.length; index++) {
      final file = File(filePaths[index]);
      if (!file.existsSync()) continue;
      final type = photoTypes.length > index ? photoTypes[index] : 'after';
      fields['photos_$type'] = MultipartFile.fromFileSync(file.path, filename: p.basename(file.path));
    }
    return FormData.fromMap(fields);
  }
}
