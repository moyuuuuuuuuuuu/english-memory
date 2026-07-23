import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'local_app_database.g.dart';

enum PendingCreationState { pending, sending, retryWait, blocked }

enum CachedCardLocalStatus { pending, queued, blocked }

final class PendingCreationStateConverter
    extends TypeConverter<PendingCreationState, String> {
  const PendingCreationStateConverter();

  @override
  PendingCreationState fromSql(String fromDb) => switch (fromDb) {
    'pending' => PendingCreationState.pending,
    'sending' => PendingCreationState.sending,
    'retry_wait' => PendingCreationState.retryWait,
    'blocked' => PendingCreationState.blocked,
    _ => throw FormatException('Unknown pending creation state: $fromDb'),
  };

  @override
  String toSql(PendingCreationState value) => switch (value) {
    PendingCreationState.pending => 'pending',
    PendingCreationState.sending => 'sending',
    PendingCreationState.retryWait => 'retry_wait',
    PendingCreationState.blocked => 'blocked',
  };
}

final class CachedCardLocalStatusConverter
    extends TypeConverter<CachedCardLocalStatus, String> {
  const CachedCardLocalStatusConverter();

  @override
  CachedCardLocalStatus fromSql(String fromDb) => switch (fromDb) {
    'pending' => CachedCardLocalStatus.pending,
    'queued' => CachedCardLocalStatus.queued,
    'blocked' => CachedCardLocalStatus.blocked,
    _ => throw FormatException('Unknown cached card status: $fromDb'),
  };

  @override
  String toSql(CachedCardLocalStatus value) => switch (value) {
    CachedCardLocalStatus.pending => 'pending',
    CachedCardLocalStatus.queued => 'queued',
    CachedCardLocalStatus.blocked => 'blocked',
  };
}

@TableIndex(
  name: 'pending_card_creations_ready',
  columns: {#accountId, #state, #nextAttemptAt, #createdAt},
)
class PendingCardCreations extends Table {
  TextColumn get localId => text()();
  IntColumn get accountId => integer()();
  TextColumn get requestText => text().named('text')();
  TextColumn get contentType => text()();
  TextColumn get memoryStyle => text()();
  TextColumn get state =>
      text().map(const PendingCreationStateConverter())();
  IntColumn get attemptCount => integer().withDefault(const Constant(0))();
  DateTimeColumn get nextAttemptAt => dateTime().nullable()();
  TextColumn get lastErrorCode => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {localId};
}

@TableIndex(
  name: 'cached_cards_by_status',
  columns: {#accountId, #localStatus},
)
class CachedCards extends Table {
  TextColumn get localId => text()();
  IntColumn get accountId => integer()();
  TextColumn get sourceText => text()();
  TextColumn get contentType => text()();
  TextColumn get memoryStyle => text()();
  TextColumn get localStatus =>
      text().map(const CachedCardLocalStatusConverter())();
  IntColumn get serverCardId => integer().nullable()();
  IntColumn get serverJobId => integer().nullable()();
  TextColumn get generationStatus => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {localId};

  @override
  List<Set<Column<Object>>> get uniqueKeys => [
    {accountId, serverCardId},
  ];
}

class SyncStates extends Table {
  IntColumn get accountId => integer()();
  IntColumn get cursor => integer().withDefault(const Constant(0))();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {accountId};
}

@DriftDatabase(
  tables: [PendingCardCreations, CachedCards, SyncStates],
)
final class LocalAppDatabase extends _$LocalAppDatabase {
  LocalAppDatabase.defaults()
    : super(driftDatabase(name: 'english_memory'));

  LocalAppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (migrator) => migrator.createAll(),
  );
}
