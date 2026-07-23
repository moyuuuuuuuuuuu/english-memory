import 'package:flutter/material.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_spacing.dart';
import '../../auth/domain/authenticated_user.dart';

class ProfilePage extends StatelessWidget {
  const ProfilePage({required this.user, required this.onLogout, super.key});

  final AuthenticatedUser user;
  final Future<void> Function() onLogout;

  static const pageKey = ValueKey('profile-page');

  @override
  Widget build(BuildContext context) => SafeArea(
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
          const Spacer(),
          OutlinedButton.icon(
            onPressed: onLogout,
            icon: const Icon(Icons.logout),
            label: const Text('退出登录'),
          ),
        ],
      ),
    ),
  );
}
