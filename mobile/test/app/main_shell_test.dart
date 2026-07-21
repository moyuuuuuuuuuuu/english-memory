import 'package:english_memory/app/english_memory_app.dart';
import 'package:english_memory/core/l10n/app_strings.dart';
import 'package:english_memory/core/theme/app_colors.dart';
import 'package:english_memory/features/capture/presentation/capture_page.dart';
import 'package:english_memory/features/home/presentation/home_page.dart';
import 'package:english_memory/features/library/presentation/library_page.dart';
import 'package:english_memory/features/profile/presentation/profile_page.dart';
import 'package:english_memory/features/review/presentation/review_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shows five labeled destinations in product order', (
    tester,
  ) async {
    await tester.pumpWidget(const EnglishMemoryApp());

    final navigation = tester.widget<NavigationBar>(
      find.byType(NavigationBar),
    );
    expect(
      navigation.destinations
          .map((destination) => (destination as NavigationDestination).label),
      <String>[
        AppStrings.home,
        AppStrings.review,
        AppStrings.capture,
        AppStrings.library,
        AppStrings.profile,
      ],
    );
  });

  testWidgets('switches to every destination', (tester) async {
    await tester.pumpWidget(const EnglishMemoryApp());

    for (final target in <(String, Key)>[
      (AppStrings.review, ReviewPage.pageKey),
      (AppStrings.capture, CapturePage.pageKey),
      (AppStrings.library, LibraryPage.pageKey),
      (AppStrings.profile, ProfilePage.pageKey),
      (AppStrings.home, HomePage.pageKey),
    ]) {
      await tester.tap(find.text(target.$1));
      await tester.pumpAndSettle();

      expect(find.byKey(target.$2), findsOneWidget);
    }
  });

  testWidgets('keeps destination widgets mounted when switching tabs', (
    tester,
  ) async {
    await tester.pumpWidget(const EnglishMemoryApp());
    final initialHome = tester.element(find.byKey(HomePage.pageKey));

    await tester.tap(find.text(AppStrings.review));
    await tester.pumpAndSettle();
    await tester.tap(find.text(AppStrings.home));
    await tester.pumpAndSettle();

    expect(tester.element(find.byKey(HomePage.pageKey)), same(initialHome));
  });

  testWidgets('gives the selected Capture destination a gold emphasis', (
    tester,
  ) async {
    await tester.pumpWidget(const EnglishMemoryApp());

    await tester.tap(find.text(AppStrings.capture));
    await tester.pumpAndSettle();

    final emphasis = tester.widget<DecoratedBox>(
      find.byKey(const ValueKey('capture-selected-icon')),
    );
    expect(
      (emphasis.decoration as BoxDecoration).color,
      AppColors.champagneGold,
    );
  });
}
