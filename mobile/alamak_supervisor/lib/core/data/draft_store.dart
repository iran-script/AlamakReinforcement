import 'dart:convert';
import 'dart:io';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';
import 'package:uuid/uuid.dart';

class DraftStore {
  static const _uuid = Uuid();
  Database? _database;

  Future<Database> get database async => _database ??= await openDatabase(
        p.join(await getDatabasesPath(), 'alamak_supervisor_drafts.db'),
        version: 1,
        onCreate: (db, version) => db.execute('''
          CREATE TABLE pending_drafts (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            kind TEXT NOT NULL,
            payload TEXT NOT NULL,
            files TEXT NOT NULL,
            created_at TEXT NOT NULL
          )
        '''),
      );

  Future<String> save({
    String? id,
    required int userId,
    required String kind,
    required Map<String, Object?> payload,
    required List<String> files,
  }) async {
    id ??= _uuid.v4();
    await (await database).insert('pending_drafts', {
      'id': id,
      'user_id': userId,
      'kind': kind,
      'payload': jsonEncode(payload),
      'files': jsonEncode(files),
      'created_at': DateTime.now().toUtc().toIso8601String(),
    });
    return id;
  }

  Future<List<Map<String, Object?>>> list(int userId) async =>
      (await database).query('pending_drafts', where: 'user_id = ?', whereArgs: [userId], orderBy: 'created_at DESC');

  Future<void> delete(String id, int userId) async =>
      (await database).delete('pending_drafts', where: 'id = ? AND user_id = ?', whereArgs: [id, userId]);

  Future<void> clearUser(int userId) async {
    final db = await database;
    final rows = await db.query('pending_drafts', where: 'user_id = ?', whereArgs: [userId]);
    await _deleteFiles(rows);
    await db.delete('pending_drafts', where: 'user_id = ?', whereArgs: [userId]);
  }

  Future<void> clearAll() async {
    final db = await database;
    final rows = await db.query('pending_drafts');
    await _deleteFiles(rows);
    await db.delete('pending_drafts');
  }

  Future<void> _deleteFiles(List<Map<String, Object?>> rows) async {
    for (final row in rows) {
      final files = List<String>.from(jsonDecode(row['files']! as String) as List);
      for (final path in files) {
        try {
          final file = File(path);
          if (await file.exists()) await file.delete();
        } catch (_) {
          // Continue clearing other local files and records.
        }
      }
    }
  }
}
