import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/api_response_parser.dart';
import '../domain/card_creation_accepted.dart';
import '../domain/pending_card_creation.dart';

abstract interface class MemoryCardGateway {
  Future<CardCreationAccepted> create(PendingCardCreation item);
}

final class MemoryCardApi implements MemoryCardGateway {
  const MemoryCardApi(this._dio);

  final Dio _dio;

  @override
  Future<CardCreationAccepted> create(PendingCardCreation item) async {
    try {
      final response = await _dio.post<dynamic>(
        '/api/memory-cards',
        data: {
          'text': item.text,
          'content_type': item.contentType,
          'memory_style': item.memoryStyle,
        },
        options: Options(headers: {'Idempotency-Key': item.localId}),
      );
      return _accepted(ApiResponseParser.data(response));
    } on DioException catch (error) {
      throw ApiResponseParser.fromDioException(error);
    } on ApiException {
      rethrow;
    } on FormatException {
      throw const ApiException(
        code: 'SERVER_ERROR',
        userMessage: '服务暂时不可用，请稍后重试。',
      );
    }
  }

  CardCreationAccepted _accepted(Map<String, Object?> data) {
    final cardId = data['card_id'];
    final jobId = data['job_id'];
    final status = data['status'];
    final replayed = data['replayed'];
    final dispatchPending = data['dispatch_pending'];
    if (cardId is! num ||
        cardId.toInt() <= 0 ||
        jobId is! num ||
        jobId.toInt() <= 0 ||
        status is! String ||
        status.isEmpty ||
        replayed is! bool ||
        dispatchPending is! bool) {
      throw const FormatException('Invalid card creation response.');
    }
    return CardCreationAccepted(
      cardId: cardId.toInt(),
      jobId: jobId.toInt(),
      status: status,
      replayed: replayed,
      dispatchPending: dispatchPending,
    );
  }
}
