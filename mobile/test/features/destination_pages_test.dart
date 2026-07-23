import 'package:english_memory/features/capture/data/capture_image.dart';
import 'package:english_memory/features/capture/data/image_crop_service.dart';
import 'package:english_memory/features/capture/data/image_source_service.dart';
import 'package:english_memory/features/capture/data/text_recognition_service.dart';
import 'package:english_memory/features/capture/presentation/capture_controller.dart';
import 'package:english_memory/features/capture/presentation/capture_page.dart';
import 'package:english_memory/features/home/presentation/home_page.dart';
import 'package:english_memory/features/library/presentation/library_page.dart';
import 'package:english_memory/features/profile/presentation/profile_page.dart';
import 'package:english_memory/features/review/presentation/review_page.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const cases = <(Widget, String)>[
    (HomePage(), '今天想记住什么？'),
    (ReviewPage(), '复习'),
    (LibraryPage(), '卡片库'),
    (
      ProfilePage(
        user: AuthenticatedUser(
          id: 1,
          email: 'learner@example.com',
          username: null,
          timezone: 'Asia/Shanghai',
        ),
        onLogout: _noopLogout,
      ),
      '我的',
    ),
  ];

  for (final (page, title) in cases) {
    testWidgets('$title identifies its page', (tester) async {
      await tester.pumpWidget(MaterialApp(home: page));

      expect(find.text(title), findsOneWidget);
    });
  }

  testWidgets('捕捉英语 identifies its page', (tester) async {
    final controller = CaptureController(
      _NoopImageSource(),
      _NoopCropper(),
      _NoopRecognizer(),
    );
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: CapturePage(controller: controller)),
      ),
    );

    expect(find.text('捕捉英语'), findsOneWidget);
  });
}

Future<void> _noopLogout() async {}

final class _NoopImageSource implements ImageSourceService {
  @override
  Future<CaptureImage?> pick(CaptureImageSource source) async => null;
}

final class _NoopCropper implements ImageCropService {
  @override
  Future<CaptureImage?> crop(CaptureImage image) async => null;
}

final class _NoopRecognizer implements TextRecognitionService {
  @override
  void dispose() {}

  @override
  Future<String> recognize(CaptureImage image) async => '';
}
