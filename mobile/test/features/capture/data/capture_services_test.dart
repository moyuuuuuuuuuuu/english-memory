import 'package:english_memory/features/capture/data/capture_image.dart';
import 'package:english_memory/features/capture/data/capture_service_exception.dart';
import 'package:english_memory/features/capture/data/image_source_service.dart';
import 'package:english_memory/features/capture/data/mlkit_text_recognition_service.dart';
import 'package:english_memory/features/capture/data/plugin_image_crop_service.dart';
import 'package:english_memory/features/capture/data/plugin_image_source_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('capture image compares by local path', () {
    expect(const CaptureImage('/image.jpg'), const CaptureImage('/image.jpg'));
  });

  test(
    'picker maps camera and gallery through the application source',
    () async {
      final requested = <CaptureImageSource>[];
      final service = PluginImageSourceService(
        pickImageFile: (source) async {
          requested.add(source);
          return source == CaptureImageSource.camera
              ? '/camera.jpg'
              : '/gallery.jpg';
        },
      );

      expect(
        await service.pick(CaptureImageSource.camera),
        const CaptureImage('/camera.jpg'),
      );
      expect(
        await service.pick(CaptureImageSource.gallery),
        const CaptureImage('/gallery.jpg'),
      );
      expect(requested, [
        CaptureImageSource.camera,
        CaptureImageSource.gallery,
      ]);
    },
  );

  test('picker returns null when the user cancels', () async {
    final service = PluginImageSourceService(pickImageFile: (_) async => null);

    expect(await service.pick(CaptureImageSource.gallery), isNull);
  });

  test('picker maps permission failures to an application exception', () async {
    final service = PluginImageSourceService(
      pickImageFile: (_) async =>
          throw PlatformException(code: 'camera_access_denied'),
    );

    await expectLater(
      service.pick(CaptureImageSource.camera),
      throwsA(
        isA<CaptureServiceException>().having(
          (error) => error.failure,
          'failure',
          CaptureServiceFailure.permissionDenied,
        ),
      ),
    );
  });

  test('cropper passes the source path and maps a successful result', () async {
    String? requestedPath;
    final service = PluginImageCropService(
      cropImageFile: (path) async {
        requestedPath = path;
        return '/cropped.jpg';
      },
    );

    final result = await service.crop(const CaptureImage('/source.jpg'));

    expect(requestedPath, '/source.jpg');
    expect(result, const CaptureImage('/cropped.jpg'));
  });

  test('cropper returns null when the user cancels', () async {
    final service = PluginImageCropService(cropImageFile: (_) async => null);

    expect(await service.crop(const CaptureImage('/source.jpg')), isNull);
  });

  test('recognizer passes the cropped path and disposes resources', () async {
    String? requestedPath;
    var disposed = false;
    final service = MlKitTextRecognitionService(
      recognizeImageFile: (path) async {
        requestedPath = path;
        return 'Recognized English';
      },
      disposeRecognizer: () => disposed = true,
    );

    final text = await service.recognize(const CaptureImage('/cropped.jpg'));
    service.dispose();

    expect(requestedPath, '/cropped.jpg');
    expect(text, 'Recognized English');
    expect(disposed, isTrue);
  });

  test('recognizer maps platform errors without leaking details', () async {
    final service = MlKitTextRecognitionService(
      recognizeImageFile: (_) async =>
          throw PlatformException(code: 'recognizer_failed'),
    );

    await expectLater(
      service.recognize(const CaptureImage('/cropped.jpg')),
      throwsA(
        isA<CaptureServiceException>().having(
          (error) => error.failure,
          'failure',
          CaptureServiceFailure.recognitionFailed,
        ),
      ),
    );
  });
}
