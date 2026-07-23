import 'package:image_cropper/image_cropper.dart';

import 'capture_image.dart';
import 'capture_service_exception.dart';
import 'image_crop_service.dart';

typedef CropImageFile = Future<String?> Function(String sourcePath);

final class PluginImageCropService implements ImageCropService {
  PluginImageCropService({CropImageFile? cropImageFile})
    : _cropImageFile = cropImageFile ?? _cropWithPlugin;

  final CropImageFile _cropImageFile;

  @override
  Future<CaptureImage?> crop(CaptureImage image) async {
    try {
      final path = await _cropImageFile(image.path);
      return path == null ? null : CaptureImage(path);
    } on CaptureServiceException {
      rethrow;
    } catch (_) {
      throw const CaptureServiceException(CaptureServiceFailure.cropFailed);
    }
  }

  static Future<String?> _cropWithPlugin(String sourcePath) async {
    final file = await ImageCropper().cropImage(
      sourcePath: sourcePath,
      compressFormat: ImageCompressFormat.jpg,
      compressQuality: 92,
      uiSettings: [
        AndroidUiSettings(
          toolbarTitle: '裁剪英文内容',
          initAspectRatio: CropAspectRatioPreset.original,
          lockAspectRatio: false,
        ),
        IOSUiSettings(title: '裁剪英文内容'),
      ],
    );
    return file?.path;
  }
}
