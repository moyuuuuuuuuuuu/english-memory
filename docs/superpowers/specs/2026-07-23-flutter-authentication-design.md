# Flutter Authentication Design

## Scope

This delivery adds mobile registration, login, secure session persistence, startup restoration, access-token refresh, current-user loading, and logout. It integrates with the existing Webman authentication API without changing backend behavior. Password recovery remains a visible, explicit follow-up entry and does not call the recovery API in this delivery.

## Goals

- Gate the existing five-destination shell behind a restored authenticated session.
- Support login by email or username and registration by either identity type.
- Store session secrets only in Android Keystore-backed or iOS Keychain-backed secure storage.
- Rotate refresh tokens atomically and perform at most one refresh for concurrent unauthorized requests.
- Clear local credentials whenever the server can no longer validate the session.
- Keep networking, storage, session state, and presentation independently testable.

## Non-Goals

- Password recovery and reset submission.
- Social login, biometrics, passkeys, or multi-account switching.
- Card, review, OCR, or offline synchronization API integration.
- A general-purpose state-management, routing, or dependency-injection framework.

## Dependencies

- `dio` provides JSON HTTP requests, typed request options, interceptors, cancellation, and safe one-time request replay.
- `flutter_secure_storage` stores access and refresh credentials with platform secure-storage facilities.

No other runtime package is introduced. Session state uses Flutter's built-in `ChangeNotifier` and `ListenableBuilder`.

## Configuration

The API base URL comes from `--dart-define=API_BASE_URL=<url>`. Production code does not hardcode `e.test`, emulator loopback addresses, LAN addresses, or a production hostname. The composition root validates that the value is a non-empty absolute HTTP or HTTPS URL and shows a developer-facing configuration screen when it is invalid.

Tests inject a client or base URL directly and never call a real server.

## Source Structure

```text
mobile/lib/
├── app/
│   ├── app_dependencies.dart
│   ├── authentication_gate.dart
│   └── english_memory_app.dart
├── core/
│   ├── config/api_config.dart
│   └── network/
│       ├── api_client.dart
│       ├── api_exception.dart
│       └── api_response_parser.dart
└── features/auth/
    ├── data/
    │   ├── auth_api.dart
    │   ├── auth_repository.dart
    │   └── secure_session_store.dart
    ├── domain/
    │   ├── authenticated_user.dart
    │   ├── auth_session.dart
    │   └── session_credentials.dart
    └── presentation/
        ├── login_page.dart
        ├── register_page.dart
        ├── session_controller.dart
        └── startup_page.dart
```

Tests mirror these boundaries under `mobile/test/` and use in-memory stores plus Dio mock adapters or a local mock transport.

## Backend Contract

The client consumes these existing endpoints:

- `POST /api/auth/register` with optional `email`, optional `username`, and `password`. Success is HTTP 201 with `data.user`; it does not create a session.
- `POST /api/auth/login` with `identity`, `password`, and `device_name`. Success returns `user`, `token_type`, `access_token`, `expires_in`, `refresh_token`, and `refresh_expires_in`.
- `POST /api/auth/refresh` with `refresh_token`. Success returns a new access token and a rotated refresh token with expiration durations.
- `GET /api/auth/me` with a Bearer access token. Success returns `data.user`.
- `POST /api/auth/logout` with a Bearer access token and `refresh_token`. Success returns `data.logged_out=true`.

The user entity contains `id`, nullable `email`, nullable `username`, and `timezone`. Provider or raw response maps never escape the data layer.

## Domain Models

`AuthenticatedUser` is immutable and validates a positive ID. `SessionCredentials` contains the access token, refresh token, and absolute expiration timestamps. `AuthSession` combines the current user and credentials.

Expiration durations from the API are converted immediately to UTC timestamps using an injected clock. This avoids spreading relative-TTL calculations through the app and makes expiry behavior deterministic in tests.

## Secure Storage

`SecureSessionStore` exposes `read`, `write`, and `clear`. Its production adapter uses `flutter_secure_storage`; tests use an in-memory implementation. Passwords and form values are never persisted.

The credentials are serialized as one versioned JSON value. Each successful login or refresh replaces that value in one storage write so a newly rotated refresh token cannot be paired with an older access token. Malformed or unsupported stored data is cleared and treated as signed out.

## Networking and Refresh Coordination

`ApiClient` adds the current access token to authenticated requests. It does not attach credentials to registration, login, or refresh calls.

On an authenticated HTTP 401, the client asks `AuthRepository` for a refresh. Refresh is single-flight: concurrent callers await the same future. On success, the repository stores the rotated credentials before any original request is replayed. Each request may be replayed once, marked by request metadata; a second 401 is returned as an authentication failure rather than entering a loop.

Refresh requests never pass through automatic refresh handling. `INVALID_REFRESH_TOKEN`, a rejected current-user request after refresh, malformed responses, or missing stored credentials clear secure storage and notify the session controller that authentication ended.

Connectivity errors do not clear credentials. Startup remains on a retryable error state so a temporary network outage does not sign the user out.

## Session Lifecycle

`SessionController` owns one of these states:

- `restoring`: reading secure storage and validating the server session.
- `signedOut`: no valid credentials.
- `authenticating`: a login or registration transition is running.
- `authenticated`: user and credentials are valid.
- `offlineError`: credentials exist but restoration could not reach the server.

At startup, the controller reads credentials. If none exist, it becomes signed out. If the access token is expired or near expiry, it refreshes first. It then calls `/api/auth/me`; success enters the main shell. A server authentication rejection clears storage and signs out. A network failure preserves credentials and offers Retry and Sign out.

Login stores the returned credentials, loads the returned user, and enters the shell. Registration returns to Login with the selected identity prefilled and a success message because the backend intentionally does not issue tokens on registration.

Logout is best effort: the client attempts the authenticated logout request, but local secure storage is cleared and the UI becomes signed out even when the network call fails.

## Presentation

`AuthenticationGate` listens to `SessionController` and switches between `StartupPage`, `LoginPage`, and the existing `MainShell`. It does not perform networking itself.

Login contains identity and password fields, password visibility control, loading/disabled state, register navigation, and a password-recovery button. Registration lets the user choose email or username, accepts one identity value, requires password confirmation, and validates before calling the repository.

The recovery button shows a clear Chinese message that recovery will be added in the next delivery. It is not silent and does not suggest that an email was sent.

Authentication pages use the established warm-white champagne theme, shared spacing and radii, readable labels, autofill hints, keyboard actions, and semantic error text. Server messages may be displayed only after stable error-code mapping; raw exceptions and response bodies are never shown.

## Validation and Error Mapping

The client trims identities before submission. Login requires a non-empty identity and password. Registration requires a syntactically valid email or a username of 3–32 ASCII letters, digits, or underscores, a password of at least eight characters, and matching confirmation.

Stable API codes map to focused Chinese messages, including `INVALID_INPUT`, `IDENTITY_TAKEN`, `INVALID_CREDENTIALS`, `ACCOUNT_DISABLED`, `INVALID_REFRESH_TOKEN`, and `UNAUTHENTICATED`. Unknown server failures use a generic retry message. Timeouts and connectivity failures use a distinct network message.

## Testing

- Domain tests cover parsing, validation, TTL-to-timestamp conversion, and malformed data rejection.
- Storage tests cover versioned serialization, replacement, clearing, and corrupt-data recovery.
- API tests cover request shapes, response envelopes, stable error mapping, Bearer attachment, and logout payloads.
- Refresh tests prove token rotation is stored before replay, concurrent 401 responses perform one refresh, and requests replay no more than once.
- Controller tests cover startup with no credentials, valid restoration, expired-token refresh, authentication rejection, offline retry, login, registration handoff, and best-effort logout.
- Widget tests cover the gate states, login/register validation, loading behavior, password visibility, recovery notice, successful navigation, and logout from Profile.

All behavior changes follow red-green-refactor. Verification requires `flutter analyze`, `flutter test`, the existing backend PHPUnit suite, Composer validation, Nginx configuration validation, and `git diff --check`.

## Acceptance Criteria

- A fresh launch without credentials shows Login.
- Valid login reaches the existing main shell and survives app restart through secure restoration.
- Registration supports email or username and returns to a prefilled Login page.
- Expired access credentials refresh with token rotation; concurrent refresh demand results in one refresh request.
- Invalid or replaced sessions clear secure storage and return to Login.
- Network restoration errors preserve credentials and offer retry/sign-out actions.
- Logout clears local credentials even if its network request fails.
- Passwords are never stored and raw backend/provider errors never reach the UI.
- The Profile destination provides the logout action.
- Flutter analysis, Flutter tests, and backend verification pass from fresh commands.
