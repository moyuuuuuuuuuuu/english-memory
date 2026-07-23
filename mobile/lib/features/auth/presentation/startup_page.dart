import 'package:flutter/material.dart';

import '../../../core/theme/app_spacing.dart';
import 'session_state.dart';

final class StartupPage extends StatelessWidget {
  const StartupPage({
    required this.state,
    required this.onRetry,
    required this.onSignOut,
    super.key,
  });

  final SessionState state;
  final Future<void> Function() onRetry;
  final Future<void> Function() onSignOut;

  @override
  Widget build(BuildContext context) {
    final offline = state is SessionOfflineError
        ? state as SessionOfflineError
        : null;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: offline == null
                ? const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CircularProgressIndicator(),
                      SizedBox(height: AppSpacing.md),
                      Text('正在恢复学习进度…'),
                    ],
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.cloud_off_outlined, size: 48),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        offline.message,
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      FilledButton(
                        onPressed: onRetry,
                        child: const Text('重试'),
                      ),
                      TextButton(
                        onPressed: onSignOut,
                        child: const Text('退出登录'),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
