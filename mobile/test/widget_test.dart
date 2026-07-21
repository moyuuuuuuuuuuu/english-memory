import 'package:english_memory/app/english_memory_app.dart';
import 'package:english_memory/core/l10n/app_strings.dart';
import 'package:english_memory/core/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('app starts on Home with the shared theme', (tester) async {
    await tester.pumpWidget(const EnglishMemoryApp());

    final app = tester.widget<MaterialApp>(find.byType(MaterialApp));
    expect(app.title, AppStrings.appName);
    expect(app.theme!.scaffoldBackgroundColor, AppColors.warmWhite);
    expect(find.text(AppStrings.homeTitle), findsOneWidget);
  });
}
