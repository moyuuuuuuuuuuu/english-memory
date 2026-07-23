import 'package:english_memory/features/auth/data/auth_api.dart';
import 'package:english_memory/features/auth/data/auth_repository.dart';
import 'package:english_memory/features/auth/domain/auth_session.dart';
import 'package:english_memory/features/auth/domain/authenticated_user.dart';
import 'package:english_memory/features/auth/domain/session_credentials.dart';
import 'package:english_memory/features/auth/presentation/login_page.dart';
import 'package:english_memory/features/auth/presentation/session_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late RecordingRepository repository;
  late SessionController controller;

  setUp(() {
    repository = RecordingRepository();
    controller = SessionController(repository);
  });

  testWidgets('validates required fields and submits a trimmed identity', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(home: LoginPage(controller: controller)),
    );

    await tester.tap(find.text('登录'));
    await tester.pump();
    expect(find.text('请输入邮箱或用户名'), findsOneWidget);
    expect(find.text('请输入密码'), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('login_identity')),
      ' learner ',
    );
    await tester.enterText(find.byKey(const Key('login_password')), 'password');
    await tester.tap(find.text('登录'));
    await tester.pumpAndSettle();

    expect(repository.loginIdentity, 'learner');
    expect(repository.deviceName, SessionController.deviceName);
  });

  testWidgets('toggles password visibility and explains recovery follow-up', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(home: LoginPage(controller: controller)),
    );
    final password = find.byKey(const Key('login_password'));
    expect(
      tester
          .widget<EditableText>(
            find.descendant(of: password, matching: find.byType(EditableText)),
          )
          .obscureText,
      isTrue,
    );

    await tester.tap(find.byTooltip('显示密码'));
    await tester.pump();
    expect(
      tester
          .widget<EditableText>(
            find.descendant(of: password, matching: find.byType(EditableText)),
          )
          .obscureText,
      isFalse,
    );

    await tester.tap(find.text('忘记密码？'));
    await tester.pump();
    expect(find.textContaining('下一版本'), findsOneWidget);
  });
}

final class RecordingRepository implements AuthRepositoryGateway {
  String? loginIdentity;
  String? deviceName;

  @override
  Future<AuthSession> login({
    required String identity,
    required String password,
    required String deviceName,
  }) async {
    loginIdentity = identity;
    this.deviceName = deviceName;
    return AuthSession(
      user: const AuthenticatedUser(
        id: 1,
        email: null,
        username: 'learner',
        timezone: 'Asia/Shanghai',
      ),
      credentials: SessionCredentials.fromTtl(
        accessToken: 'access',
        refreshToken: 'refresh',
        accessTtlSeconds: 900,
        refreshTtlSeconds: 2592000,
        now: DateTime.utc(2026),
      ),
    );
  }

  @override
  Future<void> logout() async {}

  @override
  Future<RegistrationResult> register({
    String? email,
    String? username,
    required String password,
  }) => throw UnimplementedError();

  @override
  Future<AuthSession?> restore() async => null;
}
