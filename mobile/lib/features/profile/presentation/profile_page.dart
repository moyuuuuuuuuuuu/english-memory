import 'package:flutter/material.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_spacing.dart';
import '../../auth/domain/authenticated_user.dart';
import '../../sync/presentation/pending_sync_badge.dart';

class ProfilePage extends StatelessWidget {
  const ProfilePage({
    required this.user,
    required this.onLogout,
    this.pendingCounts = const Stream<int>.empty(),
    this.onLogoutCancelled,
    this.message,
    super.key,
  });

  final AuthenticatedUser user;
  final Future<void> Function() onLogout;
  final Stream<int> pendingCounts;
  final Future<void> Function()? onLogoutCancelled;
  final String? message;

  static const pageKey = ValueKey('profile-page');

  @override
  Widget build(BuildContext context) => StreamBuilder<int>(
    stream: pendingCounts,
    initialData: 0,
    builder: (context, snapshot) => SafeArea(
      key: pageKey,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              AppStrings.profileTitle,
              style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(user.email ?? user.username ?? '学习者'),
            const SizedBox(height: AppSpacing.sm),
            PendingSyncBadge(count: snapshot.data ?? 0),
            if (message case final value?) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                value,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const Spacer(),
            OutlinedButton.icon(
              onPressed: () => _requestLogout(context, snapshot.data ?? 0),
              icon: const Icon(Icons.logout),
              label: const Text('退出登录'),
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _requestLogout(BuildContext context, int pendingCount) async {
    if (pendingCount < 1) {
      await onLogout();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('确认退出登录？'),
        content: Text('还有 $pendingCount 条内容尚未同步。退出后将删除这些内容和本机缓存。'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('取消'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('删除并退出'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await onLogout();
    } else {
      await onLogoutCancelled?.call();
    }
  }
}
