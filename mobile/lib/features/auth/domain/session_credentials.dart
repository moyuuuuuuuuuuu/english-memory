final class SessionCredentials {
  const SessionCredentials._({
    required this.accessToken,
    required this.refreshToken,
    required this.accessExpiresAt,
    required this.refreshExpiresAt,
  });

  final String accessToken;
  final String refreshToken;
  final DateTime accessExpiresAt;
  final DateTime refreshExpiresAt;

  factory SessionCredentials.fromTtl({
    required String accessToken,
    required String refreshToken,
    required int accessTtlSeconds,
    required int refreshTtlSeconds,
    required DateTime now,
  }) {
    if (accessTtlSeconds <= 0 || refreshTtlSeconds <= 0) {
      throw const FormatException('Credential TTL must be positive.');
    }
    return SessionCredentials._validated(
      accessToken: accessToken,
      refreshToken: refreshToken,
      accessExpiresAt: now.toUtc().add(Duration(seconds: accessTtlSeconds)),
      refreshExpiresAt: now.toUtc().add(Duration(seconds: refreshTtlSeconds)),
    );
  }

  factory SessionCredentials.fromJson(Map<String, Object?> json) {
    final accessToken = json['access_token'];
    final refreshToken = json['refresh_token'];
    final accessExpiresAt = DateTime.tryParse(
      json['access_expires_at'] is String
          ? json['access_expires_at']! as String
          : '',
    );
    final refreshExpiresAt = DateTime.tryParse(
      json['refresh_expires_at'] is String
          ? json['refresh_expires_at']! as String
          : '',
    );
    if (accessToken is! String ||
        refreshToken is! String ||
        accessExpiresAt == null ||
        refreshExpiresAt == null) {
      throw const FormatException('Invalid session credentials.');
    }
    return SessionCredentials._validated(
      accessToken: accessToken,
      refreshToken: refreshToken,
      accessExpiresAt: accessExpiresAt.toUtc(),
      refreshExpiresAt: refreshExpiresAt.toUtc(),
    );
  }

  factory SessionCredentials._validated({
    required String accessToken,
    required String refreshToken,
    required DateTime accessExpiresAt,
    required DateTime refreshExpiresAt,
  }) {
    if (accessToken.trim().isEmpty ||
        refreshToken.trim().isEmpty ||
        !refreshExpiresAt.isAfter(accessExpiresAt)) {
      throw const FormatException('Invalid session credentials.');
    }
    return SessionCredentials._(
      accessToken: accessToken,
      refreshToken: refreshToken,
      accessExpiresAt: accessExpiresAt.toUtc(),
      refreshExpiresAt: refreshExpiresAt.toUtc(),
    );
  }

  Map<String, Object?> toJson() => {
    'access_token': accessToken,
    'refresh_token': refreshToken,
    'access_expires_at': accessExpiresAt.toUtc().toIso8601String(),
    'refresh_expires_at': refreshExpiresAt.toUtc().toIso8601String(),
  };

  bool isExpiring(DateTime now, Duration skew) =>
      !accessExpiresAt.isAfter(now.toUtc().add(skew));

  @override
  bool operator ==(Object other) =>
      other is SessionCredentials &&
      accessToken == other.accessToken &&
      refreshToken == other.refreshToken &&
      accessExpiresAt == other.accessExpiresAt &&
      refreshExpiresAt == other.refreshExpiresAt;

  @override
  int get hashCode => Object.hash(
    accessToken,
    refreshToken,
    accessExpiresAt,
    refreshExpiresAt,
  );
}
