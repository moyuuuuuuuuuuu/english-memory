import 'package:dio/dio.dart';
import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../../support/fake_dio_adapter.dart';

void main() {
  late FakeDioAdapter adapter;
  late AuthApi api;
  final now = DateTime.utc(2026, 7, 23, 8);

  setUp(() {
    adapter = FakeDioAdapter((request) async {
      switch (request.path) {
        case '/api/auth/register':
          return jsonResponse({
            'success': true,
            'data': {
              'user': {
                'id': 9,
                'email': 'learner@example.com',
                'username': null,
              },
            },
          }, statusCode: 201);
        case '/api/auth/login':
          return jsonResponse({
            'success': true,
            'data': {
              'user': {
                'id': 9,
                'email': 'learner@example.com',
                'username': null,
                'timezone': 'Asia/Shanghai',
              },
              'access_token': 'access-login',
              'expires_in': 900,
              'refresh_token': 'refresh-login',
              'refresh_expires_in': 2592000,
            },
          });
        case '/api/auth/refresh':
          return jsonResponse({
            'success': true,
            'data': {
              'access_token': 'access-rotated',
              'expires_in': 900,
              'refresh_token': 'refresh-rotated',
              'refresh_expires_in': 2592000,
            },
          });
        case '/api/auth/me':
          return jsonResponse({
            'success': true,
            'data': {
              'user': {
                'id': 9,
                'email': 'learner@example.com',
                'username': null,
                'timezone': 'Asia/Shanghai',
              },
            },
          });
        case '/api/auth/logout':
          return jsonResponse({
            'success': true,
            'data': {'logged_out': true},
          });
      }
      return jsonResponse({'success': false}, statusCode: 404);
    });
    final dio = Dio(BaseOptions(baseUrl: 'https://api.example.com'))
      ..httpClientAdapter = adapter;
    api = AuthApi(dio, now: () => now);
  });

  test('register sends one selected identity', () async {
    final result = await api.register(
      email: 'learner@example.com',
      password: 'SecurePass123!',
    );

    expect(result.user.id, 9);
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/api/auth/register');
    expect(adapter.requests.single.data, {
      'email': 'learner@example.com',
      'password': 'SecurePass123!',
    });
  });

  test('login parses user and relative token TTL values', () async {
    final result = await api.login(
      identity: 'learner@example.com',
      password: 'SecurePass123!',
      deviceName: 'english-memory-mobile',
    );

    expect(result.user.email, 'learner@example.com');
    expect(result.credentials.accessToken, 'access-login');
    expect(
      result.credentials.accessExpiresAt,
      now.add(const Duration(minutes: 15)),
    );
    expect(adapter.requests.single.data, {
      'identity': 'learner@example.com',
      'password': 'SecurePass123!',
      'device_name': 'english-memory-mobile',
    });
  });

  test('refresh submits old token and parses rotated credentials', () async {
    final result = await api.refresh('refresh-login');

    expect(result.accessToken, 'access-rotated');
    expect(result.refreshToken, 'refresh-rotated');
    expect(adapter.requests.single.data, {'refresh_token': 'refresh-login'});
  });

  test('current user attaches only the Bearer access token', () async {
    final user = await api.currentUser('access-login');

    expect(user.id, 9);
    expect(
      adapter.requests.single.headers['Authorization'],
      'Bearer access-login',
    );
  });

  test('logout attaches access token and submits refresh token', () async {
    await api.logout(
      accessToken: 'access-login',
      refreshToken: 'refresh-login',
    );

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/api/auth/logout');
    expect(
      adapter.requests.single.headers['Authorization'],
      'Bearer access-login',
    );
    expect(adapter.requests.single.data, {'refresh_token': 'refresh-login'});
  });
}
