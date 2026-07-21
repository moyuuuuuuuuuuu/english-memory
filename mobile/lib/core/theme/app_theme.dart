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
