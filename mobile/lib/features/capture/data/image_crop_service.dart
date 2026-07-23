import 'capture_image.dart';

abstract interface class ImageCropService {
  Future<CaptureImage?> crop(CaptureImage image);
}
