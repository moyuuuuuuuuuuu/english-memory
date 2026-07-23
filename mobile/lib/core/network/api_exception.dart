final class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.userMessage,
    this.statusCode,
    this.isNetwork = false,
    this.retryAfterAt,
  });

  final String code;
  final String userMessage;
  final int? statusCode;
  final bool isNetwork;
  final DateTime? retryAfterAt;

  bool get isAuthenticationFailure =>
      code == 'INVALID_REFRESH_TOKEN' || code == 'UNAUTHENTICATED';

  @override
  String toString() => 'ApiException($code)';
}
