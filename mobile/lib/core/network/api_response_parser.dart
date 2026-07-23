import 'dart:io';

import 'package:dio/dio.dart';

import 'api_exception.dart';

abstract final class ApiResponseParser {
  static const _messages = <String, String>{
    'INVALID_INPUT': '输入内容不符合要求。',
    'IDENTITY_TAKEN': '该邮箱或用户名已被使用。',
    'INVALID_CREDENTIALS': '账号或密码不正确。',
    'ACCOUNT_DISABLED': '该账号已停用。',
    'INVALID_REFRESH_TOKEN': '登录状态已失效，请重新登录。',
    'UNAUTHENTICATED': '请重新登录。',
    'IDEMPOTENCY_CONFLICT': '该请求与已提交内容冲突，请重新保存。',
    'IDEMPOTENCY_KEY_REQUIRED': '请求标识缺失，请重新保存。',
  };

  static Map<String, Object?> data(
    Response<dynamic> response, {
    DateTime Function()? now,
  }) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      if (body['success'] == true && body['data'] is Map<String, dynamic>) {
        return (body['data'] as Map<String, dynamic>).cast<String, Object?>();
      }
      final error = body['error'];
      if (body['success'] == false && error is Map<String, dynamic>) {
        final code = error['code'];
        if (code is String && _messages.containsKey(code)) {
          throw ApiException(
            code: code,
            userMessage: _messages[code]!,
            statusCode: response.statusCode,
            retryAfterAt: _retryAfterAt(response, now ?? DateTime.now),
          );
        }
      }
    }
    throw ApiException(
      code: 'SERVER_ERROR',
      userMessage: '服务暂时不可用，请稍后重试。',
      statusCode: response.statusCode,
      retryAfterAt: _retryAfterAt(response, now ?? DateTime.now),
    );
  }

  static ApiException fromDioException(
    DioException error, {
    DateTime Function()? now,
  }) {
    final response = error.response;
    if (response != null) {
      try {
        data(response, now: now);
      } on ApiException catch (exception) {
        return exception;
      }
    }

    final networkFailure =
        response == null &&
        error.type != DioExceptionType.cancel &&
        error.type != DioExceptionType.badResponse;
    if (networkFailure) {
      return const ApiException(
        code: 'NETWORK_ERROR',
        userMessage: '网络连接失败，请检查网络后重试。',
        isNetwork: true,
      );
    }
    return ApiException(
      code: 'SERVER_ERROR',
      userMessage: '服务暂时不可用，请稍后重试。',
      statusCode: response?.statusCode,
    );
  }

  static DateTime? _retryAfterAt(
    Response<dynamic> response,
    DateTime Function() now,
  ) {
    final value = response.headers.value('retry-after')?.trim();
    if (value == null || value.isEmpty) return null;
    final seconds = int.tryParse(value);
    if (seconds != null) {
      return seconds < 0 ? null : now().toUtc().add(Duration(seconds: seconds));
    }
    try {
      return HttpDate.parse(value).toUtc();
    } on FormatException {
      return null;
    }
  }
}
