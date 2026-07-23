import '../data/capture_image.dart';
import '../domain/capture_content_type.dart';
import '../domain/capture_memory_style.dart';

sealed class CaptureState {
  const CaptureState();
}

final class CaptureReady extends CaptureState {
  const CaptureReady({
    required this.text,
    required this.contentType,
    required this.memoryStyle,
    this.image,
    this.message,
  });

  final String text;
  final CaptureContentType contentType;
  final CaptureMemoryStyle memoryStyle;
  final CaptureImage? image;
  final String? message;

  CaptureReady copyWith({
    String? text,
    CaptureContentType? contentType,
    CaptureMemoryStyle? memoryStyle,
    CaptureImage? image,
    bool removeImage = false,
    String? message,
    bool clearMessage = true,
  }) => CaptureReady(
    text: text ?? this.text,
    contentType: contentType ?? this.contentType,
    memoryStyle: memoryStyle ?? this.memoryStyle,
    image: removeImage ? null : image ?? this.image,
    message: clearMessage ? message : this.message,
  );
}

final class CaptureSelectingImage extends CaptureState {
  const CaptureSelectingImage(this.previous);

  final CaptureReady previous;
}

final class CaptureCroppingImage extends CaptureState {
  const CaptureCroppingImage(this.previous);

  final CaptureReady previous;
}

final class CaptureRecognizingText extends CaptureState {
  const CaptureRecognizingText(this.previous, this.image);

  final CaptureReady previous;
  final CaptureImage image;
}

final class CaptureFailure extends CaptureState {
  const CaptureFailure(this.previous, this.message);

  final CaptureReady previous;
  final String message;
}
