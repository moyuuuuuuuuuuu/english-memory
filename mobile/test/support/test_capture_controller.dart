import 'package:english_memory/features/capture/data/capture_image.dart';
import 'package:english_memory/features/capture/data/image_crop_service.dart';
import 'package:english_memory/features/capture/data/image_source_service.dart';
import 'package:english_memory/features/capture/data/text_recognition_service.dart';
import 'package:english_memory/features/capture/presentation/capture_controller.dart';

CaptureController createTestCaptureController() =>
    CaptureController(_TestImageSource(), _TestCropper(), _TestRecognizer());

final class _TestImageSource implements ImageSourceService {
  @override
  Future<CaptureImage?> pick(CaptureImageSource source) async => null;
}

final class _TestCropper implements ImageCropService {
  @override
  Future<CaptureImage?> crop(CaptureImage image) async => null;
}

final class _TestRecognizer implements TextRecognitionService {
  @override
  void dispose() {}

  @override
  Future<String> recognize(CaptureImage image) async => '';
}
