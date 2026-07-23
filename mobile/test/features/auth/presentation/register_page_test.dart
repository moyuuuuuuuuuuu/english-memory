import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/presentation/register_page.dart';
import 'package:english_memory/features/auth/presentation/session_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late RegisterRepository repository;
  late SessionController controller;
  late String registeredIdentity;

  setUp(() {
    repository = RegisterRepository();
    controller = SessionController(repository);
    registeredIdentity = '';
  });

  Future<void> pump(WidgetTester tester) => tester.pumpWidget(
    MaterialApp(
      home: RegisterPage(
        controller: controller,
        onRegistered: (value) => registeredIdentity = value,
        onCancel: () {},
      ),
    ),
  );

  testWidgets('validates email, password length, and confirmation', (
    tester,
  ) async {
    await pump(tester);
    await tester.enterText(find.byKey(const Key('register_identity')), 'bad');
    await tester.enterText(find.byKey(const Key('register_password')), 'short');
    await tester.enterText(
      find.byKey(const Key('register_confirmation')),
      'different',
    );
    await tester.tap(find.text('创建账号'));
    await tester.pump();

    expect(find.text('请输入有效的邮箱地址'), findsOneWidget);
    expect(find.text('密码至少需要 8 个字符'), findsOneWidget);
    expect(find.text('两次输入的密码不一致'), findsOneWidget);
  });

  testWidgets('username mode enforces rules and returns successful prefill', (
    tester,
  ) async {
    await pump(tester);
    await tester.tap(find.text('用户名'));
    await tester.pump();
    await tester.enterText(
      find.byKey(const Key('register_identity')),
      ' memory_learner ',
    );
    await tester.enterText(
      find.byKey(const Key('register_password')),
      'SecurePass123!',
    );
    await tester.enterText(
      find.byKey(const Key('register_confirmation')),
      'SecurePass123!',
    );
    await tester.tap(find.text('创建账号'));
    await tester.pumpAndSettle();

    expect(repository.username, 'memory_learner');
    expect(repository.email, isNull);
    expect(registeredIdentity, 'memory_learner');
  });
}

final class RegisterRepository implements AuthRepositoryGateway {
  String? email;
  String? username;

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) async {
    this.email = email;
    this.username = username;
    return const RegistrationResult(
      AuthenticatedUser(
        id: 2,
        email: null,
        username: 'memory_learner',
        timezone: 'Asia/Shanghai',
      ),
    );
  }

  @override
  Future<AuthSession> login({
    required String identity,
    required String password,
    required String deviceName,
  }) => throw UnimplementedError();

  @override
  Future<void> logout() async {}

  @override
  Future<AuthSession?> restore() async => null;
}
