import 'package:dio/dio.dart';

import '../../../core/network/api_response_parser.dart';
import '../domain/authenticated_user.dart';
import '../domain/session_credentials.dart';

final class RegistrationResult {
  const RegistrationResult(this.user);

  final AuthenticatedUser user;
}

final class LoginResult {
  const LoginResult({required this.user, required this.credentials});

  final AuthenticatedUser user;
  final SessionCredentials credentials;
}

abstract interface class AuthGateway {
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  });

  Future<LoginResult> login({
    required String identity,
    required String password,
    required String deviceName,
  });

  Future<SessionCredentials> refresh(String refreshToken);

  Future<AuthenticatedUser> currentUser(String accessToken);

  Future<void> logout({
    required String accessToken,
    required String refreshToken,
  });
}

final class AuthApi implements AuthGateway {
  AuthApi(this._dio, {DateTime Function()? now})
    : _now = now ?? DateTime.now;

  final Dio _dio;
  final DateTime Function() _now;

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) async {
    final payload = <String, Object?>{'password': password};
    if (email != null) {
      payload['email'] = email;
    }
    if (username != null) {
      payload['username'] = username;
    }
    final data = await _post('/api/auth/register', payload);
    return RegistrationResult(_user(data));
  }

  @override
  Future<LoginResult> login({
    required String identity,
    required String password,
    required String deviceName,
  }) async {
    final data = await _post('/api/auth/login', {
      'identity': identity,
      'password': password,
      'device_name': deviceName,
    });
    return LoginResult(user: _user(data), credentials: _credentials(data));
  }

  @override
  Future<SessionCredentials> refresh(String refreshToken) async {
    final data = await _post('/api/auth/refresh', {
      'refresh_token': refreshToken,
    });
    return _credentials(data);
  }

  @override
  Future<AuthenticatedUser> currentUser(String accessToken) async {
    final data = await _get(
      '/api/auth/me',
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );
    return _user(data);
  }

  @override
  Future<void> logout({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _post(
      '/api/auth/logout',
      {'refresh_token': refreshToken},
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );
  }

  Future<Map<String, Object?>> _post(
    String path,
    Map<String, Object?> payload, {
    Options? options,
  }) async {
    try {
      return ApiResponseParser.data(
        await _dio.post<dynamic>(path, data: payload, options: options),
      );
    } on DioException catch (error) {
      throw ApiResponseParser.fromDioException(error);
    }
  }

  Future<Map<String, Object?>> _get(String path, {Options? options}) async {
    try {
      return ApiResponseParser.data(
        await _dio.get<dynamic>(path, options: options),
      );
    } on DioException catch (error) {
      throw ApiResponseParser.fromDioException(error);
    }
  }

  AuthenticatedUser _user(Map<String, Object?> data) {
    final raw = data['user'];
    if (raw is! Map<String, dynamic>) {
      throw const FormatException('Invalid user response.');
    }
    return AuthenticatedUser.fromJson(raw.cast<String, Object?>());
  }

  SessionCredentials _credentials(Map<String, Object?> data) {
    final accessToken = data['access_token'];
    final refreshToken = data['refresh_token'];
    final accessTtl = data['expires_in'];
    final refreshTtl = data['refresh_expires_in'];
    if (accessToken is! String ||
        refreshToken is! String ||
        accessTtl is! num ||
        refreshTtl is! num) {
      throw const FormatException('Invalid credential response.');
    }
    return SessionCredentials.fromTtl(
      accessToken: accessToken,
      refreshToken: refreshToken,
      accessTtlSeconds: accessTtl.toInt(),
      refreshTtlSeconds: refreshTtl.toInt(),
      now: _now(),
    );
  }
}
