import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/features/sync/application/retry_policy.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final now = DateTime.utc(2026, 7, 23, 8);
  late RetryPolicy policy;

  setUp(() {
    policy = RetryPolicy(now: () => now, jitter: (_) => Duration.zero);
  });

  test('classifies transient authentication and permanent failures', () {
    expect(
      policy
          .decide(
            const ApiException(
              code: 'NETWORK_ERROR',
              userMessage: 'safe',
              isNetwork: true,
            ),
            attempts: 1,
          )
          .disposition,
      SyncFailureDisposition.retry,
    );
    expect(
      policy
          .decide(
            const ApiException(
              code: 'UNAUTHENTICATED',
              userMessage: 'safe',
              statusCode: 401,
            ),
            attempts: 4,
          )
          .disposition,
      SyncFailureDisposition.pauseAuthentication,
    );
    expect(
      policy
          .decide(
            const ApiException(
              code: 'INVALID_INPUT',
              userMessage: 'safe',
              statusCode: 422,
            ),
            attempts: 1,
          )
          .disposition,
      SyncFailureDisposition.block,
    );
  });

  test('retries 429 and server errors', () {
    for (final status in [429, 500, 503]) {
      expect(
        policy
            .decide(
              ApiException(
                code: 'SERVER_ERROR',
                userMessage: 'safe',
                statusCode: status,
              ),
              attempts: 1,
            )
            .disposition,
        SyncFailureDisposition.retry,
      );
    }
  });

  test('exponential delay starts at two seconds and caps at five minutes', () {
    expect(
      policy
          .decide(
            const ApiException(
              code: 'NETWORK_ERROR',
              userMessage: 'safe',
              isNetwork: true,
            ),
            attempts: 1,
          )
          .nextAttemptAt,
      now.add(const Duration(seconds: 2)),
    );
    expect(
      policy
          .decide(
            const ApiException(
              code: 'NETWORK_ERROR',
              userMessage: 'safe',
              isNetwork: true,
            ),
            attempts: 20,
          )
          .nextAttemptAt,
      now.add(const Duration(minutes: 5)),
    );
  });

  test('Retry-After can extend but never shorten calculated backoff', () {
    final later = now.add(const Duration(minutes: 2));
    expect(
      policy
          .decide(
            ApiException(
              code: 'SERVER_ERROR',
              userMessage: 'safe',
              statusCode: 429,
              retryAfterAt: later,
            ),
            attempts: 1,
          )
          .nextAttemptAt,
      later,
    );
    expect(
      policy
          .decide(
            ApiException(
              code: 'SERVER_ERROR',
              userMessage: 'safe',
              statusCode: 429,
              retryAfterAt: now.add(const Duration(seconds: 1)),
            ),
            attempts: 1,
          )
          .nextAttemptAt,
      now.add(const Duration(seconds: 2)),
    );
  });
}
