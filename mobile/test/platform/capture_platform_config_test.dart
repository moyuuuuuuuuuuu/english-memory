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
