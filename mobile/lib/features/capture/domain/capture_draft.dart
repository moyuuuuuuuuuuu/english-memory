import 'capture_content_type.dart';
import 'capture_memory_style.dart';
import 'recognized_text_normalizer.dart';

final class CaptureDraft {
  CaptureDraft({
    required String text,
    required this.contentType,
    required this.memoryStyle,
  }) : text = normalizeRecognizedText(text) {
    if (this.text.isEmpty) {
      throw const FormatException('Capture draft text cannot be empty.');
    }
  }

  final String text;
  final CaptureContentType contentType;
  final CaptureMemoryStyle memoryStyle;

  Map<String, Object?> toJson() => {
    'text': text,
    'content_type': contentType.apiValue,
    'memory_style': memoryStyle.apiValue,
  };

  @override
  bool operator ==(Object other) =>
      other is CaptureDraft &&
      text == other.text &&
      contentType == other.contentType &&
      memoryStyle == other.memoryStyle;

  @override
  int get hashCode => Object.hash(text, contentType, memoryStyle);
}
