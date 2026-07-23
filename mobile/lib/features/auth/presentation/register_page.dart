import 'package:flutter/material.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_spacing.dart';
import 'session_controller.dart';
import 'session_state.dart';

enum RegistrationIdentity { email, username }

final class RegisterPage extends StatefulWidget {
  const RegisterPage({
    required this.controller,
    required this.onRegistered,
    required this.onCancel,
    super.key,
  });

  final SessionController controller;
  final ValueChanged<String> onRegistered;
  final VoidCallback onCancel;

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

final class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();
  final _identityController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmationController = TextEditingController();
  RegistrationIdentity _mode = RegistrationIdentity.email;
  bool _obscurePassword = true;

  @override
  void dispose() {
    _identityController.dispose();
    _passwordController.dispose();
    _confirmationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.controller,
    builder: (context, _) {
      final loading = widget.controller.state is SessionAuthenticating;
      return Scaffold(
        appBar: AppBar(
          leading: IconButton(
            onPressed: loading ? null : widget.onCancel,
            icon: const Icon(Icons.arrow_back),
          ),
          title: const Text('注册'),
        ),
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SegmentedButton<RegistrationIdentity>(
                        segments: const [
                          ButtonSegment(
                            value: RegistrationIdentity.email,
                            label: Text('邮箱'),
                          ),
                          ButtonSegment(
                            value: RegistrationIdentity.username,
                            label: Text('用户名'),
                          ),
                        ],
                        selected: {_mode},
                        onSelectionChanged: loading
                            ? null
                            : (value) => setState(() {
                                _mode = value.single;
                                _formKey.currentState?.reset();
                              }),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      TextFormField(
                        key: const Key('register_identity'),
                        controller: _identityController,
                        enabled: !loading,
                        textInputAction: TextInputAction.next,
                        keyboardType: _mode == RegistrationIdentity.email
                            ? TextInputType.emailAddress
                            : TextInputType.text,
                        decoration: InputDecoration(
                          labelText: _mode == RegistrationIdentity.email
                              ? '邮箱'
                              : '用户名',
                          border: const OutlineInputBorder(),
                        ),
                        validator: _validateIdentity,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextFormField(
                        key: const Key('register_password'),
                        controller: _passwordController,
                        enabled: !loading,
                        obscureText: _obscurePassword,
                        autofillHints: const [AutofillHints.newPassword],
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: AppStrings.password,
                          border: const OutlineInputBorder(),
                          suffixIcon: IconButton(
                            tooltip: _obscurePassword ? '显示密码' : '隐藏密码',
                            onPressed: loading
                                ? null
                                : () => setState(
                                    () => _obscurePassword = !_obscurePassword,
                                  ),
                            icon: const Icon(Icons.visibility_outlined),
                          ),
                        ),
                        validator: (value) =>
                            (value?.length ?? 0) < 8 ? '密码至少需要 8 个字符' : null,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      TextFormField(
                        key: const Key('register_confirmation'),
                        controller: _confirmationController,
                        enabled: !loading,
                        obscureText: true,
                        textInputAction: TextInputAction.done,
                        onFieldSubmitted: (_) => _submit(),
                        decoration: const InputDecoration(
                          labelText: '确认密码',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) => value != _passwordController.text
                            ? '两次输入的密码不一致'
                            : null,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      FilledButton(
                        onPressed: loading ? null : _submit,
                        child: loading
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text(AppStrings.createAccount),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
    },
  );

  String? _validateIdentity(String? value) {
    final identity = value?.trim() ?? '';
    if (_mode == RegistrationIdentity.email) {
      return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(identity)
          ? null
          : '请输入有效的邮箱地址';
    }
    return RegExp(r'^[A-Za-z0-9_]{3,32}$').hasMatch(identity)
        ? null
        : '用户名需为 3–32 位字母、数字或下划线';
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final identity = _identityController.text.trim();
    await widget.controller.register(
      email: _mode == RegistrationIdentity.email ? identity : null,
      username: _mode == RegistrationIdentity.username ? identity : null,
      password: _passwordController.text,
    );
    if (!mounted) return;
    final state = widget.controller.state;
    if (state is SessionSignedOut &&
        state.message?.startsWith('注册成功') == true) {
      widget.onRegistered(identity);
    }
  }
}
