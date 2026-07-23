import 'package:dio/dio.dart';
import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/features/sync/data/memory_card_api.dart';
import 'package:english_memory/features/sync/domain/pending_card_creation.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../support/fake_dio_adapter.dart';

void main() {
  const item = PendingCardCreation(
    localId: 'local-uuid',
    accountId: 7,
    text: 'ambition',
    contentType: 'word',
    memoryStyle: 'auto',
    attemptCount: 0,
  );
  late FakeDioAdapter adapter;
  late MemoryCardApi api;

  setUp(() {
    adapter = FakeDioAdapter(
      (_) async => jsonResponse({
        'success': true,
        'data': {
          'card_id': 11,
          'job_id': 22,
          'status': 'queued',
          'replayed': false,
          'dispatch_pending': false,
        },
      }, statusCode: 202),
    );
    api = MemoryCardApi(
      Dio(BaseOptions(baseUrl: 'https://api.example.com'))
        ..httpClientAdapter = adapter,
    );
  });

  test('posts the immutable payload with its idempotency key', () async {
    final result = await api.create(item);

    expect(result.cardId, 11);
    expect(result.jobId, 22);
    expect(result.status, 'queued');
    expect(result.replayed, isFalse);
    expect(result.dispatchPending, isFalse);
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/api/memory-cards');
    expect(adapter.requests.single.headers['Idempotency-Key'], 'local-uuid');
    expect(adapter.requests.single.data, {
      'text': 'ambition',
      'content_type': 'word',
      'memory_style': 'auto',
    });
  });

  test('accepts a stable replay result', () async {
    adapter.handler = (_) async => jsonResponse({
      'success': true,
      'data': {
        'card_id': 11,
        'job_id': 22,
        'status': 'completed',
        'replayed': true,
        'dispatch_pending': false,
      },
    }, statusCode: 202);

    final result = await api.create(item);

    expect(result.replayed, isTrue);
    expect(result.status, 'completed');
  });

  test('maps malformed success data to a safe API error', () async {
    adapter.handler = (_) async => jsonResponse({
      'success': true,
      'data': {'card_id': 0, 'job_id': 'secret-invalid-id', 'status': ''},
    }, statusCode: 202);

    await expectLater(
      api.create(item),
      throwsA(
        isA<ApiException>()
            .having((error) => error.code, 'code', 'SERVER_ERROR')
            .having(
              (error) => error.userMessage,
              'message',
              isNot(contains('secret-invalid-id')),
            ),
      ),
    );
  });

  test('preserves stable permanent error codes', () async {
    adapter.handler = (_) async => jsonResponse({
      'success': false,
      'error': {
        'code': 'IDEMPOTENCY_CONFLICT',
        'message': 'raw provider detail',
      },
    }, statusCode: 409);

    await expectLater(
      api.create(item),
      throwsA(
        isA<ApiException>()
            .having((error) => error.code, 'code', 'IDEMPOTENCY_CONFLICT')
            .having(
              (error) => error.userMessage,
              'message',
              isNot(contains('provider')),
            ),
      ),
    );
  });
}
