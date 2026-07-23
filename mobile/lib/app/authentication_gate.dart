import 'package:flutter/material.dart';

import '../features/auth/presentation/login_page.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/auth/presentation/session_state.dart';
import '../features/auth/presentation/startup_page.dart';
import '../features/capture/presentation/capture_controller.dart';
import '../features/sync/application/sync_lifecycle.dart';
import '../features/sync/data/card_outbox_repository.dart';
import 'main_shell.dart';

final class AuthenticationGate extends StatefulWidget {
  const AuthenticationGate({
    required this.controller,
    required this.captureController,
    this.outbox,
    this.syncLifecycle,
    super.key,
  });

  final SessionController controller;
  final CaptureController captureController;
  final CardOutboxGateway? outbox;
  final SyncLifecycleGateway? syncLifecycle;

  @override
  State<AuthenticationGate> createState() => _AuthenticationGateState();
}

final class _AuthenticationGateState extends State<AuthenticationGate> {
  @override
  void initState() {
    super.initState();
    widget.controller.restore();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.controller,
    builder: (context, _) {
      final state = widget.controller.state;
      return switch (state) {
        SessionAuthenticated(:final session) => MainShell(
          user: session.user,
          onLogout: () => widget.controller.confirmedLogout(session.user.id),
          captureController: widget.captureController,
          pendingCounts:
              widget.outbox?.watchOutstandingCount(session.user.id) ??
              const Stream<int>.empty(),
          onLogoutCancelled: widget.syncLifecycle?.drain,
          onResumed: widget.syncLifecycle?.onResumed,
          message: state.message,
        ),
        SessionSignedOut(:final prefilledIdentity) => LoginPage(
          key: ValueKey(prefilledIdentity),
          controller: widget.controller,
          initialIdentity: prefilledIdentity,
        ),
        SessionOfflineError() => StartupPage(
          state: state,
          onRetry: widget.controller.retryRestore,
          onSignOut: widget.controller.logout,
        ),
        SessionRestoring() || SessionAuthenticating() => StartupPage(
          state: state,
          onRetry: widget.controller.retryRestore,
          onSignOut: widget.controller.logout,
        ),
      };
    },
  );
}
