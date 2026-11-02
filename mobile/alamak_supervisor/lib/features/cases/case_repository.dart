import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../auth/session_controller.dart';

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
}
