import 'capture_image.dart';

enum CaptureImageSource { camera, gallery }

abstract interface class ImageSourceService {
  Future<CaptureImage?> pick(CaptureImageSource source);
}
