import 'capture_content_type.dart';

String normalizeRecognizedText(String value) {
  final lines = value
      .replaceAll('\r\n', '\n')
      .replaceAll('\r', '\n')
      .split('\n')
      .map((line) => line.trimRight())
      .toList();
  return lines.join('\n').trim().replaceAll(RegExp(r'\n{3,}'), '\n\n');
}

CaptureContentType suggestContentType(String value) {
  final text = normalizeRecognizedText(value);
  return RegExp(r'\s|[.!?。！？]').hasMatch(text)
      ? CaptureContentType.sentence
      : CaptureContentType.word;
}
