import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import 'capture_image.dart';
import 'capture_service_exception.dart';
import 'image_source_service.dart';

typedef PickImageFile = Future<String?> Function(CaptureImageSource source);

final class PluginImageSourceService implements ImageSourceService {
  PluginImageSourceService({PickImageFile? pickImageFile})
    : _pickImageFile = pickImageFile ?? _pickWithPlugin;

  final PickImageFile _pickImageFile;

  @override
  Future<CaptureImage?> pick(CaptureImageSource source) async {
    try {
      final path = await _pickImageFile(source);
      return path == null ? null : CaptureImage(path);
    } on PlatformException catch (error) {
      throw CaptureServiceException(_mapPlatformFailure(error));
    } on CaptureServiceException {
      rethrow;
    } catch (_) {
      throw const CaptureServiceException(
        CaptureServiceFailure.selectionFailed,
      );
    }
  }

  static Future<String?> _pickWithPlugin(CaptureImageSource source) async {
    final file = await ImagePicker().pickImage(
      source: source == CaptureImageSource.camera
          ? ImageSource.camera
          : ImageSource.gallery,
      imageQuality: 96,
    );
    return file?.path;
  }

  CaptureServiceFailure _mapPlatformFailure(PlatformException error) {
    final code = error.code.toLowerCase();
    if (code.contains('denied') ||
        code.contains('restricted') ||
        code.contains('permission')) {
      return CaptureServiceFailure.permissionDenied;
    }
    if (code.contains('camera') && code.contains('unavailable')) {
      return CaptureServiceFailure.cameraUnavailable;
    }
    return CaptureServiceFailure.selectionFailed;
  }
}
