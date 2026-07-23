import '../../../core/network/api_exception.dart';

enum SyncFailureDisposition { retry, pauseAuthentication, block }

final class RetryDecision {
  const RetryDecision(this.disposition, {this.nextAttemptAt});

  final SyncFailureDisposition disposition;
  final DateTime? nextAttemptAt;
}

final class RetryPolicy {
  RetryPolicy({
    DateTime Function()? now,
    Duration Function(Duration maximum)? jitter,
  }) : _now = now ?? DateTime.now,
       _jitter = jitter ?? _noJitter;

  static const _maximumDelay = Duration(minutes: 5);

  final DateTime Function() _now;
  final Duration Function(Duration maximum) _jitter;

  RetryDecision decide(ApiException error, {required int attempts}) {
    if (error.isAuthenticationFailure || error.statusCode == 401) {
      return const RetryDecision(SyncFailureDisposition.pauseAuthentication);
    }

    final status = error.statusCode;
    final retryable =
        error.isNetwork || status == 429 || (status != null && status >= 500);
    if (!retryable) {
      return const RetryDecision(SyncFailureDisposition.block);
    }

    final safeAttempts = attempts < 1 ? 1 : attempts;
    final exponent = (safeAttempts - 1).clamp(0, 8);
    final seconds = 2 * (1 << exponent);
    final raw = Duration(seconds: seconds);
    final capped = raw > _maximumDelay ? _maximumDelay : raw;
    final jittered = capped + _jitter(capped);
    final bounded = jittered > _maximumDelay ? _maximumDelay : jittered;
    final calculated = _now().toUtc().add(bounded);
    final retryAfter = error.retryAfterAt?.toUtc();
    final next = retryAfter != null && retryAfter.isAfter(calculated)
        ? retryAfter
        : calculated;

    return RetryDecision(SyncFailureDisposition.retry, nextAttemptAt: next);
  }

  static Duration _noJitter(Duration _) => Duration.zero;
}
