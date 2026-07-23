import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../../../core/database/local_app_database.dart';
import '../../capture/domain/capture_draft.dart';
import '../domain/card_creation_accepted.dart';
import '../domain/pending_card_creation.dart';
import '../application/sync_lifecycle.dart';

abstract interface class CardOutboxGateway {
  Future<String> enqueue(int accountId, CaptureDraft draft);

  Future<PendingCardCreation?> nextReady(int accountId, DateTime now);

  Future<DateTime?> nextRetryAt(int accountId);

  Future<void> markSending(int accountId, String localId);

  Future<void> markRetry(
    int accountId,
    String localId, {
    required int attempts,
    required DateTime at,
    required String code,
  });

  Future<void> markBlocked(int accountId, String localId, String code);

  Future<void> complete(
    int accountId,
    String localId,
    CardCreationAccepted result,
  );

  Future<void> recoverSending(int accountId);

  Future<void> retryBlocked(int accountId, String localId);

  Stream<int> watchOutstandingCount(int accountId);

  Future<int> outstandingCount(int accountId);

  Future<void> clearAccount(int accountId);
}

final class DriftCardOutboxRepository
    implements CardOutboxGateway, AccountLocalDataGateway {
  DriftCardOutboxRepository(
    this._database, {
    String Function()? uuid,
    DateTime Function()? now,
  }) : _uuid = uuid ?? const Uuid().v4,
       _now = now ?? DateTime.now;

  final LocalAppDatabase _database;
  final String Function() _uuid;
  final DateTime Function() _now;

  @override
  Future<String> enqueue(int accountId, CaptureDraft draft) {
    if (accountId <= 0) {
      throw ArgumentError.value(accountId, 'accountId');
    }
    final localId = _uuid();
    final timestamp = _now();

    return _database.transaction(() async {
      await _database
          .into(_database.cachedCards)
          .insert(
            CachedCardsCompanion.insert(
              localId: localId,
              accountId: accountId,
              sourceText: draft.text,
              contentType: draft.contentType.apiValue,
              memoryStyle: draft.memoryStyle.apiValue,
              localStatus: CachedCardLocalStatus.pending,
              createdAt: timestamp,
              updatedAt: timestamp,
            ),
          );
      await _database
          .into(_database.pendingCardCreations)
          .insert(
            PendingCardCreationsCompanion.insert(
              localId: localId,
              accountId: accountId,
              requestText: draft.text,
              contentType: draft.contentType.apiValue,
              memoryStyle: draft.memoryStyle.apiValue,
              state: PendingCreationState.pending,
              createdAt: timestamp,
              updatedAt: timestamp,
            ),
          );
      return localId;
    });
  }

  @override
  Future<PendingCardCreation?> nextReady(int accountId, DateTime now) async {
    final query = _database.select(_database.pendingCardCreations)
      ..where(
        (row) =>
            row.accountId.equals(accountId) &
            (row.state.equalsValue(PendingCreationState.pending) |
                (row.state.equalsValue(PendingCreationState.retryWait) &
                    row.nextAttemptAt.isNotNull() &
                    row.nextAttemptAt.isSmallerOrEqualValue(now))),
      )
      ..orderBy([
        (row) => OrderingTerm.asc(row.createdAt),
        (row) => OrderingTerm.asc(row.localId),
      ])
      ..limit(1);
    final row = await query.getSingleOrNull();
    if (row == null) return null;

    return PendingCardCreation(
      localId: row.localId,
      accountId: row.accountId,
      text: row.requestText,
      contentType: row.contentType,
      memoryStyle: row.memoryStyle,
      attemptCount: row.attemptCount,
    );
  }

  @override
  Future<DateTime?> nextRetryAt(int accountId) async {
    final query = _database.select(_database.pendingCardCreations)
      ..where(
        (row) =>
            row.accountId.equals(accountId) &
            row.state.equalsValue(PendingCreationState.retryWait) &
            row.nextAttemptAt.isNotNull(),
      )
      ..orderBy([(row) => OrderingTerm.asc(row.nextAttemptAt)])
      ..limit(1);
    return (await query.getSingleOrNull())?.nextAttemptAt?.toUtc();
  }

  @override
  Future<void> markSending(int accountId, String localId) async {
    await (_database.update(_database.pendingCardCreations)..where(
          (row) =>
              row.accountId.equals(accountId) & row.localId.equals(localId),
        ))
        .write(
          PendingCardCreationsCompanion(
            state: const Value(PendingCreationState.sending),
            nextAttemptAt: const Value(null),
            lastErrorCode: const Value(null),
            updatedAt: Value(_now()),
          ),
        );
  }

  @override
  Future<void> markRetry(
    int accountId,
    String localId, {
    required int attempts,
    required DateTime at,
    required String code,
  }) async {
    await (_database.update(_database.pendingCardCreations)..where(
          (row) =>
              row.accountId.equals(accountId) & row.localId.equals(localId),
        ))
        .write(
          PendingCardCreationsCompanion(
            state: const Value(PendingCreationState.retryWait),
            attemptCount: Value(attempts),
            nextAttemptAt: Value(at),
            lastErrorCode: Value(code),
            updatedAt: Value(_now()),
          ),
        );
  }

  @override
  Future<void> markBlocked(int accountId, String localId, String code) {
    final timestamp = _now();
    return _database.transaction(() async {
      await (_database.update(_database.pendingCardCreations)..where(
            (row) =>
                row.accountId.equals(accountId) & row.localId.equals(localId),
          ))
          .write(
            PendingCardCreationsCompanion(
              state: const Value(PendingCreationState.blocked),
              nextAttemptAt: const Value(null),
              lastErrorCode: Value(code),
              updatedAt: Value(timestamp),
            ),
          );
      await (_database.update(_database.cachedCards)..where(
            (row) =>
                row.accountId.equals(accountId) & row.localId.equals(localId),
          ))
          .write(
            CachedCardsCompanion(
              localStatus: const Value(CachedCardLocalStatus.blocked),
              updatedAt: Value(timestamp),
            ),
          );
    });
  }

  @override
  Future<void> complete(
    int accountId,
    String localId,
    CardCreationAccepted result,
  ) {
    final timestamp = _now();
    return _database.transaction(() async {
      final updated =
          await (_database.update(_database.cachedCards)..where(
                (row) =>
                    row.accountId.equals(accountId) &
                    row.localId.equals(localId),
              ))
              .write(
                CachedCardsCompanion(
                  localStatus: const Value(CachedCardLocalStatus.queued),
                  serverCardId: Value(result.cardId),
                  serverJobId: Value(result.jobId),
                  generationStatus: Value(result.status),
                  updatedAt: Value(timestamp),
                ),
              );
      if (updated != 1) {
        throw StateError('Cached card placeholder is missing.');
      }
      await (_database.delete(_database.pendingCardCreations)..where(
            (row) =>
                row.accountId.equals(accountId) & row.localId.equals(localId),
          ))
          .go();
    });
  }

  @override
  Future<void> recoverSending(int accountId) async {
    await (_database.update(_database.pendingCardCreations)..where(
          (row) =>
              row.accountId.equals(accountId) &
              row.state.equalsValue(PendingCreationState.sending),
        ))
        .write(
          PendingCardCreationsCompanion(
            state: const Value(PendingCreationState.pending),
            updatedAt: Value(_now()),
          ),
        );
  }

  @override
  Future<void> retryBlocked(int accountId, String localId) {
    final timestamp = _now();
    return _database.transaction(() async {
      await (_database.update(_database.pendingCardCreations)..where(
            (row) =>
                row.accountId.equals(accountId) &
                row.localId.equals(localId) &
                row.state.equalsValue(PendingCreationState.blocked),
          ))
          .write(
            PendingCardCreationsCompanion(
              state: const Value(PendingCreationState.pending),
              attemptCount: const Value(0),
              nextAttemptAt: const Value(null),
              lastErrorCode: const Value(null),
              updatedAt: Value(timestamp),
            ),
          );
      await (_database.update(_database.cachedCards)..where(
            (row) =>
                row.accountId.equals(accountId) & row.localId.equals(localId),
          ))
          .write(
            CachedCardsCompanion(
              localStatus: const Value(CachedCardLocalStatus.pending),
              updatedAt: Value(timestamp),
            ),
          );
    });
  }

  @override
  Stream<int> watchOutstandingCount(int accountId) =>
      (_database.select(_database.pendingCardCreations)
            ..where((row) => row.accountId.equals(accountId)))
          .watch()
          .map((rows) => rows.length);

  @override
  Future<int> outstandingCount(int accountId) async =>
      (_database.select(_database.pendingCardCreations)
            ..where((row) => row.accountId.equals(accountId)))
          .get()
          .then((rows) => rows.length);

  @override
  Future<void> clearAccount(int accountId) {
    return _database.transaction(() async {
      await (_database.delete(
        _database.pendingCardCreations,
      )..where((row) => row.accountId.equals(accountId))).go();
      await (_database.delete(
        _database.cachedCards,
      )..where((row) => row.accountId.equals(accountId))).go();
      await (_database.delete(
        _database.syncStates,
      )..where((row) => row.accountId.equals(accountId))).go();
    });
  }
}
