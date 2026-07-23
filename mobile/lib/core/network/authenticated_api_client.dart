import 'package:dio/dio.dart';

import '../../features/auth/data/session_token_provider.dart';
import '../../features/auth/domain/session_credentials.dart';
import 'api_exception.dart';

final class AuthenticatedApiClient {
  AuthenticatedApiClient(this.dio, this._tokens) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: _attachAccessToken,
        onError: _refreshAndReplay,
      ),
    );
  }

  static const _replayedKey = 'auth_replayed';

  final Dio dio;
  final SessionTokenProvider _tokens;
  Future<SessionCredentials>? _refreshInFlight;

  void _attachAccessToken(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) {
    final credentials = _tokens.currentCredentials;
    if (credentials != null) {
      options.headers['Authorization'] = 'Bearer ${credentials.accessToken}';
    }
    handler.next(options);
  }

  Future<void> _refreshAndReplay(
    DioException error,
    ErrorInterceptorHandler handler,
  ) async {
    final request = error.requestOptions;
    if (error.response?.statusCode != 401 ||
        request.extra[_replayedKey] == true) {
      handler.next(error);
      return;
    }

    try {
      final current = _tokens.currentCredentials;
      final failedAuthorization = request.headers['Authorization'];
      final credentials =
          current != null &&
                  failedAuthorization != 'Bearer ${current.accessToken}'
              ? current
              : await _refreshOnce();
      request
        ..extra = {...request.extra, _replayedKey: true}
        ..headers = {
          ...request.headers,
          'Authorization': 'Bearer ${credentials.accessToken}',
        };
      handler.resolve(await dio.fetch<dynamic>(request));
    } on ApiException catch (refreshError) {
      if (refreshError.isAuthenticationFailure) {
        await _tokens.invalidateSession();
      }
      handler.next(error);
    } on DioException {
      handler.next(error);
    }
  }

  Future<SessionCredentials> _refreshOnce() {
    final existing = _refreshInFlight;
    if (existing != null) {
      return existing;
    }

    final running = _refreshAndReset();
    _refreshInFlight = running;
    return running;
  }

  Future<SessionCredentials> _refreshAndReset() async {
    try {
      return await _tokens.refreshCredentials();
    } finally {
      _refreshInFlight = null;
    }
  }
}
