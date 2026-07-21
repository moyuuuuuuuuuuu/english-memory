import 'package:flutter/material.dart';

import '../core/l10n/app_strings.dart';
import '../core/theme/app_theme.dart';
import 'main_shell.dart';

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
