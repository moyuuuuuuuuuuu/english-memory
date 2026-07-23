import 'package:flutter/material.dart';

import '../features/auth/presentation/login_page.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/auth/presentation/session_state.dart';
import '../features/auth/presentation/startup_page.dart';
import 'main_shell.dart';

final class AuthenticationGate extends StatefulWidget {
  const AuthenticationGate({required this.controller, super.key});

  final SessionController controller;

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
          onLogout: widget.controller.logout,
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
