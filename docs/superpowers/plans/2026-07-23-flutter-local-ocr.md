# Flutter Local OCR Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a mobile-only capture workflow that accepts manual English, camera photos, or gallery photos; crops and recognizes Latin text locally; keeps the text editable; and emits a validated local draft.

**Architecture:** Application-owned domain types and service contracts isolate `image_picker`, `image_cropper`, and ML Kit from the controller and widgets. A `CaptureController` owns the cancellation-safe state machine, while `CapturePage` renders state and emits a local `CaptureDraft` without making a backend request.

**Tech Stack:** Flutter 3.44.7, Dart 3.12.2, Material 3, `image_picker 1.2.3`, `image_cropper 12.2.1`, `google_mlkit_text_recognition 0.16.0`, Flutter test.

## Global Constraints

- Android and iOS are the supported runtime platforms; desktop and web OCR are out of scope.
- Camera and gallery selection must both be supported.
- Images and recognized text remain local; this delivery makes no card-generation network request.
- Only ML Kit's default Latin recognizer is enabled.
- Cancelling selection or cropping must preserve the previous draft byte-for-byte.
- Raw plugin exceptions and paths must never be shown to the user.
- Content types serialize exactly as `word` and `sentence`.
- Memory styles serialize exactly as `auto`, `phonetic_story`, and `semantic_scene`.
- Production platform floors are Android API 24 and iOS 15.5.
- Every behavior change follows red-green-refactor and ends with fresh tests.

---

### Task 1: Add Mobile Image and OCR Dependencies

**Files:**
- Modify: `mobile/pubspec.yaml`
- Modify: `mobile/pubspec.lock`
- Modify: `mobile/android/app/build.gradle.kts`
- Modify: `mobile/android/app/src/main/AndroidManifest.xml`
- Modify: `mobile/ios/Runner/Info.plist`
- Modify: `mobile/ios/Runner.xcodeproj/project.pbxproj`
- Create: `mobile/test/platform/capture_platform_config_test.dart`

**Interfaces:**
- Consumes: Flutter's generated Android/iOS projects.
- Produces: Android API 24+, iOS 15.5+, camera/photo usage descriptions, registered `UCropActivity`, and locked plugin dependencies.

- [ ] **Step 1: Write the failing platform-configuration test**

```dart
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Android config supports picker and registers cropper', () {
    final gradle = File('android/app/build.gradle.kts').readAsStringSync();
    final manifest = File(
      'android/app/src/main/AndroidManifest.xml',
    ).readAsStringSync();

    expect(gradle, contains('minSdk = 24'));
    expect(manifest, contains('com.yalantis.ucrop.UCropActivity'));
  });

  test('iOS config declares permissions and deployment target', () {
    final plist = File('ios/Runner/Info.plist').readAsStringSync();
    final project = File(
      'ios/Runner.xcodeproj/project.pbxproj',
    ).readAsStringSync();

    expect(plist, contains('NSCameraUsageDescription'));
    expect(plist, contains('NSPhotoLibraryUsageDescription'));
    expect(project, isNot(contains('IPHONEOS_DEPLOYMENT_TARGET = 13.0')));
    expect(project, contains('IPHONEOS_DEPLOYMENT_TARGET = 15.5'));
  });
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
cd mobile
flutter test test/platform/capture_platform_config_test.dart
```

Expected: FAIL because the SDK floors, permission descriptions, and crop activity are absent.

- [ ] **Step 3: Add dependencies and platform configuration**

Add to `dependencies`:

```yaml
  google_mlkit_text_recognition: ^0.16.0
  image_cropper: ^12.2.1
  image_picker: ^1.2.3
```

Set Android's floor:

```kotlin
defaultConfig {
    applicationId = "com.englishmemory.english_memory"
    minSdk = 24
    targetSdk = flutter.targetSdkVersion
    versionCode = flutter.versionCode
    versionName = flutter.versionName
}
```

Register uCrop inside `<application>`:

```xml
<activity
    android:name="com.yalantis.ucrop.UCropActivity"
    android:screenOrientation="portrait"
    android:theme="@style/Theme.AppCompat.Light.NoActionBar" />
```

Add the iOS keys:

```xml
<key>NSCameraUsageDescription</key>
<string>用于拍摄需要识别的英文内容。</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>用于选择需要识别的英文图片。</string>
```

Replace every project deployment target with:

```text
IPHONEOS_DEPLOYMENT_TARGET = 15.5;
```

Run `flutter pub get` to update the lockfile.

- [ ] **Step 4: Verify GREEN and dependency scope**

Run:

```bash
cd mobile
flutter test test/platform/capture_platform_config_test.dart
flutter pub deps --style=compact
flutter analyze
```

Expected: platform test passes, all three direct dependencies resolve, and analysis reports no issues.

- [ ] **Step 5: Commit**

```bash
git add mobile/pubspec.yaml mobile/pubspec.lock \
  mobile/android/app/build.gradle.kts \
  mobile/android/app/src/main/AndroidManifest.xml \
  mobile/ios/Runner/Info.plist \
  mobile/ios/Runner.xcodeproj/project.pbxproj \
  mobile/test/platform/capture_platform_config_test.dart
git commit -m "build: configure Flutter local OCR plugins"
```

---

### Task 2: Define Capture Draft Domain Types

**Files:**
- Create: `mobile/lib/features/capture/domain/capture_content_type.dart`
- Create: `mobile/lib/features/capture/domain/capture_memory_style.dart`
- Create: `mobile/lib/features/capture/domain/capture_draft.dart`
- Create: `mobile/lib/features/capture/domain/recognized_text_normalizer.dart`
- Create: `mobile/test/features/capture/domain/capture_draft_test.dart`

**Interfaces:**
- Consumes: raw or manually edited text.
- Produces: `CaptureContentType`, `CaptureMemoryStyle`, `CaptureDraft`, `normalizeRecognizedText(String)`, and `suggestContentType(String)`.

- [ ] **Step 1: Write failing domain tests**

```dart
import 'package:english_memory/features/capture/domain/capture_content_type.dart';
import 'package:english_memory/features/capture/domain/capture_draft.dart';
import 'package:english_memory/features/capture/domain/capture_memory_style.dart';
import 'package:english_memory/features/capture/domain/recognized_text_normalizer.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('enums serialize to backend values', () {
    expect(CaptureContentType.word.apiValue, 'word');
    expect(CaptureContentType.sentence.apiValue, 'sentence');
    expect(CaptureMemoryStyle.auto.apiValue, 'auto');
    expect(
      CaptureMemoryStyle.phoneticStory.apiValue,
      'phonetic_story',
    );
    expect(
      CaptureMemoryStyle.semanticScene.apiValue,
      'semantic_scene',
    );
  });

  test('normalizes whitespace without rewriting content', () {
    expect(
      normalizeRecognizedText('  Hello  \r\nworld!   \n\n\nAgain.  '),
      'Hello\nworld!\n\nAgain.',
    );
  });

  test('suggests word or sentence without overriding the selection', () {
    expect(suggestContentType('ambition'), CaptureContentType.word);
    expect(
      suggestContentType('Build a vivid memory.'),
      CaptureContentType.sentence,
    );
  });

  test('draft trims text and rejects empty content', () {
    final draft = CaptureDraft(
      text: ' ambition ',
      contentType: CaptureContentType.word,
      memoryStyle: CaptureMemoryStyle.auto,
    );
    expect(draft.text, 'ambition');
    expect(
      () => CaptureDraft(
        text: ' \n ',
        contentType: CaptureContentType.sentence,
        memoryStyle: CaptureMemoryStyle.semanticScene,
      ),
      throwsFormatException,
    );
  });
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
cd mobile
flutter test test/features/capture/domain/capture_draft_test.dart
```

Expected: compilation fails because the capture domain files do not exist.

- [ ] **Step 3: Implement the domain types**

Use enhanced enums:

```dart
enum CaptureContentType {
  word('word'),
  sentence('sentence');

  const CaptureContentType(this.apiValue);
  final String apiValue;
}
```

```dart
enum CaptureMemoryStyle {
  auto('auto'),
  phoneticStory('phonetic_story'),
  semanticScene('semantic_scene');

  const CaptureMemoryStyle(this.apiValue);
  final String apiValue;
}
```

Implement normalization:

```dart
String normalizeRecognizedText(String value) {
  final lines = value
      .replaceAll('\r\n', '\n')
      .replaceAll('\r', '\n')
      .split('\n')
      .map((line) => line.trimRight())
      .toList();
  return lines.join('\n').trim().replaceAll(RegExp(r'\n{3,}'), '\n\n');
}

CaptureContentType suggestContentType(String value) {
  final text = normalizeRecognizedText(value);
  return RegExp(r'\s|[.!?。！？]').hasMatch(text)
      ? CaptureContentType.sentence
      : CaptureContentType.word;
}
```

Implement `CaptureDraft` with final fields, constructor normalization, empty-text validation, equality, `hashCode`, and `toJson()` using the exact API values.

- [ ] **Step 4: Verify GREEN and commit**

Run:

```bash
cd mobile
flutter test test/features/capture/domain/capture_draft_test.dart
flutter analyze
cd ..
git add mobile/lib/features/capture/domain \
  mobile/test/features/capture/domain/capture_draft_test.dart
git commit -m "feat: define Flutter capture drafts"
```

Expected: domain tests pass and analysis reports no issues.

---

### Task 3: Isolate Picker, Cropper, and OCR Plugins

**Files:**
- Create: `mobile/lib/features/capture/data/capture_image.dart`
- Create: `mobile/lib/features/capture/data/capture_service_exception.dart`
- Create: `mobile/lib/features/capture/data/image_source_service.dart`
- Create: `mobile/lib/features/capture/data/image_crop_service.dart`
- Create: `mobile/lib/features/capture/data/text_recognition_service.dart`
- Create: `mobile/lib/features/capture/data/plugin_image_source_service.dart`
- Create: `mobile/lib/features/capture/data/plugin_image_crop_service.dart`
- Create: `mobile/lib/features/capture/data/mlkit_text_recognition_service.dart`
- Create: `mobile/test/features/capture/data/capture_services_test.dart`

**Interfaces:**
- Produces:
  - `CaptureImage(String path)`
  - `enum CaptureImageSource { camera, gallery }`
  - `ImageSourceService.pick(CaptureImageSource): Future<CaptureImage?>`
  - `ImageCropService.crop(CaptureImage): Future<CaptureImage?>`
  - `TextRecognitionService.recognize(CaptureImage): Future<String>`
  - `TextRecognitionService.dispose(): void`
  - `CaptureServiceException(CaptureServiceFailure failure)`

- [ ] **Step 1: Write failing contract and adapter tests**

Give each production adapter one testing seam:

```dart
typedef PickImageFile = Future<String?> Function(CaptureImageSource source);
typedef CropImageFile = Future<String?> Function(String sourcePath);
typedef RecognizeImageFile = Future<String> Function(String sourcePath);
```

The public constructors accept nullable versions of these callbacks and use the real plugin when omitted. Tests inject callbacks that record arguments and return deterministic paths/text. Assert:

```dart
expect(await source.pick(CaptureImageSource.camera), CaptureImage('/camera.jpg'));
expect(await source.pick(CaptureImageSource.gallery), CaptureImage('/gallery.jpg'));
expect(await cropper.crop(const CaptureImage('/source.jpg')),
    const CaptureImage('/cropped.jpg'));
expect(await recognizer.recognize(const CaptureImage('/cropped.jpg')),
    'Recognized English');
```

Also assert cancellation returns `null` and a camera permission `PlatformException` maps to `CaptureServiceFailure.permissionDenied`. Keep `_recognizeWithMlKit` private and verify by source inspection that it uses both `InputImage.fromFilePath(path)` and `TextRecognitionScript.latin`; behavior tests exercise the injected recognizer callback.

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
cd mobile
flutter test test/features/capture/data/capture_services_test.dart
```

Expected: compilation fails because the service contracts and adapters do not exist.

- [ ] **Step 3: Implement contracts and production adapters**

Define the contracts:

```dart
enum CaptureImageSource { camera, gallery }

abstract interface class ImageSourceService {
  Future<CaptureImage?> pick(CaptureImageSource source);
}

abstract interface class ImageCropService {
  Future<CaptureImage?> crop(CaptureImage image);
}

abstract interface class TextRecognitionService {
  Future<String> recognize(CaptureImage image);
  void dispose();
}
```

The picker maps application sources to `ImageSource.camera` and `ImageSource.gallery`, returning only `XFile.path`. The cropper uses free-form rectangular cropping, JPEG quality 92, and platform UI titles in Chinese. Each adapter uses its injected callback when present. Otherwise, the ML Kit adapter owns:

```dart
final TextRecognizer _recognizer = TextRecognizer(
  script: TextRecognitionScript.latin,
);
```

It returns `recognizedText.text` and closes the recognizer in `dispose()`. Catch `PlatformException` inside adapters and throw application-owned failures:

```dart
enum CaptureServiceFailure {
  permissionDenied,
  cameraUnavailable,
  selectionFailed,
  cropFailed,
  recognitionFailed,
}
```

- [ ] **Step 4: Verify GREEN and commit**

Run:

```bash
cd mobile
flutter test test/features/capture/data/capture_services_test.dart
flutter analyze
cd ..
git add mobile/lib/features/capture/data \
  mobile/test/features/capture/data/capture_services_test.dart
git commit -m "feat: isolate Flutter local OCR services"
```

Expected: service tests pass and presentation imports no plugin package.

---

### Task 4: Implement the Cancellation-Safe Capture Controller

**Files:**
- Create: `mobile/lib/features/capture/presentation/capture_state.dart`
- Create: `mobile/lib/features/capture/presentation/capture_controller.dart`
- Create: `mobile/test/features/capture/presentation/capture_controller_test.dart`

**Interfaces:**
- Consumes: the three service contracts and capture domain types.
- Produces:
  - `CaptureController(ImageSourceService, ImageCropService, TextRecognitionService)`
  - `CaptureState get state`
  - `Future<void> acquire(CaptureImageSource source)`
  - `void editText(String text)`
  - `void selectContentType(CaptureContentType value)`
  - `void selectMemoryStyle(CaptureMemoryStyle value)`
  - `void removeImage()`
  - `CaptureDraft createDraft()`

- [ ] **Step 1: Write failing controller tests**

Define `FakeImageSourceService`, `FakeImageCropService`, and `FakeTextRecognitionService` in the test file. Each fake records calls and exposes a result, an exception, and an optional `Completer` barrier. Cover these exact transitions:

```dart
expect(controller.state, isA<CaptureReady>());
await controller.acquire(CaptureImageSource.camera);
expect(source.requested, CaptureImageSource.camera);
expect((controller.state as CaptureReady).text, 'Hello world');
expect((controller.state as CaptureReady).image?.path, '/cropped.jpg');
```

Add separate tests for gallery selection, picker cancellation, crop cancellation, source/crop/OCR failures, no ASCII letters, draft preservation, content-type suggestion after OCR, manual selection remaining authoritative after edits, duplicate acquisition calls, image removal, and `createDraft()`.

The duplicate-call test holds the picker with a `Completer`:

```dart
final first = controller.acquire(CaptureImageSource.camera);
final second = controller.acquire(CaptureImageSource.gallery);
expect(source.calls, 1);
barrier.complete(const CaptureImage('/camera.jpg'));
await Future.wait([first, second]);
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
cd mobile
flutter test test/features/capture/presentation/capture_controller_test.dart
```

Expected: compilation fails because controller and state types do not exist.

- [ ] **Step 3: Implement states and orchestration**

Use sealed states:

```dart
sealed class CaptureState {
  const CaptureState();
}

final class CaptureReady extends CaptureState {
  const CaptureReady({
    required this.text,
    required this.contentType,
    required this.memoryStyle,
    this.image,
    this.message,
  });
  final String text;
  final CaptureContentType contentType;
  final CaptureMemoryStyle memoryStyle;
  final CaptureImage? image;
  final String? message;
}

final class CaptureSelectingImage extends CaptureState {
  const CaptureSelectingImage(this.previous);
  final CaptureReady previous;
}

final class CaptureCroppingImage extends CaptureState {
  const CaptureCroppingImage(this.previous);
  final CaptureReady previous;
}

final class CaptureRecognizingText extends CaptureState {
  const CaptureRecognizingText(this.previous, this.image);
  final CaptureReady previous;
  final CaptureImage image;
}

final class CaptureFailure extends CaptureState {
  const CaptureFailure(this.previous, this.message);
  final CaptureReady previous;
  final String message;
}
```

Controller rules:

- derive `_ready` from every state;
- reject acquisition unless current state is ready or failure;
- restore `previous` without notification message for cancellation;
- require at least one `[A-Za-z]` in OCR output;
- preserve previous text on empty/non-English OCR;
- suggest content type only after successful OCR, not after manual edits;
- map every `CaptureServiceFailure` to stable Chinese copy;
- call recognition service `dispose()` from controller `dispose()`.

- [ ] **Step 4: Verify GREEN and commit**

Run:

```bash
cd mobile
flutter test test/features/capture/presentation/capture_controller_test.dart
flutter analyze
cd ..
git add mobile/lib/features/capture/presentation/capture_state.dart \
  mobile/lib/features/capture/presentation/capture_controller.dart \
  mobile/test/features/capture/presentation/capture_controller_test.dart
git commit -m "feat: coordinate Flutter local OCR capture"
```

Expected: every cancellation and failure test passes without draft loss.

---

### Task 5: Build the Capture and Confirmation UI

**Files:**
- Modify: `mobile/lib/features/capture/presentation/capture_page.dart`
- Modify: `mobile/lib/core/l10n/app_strings.dart`
- Create: `mobile/test/features/capture/presentation/capture_page_test.dart`
- Modify: `mobile/test/features/destination_pages_test.dart`

**Interfaces:**
- Consumes: `CaptureController`.
- Produces: `CapturePage({required CaptureController controller, ValueChanged<CaptureDraft>? onDraftReady})`.

- [ ] **Step 1: Write failing widget tests**

Create controller fakes through the service contracts and pump:

```dart
MaterialApp(
  home: CapturePage(
    controller: controller,
    onDraftReady: drafts.add,
  ),
)
```

Assert:

- title and manual multiline field render;
- `拍照识别` requests camera;
- `选择照片` requests gallery;
- buttons disable and progress copy appears during selection/crop/OCR;
- recognized text updates the editable field;
- preview renders from the cropped path and `移除图片` removes it;
- word/sentence and all three memory-style choices update controller state;
- empty Continue shows `请输入要记忆的英文内容`;
- valid Continue emits one `CaptureDraft`;
- a SnackBar says `生成与同步将在下一阶段接入`;
- permission and OCR failures show stable Chinese messages;
- no Dio or repository fake is required by the page.

- [ ] **Step 2: Run the tests and verify RED**

Run:

```bash
cd mobile
flutter test test/features/capture/presentation/capture_page_test.dart \
  test/features/destination_pages_test.dart
```

Expected: compilation fails because `CapturePage` does not accept a controller.

- [ ] **Step 3: Implement the responsive form**

Use `ListenableBuilder`, `Form`, a disposed `TextEditingController`, `SingleChildScrollView`, and a max-width constraint. Synchronize controller-to-field OCR changes without moving the cursor during ordinary manual edits.

Required keys:

```dart
const Key('capture_text');
const Key('capture_camera');
const Key('capture_gallery');
const Key('capture_preview');
const Key('capture_continue');
```

Use segmented controls for content type, choice chips for memory style, and semantic error text. Continue executes:

```dart
try {
  final draft = widget.controller.createDraft();
  widget.onDraftReady?.call(draft);
  if (!mounted) return;
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(content: Text('生成与同步将在下一阶段接入')),
  );
} on FormatException {
  setState(() => _validationMessage = '请输入要记忆的英文内容');
}
```

Update the destination-page test to create `CapturePage(controller: fakeController)` and dispose the controller after each test.

- [ ] **Step 4: Verify GREEN and commit**

Run:

```bash
cd mobile
flutter test test/features/capture/presentation/capture_page_test.dart \
  test/features/destination_pages_test.dart
flutter analyze
cd ..
git add mobile/lib/features/capture/presentation/capture_page.dart \
  mobile/lib/core/l10n/app_strings.dart \
  mobile/test/features/capture/presentation/capture_page_test.dart \
  mobile/test/features/destination_pages_test.dart
git commit -m "feat: add Flutter local OCR capture screen"
```

Expected: widget tests pass at a 320-pixel test width without overflow.

---

### Task 6: Compose Capture Dependencies and Preserve Tab State

**Files:**
- Modify: `mobile/lib/app/app_dependencies.dart`
- Modify: `mobile/lib/app/authentication_gate.dart`
- Modify: `mobile/lib/app/main_shell.dart`
- Modify: `mobile/test/app/app_dependencies_test.dart`
- Modify: `mobile/test/app/authentication_gate_test.dart`
- Modify: `mobile/test/app/main_shell_test.dart`
- Modify: `mobile/test/widget_test.dart`

**Interfaces:**
- Consumes: production plugin adapters and `CaptureController`.
- Produces:
  - `AppDependencies({required SessionController controller, required CaptureController captureController})`
  - `AppDependencies.captureController`
  - `AuthenticationGate({required SessionController controller, required CaptureController captureController})`
  - `MainShell({required AuthenticatedUser user, required Future<void> Function() onLogout, required CaptureController captureController})`

- [ ] **Step 1: Write failing composition and preservation tests**

Add a production-dependency assertion:

```dart
expect(dependencies.captureController, isNull);
```

for invalid API configuration, proving no capture plugin is constructed in the configuration-error path.

For injected dependencies, construct both controllers and assert the authenticated gate passes the exact capture controller into `MainShell`.

Extend the shell preservation test:

```dart
await tester.tap(find.text(AppStrings.capture));
await tester.pumpAndSettle();
await tester.enterText(find.byKey(const Key('capture_text')), 'kept draft');
await tester.tap(find.text(AppStrings.home));
await tester.pumpAndSettle();
await tester.tap(find.text(AppStrings.capture));
await tester.pumpAndSettle();
expect(find.text('kept draft'), findsOneWidget);
```

- [ ] **Step 2: Run the tests and verify RED**

Run:

```bash
cd mobile
flutter test test/app/app_dependencies_test.dart \
  test/app/authentication_gate_test.dart \
  test/app/main_shell_test.dart \
  test/widget_test.dart
```

Expected: compilation fails on the new required capture-controller contracts.

- [ ] **Step 3: Wire production and injected dependencies**

`AppDependencies.production()` constructs:

```dart
final captureController = CaptureController(
  PluginImageSourceService(),
  PluginImageCropService(),
  MlKitTextRecognitionService(),
);
```

Only construct it after `ApiConfig.parse` succeeds. Store it on dependencies and dispose it alongside the session controller.

Pass the same controller through `EnglishMemoryApp -> AuthenticationGate -> MainShell -> CapturePage`. Keep `MainShell._pages` initialized once in `initState`, preserving text and selector state across tab switches.

Update every test constructor with `FakeImageSourceService`, `FakeImageCropService`, and `FakeTextRecognitionService`; no test initializes a real platform plugin.

- [ ] **Step 4: Verify GREEN, run full Flutter tests, and commit**

Run:

```bash
cd mobile
flutter test test/app/app_dependencies_test.dart \
  test/app/authentication_gate_test.dart \
  test/app/main_shell_test.dart \
  test/widget_test.dart
flutter test
flutter analyze
cd ..
git add mobile/lib/app mobile/test/app mobile/test/widget_test.dart
git commit -m "feat: compose Flutter local OCR capture"
```

Expected: all Flutter tests pass, analysis is clean, and capture text survives tab changes.

---

### Task 7: Verify Platform Scope and Update Project Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`

**Interfaces:**
- Produces: exact stage-7 OCR completion evidence and next handoff at reliable pending synchronization.

- [ ] **Step 1: Run fresh Flutter gates**

```bash
cd mobile
flutter --version
flutter pub get
flutter analyze
flutter test
cd ..
git diff --check
```

Expected: Flutter 3.44.7 / Dart 3.12.2, no analysis issues, all tests pass, and no whitespace errors.

- [ ] **Step 2: Audit privacy and plugin boundaries**

Run:

```bash
rg -n "dio|http|post\\(|put\\(" mobile/lib/features/capture
rg -n "image_picker|image_cropper|google_mlkit" \
  mobile/lib/features/capture/presentation \
  mobile/lib/features/capture/domain
rg -n "TextRecognitionScript\\.(chinese|japanese|korean|devanagiri)" mobile
git status --short
```

Expected:

- capture has no network calls;
- domain and presentation import no plugin packages;
- no non-Latin ML Kit recognizer is enabled;
- only `PROJECT_PLAN.md` remains uncommitted after feature commits.

- [ ] **Step 3: Inspect platform configuration**

Run:

```bash
rg -n "minSdk = 24|UCropActivity" \
  mobile/android/app/build.gradle.kts \
  mobile/android/app/src/main/AndroidManifest.xml
rg -n "NSCameraUsageDescription|NSPhotoLibraryUsageDescription" \
  mobile/ios/Runner/Info.plist
rg -n "IPHONEOS_DEPLOYMENT_TARGET = 15.5" \
  mobile/ios/Runner.xcodeproj/project.pbxproj
```

Expected: all required mobile settings are present.

- [ ] **Step 4: Update `PROJECT_PLAN.md` from evidence**

Mark “实现拍照、裁剪、本地英文 OCR、可编辑确认和单词/句子选择” complete. Record:

- exact Flutter/Dart versions;
- direct plugin versions from `pubspec.lock`;
- exact test count;
- analysis result;
- branch `codex/flutter-local-ocr`;
- local-only privacy boundary;
- Android API 24 and iOS 15.5 floors;
- device smoke testing still required;
- next work is local cache and reliable pending synchronization.

- [ ] **Step 5: Commit the verified handoff**

```bash
git add PROJECT_PLAN.md
git commit -m "docs: record Flutter local OCR baseline"
git status --short --branch
```

Expected: branch is clean and contains one design commit, one plan commit, focused implementation commits, and the handoff commit.
