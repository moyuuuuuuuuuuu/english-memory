import 'package:dio/dio.dart';
import 'package:english_memory/core/network/api_exception.dart';
import 'package:english_memory/core/network/api_response_parser.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('returns data from a success envelope', () {
    final data = ApiResponseParser.data(
      Response(
        requestOptions: RequestOptions(path: '/test'),
        statusCode: 200,
        data: {
          'success': true,
          'data': {'value': 7},
        },
      ),
    );

    expect(data, {'value': 7});
  });

  test('maps stable API codes to safe Chinese messages', () {
    for (final entry in <String, String>{
      'INVALID_INPUT': '输入内容不符合要求。',
      'IDENTITY_TAKEN': '该邮箱或用户名已被使用。',
      'INVALID_CREDENTIALS': '账号或密码不正确。',
      'ACCOUNT_DISABLED': '该账号已停用。',
      'INVALID_REFRESH_TOKEN': '登录状态已失效，请重新登录。',
      'UNAUTHENTICATED': '请重新登录。',
    }.entries) {
      expect(
        () => ApiResponseParser.data(
          Response(
            requestOptions: RequestOptions(path: '/test'),
            statusCode: 401,
            data: {
              'success': false,
              'error': {'code': entry.key, 'message': 'raw-provider-message'},
            },
          ),
        ),
        throwsA(
          isA<ApiException>()
              .having((error) => error.code, 'code', entry.key)
              .having((error) => error.userMessage, 'message', entry.value)
              .having((error) => error.statusCode, 'status', 401),
        ),
      );
    }
  });

  test('maps malformed and unknown server responses generically', () {
    for (final body in <Object?>[
      null,
      {'success': true, 'data': 'not-a-map'},
      {
        'success': false,
        'error': {'code': 'UNKNOWN_PROVIDER_FAILURE', 'message': 'secret detail'},
      },
    ]) {
      expect(
        () => ApiResponseParser.data(
          Response(
            requestOptions: RequestOptions(path: '/test'),
            statusCode: 500,
            data: body,
          ),
        ),
        throwsA(
          isA<ApiException>().having(
            (error) => error.userMessage,
            'message',
            '服务暂时不可用，请稍后重试。',
          ),
        ),
      );
    }
  });

  test('maps connectivity failures without exposing raw errors', () {
    final exception = ApiResponseParser.fromDioException(
      DioException(
        requestOptions: RequestOptions(path: '/test'),
        type: DioExceptionType.connectionTimeout,
        message: 'socket secret',
      ),
    );

    expect(exception.code, 'NETWORK_ERROR');
    expect(exception.isNetwork, isTrue);
    expect(exception.userMessage, '网络连接失败，请检查网络后重试。');
    expect(exception.userMessage, isNot(contains('socket secret')));
  });
}
