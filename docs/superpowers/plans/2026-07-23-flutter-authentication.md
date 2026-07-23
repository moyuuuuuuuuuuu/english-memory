# Flutter Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add registration, login, secure credential persistence, startup restoration, coordinated refresh, current-user loading, and logout to the Flutter client.

**Architecture:** Plain Dio handles the authentication endpoints. `AuthRepository` owns provider translation, secure credential replacement, and refresh single-flight behavior. `SessionController` exposes app-level session states through `ChangeNotifier`; `AuthenticationGate` selects startup, authentication, offline recovery, or the existing main shell. The authenticated Dio interceptor consumes a narrow token-provider contract and never owns product state.

**Tech Stack:** Flutter 3.44.7, Dart 3.12, Material 3, Dio, flutter_secure_storage, flutter_test.

## Global Constraints

- API base URL must come from `--dart-define=API_BASE_URL=<absolute-http-or-https-url>`.
- Store credentials as one versioned secure-storage JSON value; never persist passwords or form values.
- Refresh token rotation must be stored before request replay.
- Concurrent refresh demand must share one refresh future; replay each request at most once.
- Authentication rejection clears credentials; connectivity failure preserves credentials.
- Registration does not create a session and returns to a prefilled Login page.
- Logout clears local credentials even when its network request fails.
- Do not add a router, state-management framework, dependency-injection framework, or password recovery API call.
- All behavior changes use red-green-refactor and each task ends with a focused commit.

---

### Task 1: Add Dependencies, API Configuration, and Domain Models

**Files:**
- Modify: `mobile/pubspec.yaml`
- Create: `mobile/lib/core/config/api_config.dart`
- Create: `mobile/lib/features/auth/domain/authenticated_user.dart`
- Create: `mobile/lib/features/auth/domain/session_credentials.dart`
- Create: `mobile/lib/features/auth/domain/auth_session.dart`
- Create: `mobile/test/core/config/api_config_test.dart`
- Create: `mobile/test/features/auth/domain/auth_models_test.dart`

**Interfaces:**
- `ApiConfig.parse(String value) -> ApiConfig`, exposing `Uri baseUrl`.
- `AuthenticatedUser.fromJson(Map<String, Object?>) -> AuthenticatedUser`.
- `SessionCredentials.fromTtl(...)` and `fromJson(...)`, plus `toJson()` and `isExpiring(DateTime now, Duration skew)`.
- `AuthSession({required AuthenticatedUser user, required SessionCredentials credentials})`.

- [ ] **Step 1: Add failing configuration and domain tests**

Test that empty, relative, and non-HTTP URLs throw `FormatException`; valid HTTP/HTTPS URLs normalize without a trailing slash. Test positive user IDs, nullable identity fields, timezone parsing, TTL conversion from an injected `now`, JSON round-trip, malformed credential rejection, and five-minute expiry skew.

```dart
final config = ApiConfig.parse('https://api.example.com/');
expect(config.baseUrl.toString(), 'https://api.example.com');

final credentials = SessionCredentials.fromTtl(
  accessToken: 'access',
  refreshToken: 'refresh',
  accessTtlSeconds: 900,
  refreshTtlSeconds: 2592000,
  now: DateTime.utc(2026, 7, 23),
);
expect(credentials.isExpiring(DateTime.utc(2026, 7, 23), const Duration(minutes: 5)), isFalse);
```

- [ ] **Step 2: Verify RED**

```bash
cd mobile
flutter test test/core/config/api_config_test.dart test/features/auth/domain/auth_models_test.dart
```

Expected: compilation fails because the configuration and domain types do not exist.

- [ ] **Step 3: Add the two runtime dependencies**

```bash
cd mobile
flutter pub add dio
flutter pub add flutter_secure_storage
```

Expected: `pubspec.yaml` contains only `dio` and `flutter_secure_storage` as new runtime packages and the lockfile updates.

- [ ] **Step 4: Implement the minimum immutable types**

Use explicit typed fields, reject blank tokens and non-positive TTL values, parse numeric JSON values defensively, and serialize timestamps as UTC ISO-8601 strings. `ApiConfig.parse` accepts only absolute `http` or `https` URIs with a host.

- [ ] **Step 5: Verify GREEN and commit**

```bash
cd mobile
flutter test test/core/config/api_config_test.dart test/features/auth/domain/auth_models_test.dart
flutter analyze
cd ..
git add mobile/pubspec.yaml mobile/pubspec.lock mobile/lib/core/config mobile/lib/features/auth/domain mobile/test/core/config mobile/test/features/auth/domain
git commit -m "feat: add Flutter authentication domain models"
```

### Task 2: Implement Versioned Secure Session Storage

**Files:**
- Create: `mobile/lib/features/auth/data/session_store.dart`
- Create: `mobile/lib/features/auth/data/secure_session_store.dart`
- Create: `mobile/test/features/auth/data/secure_session_store_test.dart`

**Interfaces:**
- `abstract interface class SessionStore` with `Future<SessionCredentials?> read()`, `Future<void> write(SessionCredentials)`, and `Future<void> clear()`.
- `SecureSessionStore(FlutterSecureStorage storage, {String key = 'auth_session_v1'}) implements SessionStore`.

- [ ] **Step 1: Write failing storage tests**

Use a fake key-value backend or `FlutterSecureStorage.setMockInitialValues`. Verify missing values return null, one versioned JSON value round-trips, a second write replaces both tokens together, `clear` removes it, and malformed/unsupported versions are cleared and return null.

```dart
await store.write(first);
await store.write(rotated);
expect(await store.read(), rotated);
expect(backingValues.keys, contains('auth_session_v1'));
expect(backingValues.values.single, isNot(contains('password')));
```

- [ ] **Step 2: Verify RED**

```bash
cd mobile && flutter test test/features/auth/data/secure_session_store_test.dart
```

Expected: compilation fails because `SessionStore` and `SecureSessionStore` do not exist.

- [ ] **Step 3: Implement the storage adapter**

Persist this shape as one string:

```json
{"version":1,"credentials":{"access_token":"...","refresh_token":"...","access_expires_at":"...","refresh_expires_at":"..."}}
```

Catch decoding and validation errors only, clear corrupt state, and do not swallow platform storage failures.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/features/auth/data/secure_session_store_test.dart
flutter analyze
cd ..
git add mobile/lib/features/auth/data/session_store.dart mobile/lib/features/auth/data/secure_session_store.dart mobile/test/features/auth/data/secure_session_store_test.dart
git commit -m "feat: persist Flutter sessions securely"
```

### Task 3: Parse API Envelopes and Implement Authentication Requests

**Files:**
- Create: `mobile/lib/core/network/api_exception.dart`
- Create: `mobile/lib/core/network/api_response_parser.dart`
- Create: `mobile/lib/features/auth/data/auth_api.dart`
- Create: `mobile/test/support/fake_dio_adapter.dart`
- Create: `mobile/test/core/network/api_response_parser_test.dart`
- Create: `mobile/test/features/auth/data/auth_api_test.dart`

**Interfaces:**
- `ApiException({required String code, required String userMessage, int? statusCode, bool isNetwork = false})`.
- `ApiResponseParser.data(Response<dynamic>) -> Map<String, Object?>`.
- `AuthApi` methods: `register`, `login`, `refresh`, `currentUser`, and `logout`.
- Result records: `LoginResult(user, credentials)` and `RegistrationResult(user)`.

- [ ] **Step 1: Write failing parser and request-contract tests**

Cover the success envelope, each stable authentication error code, unknown/malformed envelopes, timeouts, and connection errors. Assert exact methods, paths, JSON fields, Bearer header placement, and that login/refresh credentials use the injected clock for absolute expirations.

```dart
expect(request.path, '/api/auth/login');
expect(request.data, {
  'identity': 'learner@example.com',
  'password': 'SecurePass123!',
  'device_name': 'english-memory-mobile',
});
```

- [ ] **Step 2: Verify RED**

```bash
cd mobile
flutter test test/core/network/api_response_parser_test.dart test/features/auth/data/auth_api_test.dart
```

Expected: compilation fails because the parser, exception, fake adapter, and API do not exist.

- [ ] **Step 3: Implement stable error mapping**

Map `INVALID_INPUT`, `IDENTITY_TAKEN`, `INVALID_CREDENTIALS`, `ACCOUNT_DISABLED`, `INVALID_REFRESH_TOKEN`, and `UNAUTHENTICATED` to focused Chinese copy. Map Dio connection/timeout failures to `ApiException(code: 'NETWORK_ERROR', isNetwork: true, ...)`. Never expose raw exception or response text as `userMessage`.

- [ ] **Step 4: Implement `AuthApi`**

Use one injected raw Dio configured with JSON headers and timeouts. Public requests do not attach Bearer tokens. `currentUser` and `logout` receive tokens explicitly and attach only the access token as Authorization. Logout submits the refresh token in JSON.

- [ ] **Step 5: Verify GREEN and commit**

```bash
cd mobile
flutter test test/core/network/api_response_parser_test.dart test/features/auth/data/auth_api_test.dart
flutter analyze
cd ..
git add mobile/lib/core/network mobile/lib/features/auth/data/auth_api.dart mobile/test/support mobile/test/core/network mobile/test/features/auth/data/auth_api_test.dart
git commit -m "feat: add Flutter authentication API client"
```

### Task 4: Implement Repository Rotation and Single-Flight Refresh

**Files:**
- Create: `mobile/lib/features/auth/data/session_token_provider.dart`
- Create: `mobile/lib/features/auth/data/auth_repository.dart`
- Create: `mobile/test/features/auth/data/auth_repository_test.dart`

**Interfaces:**
- `abstract interface class SessionTokenProvider` with `currentCredentials`, `refreshCredentials()`, and `invalidateSession()`.
- `AuthRepository(AuthApi api, SessionStore store, {DateTime Function()? now}) implements SessionTokenProvider`.
- Public methods: `restore`, `login`, `register`, `refreshCredentials`, `currentUser`, and `logout`.

- [ ] **Step 1: Write failing repository tests**

Cover no stored session, valid restore, pre-restore refresh for near-expiry access, current-user validation, invalid refresh clearing, network restoration preserving storage, login persistence, registration without persistence, best-effort logout clearing, and concurrent refresh calls invoking `AuthApi.refresh` once.

```dart
final results = await Future.wait([
  repository.refreshCredentials(),
  repository.refreshCredentials(),
  repository.refreshCredentials(),
]);
expect(api.refreshCalls, 1);
expect(results.toSet(), hasLength(1));
expect(await store.read(), rotatedCredentials);
```

- [ ] **Step 2: Verify RED**

```bash
cd mobile && flutter test test/features/auth/data/auth_repository_test.dart
```

Expected: compilation fails because the repository and provider contract do not exist.

- [ ] **Step 3: Implement repository state and refresh coordination**

Keep current credentials in memory after storage reads. Store a private `Future<SessionCredentials>? _refreshInFlight`; return it to concurrent callers and clear it in `whenComplete`. Write rotated credentials before completing the future. Clear state only for authentication rejection, not network errors.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/features/auth/data/auth_repository_test.dart
flutter analyze
cd ..
git add mobile/lib/features/auth/data/session_token_provider.dart mobile/lib/features/auth/data/auth_repository.dart mobile/test/features/auth/data/auth_repository_test.dart
git commit -m "feat: coordinate Flutter session refresh"
```

### Task 5: Add the Authenticated Dio Interceptor

**Files:**
- Create: `mobile/lib/core/network/authenticated_api_client.dart`
- Create: `mobile/test/core/network/authenticated_api_client_test.dart`

**Interfaces:**
- `AuthenticatedApiClient(Dio dio, SessionTokenProvider tokens)` exposing `Dio get dio`.
- Request metadata key `auth_replayed` prevents a second replay.

- [ ] **Step 1: Write failing interceptor tests**

Assert Bearer attachment, no header without credentials, one 401 refresh/replay, rotated token on replay, one refresh for concurrent 401 responses, no refresh on non-401, no recursive refresh, and second-401 propagation.

```dart
expect(adapter.requests[0].headers['Authorization'], 'Bearer old-access');
expect(adapter.requests[1].headers['Authorization'], 'Bearer new-access');
expect(provider.refreshCalls, 1);
```

- [ ] **Step 2: Verify RED**

```bash
cd mobile && flutter test test/core/network/authenticated_api_client_test.dart
```

Expected: compilation fails because `AuthenticatedApiClient` does not exist.

- [ ] **Step 3: Implement one-time replay**

Clone the failed request with its method, URL, data, query, headers, cancellation, progress callbacks, response type, and extras. Set `auth_replayed=true`, attach the new access token, and resolve with the replay response. On refresh authentication failure, invalidate the session and reject the original failure.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/core/network/authenticated_api_client_test.dart
flutter analyze
cd ..
git add mobile/lib/core/network/authenticated_api_client.dart mobile/test/core/network/authenticated_api_client_test.dart
git commit -m "feat: refresh unauthorized Flutter requests"
```

### Task 6: Implement Session State

**Files:**
- Create: `mobile/lib/features/auth/presentation/session_state.dart`
- Create: `mobile/lib/features/auth/presentation/session_controller.dart`
- Create: `mobile/lib/features/auth/presentation/startup_page.dart`
- Create: `mobile/test/features/auth/presentation/session_controller_test.dart`

**Interfaces:**
- Sealed states: `SessionRestoring`, `SessionSignedOut`, `SessionAuthenticating`, `SessionAuthenticated`, and `SessionOfflineError`.
- `SessionController` methods: `restore`, `login`, `register`, `retryRestore`, and `logout`.

- [ ] **Step 1: Write failing controller tests**

Cover every state transition from the design, including network restoration preserving credentials, authentication rejection signing out, login success, registration handoff data, and logout clearing despite API error. Test Retry and Sign out callbacks on the startup offline-error presentation.

- [ ] **Step 2: Verify RED**

```bash
cd mobile
flutter test test/features/auth/presentation/session_controller_test.dart
```

Expected: compilation fails because state, controller, and startup page do not exist.

- [ ] **Step 3: Implement state controller and startup states**

Prevent duplicate form submissions while `SessionAuthenticating`. Store the original offline exception only as a stable user message. `retryRestore` re-enters restoring. `logout` always ends signed out in `finally`.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/features/auth/presentation/session_controller_test.dart
flutter analyze
cd ..
git add mobile/lib/features/auth/presentation/session_state.dart mobile/lib/features/auth/presentation/session_controller.dart mobile/lib/features/auth/presentation/startup_page.dart mobile/test/features/auth/presentation/session_controller_test.dart
git commit -m "feat: manage Flutter session state"
```

### Task 7: Build Login and Registration Presentation

**Files:**
- Create: `mobile/lib/features/auth/presentation/login_page.dart`
- Create: `mobile/lib/features/auth/presentation/register_page.dart`
- Modify: `mobile/lib/core/l10n/app_strings.dart`
- Create: `mobile/test/features/auth/presentation/login_page_test.dart`
- Create: `mobile/test/features/auth/presentation/register_page_test.dart`

**Interfaces:**
- `LoginPage({required SessionController controller, String? initialIdentity})`.
- `RegisterPage({required SessionController controller, required ValueChanged<String> onRegistered, required VoidCallback onCancel})`.

- [ ] **Step 1: Write failing widget tests**

Test required-field validation, trimmed identity submission, password visibility, keyboard semantics, disabled/loading controls, stable API error display, registration mode selection, exact username/email/password rules, password confirmation, successful registration callback with prefill, and the explicit recovery follow-up notice.

- [ ] **Step 2: Verify RED**

```bash
cd mobile
flutter test test/features/auth/presentation/login_page_test.dart test/features/auth/presentation/register_page_test.dart
```

Expected: compilation fails because the pages do not exist.

- [ ] **Step 3: Implement the forms**

Use `Form`, owned controllers disposed by State, autofill hints, `TextInputAction`, semantic labels, shared theme tokens, and mounted checks after awaits. Do not log form fields. Keep registration navigation local to Login so no router dependency is needed.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/features/auth/presentation/login_page_test.dart test/features/auth/presentation/register_page_test.dart
flutter analyze
cd ..
git add mobile/lib/features/auth/presentation/login_page.dart mobile/lib/features/auth/presentation/register_page.dart mobile/lib/core/l10n/app_strings.dart mobile/test/features/auth/presentation/login_page_test.dart mobile/test/features/auth/presentation/register_page_test.dart
git commit -m "feat: add Flutter login and registration screens"
```

### Task 8: Compose Dependencies and Add Profile Logout

**Files:**
- Create: `mobile/lib/app/app_dependencies.dart`
- Create: `mobile/lib/app/authentication_gate.dart`
- Modify: `mobile/lib/app/english_memory_app.dart`
- Modify: `mobile/lib/app/main_shell.dart`
- Modify: `mobile/lib/features/profile/presentation/profile_page.dart`
- Modify: `mobile/lib/main.dart`
- Modify: `mobile/test/widget_test.dart`
- Modify: `mobile/test/app/main_shell_test.dart`
- Create: `mobile/test/app/app_dependencies_test.dart`
- Create: `mobile/test/app/authentication_gate_test.dart`

**Interfaces:**
- `AppDependencies.production()` constructs config, secure storage, raw Dio, API, repository, authenticated client, and controller.
- `AuthenticationGate({required SessionController controller})` switches between startup, login, offline recovery, and main-shell states.
- `EnglishMemoryApp({AppDependencies? dependencies})` owns and disposes production dependencies only when it creates them.
- `MainShell({required AuthenticatedUser user, required Future<void> Function() onLogout})`.
- `ProfilePage({required AuthenticatedUser user, required Future<void> Function() onLogout})`.

- [ ] **Step 1: Write failing composition and logout tests**

Assert invalid/missing API configuration renders a configuration message without a network call, injected dependencies make tests deterministic, startup calls restore once, and the gate renders Startup, Login, offline recovery, or MainShell for every controller state. Assert authenticated user identity appears in Profile, tapping Logout calls the controller, and the existing five-tab state-preservation behavior remains intact.

- [ ] **Step 2: Verify RED**

```bash
cd mobile
flutter test test/app/app_dependencies_test.dart test/app/authentication_gate_test.dart test/widget_test.dart test/app/main_shell_test.dart
```

Expected: tests fail because production composition and new shell/profile contracts do not exist.

- [ ] **Step 3: Implement the composition root and logout UI**

Read `const String.fromEnvironment('API_BASE_URL')` only in `AppDependencies.production`. Use `english-memory-mobile` as the backend `device_name`. Preserve test injection and avoid global mutable singletons.

- [ ] **Step 4: Verify GREEN and commit**

```bash
cd mobile
flutter test test/app/app_dependencies_test.dart test/app/authentication_gate_test.dart test/widget_test.dart test/app/main_shell_test.dart
flutter test
flutter analyze
cd ..
git add mobile/lib mobile/test
git commit -m "feat: compose Flutter authentication flow"
```

### Task 9: Run Full Gates and Update Project Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`

**Interfaces:**
- Produces: verified stage-7 authentication completion and the exact new test baseline.

- [ ] **Step 1: Run fresh Flutter and backend gates**

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
cd ..
docker exec -w /www/english-memory/.worktrees/flutter-authentication php82 vendor/bin/phpunit
docker exec -w /www/english-memory/.worktrees/flutter-authentication php82 composer validate --no-check-publish
docker exec nginx nginx -t
git diff --check
```

Expected: all commands return zero; backend remains at least 187 tests and 875 assertions.

- [ ] **Step 2: Audit secret and dependency scope**

```bash
rg -n "password|access_token|refresh_token" mobile/lib
sed -n '/^dependencies:/,/^dev_dependencies:/p' mobile/pubspec.yaml
git status --short
```

Expected: password references are form/API parameters only, tokens are confined to domain/data/storage boundaries, and only approved runtime dependencies were added.

- [ ] **Step 3: Update `PROJECT_PLAN.md` from evidence**

Mark “实现登录、刷新凭证、安全存储与退出” complete. Leave OCR and synchronization unchecked. Record Flutter/package versions, exact Flutter test count, analysis result, unchanged backend baseline, branch/worktree, API base-url requirement, and the next handoff at local OCR.

- [ ] **Step 4: Commit the verified handoff**

```bash
git add PROJECT_PLAN.md
git commit -m "docs: record Flutter authentication baseline"
git status --short --branch
```
