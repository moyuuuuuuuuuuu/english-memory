import 'dart:async';

import 'package:flutter/material.dart';

import '../core/l10n/app_strings.dart';
import '../core/theme/app_colors.dart';
import '../features/capture/presentation/capture_page.dart';
import '../features/capture/presentation/capture_controller.dart';
import '../features/home/presentation/home_page.dart';
import '../features/library/presentation/library_page.dart';
import '../features/profile/presentation/profile_page.dart';
import '../features/review/presentation/review_page.dart';
import '../features/auth/domain/authenticated_user.dart';

class MainShell extends StatefulWidget {
  const MainShell({
    required this.user,
    required this.onLogout,
    required this.captureController,
    this.pendingCounts = const Stream<int>.empty(),
    this.onLogoutCancelled,
    this.onResumed,
    this.message,
    super.key,
  });

  final AuthenticatedUser user;
  final Future<void> Function() onLogout;
  final CaptureController captureController;
  final Stream<int> pendingCounts;
  final Future<void> Function()? onLogoutCancelled;
  final Future<void> Function()? onResumed;
  final String? message;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> with WidgetsBindingObserver {
  var _selectedIndex = 0;

  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _pages = [
      const HomePage(),
      const ReviewPage(),
      CapturePage(controller: widget.captureController),
      const LibraryPage(),
      ProfilePage(
        user: widget.user,
        onLogout: widget.onLogout,
        pendingCounts: widget.pendingCounts,
        onLogoutCancelled: widget.onLogoutCancelled,
        message: widget.message,
      ),
    ];
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      final callback = widget.onResumed;
      if (callback != null) unawaited(callback());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: IndexedStack(index: _selectedIndex, children: _pages),
    bottomNavigationBar: NavigationBar(
      selectedIndex: _selectedIndex,
      onDestinationSelected: (index) {
        setState(() => _selectedIndex = index);
      },
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home_rounded),
          label: AppStrings.home,
        ),
        NavigationDestination(
          icon: Icon(Icons.replay_outlined),
          selectedIcon: Icon(Icons.replay_rounded),
          label: AppStrings.review,
        ),
        NavigationDestination(
          icon: Icon(Icons.center_focus_weak_outlined),
          selectedIcon: DecoratedBox(
            key: ValueKey('capture-selected-icon'),
            decoration: BoxDecoration(
              color: AppColors.champagneGold,
              shape: BoxShape.circle,
            ),
            child: Padding(
              padding: EdgeInsets.all(6),
              child: Icon(Icons.center_focus_strong_rounded),
            ),
          ),
          label: AppStrings.capture,
        ),
        NavigationDestination(
          icon: Icon(Icons.auto_stories_outlined),
          selectedIcon: Icon(Icons.auto_stories_rounded),
          label: AppStrings.library,
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline_rounded),
          selectedIcon: Icon(Icons.person_rounded),
          label: AppStrings.profile,
        ),
      ],
    ),
  );
}
