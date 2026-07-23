import 'dart:async';

import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

import 'capture_image.dart';
import 'capture_service_exception.dart';
import 'text_recognition_service.dart';

typedef RecognizeImageFile = Future<String> Function(String sourcePath);
typedef DisposeRecognizer = void Function();

final class MlKitTextRecognitionService implements TextRecognitionService {
  MlKitTextRecognitionService({
    RecognizeImageFile? recognizeImageFile,
    this.disposeRecognizer,
  }) : _recognizer = recognizeImageFile == null
           ? TextRecognizer(script: TextRecognitionScript.latin)
           : null,
       _recognizeImageFile = recognizeImageFile;

  final TextRecognizer? _recognizer;
  final RecognizeImageFile? _recognizeImageFile;
  final DisposeRecognizer? disposeRecognizer;

  @override
  Future<String> recognize(CaptureImage image) async {
    try {
      final injected = _recognizeImageFile;
      if (injected != null) {
        return await injected(image.path);
      }
      final recognized = await _recognizer!.processImage(
        InputImage.fromFilePath(image.path),
      );
      return recognized.text;
    } on CaptureServiceException {
      rethrow;
    } catch (_) {
      throw const CaptureServiceException(
        CaptureServiceFailure.recognitionFailed,
      );
    }
  }

  @override
  void dispose() {
    final injected = disposeRecognizer;
    if (injected != null) {
      injected();
      return;
    }
    final recognizer = _recognizer;
    if (recognizer != null) {
      unawaited(recognizer.close());
    }
  }
}
