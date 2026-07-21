# Flutter Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the Android/iOS Flutter application foundation with a reusable warm-white champagne theme and state-preserving five-destination navigation.

**Architecture:** Flutter's generated project lives in `mobile/`. Application composition is isolated in `lib/app`, visual tokens in `lib/core/theme`, centralized Chinese copy in `lib/core/l10n`, and each destination in its own feature directory. `NavigationBar` selects an `IndexedStack`; no speculative application dependencies are introduced.

**Tech Stack:** Flutter stable, Dart, Material 3, `flutter_test`, Android Gradle project, iOS Xcode project.

## Global Constraints

- Retain Android and iOS source under `mobile/`.
- Use Home, Review, Capture, Library, Profile in that order; Home starts selected.
- Keep all destination widgets mounted while switching tabs.
- Centralize Chinese user-facing copy in `AppStrings`.
- Do not add authentication, API, OCR, card generation, review, offline synchronization, router, state-management, dependency-injection, or networking packages.
- Generate platform projects with Flutter; never hand-write substitutes.
- All application behavior follows red-green-refactor. Generated scaffolding is setup output, not application behavior.

---

### Task 1: Provision Flutter and Generate the Project

**Files:**
- Create: `mobile/android/` (Flutter-generated)
- Create: `mobile/ios/` (Flutter-generated)
- Create: `mobile/pubspec.yaml` (Flutter-generated)
- Create: `mobile/analysis_options.yaml` (Flutter-generated)

**Interfaces:**
- Consumes: Flutter stable exposed as `flutter` in WSL.
- Produces: package `english_memory` with Android and iOS platform sources.

- [ ] **Step 1: Verify the required SDK**

```bash
command -v flutter
flutter --version
flutter doctor -v
```

Expected: stable Flutter is available. If absent, install Flutter stable using the official Linux procedure or expose an existing SDK to WSL `PATH`; do not scaffold without a working official generator.

- [ ] **Step 2: Generate Android and iOS sources**

```bash
flutter create --platforms=android,ios --org com.englishmemory \
  --project-name english_memory mobile
cd mobile && flutter pub get
```

Expected: project generation and dependency resolution succeed.

- [ ] **Step 3: Verify and commit generated output**

```bash
test -d mobile/android/app
test -d mobile/ios/Runner
test -f mobile/pubspec.yaml
git add mobile
git commit -m "chore: initialize Flutter mobile project"
```

### Task 2: Build Visual Tokens and the Theme

**Files:**
- Create: `mobile/lib/core/theme/app_colors.dart`
- Create: `mobile/lib/core/theme/app_spacing.dart`
- Create: `mobile/lib/core/theme/app_radii.dart`
- Create: `mobile/lib/core/theme/app_theme.dart`
- Create: `mobile/test/core/theme/app_theme_test.dart`

**Interfaces:**
- Produces: `AppColors`, `AppSpacing`, `AppRadii`, `AppTheme.light()`.

- [ ] **Step 1: Write the failing test**

```dart
import 'package:english_memory/core/theme/app_colors.dart';
import 'package:english_memory/core/theme/app_radii.dart';
import 'package:english_memory/core/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('light theme applies warm champagne tokens', () {
    final theme = AppTheme.light();
    expect(theme.useMaterial3, isTrue);
    expect(theme.scaffoldBackgroundColor, AppColors.warmWhite);
    expect(theme.colorScheme.primary, AppColors.champagneGold);
    expect(theme.colorScheme.onSurface, AppColors.deepBrown);
    expect(theme.cardTheme.color, AppColors.cardSurface);
    final shape = theme.cardTheme.shape! as RoundedRectangleBorder;
    expect(shape.borderRadius, BorderRadius.circular(AppRadii.card));
  });
}
```

- [ ] **Step 2: Verify red**

```bash
cd mobile && flutter test test/core/theme/app_theme_test.dart
```

Expected: missing theme types cause compilation failure.

- [ ] **Step 3: Add tokens**

```dart
// app_colors.dart
import 'package:flutter/material.dart';
abstract final class AppColors {
  static const warmWhite = Color(0xFFFFFBF4);
  static const cardSurface = Color(0xFFFFF7E8);
  static const champagneGold = Color(0xFFC89B4B);
  static const softGold = Color(0xFFF3E1B8);
  static const deepBrown = Color(0xFF392E24);
  static const outline = Color(0xFFE9DCC5);
}

// app_spacing.dart
abstract final class AppSpacing {
  static const sm = 8.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
}

// app_radii.dart
abstract final class AppRadii {
  static const card = 20.0;
  static const control = 16.0;
}
```

- [ ] **Step 4: Add `AppTheme.light()`**

```dart
import 'package:flutter/material.dart';
import 'app_colors.dart';
import 'app_radii.dart';

abstract final class AppTheme {
  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.champagneGold,
      surface: AppColors.warmWhite,
    ).copyWith(
      primary: AppColors.champagneGold,
      onPrimary: AppColors.deepBrown,
      onSurface: AppColors.deepBrown,
      outline: AppColors.outline,
      surfaceContainer: AppColors.cardSurface,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.warmWhite,
      cardTheme: CardThemeData(
        color: AppColors.cardSurface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.card),
          side: const BorderSide(color: AppColors.outline),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: AppColors.cardSurface,
        indicatorColor: AppColors.softGold,
      ),
    );
  }
}
```

- [ ] **Step 5: Verify green and commit**

```bash
cd mobile
flutter test test/core/theme/app_theme_test.dart
flutter analyze
cd ..
git add mobile/lib/core/theme mobile/test/core/theme
git commit -m "feat: add warm champagne Flutter theme"
```

### Task 3: Add Copy and Destination Pages

**Files:**
- Create: `mobile/lib/core/l10n/app_strings.dart`
- Create: `mobile/lib/features/{home,review,capture,library,profile}/presentation/*_page.dart`
- Create: `mobile/test/features/destination_pages_test.dart`

**Interfaces:**
- Produces: `AppStrings` and five const page widgets with stable `pageKey` values.

- [ ] **Step 1: Write a failing table-driven widget test**

```dart
import 'package:english_memory/features/capture/presentation/capture_page.dart';
import 'package:english_memory/features/home/presentation/home_page.dart';
import 'package:english_memory/features/library/presentation/library_page.dart';
import 'package:english_memory/features/profile/presentation/profile_page.dart';
import 'package:english_memory/features/review/presentation/review_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const cases = <(Widget, String)>[
    (HomePage(), '今天想记住什么？'),
    (ReviewPage(), '复习'),
    (CapturePage(), '捕捉英语'),
    (LibraryPage(), '卡片库'),
    (ProfilePage(), '我的'),
  ];
  for (final (page, title) in cases) {
    testWidgets('$title identifies its page', (tester) async {
      await tester.pumpWidget(MaterialApp(home: page));
      expect(find.text(title), findsOneWidget);
    });
  }
}
```

- [ ] **Step 2: Verify red**

```bash
cd mobile && flutter test test/features/destination_pages_test.dart
```

Expected: missing page types cause compilation failure.

- [ ] **Step 3: Add centralized copy**

```dart
abstract final class AppStrings {
  static const appName = '英语记忆';
  static const home = '首页';
  static const review = '复习';
  static const capture = '捕捉';
  static const library = '卡片库';
  static const profile = '我的';
  static const homeTitle = '今天想记住什么？';
  static const reviewTitle = '复习';
  static const captureTitle = '捕捉英语';
  static const libraryTitle = '卡片库';
  static const profileTitle = '我的';
}
```

- [ ] **Step 4: Add each minimal page**

Use this exact structure for each page, substituting the class, key, and title:

```dart
import 'package:flutter/material.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_spacing.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});
  static const pageKey = ValueKey('home-page');
  @override
  Widget build(BuildContext context) => const SafeArea(
    key: pageKey,
    child: Padding(
      padding: EdgeInsets.all(AppSpacing.lg),
      child: Align(
        alignment: Alignment.topLeft,
        child: Text(AppStrings.homeTitle,
          style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700)),
      ),
    ),
  );
}
```

Exact substitutions:

```text
ReviewPage/review-page/AppStrings.reviewTitle
CapturePage/capture-page/AppStrings.captureTitle
LibraryPage/library-page/AppStrings.libraryTitle
ProfilePage/profile-page/AppStrings.profileTitle
```

- [ ] **Step 5: Verify green and commit**

```bash
cd mobile && flutter test test/features/destination_pages_test.dart
cd ..
git add mobile/lib/core/l10n mobile/lib/features mobile/test/features
git commit -m "feat: add Flutter destination pages"
```

### Task 4: Build the State-Preserving Shell

**Files:**
- Create: `mobile/lib/app/main_shell.dart`
- Create: `mobile/lib/app/english_memory_app.dart`
- Modify: `mobile/lib/main.dart`
- Replace: `mobile/test/widget_test.dart`
- Create: `mobile/test/app/main_shell_test.dart`

**Interfaces:**
- Consumes: theme, copy, and five pages.
- Produces: `EnglishMemoryApp` root and `MainShell` navigation.

- [ ] **Step 1: Write failing root and navigation tests**

The root test pumps `EnglishMemoryApp` and asserts `AppStrings.appName`, `AppColors.warmWhite`, and `AppStrings.homeTitle`. The shell test asserts the five labels in order, taps each label and observes its title, then stores `tester.element(find.byKey(HomePage.pageKey))`, visits Review, returns Home, and asserts `same(initialHome)`.

```dart
expect(find.byType(NavigationBar), findsOneWidget);
for (final label in [
  AppStrings.home, AppStrings.review, AppStrings.capture,
  AppStrings.library, AppStrings.profile,
]) {
  expect(find.text(label), findsOneWidget);
}
```

- [ ] **Step 2: Verify red**

```bash
cd mobile && flutter test test/widget_test.dart test/app/main_shell_test.dart
```

Expected: missing `EnglishMemoryApp` and `MainShell` cause compilation failure.

- [ ] **Step 3: Implement `MainShell`**

```dart
class MainShell extends StatefulWidget {
  const MainShell({super.key});
  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  var _selectedIndex = 0;
  static const _pages = <Widget>[
    HomePage(), ReviewPage(), CapturePage(), LibraryPage(), ProfilePage(),
  ];
  @override
  Widget build(BuildContext context) => Scaffold(
    body: IndexedStack(index: _selectedIndex, children: _pages),
    bottomNavigationBar: NavigationBar(
      selectedIndex: _selectedIndex,
      onDestinationSelected: (index) => setState(() => _selectedIndex = index),
      destinations: const [
        NavigationDestination(icon: Icon(Icons.home_outlined), label: AppStrings.home),
        NavigationDestination(icon: Icon(Icons.replay_outlined), label: AppStrings.review),
        NavigationDestination(icon: Icon(Icons.center_focus_strong), label: AppStrings.capture),
        NavigationDestination(icon: Icon(Icons.auto_stories_outlined), label: AppStrings.library),
        NavigationDestination(icon: Icon(Icons.person_outline), label: AppStrings.profile),
      ],
    ),
  );
}
```

Imports are the five pages, `AppStrings`, and Material.

- [ ] **Step 4: Implement root and entry point**

```dart
class EnglishMemoryApp extends StatelessWidget {
  const EnglishMemoryApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: AppStrings.appName,
    debugShowCheckedModeBanner: false,
    theme: AppTheme.light(),
    home: const MainShell(),
  );
}
```

`main.dart`:

```dart
import 'package:flutter/widgets.dart';
import 'app/english_memory_app.dart';
void main() => runApp(const EnglishMemoryApp());
```

- [ ] **Step 5: Verify green and commit**

```bash
cd mobile
flutter test
flutter analyze
cd ..
git add mobile/lib mobile/test
git commit -m "feat: add state-preserving mobile navigation"
```

### Task 5: Verify and Update the Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`

**Interfaces:**
- Produces: fresh Flutter and backend baseline evidence in the project source of truth.

- [ ] **Step 1: Run all gates**

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
cd ..
docker exec -w /www/english-memory/.worktrees/flutter-foundation php82 vendor/bin/phpunit
docker exec -w /www/english-memory/.worktrees/flutter-foundation php82 composer validate --no-check-publish
git diff --check
```

Expected: Flutter gates pass; backend reports `OK (187 tests, 875 assertions)`; Composer is valid; diff check is silent.

- [ ] **Step 2: Audit platform and dependency scope**

```bash
test -d mobile/android/app
test -d mobile/ios/Runner
sed -n '/^dependencies:/,/^dev_dependencies:/p' mobile/pubspec.yaml
```

Expected: both platform projects exist and runtime dependencies contain no excluded package category.

- [ ] **Step 3: Record only verified completion**

Mark the Flutter initialization/design-system and five-navigation items complete in `PROJECT_PLAN.md`. Leave authentication, OCR, local cache, and synchronization unchecked. Record the exact Flutter version, test count, analysis result, `codex/flutter-foundation` branch, worktree path, and unchanged backend baseline.

- [ ] **Step 4: Review and commit**

```bash
git status --short
git diff --stat master...HEAD
git diff --check
git add PROJECT_PLAN.md
git commit -m "docs: record Flutter foundation baseline"
```
