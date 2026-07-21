# Flutter Foundation Design

## Scope

This delivery creates the Flutter Android/iOS application foundation under `mobile/`. It includes the application shell, a reusable warm-white champagne design system, five primary navigation destinations, and automated tests. Authentication, API integration, OCR, card generation, review behavior, and offline synchronization remain separate follow-up deliveries.

## Goals

- Create a conventional Flutter project that retains both Android and iOS source trees.
- Make Home the initial destination and expose Home, Review, Capture, Library, and Profile through persistent bottom navigation.
- Establish reusable visual tokens for the product's warm-white champagne direction.
- Preserve each destination's widget state while switching tabs.
- Provide a clean extension point for later feature modules without adding speculative dependencies.

## Architecture

The client uses Flutter and Material 3 with a feature-first source layout. `lib/app/` owns application composition and the main shell. `lib/core/theme/` owns colors, spacing, radii, and `ThemeData`. Each destination lives below `lib/features/<feature>/presentation/` and exports one focused landing page.

The shell uses `NavigationBar` and `IndexedStack`. The navigation bar owns only the selected index; the destination pages own their future feature state. No router package, state-management package, dependency-injection framework, or networking package is introduced in this delivery.

## Project Structure

```text
mobile/
├── android/
├── ios/
├── lib/
│   ├── main.dart
│   ├── app/
│   │   ├── english_memory_app.dart
│   │   └── main_shell.dart
│   ├── core/
│   │   ├── l10n/app_strings.dart
│   │   └── theme/
│   │       ├── app_colors.dart
│   │       ├── app_spacing.dart
│   │       ├── app_radii.dart
│   │       └── app_theme.dart
│   └── features/
│       ├── home/presentation/home_page.dart
│       ├── review/presentation/review_page.dart
│       ├── capture/presentation/capture_page.dart
│       ├── library/presentation/library_page.dart
│       └── profile/presentation/profile_page.dart
└── test/
    ├── app/english_memory_app_test.dart
    ├── app/main_shell_test.dart
    └── core/theme/app_theme_test.dart
```

## Visual System

The default background is a warm off-white rather than pure white. Champagne gold is the primary accent, with deep warm brown used for readable foreground text. Cards use a subtly warmer surface, restrained shadows, rounded corners, and low-contrast borders. Gradients and particles are excluded from this foundation because they should support real content and success feedback rather than decorate placeholders.

Capture occupies the center navigation position and receives a stronger gold treatment while retaining the same interaction and accessibility semantics as the other destinations. All five destinations expose text labels; the interface does not rely on icons alone.

Visual values are centralized in named tokens. Feature widgets must consume those tokens or the generated `ThemeData` instead of declaring ad hoc colors, spacing, or corner radii.

## Navigation and State

Home is selected at startup. Tapping a destination updates the selected index and shows the corresponding page. `IndexedStack` keeps all five destination widgets mounted so scroll positions and future local state survive tab changes. Android system back behavior is left at Flutter's default for this single-shell delivery; nested navigation will be designed when real destination flows are added.

The five initial pages are intentionally lightweight product placeholders. Each identifies its destination in Chinese and contains only enough structure to verify typography, surfaces, and navigation. They do not fabricate card, streak, or review data.

## Copy and Accessibility

User-facing Chinese labels are centralized in `AppStrings` to prevent duplicated literals and prepare for later localization. Navigation destinations have stable Chinese labels and standard Material icons. Text and selected/unselected states must retain readable contrast on the warm palette. Widget tests locate navigation by visible labels, which also verifies that labels remain available to assistive technologies.

## Error Handling

This foundation performs no I/O and therefore has no runtime network or persistence errors. Invalid navigation indices cannot enter through the public UI because `NavigationBar` supplies an index from the fixed destination list. Later feature errors will be handled inside their owning feature boundaries rather than in the shell.

## Testing and Verification

Widget tests verify that Home is initially visible, all five navigation labels are present, switching each destination shows the correct page, and destination state remains mounted across tab changes. Theme tests verify Material 3, the warm background, champagne primary color, and shared card shape.

The delivery gate is:

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
```

Android/iOS generated project files must remain present. APK compilation is deferred to the release stage, but project initialization must not remove iOS source merely because signing is unavailable on Linux.

## Environment Constraint

Flutter and Dart are not currently available on the WSL `PATH`. Before scaffolding, implementation will look for a Windows Flutter installation reachable from WSL. If none exists, the implementation pauses before generated project creation and reports the exact installation requirement; hand-written substitutes for Flutter-generated Android/iOS projects are not acceptable.

## Acceptance Criteria

- `mobile/` is a valid Flutter project with Android and iOS source.
- The application starts on Home and provides Home, Review, Capture, Library, and Profile navigation.
- Switching destinations preserves mounted page state.
- The shared warm-white champagne theme is applied without feature-local style constants.
- No authentication, OCR, API, offline queue, router, or state-management dependency is added.
- `flutter analyze` and `flutter test` pass in a configured Flutter environment.
