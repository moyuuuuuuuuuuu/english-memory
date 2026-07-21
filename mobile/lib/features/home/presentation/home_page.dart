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
        child: Text(
          AppStrings.homeTitle,
          style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
        ),
      ),
    ),
  );
}
