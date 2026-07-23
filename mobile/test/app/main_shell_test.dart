import 'package:english_memory/app/main_shell.dart';
import 'package:english_memory/core/l10n/app_strings.dart';
import 'package:english_memory/core/theme/app_colors.dart';
import 'package:english_memory/features/capture/presentation/capture_page.dart';
import 'package:english_memory/features/home/presentation/home_page.dart';
import 'package:english_memory/features/library/presentation/library_page.dart';
import 'package:english_memory/features/profile/presentation/profile_page.dart';
import 'package:english_memory/features/review/presentation/review_page.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  Widget app() => const MaterialApp(
    home: MainShell(
      user: AuthenticatedUser(
        id: 1,
        email: 'learner@example.com',
        username: null,
        timezone: 'Asia/Shanghai',
      ),
      onLogout: _noopLogout,
    ),
  );

  testWidgets('shows five labeled destinations in product order', (
    tester,
  ) async {
    await tester.pumpWidget(app());

    final navigation = tester.widget<NavigationBar>(find.byType(NavigationBar));
    expect(
      navigation.destinations.map(
        (destination) => (destination as NavigationDestination).label,
      ),
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
    await tester.pumpWidget(app());

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
    await tester.pumpWidget(app());
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
    await tester.pumpWidget(app());

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

  testWidgets('profile shows identity and invokes logout', (tester) async {
    var logoutCalls = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: MainShell(
          user: const AuthenticatedUser(
            id: 1,
            email: 'learner@example.com',
            username: null,
            timezone: 'Asia/Shanghai',
          ),
          onLogout: () async => logoutCalls++,
        ),
      ),
    );

    await tester.tap(find.text(AppStrings.profile));
    await tester.pumpAndSettle();
    expect(find.text('learner@example.com'), findsOneWidget);

    await tester.tap(find.text('退出登录'));
    await tester.pump();
    expect(logoutCalls, 1);
  });
}

Future<void> _noopLogout() async {}
