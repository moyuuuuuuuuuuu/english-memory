import 'package:english_memory/core/theme/app_colors.dart';
import 'package:english_memory/core/theme/app_radii.dart';
import 'package:english_memory/core/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('light theme applies warm champagne tokens', () {
    final theme = AppTheme.light();

    expect(theme.useMaterial3, isTrue);
    expect(theme.scaffoldBackgroundColor, AppColors.warmWhite);
    expect(theme.colorScheme.primary, AppColors.champagneGold);
    expect(theme.colorScheme.onSurface, AppColors.deepBrown);
    expect(theme.cardTheme.color, AppColors.cardSurface);

    final shape = theme.cardTheme.shape! as RoundedRectangleBorder;
    expect(shape.borderRadius, BorderRadius.circular(AppRadii.card));
  });
}
