import 'package:flutter/material.dart';

import '../core/l10n/app_strings.dart';
import '../core/theme/app_theme.dart';
import 'app_dependencies.dart';
import 'authentication_gate.dart';

class EnglishMemoryApp extends StatefulWidget {
  const EnglishMemoryApp({this.dependencies, super.key});

  final AppDependencies? dependencies;

  @override
  State<EnglishMemoryApp> createState() => _EnglishMemoryAppState();
}

final class _EnglishMemoryAppState extends State<EnglishMemoryApp> {
  late final AppDependencies _dependencies;
  late final bool _ownsDependencies;

  @override
  void initState() {
    super.initState();
    _ownsDependencies = widget.dependencies == null;
    _dependencies = widget.dependencies ?? AppDependencies.production();
  }

  @override
  void dispose() {
    if (_ownsDependencies) {
      _dependencies.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = _dependencies.controller;
    final captureController = _dependencies.captureController;
    return MaterialApp(
      title: AppStrings.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: controller == null || captureController == null
          ? Scaffold(
              body: Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    _dependencies.configurationMessage ?? '应用配置无效。',
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            )
          : AuthenticationGate(
              controller: controller,
              captureController: captureController,
              outbox: _dependencies.outbox,
              syncLifecycle: _dependencies.syncLifecycle,
            ),
    );
  }
}
