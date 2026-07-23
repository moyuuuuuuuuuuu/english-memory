import 'dart:async';

import 'package:english_memory/features/capture/data/capture_image.dart';
import 'package:english_memory/features/capture/data/capture_service_exception.dart';
import 'package:english_memory/features/capture/data/image_crop_service.dart';
import 'package:english_memory/features/capture/data/image_source_service.dart';
import 'package:english_memory/features/capture/data/text_recognition_service.dart';
import 'package:english_memory/features/capture/domain/capture_content_type.dart';
import 'package:english_memory/features/capture/domain/capture_memory_style.dart';
import 'package:english_memory/features/capture/presentation/capture_controller.dart';
import 'package:english_memory/features/capture/presentation/capture_state.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late FakeImageSourceService source;
  late FakeImageCropService cropper;
  late FakeTextRecognitionService recognizer;
  late CaptureController controller;

  setUp(() {
    source = FakeImageSourceService();
    cropper = FakeImageCropService();
    recognizer = FakeTextRecognitionService();
    controller = CaptureController(source, cropper, recognizer);
  });

  tearDown(() => controller.dispose());

  test('starts ready with default selections', () {
    final state = controller.state as CaptureReady;

    expect(state.text, isEmpty);
    expect(state.contentType, CaptureContentType.word);
    expect(state.memoryStyle, CaptureMemoryStyle.auto);
    expect(state.image, isNull);
  });

  test('camera acquisition crops, recognizes, and suggests sentence', () async {
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = '  Hello world.  ';

    await controller.acquire(CaptureImageSource.camera);

    expect(source.requested, [CaptureImageSource.camera]);
    expect(cropper.requested, [const CaptureImage('/camera.jpg')]);
    expect(recognizer.requested, [const CaptureImage('/cropped.jpg')]);
    final state = controller.state as CaptureReady;
    expect(state.text, 'Hello world.');
    expect(state.image, const CaptureImage('/cropped.jpg'));
    expect(state.contentType, CaptureContentType.sentence);
  });

  test('gallery acquisition uses the gallery source', () async {
    source.result = const CaptureImage('/gallery.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = 'ambition';

    await controller.acquire(CaptureImageSource.gallery);

    expect(source.requested, [CaptureImageSource.gallery]);
    expect(
      (controller.state as CaptureReady).contentType,
      CaptureContentType.word,
    );
  });

  test('picker cancellation preserves the exact previous draft', () async {
    controller
      ..editText('keep me')
      ..selectMemoryStyle(CaptureMemoryStyle.semanticScene);
    final before = controller.state;
    source.result = null;

    await controller.acquire(CaptureImageSource.camera);

    expect(controller.state, same(before));
  });

  test('crop cancellation preserves the exact previous draft', () async {
    controller.editText('keep me');
    final before = controller.state;
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = null;

    await controller.acquire(CaptureImageSource.camera);

    expect(controller.state, same(before));
  });

  test('permission failure retains draft and exposes stable message', () async {
    controller.editText('keep me');
    source.error = const CaptureServiceException(
      CaptureServiceFailure.permissionDenied,
    );

    await controller.acquire(CaptureImageSource.camera);

    final state = controller.state as CaptureFailure;
    expect(state.previous.text, 'keep me');
    expect(state.message, contains('系统设置'));
  });

  test('crop and recognition failures use focused messages', () async {
    source.result = const CaptureImage('/camera.jpg');
    cropper.error = const CaptureServiceException(
      CaptureServiceFailure.cropFailed,
    );
    await controller.acquire(CaptureImageSource.camera);
    expect((controller.state as CaptureFailure).message, contains('裁剪'));

    cropper
      ..error = null
      ..result = const CaptureImage('/cropped.jpg');
    recognizer.error = const CaptureServiceException(
      CaptureServiceFailure.recognitionFailed,
    );
    await controller.acquire(CaptureImageSource.camera);
    expect((controller.state as CaptureFailure).message, contains('识别'));
  });

  test('non-English OCR keeps old text and cropped preview', () async {
    controller.editText('keep me');
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = '123 !!!';

    await controller.acquire(CaptureImageSource.camera);

    final state = controller.state as CaptureFailure;
    expect(state.previous.text, 'keep me');
    expect(state.previous.image, const CaptureImage('/cropped.jpg'));
    expect(state.message, contains('英文'));
  });

  test('manual type selection remains authoritative during edits', () {
    controller
      ..selectContentType(CaptureContentType.sentence)
      ..editText('word');

    expect(
      (controller.state as CaptureReady).contentType,
      CaptureContentType.sentence,
    );
  });

  test('duplicate acquisition is ignored while picker is active', () async {
    source.barrier = Completer<CaptureImage?>();

    final first = controller.acquire(CaptureImageSource.camera);
    final second = controller.acquire(CaptureImageSource.gallery);
    expect(source.requested, [CaptureImageSource.camera]);

    source.barrier!.complete(null);
    await Future.wait([first, second]);
    expect(source.requested, [CaptureImageSource.camera]);
  });

  test('remove image keeps edited text and selections', () async {
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = 'hello';
    await controller.acquire(CaptureImageSource.camera);
    controller
      ..selectMemoryStyle(CaptureMemoryStyle.phoneticStory)
      ..removeImage();

    final state = controller.state as CaptureReady;
    expect(state.image, isNull);
    expect(state.text, 'hello');
    expect(state.memoryStyle, CaptureMemoryStyle.phoneticStory);
  });

  test('createDraft emits the current validated local draft', () {
    controller
      ..editText(' vivid memory ')
      ..selectContentType(CaptureContentType.sentence)
      ..selectMemoryStyle(CaptureMemoryStyle.semanticScene);

    final draft = controller.createDraft();

    expect(draft.text, 'vivid memory');
    expect(draft.contentType, CaptureContentType.sentence);
    expect(draft.memoryStyle, CaptureMemoryStyle.semanticScene);
  });

  test('disposing the controller releases OCR resources', () {
    controller.dispose();
    expect(recognizer.disposed, isTrue);
    controller = CaptureController(source, cropper, recognizer);
  });
}

final class FakeImageSourceService implements ImageSourceService {
  CaptureImage? result;
  CaptureServiceException? error;
  Completer<CaptureImage?>? barrier;
  final List<CaptureImageSource> requested = [];

  @override
  Future<CaptureImage?> pick(CaptureImageSource source) async {
    requested.add(source);
    if (error case final value?) throw value;
    if (barrier case final value?) return value.future;
    return result;
  }
}

final class FakeImageCropService implements ImageCropService {
  CaptureImage? result;
  CaptureServiceException? error;
  final List<CaptureImage> requested = [];

  @override
  Future<CaptureImage?> crop(CaptureImage image) async {
    requested.add(image);
    if (error case final value?) throw value;
    return result;
  }
}

final class FakeTextRecognitionService implements TextRecognitionService {
  String result = '';
  CaptureServiceException? error;
  bool disposed = false;
  final List<CaptureImage> requested = [];

  @override
  Future<String> recognize(CaptureImage image) async {
    requested.add(image);
    if (error case final value?) throw value;
    return result;
  }

  @override
  void dispose() => disposed = true;
}
