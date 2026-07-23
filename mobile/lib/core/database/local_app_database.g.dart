// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'local_app_database.dart';

// ignore_for_file: type=lint
class $PendingCardCreationsTable extends PendingCardCreations
    with TableInfo<$PendingCardCreationsTable, PendingCardCreationRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingCardCreationsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _localIdMeta = const VerificationMeta(
    'localId',
  );
  @override
  late final GeneratedColumn<String> localId = GeneratedColumn<String>(
    'local_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _accountIdMeta = const VerificationMeta(
    'accountId',
  );
  @override
  late final GeneratedColumn<int> accountId = GeneratedColumn<int>(
    'account_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _requestTextMeta = const VerificationMeta(
    'requestText',
  );
  @override
  late final GeneratedColumn<String> requestText = GeneratedColumn<String>(
    'text',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _contentTypeMeta = const VerificationMeta(
    'contentType',
  );
  @override
  late final GeneratedColumn<String> contentType = GeneratedColumn<String>(
    'content_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _memoryStyleMeta = const VerificationMeta(
    'memoryStyle',
  );
  @override
  late final GeneratedColumn<String> memoryStyle = GeneratedColumn<String>(
    'memory_style',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  late final GeneratedColumnWithTypeConverter<PendingCreationState, String>
  state =
      GeneratedColumn<String>(
        'state',
        aliasedName,
        false,
        type: DriftSqlType.string,
        requiredDuringInsert: true,
      ).withConverter<PendingCreationState>(
        $PendingCardCreationsTable.$converterstate,
      );
  static const VerificationMeta _attemptCountMeta = const VerificationMeta(
    'attemptCount',
  );
  @override
  late final GeneratedColumn<int> attemptCount = GeneratedColumn<int>(
    'attempt_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextAttemptAtMeta = const VerificationMeta(
    'nextAttemptAt',
  );
  @override
  late final GeneratedColumn<DateTime> nextAttemptAt =
      GeneratedColumn<DateTime>(
        'next_attempt_at',
        aliasedName,
        true,
        type: DriftSqlType.dateTime,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _lastErrorCodeMeta = const VerificationMeta(
    'lastErrorCode',
  );
  @override
  late final GeneratedColumn<String> lastErrorCode = GeneratedColumn<String>(
    'last_error_code',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    localId,
    accountId,
    requestText,
    contentType,
    memoryStyle,
    state,
    attemptCount,
    nextAttemptAt,
    lastErrorCode,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_card_creations';
  @override
  VerificationContext validateIntegrity(
    Insertable<PendingCardCreationRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('local_id')) {
      context.handle(
        _localIdMeta,
        localId.isAcceptableOrUnknown(data['local_id']!, _localIdMeta),
      );
    } else if (isInserting) {
      context.missing(_localIdMeta);
    }
    if (data.containsKey('account_id')) {
      context.handle(
        _accountIdMeta,
        accountId.isAcceptableOrUnknown(data['account_id']!, _accountIdMeta),
      );
    } else if (isInserting) {
      context.missing(_accountIdMeta);
    }
    if (data.containsKey('text')) {
      context.handle(
        _requestTextMeta,
        requestText.isAcceptableOrUnknown(data['text']!, _requestTextMeta),
      );
    } else if (isInserting) {
      context.missing(_requestTextMeta);
    }
    if (data.containsKey('content_type')) {
      context.handle(
        _contentTypeMeta,
        contentType.isAcceptableOrUnknown(
          data['content_type']!,
          _contentTypeMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_contentTypeMeta);
    }
    if (data.containsKey('memory_style')) {
      context.handle(
        _memoryStyleMeta,
        memoryStyle.isAcceptableOrUnknown(
          data['memory_style']!,
          _memoryStyleMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_memoryStyleMeta);
    }
    if (data.containsKey('attempt_count')) {
      context.handle(
        _attemptCountMeta,
        attemptCount.isAcceptableOrUnknown(
          data['attempt_count']!,
          _attemptCountMeta,
        ),
      );
    }
    if (data.containsKey('next_attempt_at')) {
      context.handle(
        _nextAttemptAtMeta,
        nextAttemptAt.isAcceptableOrUnknown(
          data['next_attempt_at']!,
          _nextAttemptAtMeta,
        ),
      );
    }
    if (data.containsKey('last_error_code')) {
      context.handle(
        _lastErrorCodeMeta,
        lastErrorCode.isAcceptableOrUnknown(
          data['last_error_code']!,
          _lastErrorCodeMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {localId};
  @override
  PendingCardCreationRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingCardCreationRow(
      localId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_id'],
      )!,
      accountId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}account_id'],
      )!,
      requestText: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}text'],
      )!,
      contentType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}content_type'],
      )!,
      memoryStyle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}memory_style'],
      )!,
      state: $PendingCardCreationsTable.$converterstate.fromSql(
        attachedDatabase.typeMapping.read(
          DriftSqlType.string,
          data['${effectivePrefix}state'],
        )!,
      ),
      attemptCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempt_count'],
      )!,
      nextAttemptAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}next_attempt_at'],
      ),
      lastErrorCode: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}last_error_code'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $PendingCardCreationsTable createAlias(String alias) {
    return $PendingCardCreationsTable(attachedDatabase, alias);
  }

  static TypeConverter<PendingCreationState, String> $converterstate =
      const PendingCreationStateConverter();
}

class PendingCardCreationRow extends DataClass
    implements Insertable<PendingCardCreationRow> {
  final String localId;
  final int accountId;
  final String requestText;
  final String contentType;
  final String memoryStyle;
  final PendingCreationState state;
  final int attemptCount;
  final DateTime? nextAttemptAt;
  final String? lastErrorCode;
  final DateTime createdAt;
  final DateTime updatedAt;
  const PendingCardCreationRow({
    required this.localId,
    required this.accountId,
    required this.requestText,
    required this.contentType,
    required this.memoryStyle,
    required this.state,
    required this.attemptCount,
    this.nextAttemptAt,
    this.lastErrorCode,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['local_id'] = Variable<String>(localId);
    map['account_id'] = Variable<int>(accountId);
    map['text'] = Variable<String>(requestText);
    map['content_type'] = Variable<String>(contentType);
    map['memory_style'] = Variable<String>(memoryStyle);
    {
      map['state'] = Variable<String>(
        $PendingCardCreationsTable.$converterstate.toSql(state),
      );
    }
    map['attempt_count'] = Variable<int>(attemptCount);
    if (!nullToAbsent || nextAttemptAt != null) {
      map['next_attempt_at'] = Variable<DateTime>(nextAttemptAt);
    }
    if (!nullToAbsent || lastErrorCode != null) {
      map['last_error_code'] = Variable<String>(lastErrorCode);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  PendingCardCreationsCompanion toCompanion(bool nullToAbsent) {
    return PendingCardCreationsCompanion(
      localId: Value(localId),
      accountId: Value(accountId),
      requestText: Value(requestText),
      contentType: Value(contentType),
      memoryStyle: Value(memoryStyle),
      state: Value(state),
      attemptCount: Value(attemptCount),
      nextAttemptAt: nextAttemptAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextAttemptAt),
      lastErrorCode: lastErrorCode == null && nullToAbsent
          ? const Value.absent()
          : Value(lastErrorCode),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory PendingCardCreationRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingCardCreationRow(
      localId: serializer.fromJson<String>(json['localId']),
      accountId: serializer.fromJson<int>(json['accountId']),
      requestText: serializer.fromJson<String>(json['requestText']),
      contentType: serializer.fromJson<String>(json['contentType']),
      memoryStyle: serializer.fromJson<String>(json['memoryStyle']),
      state: serializer.fromJson<PendingCreationState>(json['state']),
      attemptCount: serializer.fromJson<int>(json['attemptCount']),
      nextAttemptAt: serializer.fromJson<DateTime?>(json['nextAttemptAt']),
      lastErrorCode: serializer.fromJson<String?>(json['lastErrorCode']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'localId': serializer.toJson<String>(localId),
      'accountId': serializer.toJson<int>(accountId),
      'requestText': serializer.toJson<String>(requestText),
      'contentType': serializer.toJson<String>(contentType),
      'memoryStyle': serializer.toJson<String>(memoryStyle),
      'state': serializer.toJson<PendingCreationState>(state),
      'attemptCount': serializer.toJson<int>(attemptCount),
      'nextAttemptAt': serializer.toJson<DateTime?>(nextAttemptAt),
      'lastErrorCode': serializer.toJson<String?>(lastErrorCode),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  PendingCardCreationRow copyWith({
    String? localId,
    int? accountId,
    String? requestText,
    String? contentType,
    String? memoryStyle,
    PendingCreationState? state,
    int? attemptCount,
    Value<DateTime?> nextAttemptAt = const Value.absent(),
    Value<String?> lastErrorCode = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => PendingCardCreationRow(
    localId: localId ?? this.localId,
    accountId: accountId ?? this.accountId,
    requestText: requestText ?? this.requestText,
    contentType: contentType ?? this.contentType,
    memoryStyle: memoryStyle ?? this.memoryStyle,
    state: state ?? this.state,
    attemptCount: attemptCount ?? this.attemptCount,
    nextAttemptAt: nextAttemptAt.present
        ? nextAttemptAt.value
        : this.nextAttemptAt,
    lastErrorCode: lastErrorCode.present
        ? lastErrorCode.value
        : this.lastErrorCode,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  PendingCardCreationRow copyWithCompanion(PendingCardCreationsCompanion data) {
    return PendingCardCreationRow(
      localId: data.localId.present ? data.localId.value : this.localId,
      accountId: data.accountId.present ? data.accountId.value : this.accountId,
      requestText: data.requestText.present
          ? data.requestText.value
          : this.requestText,
      contentType: data.contentType.present
          ? data.contentType.value
          : this.contentType,
      memoryStyle: data.memoryStyle.present
          ? data.memoryStyle.value
          : this.memoryStyle,
      state: data.state.present ? data.state.value : this.state,
      attemptCount: data.attemptCount.present
          ? data.attemptCount.value
          : this.attemptCount,
      nextAttemptAt: data.nextAttemptAt.present
          ? data.nextAttemptAt.value
          : this.nextAttemptAt,
      lastErrorCode: data.lastErrorCode.present
          ? data.lastErrorCode.value
          : this.lastErrorCode,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingCardCreationRow(')
          ..write('localId: $localId, ')
          ..write('accountId: $accountId, ')
          ..write('requestText: $requestText, ')
          ..write('contentType: $contentType, ')
          ..write('memoryStyle: $memoryStyle, ')
          ..write('state: $state, ')
          ..write('attemptCount: $attemptCount, ')
          ..write('nextAttemptAt: $nextAttemptAt, ')
          ..write('lastErrorCode: $lastErrorCode, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    localId,
    accountId,
    requestText,
    contentType,
    memoryStyle,
    state,
    attemptCount,
    nextAttemptAt,
    lastErrorCode,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingCardCreationRow &&
          other.localId == this.localId &&
          other.accountId == this.accountId &&
          other.requestText == this.requestText &&
          other.contentType == this.contentType &&
          other.memoryStyle == this.memoryStyle &&
          other.state == this.state &&
          other.attemptCount == this.attemptCount &&
          other.nextAttemptAt == this.nextAttemptAt &&
          other.lastErrorCode == this.lastErrorCode &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class PendingCardCreationsCompanion
    extends UpdateCompanion<PendingCardCreationRow> {
  final Value<String> localId;
  final Value<int> accountId;
  final Value<String> requestText;
  final Value<String> contentType;
  final Value<String> memoryStyle;
  final Value<PendingCreationState> state;
  final Value<int> attemptCount;
  final Value<DateTime?> nextAttemptAt;
  final Value<String?> lastErrorCode;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const PendingCardCreationsCompanion({
    this.localId = const Value.absent(),
    this.accountId = const Value.absent(),
    this.requestText = const Value.absent(),
    this.contentType = const Value.absent(),
    this.memoryStyle = const Value.absent(),
    this.state = const Value.absent(),
    this.attemptCount = const Value.absent(),
    this.nextAttemptAt = const Value.absent(),
    this.lastErrorCode = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PendingCardCreationsCompanion.insert({
    required String localId,
    required int accountId,
    required String requestText,
    required String contentType,
    required String memoryStyle,
    required PendingCreationState state,
    this.attemptCount = const Value.absent(),
    this.nextAttemptAt = const Value.absent(),
    this.lastErrorCode = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : localId = Value(localId),
       accountId = Value(accountId),
       requestText = Value(requestText),
       contentType = Value(contentType),
       memoryStyle = Value(memoryStyle),
       state = Value(state),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<PendingCardCreationRow> custom({
    Expression<String>? localId,
    Expression<int>? accountId,
    Expression<String>? requestText,
    Expression<String>? contentType,
    Expression<String>? memoryStyle,
    Expression<String>? state,
    Expression<int>? attemptCount,
    Expression<DateTime>? nextAttemptAt,
    Expression<String>? lastErrorCode,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (localId != null) 'local_id': localId,
      if (accountId != null) 'account_id': accountId,
      if (requestText != null) 'text': requestText,
      if (contentType != null) 'content_type': contentType,
      if (memoryStyle != null) 'memory_style': memoryStyle,
      if (state != null) 'state': state,
      if (attemptCount != null) 'attempt_count': attemptCount,
      if (nextAttemptAt != null) 'next_attempt_at': nextAttemptAt,
      if (lastErrorCode != null) 'last_error_code': lastErrorCode,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PendingCardCreationsCompanion copyWith({
    Value<String>? localId,
    Value<int>? accountId,
    Value<String>? requestText,
    Value<String>? contentType,
    Value<String>? memoryStyle,
    Value<PendingCreationState>? state,
    Value<int>? attemptCount,
    Value<DateTime?>? nextAttemptAt,
    Value<String?>? lastErrorCode,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return PendingCardCreationsCompanion(
      localId: localId ?? this.localId,
      accountId: accountId ?? this.accountId,
      requestText: requestText ?? this.requestText,
      contentType: contentType ?? this.contentType,
      memoryStyle: memoryStyle ?? this.memoryStyle,
      state: state ?? this.state,
      attemptCount: attemptCount ?? this.attemptCount,
      nextAttemptAt: nextAttemptAt ?? this.nextAttemptAt,
      lastErrorCode: lastErrorCode ?? this.lastErrorCode,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (localId.present) {
      map['local_id'] = Variable<String>(localId.value);
    }
    if (accountId.present) {
      map['account_id'] = Variable<int>(accountId.value);
    }
    if (requestText.present) {
      map['text'] = Variable<String>(requestText.value);
    }
    if (contentType.present) {
      map['content_type'] = Variable<String>(contentType.value);
    }
    if (memoryStyle.present) {
      map['memory_style'] = Variable<String>(memoryStyle.value);
    }
    if (state.present) {
      map['state'] = Variable<String>(
        $PendingCardCreationsTable.$converterstate.toSql(state.value),
      );
    }
    if (attemptCount.present) {
      map['attempt_count'] = Variable<int>(attemptCount.value);
    }
    if (nextAttemptAt.present) {
      map['next_attempt_at'] = Variable<DateTime>(nextAttemptAt.value);
    }
    if (lastErrorCode.present) {
      map['last_error_code'] = Variable<String>(lastErrorCode.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingCardCreationsCompanion(')
          ..write('localId: $localId, ')
          ..write('accountId: $accountId, ')
          ..write('requestText: $requestText, ')
          ..write('contentType: $contentType, ')
          ..write('memoryStyle: $memoryStyle, ')
          ..write('state: $state, ')
          ..write('attemptCount: $attemptCount, ')
          ..write('nextAttemptAt: $nextAttemptAt, ')
          ..write('lastErrorCode: $lastErrorCode, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedCardsTable extends CachedCards
    with TableInfo<$CachedCardsTable, CachedCardRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedCardsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _localIdMeta = const VerificationMeta(
    'localId',
  );
  @override
  late final GeneratedColumn<String> localId = GeneratedColumn<String>(
    'local_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _accountIdMeta = const VerificationMeta(
    'accountId',
  );
  @override
  late final GeneratedColumn<int> accountId = GeneratedColumn<int>(
    'account_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _sourceTextMeta = const VerificationMeta(
    'sourceText',
  );
  @override
  late final GeneratedColumn<String> sourceText = GeneratedColumn<String>(
    'source_text',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _contentTypeMeta = const VerificationMeta(
    'contentType',
  );
  @override
  late final GeneratedColumn<String> contentType = GeneratedColumn<String>(
    'content_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _memoryStyleMeta = const VerificationMeta(
    'memoryStyle',
  );
  @override
  late final GeneratedColumn<String> memoryStyle = GeneratedColumn<String>(
    'memory_style',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  late final GeneratedColumnWithTypeConverter<CachedCardLocalStatus, String>
  localStatus =
      GeneratedColumn<String>(
        'local_status',
        aliasedName,
        false,
        type: DriftSqlType.string,
        requiredDuringInsert: true,
      ).withConverter<CachedCardLocalStatus>(
        $CachedCardsTable.$converterlocalStatus,
      );
  static const VerificationMeta _serverCardIdMeta = const VerificationMeta(
    'serverCardId',
  );
  @override
  late final GeneratedColumn<int> serverCardId = GeneratedColumn<int>(
    'server_card_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _serverJobIdMeta = const VerificationMeta(
    'serverJobId',
  );
  @override
  late final GeneratedColumn<int> serverJobId = GeneratedColumn<int>(
    'server_job_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _generationStatusMeta = const VerificationMeta(
    'generationStatus',
  );
  @override
  late final GeneratedColumn<String> generationStatus = GeneratedColumn<String>(
    'generation_status',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    localId,
    accountId,
    sourceText,
    contentType,
    memoryStyle,
    localStatus,
    serverCardId,
    serverJobId,
    generationStatus,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_cards';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedCardRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('local_id')) {
      context.handle(
        _localIdMeta,
        localId.isAcceptableOrUnknown(data['local_id']!, _localIdMeta),
      );
    } else if (isInserting) {
      context.missing(_localIdMeta);
    }
    if (data.containsKey('account_id')) {
      context.handle(
        _accountIdMeta,
        accountId.isAcceptableOrUnknown(data['account_id']!, _accountIdMeta),
      );
    } else if (isInserting) {
      context.missing(_accountIdMeta);
    }
    if (data.containsKey('source_text')) {
      context.handle(
        _sourceTextMeta,
        sourceText.isAcceptableOrUnknown(data['source_text']!, _sourceTextMeta),
      );
    } else if (isInserting) {
      context.missing(_sourceTextMeta);
    }
    if (data.containsKey('content_type')) {
      context.handle(
        _contentTypeMeta,
        contentType.isAcceptableOrUnknown(
          data['content_type']!,
          _contentTypeMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_contentTypeMeta);
    }
    if (data.containsKey('memory_style')) {
      context.handle(
        _memoryStyleMeta,
        memoryStyle.isAcceptableOrUnknown(
          data['memory_style']!,
          _memoryStyleMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_memoryStyleMeta);
    }
    if (data.containsKey('server_card_id')) {
      context.handle(
        _serverCardIdMeta,
        serverCardId.isAcceptableOrUnknown(
          data['server_card_id']!,
          _serverCardIdMeta,
        ),
      );
    }
    if (data.containsKey('server_job_id')) {
      context.handle(
        _serverJobIdMeta,
        serverJobId.isAcceptableOrUnknown(
          data['server_job_id']!,
          _serverJobIdMeta,
        ),
      );
    }
    if (data.containsKey('generation_status')) {
      context.handle(
        _generationStatusMeta,
        generationStatus.isAcceptableOrUnknown(
          data['generation_status']!,
          _generationStatusMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {localId};
  @override
  List<Set<GeneratedColumn>> get uniqueKeys => [
    {accountId, serverCardId},
  ];
  @override
  CachedCardRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedCardRow(
      localId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_id'],
      )!,
      accountId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}account_id'],
      )!,
      sourceText: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}source_text'],
      )!,
      contentType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}content_type'],
      )!,
      memoryStyle: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}memory_style'],
      )!,
      localStatus: $CachedCardsTable.$converterlocalStatus.fromSql(
        attachedDatabase.typeMapping.read(
          DriftSqlType.string,
          data['${effectivePrefix}local_status'],
        )!,
      ),
      serverCardId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}server_card_id'],
      ),
      serverJobId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}server_job_id'],
      ),
      generationStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}generation_status'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $CachedCardsTable createAlias(String alias) {
    return $CachedCardsTable(attachedDatabase, alias);
  }

  static TypeConverter<CachedCardLocalStatus, String> $converterlocalStatus =
      const CachedCardLocalStatusConverter();
}

class CachedCardRow extends DataClass implements Insertable<CachedCardRow> {
  final String localId;
  final int accountId;
  final String sourceText;
  final String contentType;
  final String memoryStyle;
  final CachedCardLocalStatus localStatus;
  final int? serverCardId;
  final int? serverJobId;
  final String? generationStatus;
  final DateTime createdAt;
  final DateTime updatedAt;
  const CachedCardRow({
    required this.localId,
    required this.accountId,
    required this.sourceText,
    required this.contentType,
    required this.memoryStyle,
    required this.localStatus,
    this.serverCardId,
    this.serverJobId,
    this.generationStatus,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['local_id'] = Variable<String>(localId);
    map['account_id'] = Variable<int>(accountId);
    map['source_text'] = Variable<String>(sourceText);
    map['content_type'] = Variable<String>(contentType);
    map['memory_style'] = Variable<String>(memoryStyle);
    {
      map['local_status'] = Variable<String>(
        $CachedCardsTable.$converterlocalStatus.toSql(localStatus),
      );
    }
    if (!nullToAbsent || serverCardId != null) {
      map['server_card_id'] = Variable<int>(serverCardId);
    }
    if (!nullToAbsent || serverJobId != null) {
      map['server_job_id'] = Variable<int>(serverJobId);
    }
    if (!nullToAbsent || generationStatus != null) {
      map['generation_status'] = Variable<String>(generationStatus);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  CachedCardsCompanion toCompanion(bool nullToAbsent) {
    return CachedCardsCompanion(
      localId: Value(localId),
      accountId: Value(accountId),
      sourceText: Value(sourceText),
      contentType: Value(contentType),
      memoryStyle: Value(memoryStyle),
      localStatus: Value(localStatus),
      serverCardId: serverCardId == null && nullToAbsent
          ? const Value.absent()
          : Value(serverCardId),
      serverJobId: serverJobId == null && nullToAbsent
          ? const Value.absent()
          : Value(serverJobId),
      generationStatus: generationStatus == null && nullToAbsent
          ? const Value.absent()
          : Value(generationStatus),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory CachedCardRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedCardRow(
      localId: serializer.fromJson<String>(json['localId']),
      accountId: serializer.fromJson<int>(json['accountId']),
      sourceText: serializer.fromJson<String>(json['sourceText']),
      contentType: serializer.fromJson<String>(json['contentType']),
      memoryStyle: serializer.fromJson<String>(json['memoryStyle']),
      localStatus: serializer.fromJson<CachedCardLocalStatus>(
        json['localStatus'],
      ),
      serverCardId: serializer.fromJson<int?>(json['serverCardId']),
      serverJobId: serializer.fromJson<int?>(json['serverJobId']),
      generationStatus: serializer.fromJson<String?>(json['generationStatus']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'localId': serializer.toJson<String>(localId),
      'accountId': serializer.toJson<int>(accountId),
      'sourceText': serializer.toJson<String>(sourceText),
      'contentType': serializer.toJson<String>(contentType),
      'memoryStyle': serializer.toJson<String>(memoryStyle),
      'localStatus': serializer.toJson<CachedCardLocalStatus>(localStatus),
      'serverCardId': serializer.toJson<int?>(serverCardId),
      'serverJobId': serializer.toJson<int?>(serverJobId),
      'generationStatus': serializer.toJson<String?>(generationStatus),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  CachedCardRow copyWith({
    String? localId,
    int? accountId,
    String? sourceText,
    String? contentType,
    String? memoryStyle,
    CachedCardLocalStatus? localStatus,
    Value<int?> serverCardId = const Value.absent(),
    Value<int?> serverJobId = const Value.absent(),
    Value<String?> generationStatus = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => CachedCardRow(
    localId: localId ?? this.localId,
    accountId: accountId ?? this.accountId,
    sourceText: sourceText ?? this.sourceText,
    contentType: contentType ?? this.contentType,
    memoryStyle: memoryStyle ?? this.memoryStyle,
    localStatus: localStatus ?? this.localStatus,
    serverCardId: serverCardId.present ? serverCardId.value : this.serverCardId,
    serverJobId: serverJobId.present ? serverJobId.value : this.serverJobId,
    generationStatus: generationStatus.present
        ? generationStatus.value
        : this.generationStatus,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  CachedCardRow copyWithCompanion(CachedCardsCompanion data) {
    return CachedCardRow(
      localId: data.localId.present ? data.localId.value : this.localId,
      accountId: data.accountId.present ? data.accountId.value : this.accountId,
      sourceText: data.sourceText.present
          ? data.sourceText.value
          : this.sourceText,
      contentType: data.contentType.present
          ? data.contentType.value
          : this.contentType,
      memoryStyle: data.memoryStyle.present
          ? data.memoryStyle.value
          : this.memoryStyle,
      localStatus: data.localStatus.present
          ? data.localStatus.value
          : this.localStatus,
      serverCardId: data.serverCardId.present
          ? data.serverCardId.value
          : this.serverCardId,
      serverJobId: data.serverJobId.present
          ? data.serverJobId.value
          : this.serverJobId,
      generationStatus: data.generationStatus.present
          ? data.generationStatus.value
          : this.generationStatus,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedCardRow(')
          ..write('localId: $localId, ')
          ..write('accountId: $accountId, ')
          ..write('sourceText: $sourceText, ')
          ..write('contentType: $contentType, ')
          ..write('memoryStyle: $memoryStyle, ')
          ..write('localStatus: $localStatus, ')
          ..write('serverCardId: $serverCardId, ')
          ..write('serverJobId: $serverJobId, ')
          ..write('generationStatus: $generationStatus, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    localId,
    accountId,
    sourceText,
    contentType,
    memoryStyle,
    localStatus,
    serverCardId,
    serverJobId,
    generationStatus,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedCardRow &&
          other.localId == this.localId &&
          other.accountId == this.accountId &&
          other.sourceText == this.sourceText &&
          other.contentType == this.contentType &&
          other.memoryStyle == this.memoryStyle &&
          other.localStatus == this.localStatus &&
          other.serverCardId == this.serverCardId &&
          other.serverJobId == this.serverJobId &&
          other.generationStatus == this.generationStatus &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class CachedCardsCompanion extends UpdateCompanion<CachedCardRow> {
  final Value<String> localId;
  final Value<int> accountId;
  final Value<String> sourceText;
  final Value<String> contentType;
  final Value<String> memoryStyle;
  final Value<CachedCardLocalStatus> localStatus;
  final Value<int?> serverCardId;
  final Value<int?> serverJobId;
  final Value<String?> generationStatus;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const CachedCardsCompanion({
    this.localId = const Value.absent(),
    this.accountId = const Value.absent(),
    this.sourceText = const Value.absent(),
    this.contentType = const Value.absent(),
    this.memoryStyle = const Value.absent(),
    this.localStatus = const Value.absent(),
    this.serverCardId = const Value.absent(),
    this.serverJobId = const Value.absent(),
    this.generationStatus = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedCardsCompanion.insert({
    required String localId,
    required int accountId,
    required String sourceText,
    required String contentType,
    required String memoryStyle,
    required CachedCardLocalStatus localStatus,
    this.serverCardId = const Value.absent(),
    this.serverJobId = const Value.absent(),
    this.generationStatus = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : localId = Value(localId),
       accountId = Value(accountId),
       sourceText = Value(sourceText),
       contentType = Value(contentType),
       memoryStyle = Value(memoryStyle),
       localStatus = Value(localStatus),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<CachedCardRow> custom({
    Expression<String>? localId,
    Expression<int>? accountId,
    Expression<String>? sourceText,
    Expression<String>? contentType,
    Expression<String>? memoryStyle,
    Expression<String>? localStatus,
    Expression<int>? serverCardId,
    Expression<int>? serverJobId,
    Expression<String>? generationStatus,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (localId != null) 'local_id': localId,
      if (accountId != null) 'account_id': accountId,
      if (sourceText != null) 'source_text': sourceText,
      if (contentType != null) 'content_type': contentType,
      if (memoryStyle != null) 'memory_style': memoryStyle,
      if (localStatus != null) 'local_status': localStatus,
      if (serverCardId != null) 'server_card_id': serverCardId,
      if (serverJobId != null) 'server_job_id': serverJobId,
      if (generationStatus != null) 'generation_status': generationStatus,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedCardsCompanion copyWith({
    Value<String>? localId,
    Value<int>? accountId,
    Value<String>? sourceText,
    Value<String>? contentType,
    Value<String>? memoryStyle,
    Value<CachedCardLocalStatus>? localStatus,
    Value<int?>? serverCardId,
    Value<int?>? serverJobId,
    Value<String?>? generationStatus,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return CachedCardsCompanion(
      localId: localId ?? this.localId,
      accountId: accountId ?? this.accountId,
      sourceText: sourceText ?? this.sourceText,
      contentType: contentType ?? this.contentType,
      memoryStyle: memoryStyle ?? this.memoryStyle,
      localStatus: localStatus ?? this.localStatus,
      serverCardId: serverCardId ?? this.serverCardId,
      serverJobId: serverJobId ?? this.serverJobId,
      generationStatus: generationStatus ?? this.generationStatus,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (localId.present) {
      map['local_id'] = Variable<String>(localId.value);
    }
    if (accountId.present) {
      map['account_id'] = Variable<int>(accountId.value);
    }
    if (sourceText.present) {
      map['source_text'] = Variable<String>(sourceText.value);
    }
    if (contentType.present) {
      map['content_type'] = Variable<String>(contentType.value);
    }
    if (memoryStyle.present) {
      map['memory_style'] = Variable<String>(memoryStyle.value);
    }
    if (localStatus.present) {
      map['local_status'] = Variable<String>(
        $CachedCardsTable.$converterlocalStatus.toSql(localStatus.value),
      );
    }
    if (serverCardId.present) {
      map['server_card_id'] = Variable<int>(serverCardId.value);
    }
    if (serverJobId.present) {
      map['server_job_id'] = Variable<int>(serverJobId.value);
    }
    if (generationStatus.present) {
      map['generation_status'] = Variable<String>(generationStatus.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedCardsCompanion(')
          ..write('localId: $localId, ')
          ..write('accountId: $accountId, ')
          ..write('sourceText: $sourceText, ')
          ..write('contentType: $contentType, ')
          ..write('memoryStyle: $memoryStyle, ')
          ..write('localStatus: $localStatus, ')
          ..write('serverCardId: $serverCardId, ')
          ..write('serverJobId: $serverJobId, ')
          ..write('generationStatus: $generationStatus, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $SyncStatesTable extends SyncStates
    with TableInfo<$SyncStatesTable, SyncState> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SyncStatesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _accountIdMeta = const VerificationMeta(
    'accountId',
  );
  @override
  late final GeneratedColumn<int> accountId = GeneratedColumn<int>(
    'account_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _cursorMeta = const VerificationMeta('cursor');
  @override
  late final GeneratedColumn<int> cursor = GeneratedColumn<int>(
    'cursor',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [accountId, cursor, updatedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sync_states';
  @override
  VerificationContext validateIntegrity(
    Insertable<SyncState> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('account_id')) {
      context.handle(
        _accountIdMeta,
        accountId.isAcceptableOrUnknown(data['account_id']!, _accountIdMeta),
      );
    }
    if (data.containsKey('cursor')) {
      context.handle(
        _cursorMeta,
        cursor.isAcceptableOrUnknown(data['cursor']!, _cursorMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {accountId};
  @override
  SyncState map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return SyncState(
      accountId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}account_id'],
      )!,
      cursor: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}cursor'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $SyncStatesTable createAlias(String alias) {
    return $SyncStatesTable(attachedDatabase, alias);
  }
}

class SyncState extends DataClass implements Insertable<SyncState> {
  final int accountId;
  final int cursor;
  final DateTime updatedAt;
  const SyncState({
    required this.accountId,
    required this.cursor,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['account_id'] = Variable<int>(accountId);
    map['cursor'] = Variable<int>(cursor);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  SyncStatesCompanion toCompanion(bool nullToAbsent) {
    return SyncStatesCompanion(
      accountId: Value(accountId),
      cursor: Value(cursor),
      updatedAt: Value(updatedAt),
    );
  }

  factory SyncState.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return SyncState(
      accountId: serializer.fromJson<int>(json['accountId']),
      cursor: serializer.fromJson<int>(json['cursor']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'accountId': serializer.toJson<int>(accountId),
      'cursor': serializer.toJson<int>(cursor),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  SyncState copyWith({int? accountId, int? cursor, DateTime? updatedAt}) =>
      SyncState(
        accountId: accountId ?? this.accountId,
        cursor: cursor ?? this.cursor,
        updatedAt: updatedAt ?? this.updatedAt,
      );
  SyncState copyWithCompanion(SyncStatesCompanion data) {
    return SyncState(
      accountId: data.accountId.present ? data.accountId.value : this.accountId,
      cursor: data.cursor.present ? data.cursor.value : this.cursor,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('SyncState(')
          ..write('accountId: $accountId, ')
          ..write('cursor: $cursor, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(accountId, cursor, updatedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is SyncState &&
          other.accountId == this.accountId &&
          other.cursor == this.cursor &&
          other.updatedAt == this.updatedAt);
}

class SyncStatesCompanion extends UpdateCompanion<SyncState> {
  final Value<int> accountId;
  final Value<int> cursor;
  final Value<DateTime> updatedAt;
  const SyncStatesCompanion({
    this.accountId = const Value.absent(),
    this.cursor = const Value.absent(),
    this.updatedAt = const Value.absent(),
  });
  SyncStatesCompanion.insert({
    this.accountId = const Value.absent(),
    this.cursor = const Value.absent(),
    required DateTime updatedAt,
  }) : updatedAt = Value(updatedAt);
  static Insertable<SyncState> custom({
    Expression<int>? accountId,
    Expression<int>? cursor,
    Expression<DateTime>? updatedAt,
  }) {
    return RawValuesInsertable({
      if (accountId != null) 'account_id': accountId,
      if (cursor != null) 'cursor': cursor,
      if (updatedAt != null) 'updated_at': updatedAt,
    });
  }

  SyncStatesCompanion copyWith({
    Value<int>? accountId,
    Value<int>? cursor,
    Value<DateTime>? updatedAt,
  }) {
    return SyncStatesCompanion(
      accountId: accountId ?? this.accountId,
      cursor: cursor ?? this.cursor,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (accountId.present) {
      map['account_id'] = Variable<int>(accountId.value);
    }
    if (cursor.present) {
      map['cursor'] = Variable<int>(cursor.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SyncStatesCompanion(')
          ..write('accountId: $accountId, ')
          ..write('cursor: $cursor, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }
}

abstract class _$LocalAppDatabase extends GeneratedDatabase {
  _$LocalAppDatabase(QueryExecutor e) : super(e);
  $LocalAppDatabaseManager get managers => $LocalAppDatabaseManager(this);
  late final $PendingCardCreationsTable pendingCardCreations =
      $PendingCardCreationsTable(this);
  late final $CachedCardsTable cachedCards = $CachedCardsTable(this);
  late final $SyncStatesTable syncStates = $SyncStatesTable(this);
  late final Index pendingCardCreationsReady = Index(
    'pending_card_creations_ready',
    'CREATE INDEX pending_card_creations_ready ON pending_card_creations (account_id, state, next_attempt_at, created_at)',
  );
  late final Index cachedCardsByStatus = Index(
    'cached_cards_by_status',
    'CREATE INDEX cached_cards_by_status ON cached_cards (account_id, local_status)',
  );
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    pendingCardCreations,
    cachedCards,
    syncStates,
    pendingCardCreationsReady,
    cachedCardsByStatus,
  ];
}

typedef $$PendingCardCreationsTableCreateCompanionBuilder =
    PendingCardCreationsCompanion Function({
      required String localId,
      required int accountId,
      required String requestText,
      required String contentType,
      required String memoryStyle,
      required PendingCreationState state,
      Value<int> attemptCount,
      Value<DateTime?> nextAttemptAt,
      Value<String?> lastErrorCode,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$PendingCardCreationsTableUpdateCompanionBuilder =
    PendingCardCreationsCompanion Function({
      Value<String> localId,
      Value<int> accountId,
      Value<String> requestText,
      Value<String> contentType,
      Value<String> memoryStyle,
      Value<PendingCreationState> state,
      Value<int> attemptCount,
      Value<DateTime?> nextAttemptAt,
      Value<String?> lastErrorCode,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$PendingCardCreationsTableFilterComposer
    extends Composer<_$LocalAppDatabase, $PendingCardCreationsTable> {
  $$PendingCardCreationsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get localId => $composableBuilder(
    column: $table.localId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get requestText => $composableBuilder(
    column: $table.requestText,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnWithTypeConverterFilters<
    PendingCreationState,
    PendingCreationState,
    String
  >
  get state => $composableBuilder(
    column: $table.state,
    builder: (column) => ColumnWithTypeConverterFilters(column),
  );

  ColumnFilters<int> get attemptCount => $composableBuilder(
    column: $table.attemptCount,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get lastErrorCode => $composableBuilder(
    column: $table.lastErrorCode,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PendingCardCreationsTableOrderingComposer
    extends Composer<_$LocalAppDatabase, $PendingCardCreationsTable> {
  $$PendingCardCreationsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get localId => $composableBuilder(
    column: $table.localId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get requestText => $composableBuilder(
    column: $table.requestText,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get state => $composableBuilder(
    column: $table.state,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attemptCount => $composableBuilder(
    column: $table.attemptCount,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get lastErrorCode => $composableBuilder(
    column: $table.lastErrorCode,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PendingCardCreationsTableAnnotationComposer
    extends Composer<_$LocalAppDatabase, $PendingCardCreationsTable> {
  $$PendingCardCreationsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get localId =>
      $composableBuilder(column: $table.localId, builder: (column) => column);

  GeneratedColumn<int> get accountId =>
      $composableBuilder(column: $table.accountId, builder: (column) => column);

  GeneratedColumn<String> get requestText => $composableBuilder(
    column: $table.requestText,
    builder: (column) => column,
  );

  GeneratedColumn<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => column,
  );

  GeneratedColumn<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => column,
  );

  GeneratedColumnWithTypeConverter<PendingCreationState, String> get state =>
      $composableBuilder(column: $table.state, builder: (column) => column);

  GeneratedColumn<int> get attemptCount => $composableBuilder(
    column: $table.attemptCount,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get nextAttemptAt => $composableBuilder(
    column: $table.nextAttemptAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get lastErrorCode => $composableBuilder(
    column: $table.lastErrorCode,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$PendingCardCreationsTableTableManager
    extends
        RootTableManager<
          _$LocalAppDatabase,
          $PendingCardCreationsTable,
          PendingCardCreationRow,
          $$PendingCardCreationsTableFilterComposer,
          $$PendingCardCreationsTableOrderingComposer,
          $$PendingCardCreationsTableAnnotationComposer,
          $$PendingCardCreationsTableCreateCompanionBuilder,
          $$PendingCardCreationsTableUpdateCompanionBuilder,
          (
            PendingCardCreationRow,
            BaseReferences<
              _$LocalAppDatabase,
              $PendingCardCreationsTable,
              PendingCardCreationRow
            >,
          ),
          PendingCardCreationRow,
          PrefetchHooks Function()
        > {
  $$PendingCardCreationsTableTableManager(
    _$LocalAppDatabase db,
    $PendingCardCreationsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingCardCreationsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingCardCreationsTableOrderingComposer(
                $db: db,
                $table: table,
              ),
          createComputedFieldComposer: () =>
              $$PendingCardCreationsTableAnnotationComposer(
                $db: db,
                $table: table,
              ),
          updateCompanionCallback:
              ({
                Value<String> localId = const Value.absent(),
                Value<int> accountId = const Value.absent(),
                Value<String> requestText = const Value.absent(),
                Value<String> contentType = const Value.absent(),
                Value<String> memoryStyle = const Value.absent(),
                Value<PendingCreationState> state = const Value.absent(),
                Value<int> attemptCount = const Value.absent(),
                Value<DateTime?> nextAttemptAt = const Value.absent(),
                Value<String?> lastErrorCode = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingCardCreationsCompanion(
                localId: localId,
                accountId: accountId,
                requestText: requestText,
                contentType: contentType,
                memoryStyle: memoryStyle,
                state: state,
                attemptCount: attemptCount,
                nextAttemptAt: nextAttemptAt,
                lastErrorCode: lastErrorCode,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String localId,
                required int accountId,
                required String requestText,
                required String contentType,
                required String memoryStyle,
                required PendingCreationState state,
                Value<int> attemptCount = const Value.absent(),
                Value<DateTime?> nextAttemptAt = const Value.absent(),
                Value<String?> lastErrorCode = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => PendingCardCreationsCompanion.insert(
                localId: localId,
                accountId: accountId,
                requestText: requestText,
                contentType: contentType,
                memoryStyle: memoryStyle,
                state: state,
                attemptCount: attemptCount,
                nextAttemptAt: nextAttemptAt,
                lastErrorCode: lastErrorCode,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PendingCardCreationsTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalAppDatabase,
      $PendingCardCreationsTable,
      PendingCardCreationRow,
      $$PendingCardCreationsTableFilterComposer,
      $$PendingCardCreationsTableOrderingComposer,
      $$PendingCardCreationsTableAnnotationComposer,
      $$PendingCardCreationsTableCreateCompanionBuilder,
      $$PendingCardCreationsTableUpdateCompanionBuilder,
      (
        PendingCardCreationRow,
        BaseReferences<
          _$LocalAppDatabase,
          $PendingCardCreationsTable,
          PendingCardCreationRow
        >,
      ),
      PendingCardCreationRow,
      PrefetchHooks Function()
    >;
typedef $$CachedCardsTableCreateCompanionBuilder =
    CachedCardsCompanion Function({
      required String localId,
      required int accountId,
      required String sourceText,
      required String contentType,
      required String memoryStyle,
      required CachedCardLocalStatus localStatus,
      Value<int?> serverCardId,
      Value<int?> serverJobId,
      Value<String?> generationStatus,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$CachedCardsTableUpdateCompanionBuilder =
    CachedCardsCompanion Function({
      Value<String> localId,
      Value<int> accountId,
      Value<String> sourceText,
      Value<String> contentType,
      Value<String> memoryStyle,
      Value<CachedCardLocalStatus> localStatus,
      Value<int?> serverCardId,
      Value<int?> serverJobId,
      Value<String?> generationStatus,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$CachedCardsTableFilterComposer
    extends Composer<_$LocalAppDatabase, $CachedCardsTable> {
  $$CachedCardsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get localId => $composableBuilder(
    column: $table.localId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sourceText => $composableBuilder(
    column: $table.sourceText,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => ColumnFilters(column),
  );

  ColumnWithTypeConverterFilters<
    CachedCardLocalStatus,
    CachedCardLocalStatus,
    String
  >
  get localStatus => $composableBuilder(
    column: $table.localStatus,
    builder: (column) => ColumnWithTypeConverterFilters(column),
  );

  ColumnFilters<int> get serverCardId => $composableBuilder(
    column: $table.serverCardId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get serverJobId => $composableBuilder(
    column: $table.serverJobId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get generationStatus => $composableBuilder(
    column: $table.generationStatus,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedCardsTableOrderingComposer
    extends Composer<_$LocalAppDatabase, $CachedCardsTable> {
  $$CachedCardsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get localId => $composableBuilder(
    column: $table.localId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sourceText => $composableBuilder(
    column: $table.sourceText,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localStatus => $composableBuilder(
    column: $table.localStatus,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get serverCardId => $composableBuilder(
    column: $table.serverCardId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get serverJobId => $composableBuilder(
    column: $table.serverJobId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get generationStatus => $composableBuilder(
    column: $table.generationStatus,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedCardsTableAnnotationComposer
    extends Composer<_$LocalAppDatabase, $CachedCardsTable> {
  $$CachedCardsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get localId =>
      $composableBuilder(column: $table.localId, builder: (column) => column);

  GeneratedColumn<int> get accountId =>
      $composableBuilder(column: $table.accountId, builder: (column) => column);

  GeneratedColumn<String> get sourceText => $composableBuilder(
    column: $table.sourceText,
    builder: (column) => column,
  );

  GeneratedColumn<String> get contentType => $composableBuilder(
    column: $table.contentType,
    builder: (column) => column,
  );

  GeneratedColumn<String> get memoryStyle => $composableBuilder(
    column: $table.memoryStyle,
    builder: (column) => column,
  );

  GeneratedColumnWithTypeConverter<CachedCardLocalStatus, String>
  get localStatus => $composableBuilder(
    column: $table.localStatus,
    builder: (column) => column,
  );

  GeneratedColumn<int> get serverCardId => $composableBuilder(
    column: $table.serverCardId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get serverJobId => $composableBuilder(
    column: $table.serverJobId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get generationStatus => $composableBuilder(
    column: $table.generationStatus,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$CachedCardsTableTableManager
    extends
        RootTableManager<
          _$LocalAppDatabase,
          $CachedCardsTable,
          CachedCardRow,
          $$CachedCardsTableFilterComposer,
          $$CachedCardsTableOrderingComposer,
          $$CachedCardsTableAnnotationComposer,
          $$CachedCardsTableCreateCompanionBuilder,
          $$CachedCardsTableUpdateCompanionBuilder,
          (
            CachedCardRow,
            BaseReferences<
              _$LocalAppDatabase,
              $CachedCardsTable,
              CachedCardRow
            >,
          ),
          CachedCardRow,
          PrefetchHooks Function()
        > {
  $$CachedCardsTableTableManager(_$LocalAppDatabase db, $CachedCardsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedCardsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedCardsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedCardsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> localId = const Value.absent(),
                Value<int> accountId = const Value.absent(),
                Value<String> sourceText = const Value.absent(),
                Value<String> contentType = const Value.absent(),
                Value<String> memoryStyle = const Value.absent(),
                Value<CachedCardLocalStatus> localStatus = const Value.absent(),
                Value<int?> serverCardId = const Value.absent(),
                Value<int?> serverJobId = const Value.absent(),
                Value<String?> generationStatus = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedCardsCompanion(
                localId: localId,
                accountId: accountId,
                sourceText: sourceText,
                contentType: contentType,
                memoryStyle: memoryStyle,
                localStatus: localStatus,
                serverCardId: serverCardId,
                serverJobId: serverJobId,
                generationStatus: generationStatus,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String localId,
                required int accountId,
                required String sourceText,
                required String contentType,
                required String memoryStyle,
                required CachedCardLocalStatus localStatus,
                Value<int?> serverCardId = const Value.absent(),
                Value<int?> serverJobId = const Value.absent(),
                Value<String?> generationStatus = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => CachedCardsCompanion.insert(
                localId: localId,
                accountId: accountId,
                sourceText: sourceText,
                contentType: contentType,
                memoryStyle: memoryStyle,
                localStatus: localStatus,
                serverCardId: serverCardId,
                serverJobId: serverJobId,
                generationStatus: generationStatus,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedCardsTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalAppDatabase,
      $CachedCardsTable,
      CachedCardRow,
      $$CachedCardsTableFilterComposer,
      $$CachedCardsTableOrderingComposer,
      $$CachedCardsTableAnnotationComposer,
      $$CachedCardsTableCreateCompanionBuilder,
      $$CachedCardsTableUpdateCompanionBuilder,
      (
        CachedCardRow,
        BaseReferences<_$LocalAppDatabase, $CachedCardsTable, CachedCardRow>,
      ),
      CachedCardRow,
      PrefetchHooks Function()
    >;
typedef $$SyncStatesTableCreateCompanionBuilder =
    SyncStatesCompanion Function({
      Value<int> accountId,
      Value<int> cursor,
      required DateTime updatedAt,
    });
typedef $$SyncStatesTableUpdateCompanionBuilder =
    SyncStatesCompanion Function({
      Value<int> accountId,
      Value<int> cursor,
      Value<DateTime> updatedAt,
    });

class $$SyncStatesTableFilterComposer
    extends Composer<_$LocalAppDatabase, $SyncStatesTable> {
  $$SyncStatesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get cursor => $composableBuilder(
    column: $table.cursor,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SyncStatesTableOrderingComposer
    extends Composer<_$LocalAppDatabase, $SyncStatesTable> {
  $$SyncStatesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get accountId => $composableBuilder(
    column: $table.accountId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get cursor => $composableBuilder(
    column: $table.cursor,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SyncStatesTableAnnotationComposer
    extends Composer<_$LocalAppDatabase, $SyncStatesTable> {
  $$SyncStatesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get accountId =>
      $composableBuilder(column: $table.accountId, builder: (column) => column);

  GeneratedColumn<int> get cursor =>
      $composableBuilder(column: $table.cursor, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$SyncStatesTableTableManager
    extends
        RootTableManager<
          _$LocalAppDatabase,
          $SyncStatesTable,
          SyncState,
          $$SyncStatesTableFilterComposer,
          $$SyncStatesTableOrderingComposer,
          $$SyncStatesTableAnnotationComposer,
          $$SyncStatesTableCreateCompanionBuilder,
          $$SyncStatesTableUpdateCompanionBuilder,
          (
            SyncState,
            BaseReferences<_$LocalAppDatabase, $SyncStatesTable, SyncState>,
          ),
          SyncState,
          PrefetchHooks Function()
        > {
  $$SyncStatesTableTableManager(_$LocalAppDatabase db, $SyncStatesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SyncStatesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SyncStatesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SyncStatesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> accountId = const Value.absent(),
                Value<int> cursor = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
              }) => SyncStatesCompanion(
                accountId: accountId,
                cursor: cursor,
                updatedAt: updatedAt,
              ),
          createCompanionCallback:
              ({
                Value<int> accountId = const Value.absent(),
                Value<int> cursor = const Value.absent(),
                required DateTime updatedAt,
              }) => SyncStatesCompanion.insert(
                accountId: accountId,
                cursor: cursor,
                updatedAt: updatedAt,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SyncStatesTableProcessedTableManager =
    ProcessedTableManager<
      _$LocalAppDatabase,
      $SyncStatesTable,
      SyncState,
      $$SyncStatesTableFilterComposer,
      $$SyncStatesTableOrderingComposer,
      $$SyncStatesTableAnnotationComposer,
      $$SyncStatesTableCreateCompanionBuilder,
      $$SyncStatesTableUpdateCompanionBuilder,
      (
        SyncState,
        BaseReferences<_$LocalAppDatabase, $SyncStatesTable, SyncState>,
      ),
      SyncState,
      PrefetchHooks Function()
    >;

class $LocalAppDatabaseManager {
  final _$LocalAppDatabase _db;
  $LocalAppDatabaseManager(this._db);
  $$PendingCardCreationsTableTableManager get pendingCardCreations =>
      $$PendingCardCreationsTableTableManager(_db, _db.pendingCardCreations);
  $$CachedCardsTableTableManager get cachedCards =>
      $$CachedCardsTableTableManager(_db, _db.cachedCards);
  $$SyncStatesTableTableManager get syncStates =>
      $$SyncStatesTableTableManager(_db, _db.syncStates);
}
