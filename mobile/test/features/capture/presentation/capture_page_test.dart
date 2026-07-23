import 'dart:async';

import 'package:english_memory/features/capture/data/capture_image.dart';
import 'package:english_memory/features/capture/data/capture_service_exception.dart';
import 'package:english_memory/features/capture/data/image_crop_service.dart';
import 'package:english_memory/features/capture/data/image_source_service.dart';
import 'package:english_memory/features/capture/data/text_recognition_service.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/capture/presentation/capture_controller.dart';
import 'package:english_memory/features/capture/presentation/capture_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late PageImageSource source;
  late PageCropper cropper;
  late PageRecognizer recognizer;
  late CaptureController controller;
  late List<CaptureDraft> drafts;
  Completer<String>? saveBarrier;
  Object? saveError;

  setUp(() {
    source = PageImageSource();
    cropper = PageCropper();
    recognizer = PageRecognizer();
    drafts = [];
    controller = CaptureController(
      source,
      cropper,
      recognizer,
      saveDraft: (draft) async {
        drafts.add(draft);
        if (saveError case final error?) throw error;
        if (saveBarrier case final barrier?) return barrier.future;
        return 'local-id';
      },
    );
  });

  tearDown(() => controller.dispose());

  Future<void> pumpPage(WidgetTester tester) => tester.pumpWidget(
    MaterialApp(
      home: Scaffold(body: CapturePage(controller: controller)),
    ),
  );

  testWidgets('shows manual input plus camera and gallery entry points', (
    tester,
  ) async {
    await pumpPage(tester);

    expect(find.text('捕捉英语'), findsOneWidget);
    expect(find.byKey(const Key('capture_text')), findsOneWidget);
    expect(find.text('拍照识别'), findsOneWidget);
    expect(find.text('选择照片'), findsOneWidget);
    expect(find.text('单词'), findsOneWidget);
    expect(find.text('句子'), findsOneWidget);
    expect(find.text('自动'), findsOneWidget);
    expect(find.text('谐音故事'), findsOneWidget);
    expect(find.text('语义场景'), findsOneWidget);
  });

  testWidgets('camera OCR updates editable text and preview', (tester) async {
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = 'Hello world.';
    await pumpPage(tester);

    await tester.tap(find.byKey(const Key('capture_camera')));
    await tester.pumpAndSettle();

    expect(source.requested, [CaptureImageSource.camera]);
    expect(find.text('Hello world.'), findsOneWidget);
    expect(find.byKey(const Key('capture_preview')), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('capture_text')),
      'Edited sentence.',
    );
    expect(
      tester
          .widget<TextFormField>(find.byKey(const Key('capture_text')))
          .controller!
          .text,
      'Edited sentence.',
    );
  });

  testWidgets('gallery action requests the photo library', (tester) async {
    source.result = null;
    await pumpPage(tester);

    await tester.tap(find.byKey(const Key('capture_gallery')));
    await tester.pumpAndSettle();

    expect(source.requested, [CaptureImageSource.gallery]);
  });

  testWidgets('processing disables source buttons and shows progress', (
    tester,
  ) async {
    source.barrier = Completer<CaptureImage?>();
    await pumpPage(tester);

    await tester.tap(find.byKey(const Key('capture_camera')));
    await tester.pump();

    expect(find.text('正在打开图片…'), findsOneWidget);
    expect(
      tester
          .widget<OutlinedButton>(find.byKey(const Key('capture_camera')))
          .onPressed,
      isNull,
    );

    source.barrier!.complete(null);
    await tester.pumpAndSettle();
  });

  testWidgets('Continue durably saves then clears with feedback', (
    tester,
  ) async {
    await pumpPage(tester);
    await tester.enterText(
      find.byKey(const Key('capture_text')),
      'A vivid memory.',
    );
    await tester.ensureVisible(find.text('句子'));
    await tester.tap(find.text('句子'));
    await tester.pump();
    await tester.ensureVisible(find.text('语义场景'));
    await tester.tap(find.text('语义场景'));
    await tester.pump();
    await tester.ensureVisible(find.byKey(const Key('capture_continue')));
    await tester.tap(find.byKey(const Key('capture_continue')));
    await tester.pump();

    expect(drafts, hasLength(1));
    expect(drafts.single.text, 'A vivid memory.');
    expect(drafts.single.contentType.apiValue, 'sentence');
    expect(drafts.single.memoryStyle.apiValue, 'semantic_scene');
    expect(find.text('已保存，正在同步'), findsOneWidget);
    expect(
      tester
          .widget<TextFormField>(find.byKey(const Key('capture_text')))
          .controller!
          .text,
      isEmpty,
    );
  });

  testWidgets('saving disables duplicate submission', (tester) async {
    saveBarrier = Completer<String>();
    await pumpPage(tester);
    await tester.enterText(find.byKey(const Key('capture_text')), 'ambition');
    await tester.ensureVisible(find.byKey(const Key('capture_continue')));

    await tester.tap(find.byKey(const Key('capture_continue')));
    await tester.pump();

    expect(
      tester
          .widget<FilledButton>(find.byKey(const Key('capture_continue')))
          .onPressed,
      isNull,
    );
    expect(drafts, hasLength(1));
    saveBarrier!.complete('local-id');
    await tester.pumpAndSettle();
  });

  testWidgets('local save failure keeps the entered content', (tester) async {
    saveError = StateError('disk detail');
    await pumpPage(tester);
    await tester.enterText(find.byKey(const Key('capture_text')), 'ambition');
    await tester.ensureVisible(find.byKey(const Key('capture_continue')));

    await tester.tap(find.byKey(const Key('capture_continue')));
    await tester.pumpAndSettle();

    expect(find.text('无法保存到本机，请重试。'), findsOneWidget);
    expect(find.text('ambition'), findsOneWidget);
    expect(find.textContaining('disk detail'), findsNothing);
  });

  testWidgets('empty Continue shows focused validation', (tester) async {
    await pumpPage(tester);

    await tester.ensureVisible(find.byKey(const Key('capture_continue')));
    await tester.tap(find.byKey(const Key('capture_continue')));
    await tester.pump();

    expect(find.text('请输入要记忆的英文内容'), findsOneWidget);
    expect(drafts, isEmpty);
  });

  testWidgets('permission failure is shown without raw platform details', (
    tester,
  ) async {
    source.error = const CaptureServiceException(
      CaptureServiceFailure.permissionDenied,
    );
    await pumpPage(tester);

    await tester.tap(find.byKey(const Key('capture_camera')));
    await tester.pumpAndSettle();

    expect(find.textContaining('系统设置'), findsOneWidget);
    expect(find.textContaining('permissionDenied'), findsNothing);
  });

  testWidgets('remove image keeps recognized text', (tester) async {
    source.result = const CaptureImage('/camera.jpg');
    cropper.result = const CaptureImage('/cropped.jpg');
    recognizer.result = 'ambition';
    await pumpPage(tester);
    await tester.tap(find.byKey(const Key('capture_camera')));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('移除图片'));
    await tester.tap(find.text('移除图片'));
    await tester.pump();

    expect(find.byKey(const Key('capture_preview')), findsNothing);
    expect(find.text('ambition'), findsOneWidget);
  });
}

final class PageImageSource implements ImageSourceService {
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

final class PageCropper implements ImageCropService {
  CaptureImage? result;

  @override
  Future<CaptureImage?> crop(CaptureImage image) async => result;
}

final class PageRecognizer implements TextRecognitionService {
  String result = '';

  @override
  Future<String> recognize(CaptureImage image) async => result;

  @override
  void dispose() {}
}
