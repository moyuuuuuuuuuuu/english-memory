import 'capture_image.dart';

abstract interface class TextRecognitionService {
  Future<String> recognize(CaptureImage image);

  void dispose();
}
