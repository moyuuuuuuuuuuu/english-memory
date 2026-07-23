import 'dart:async';

import 'package:dio/dio.dart';
import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/core/network/authenticated_api_client.dart';
import 'package:english_memory/features/auth/data/session_token_provider.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/fake_dio_adapter.dart';

void main() {
  final now = DateTime.utc(2026, 7, 23, 8);

  SessionCredentials credentials(String suffix) =>
      SessionCredentials.fromTtl(
        accessToken: 'access-$suffix',
        refreshToken: 'refresh-$suffix',
        accessTtlSeconds: 900,
        refreshTtlSeconds: 2592000,
        now: now,
      );

  late Dio dio;
  late FakeTokenProvider tokens;
  late FakeDioAdapter adapter;

  setUp(() {
    dio = Dio(BaseOptions(baseUrl: 'https://api.example.com'));
    tokens = FakeTokenProvider(credentials('old'));
  });

  void build(DioRequestHandler handler) {
    adapter = FakeDioAdapter(handler);
    dio.httpClientAdapter = adapter;
    AuthenticatedApiClient(dio, tokens);
  }

  test('attaches the current Bearer token', () async {
    build((_) async => jsonResponse({'ok': true}));

    await dio.get<dynamic>('/cards');

    expect(
      adapter.requests.single.headers['Authorization'],
      'Bearer access-old',
    );
  });

  test('leaves Authorization absent without credentials', () async {
    tokens.current = null;
    build((_) async => jsonResponse({'ok': true}));

    await dio.get<dynamic>('/public');

    expect(adapter.requests.single.headers, isNot(contains('Authorization')));
  });

  test('refreshes one 401 and replays with the rotated token', () async {
    tokens.refreshed = credentials('new');
    build((request) async {
      if (request.headers['Authorization'] == 'Bearer access-old') {
        return jsonResponse({'error': 'expired'}, statusCode: 401);
      }
      return jsonResponse({'ok': true});
    });

    final response = await dio.post<dynamic>(
      '/cards',
      data: {'content': 'memory'},
      queryParameters: {'locale': 'zh-CN'},
    );

    expect(response.statusCode, 200);
    expect(tokens.refreshCalls, 1);
    expect(adapter.requests, hasLength(2));
    expect(
      adapter.requests[1].headers['Authorization'],
      'Bearer access-new',
    );
    expect(adapter.requests[1].method, 'POST');
    expect(adapter.requests[1].data, {'content': 'memory'});
    expect(adapter.requests[1].queryParameters, {'locale': 'zh-CN'});
  });

  test('concurrent 401 responses share one refresh', () async {
    final barrier = Completer<SessionCredentials>();
    tokens.refreshBarrier = barrier;
    build((request) async {
      if (request.headers['Authorization'] == 'Bearer access-old') {
        return jsonResponse({'error': 'expired'}, statusCode: 401);
      }
      return jsonResponse({'ok': true});
    });

    final first = dio.get<dynamic>('/cards/1');
    final second = dio.get<dynamic>('/cards/2');
    await tokens.refreshStarted.future;
    expect(tokens.refreshCalls, 1);

    barrier.complete(credentials('new'));
    await Future.wait([first, second]);

    expect(tokens.refreshCalls, 1);
    expect(adapter.requests, hasLength(4));
  });

  test('does not refresh a non-401 response', () async {
    build((_) async => jsonResponse({'error': 'forbidden'}, statusCode: 403));

    await expectLater(
      dio.get<dynamic>('/cards'),
      throwsA(isA<DioException>()),
    );

    expect(tokens.refreshCalls, 0);
  });

  test('propagates a second 401 without recursive refresh', () async {
    tokens.refreshed = credentials('new');
    build((_) async => jsonResponse({'error': 'unauthorized'}, statusCode: 401));

    await expectLater(
      dio.get<dynamic>('/cards'),
      throwsA(isA<DioException>()),
    );

    expect(tokens.refreshCalls, 1);
    expect(adapter.requests, hasLength(2));
  });

  test('invalidates when refresh authentication is rejected', () async {
    tokens.refreshError = const ApiException(
      code: 'INVALID_REFRESH_TOKEN',
      userMessage: '请重新登录。',
      statusCode: 401,
    );
    build((_) async => jsonResponse({'error': 'expired'}, statusCode: 401));

    await expectLater(
      dio.get<dynamic>('/cards'),
      throwsA(isA<DioException>()),
    );

    expect(tokens.invalidateCalls, 1);
    expect(adapter.requests, hasLength(1));
  });
}

final class FakeTokenProvider implements SessionTokenProvider {
  FakeTokenProvider(this.current);

  SessionCredentials? current;
  SessionCredentials? refreshed;
  Completer<SessionCredentials>? refreshBarrier;
  ApiException? refreshError;
  final Completer<void> refreshStarted = Completer<void>();
  int refreshCalls = 0;
  int invalidateCalls = 0;

  @override
  SessionCredentials? get currentCredentials => current;

  @override
  Future<void> invalidateSession() async {
    invalidateCalls++;
    current = null;
  }

  @override
  Future<SessionCredentials> refreshCredentials() async {
    refreshCalls++;
    if (!refreshStarted.isCompleted) {
      refreshStarted.complete();
    }
    if (refreshError case final error?) throw error;
    final result =
        refreshBarrier == null
            ? refreshed!
            : await refreshBarrier!.future;
    current = result;
    return result;
  }
}
