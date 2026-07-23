import 'package:drift/native.dart';
import 'package:english_memory/core/database/local_app_database.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late LocalAppDatabase database;

  setUp(() {
    database = LocalAppDatabase.forTesting(NativeDatabase.memory());
  });

  tearDown(() => database.close());

  test('creates the local card outbox schema', () async {
    final tables = await database
        .customSelect("SELECT name FROM sqlite_master WHERE type = 'table'")
        .get();

    expect(
      tables.map((row) => row.read<String>('name')),
      containsAll(['pending_card_creations', 'cached_cards', 'sync_states']),
    );

    final columns = await database
        .customSelect('PRAGMA table_info(pending_card_creations)')
        .get();

    expect(
      columns.map((row) => row.read<String>('name')),
      containsAll([
        'local_id',
        'account_id',
        'state',
        'attempt_count',
        'next_attempt_at',
      ]),
    );
  });
}
