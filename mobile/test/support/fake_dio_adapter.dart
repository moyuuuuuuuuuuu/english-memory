import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

typedef DioRequestHandler = Future<ResponseBody> Function(RequestOptions request);

final class FakeDioAdapter implements HttpClientAdapter {
  FakeDioAdapter(this.handler);

  final DioRequestHandler handler;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    requests.add(options);
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody jsonResponse(Object body, {int statusCode = 200}) => ResponseBody.fromString(
  jsonEncode(body),
  statusCode,
  headers: {
    Headers.contentTypeHeader: [Headers.jsonContentType],
  },
);
