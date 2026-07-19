# Account And Device Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add secure account registration, password login, opaque access tokens, rotating device-scoped refresh tokens, logout revocation, protected user context, and password reset flows.

**Architecture:** Controllers only translate HTTP input/output and invoke one Business. Businesses own validation, authentication decisions, transactions, and model coordination. `TokenService` and `PasswordResetMail` are infrastructure boundaries; models only persist and query records; entities carry stable public results.

**Tech Stack:** PHP 8.2, Webman 2.2, Illuminate Database, Redis 8, PHPUnit 11, PHP `password_hash()` and `random_bytes()`.

## Global Constraints

- Follow `AGENTS.md` plural layer names and dependency direction.
- Access tokens are opaque random values stored in Redis for 15 minutes; no signing secret or JWT dependency is needed.
- Refresh tokens are opaque random values, stored only as SHA-256 hashes in MySQL, expire after 30 days, and rotate on every refresh.
- Each refresh token is device-scoped through `device_name`; logout revokes the presented refresh token and removes the current access token.
- Password reset tokens are stored only as SHA-256 hashes, expire after 30 minutes, and are single-use.
- Public errors use stable codes and Chinese messages; password/token values never appear in logs or persisted plaintext.
- Every schema change is a new migration; do not edit migrations `0001` to `0003`.
- Every behavior is implemented red-green-refactor and ends with the full backend verification suite.

---

### Task 1: Registration And User Persistence

**Files:**
- Create: `app/models/User.php`
- Create: `app/entities/AuthResultEntity.php`
- Create: `app/businesses/RegisterBusiness.php`
- Create: `app/controllers/RegisterController.php`
- Create: `tests/Feature/RegisterControllerTest.php`
- Modify: `config/route.php`

**Interfaces:**
- Consumes: `RegisterBusiness::register(?string $email, ?string $username, string $password): AuthResultEntity`.
- Produces: `POST /api/auth/register` and a sanitized user payload `{id,email,username}`.

- [x] Write failing tests for email registration, username registration, blank identity, invalid email, short password, duplicate email, and duplicate username.
- [x] Run only `RegisterControllerTest` and confirm failures are caused by missing registration classes.
- [x] Implement `User` persistence helpers, `AuthResultEntity`, registration validation, password hashing, and the thin controller.
- [x] Add the explicit registration route and run the focused tests until green.
- [x] Run the full suite and commit the registration slice.

### Task 2: Access Tokens, Login, And Authentication Middleware

**Files:**
- Create: `app/entities/AuthenticatedUserEntity.php`
- Create: `app/services/TokenService.php`
- Create: `app/businesses/LoginBusiness.php`
- Create: `app/controllers/LoginController.php`
- Create: `app/middleware/Authenticate.php`
- Create: `app/businesses/CurrentUserBusiness.php`
- Create: `app/controllers/CurrentUserController.php`
- Create: `tests/Unit/TokenServiceTest.php`
- Create: `tests/Feature/LoginControllerTest.php`
- Create: `tests/Feature/AuthenticateTest.php`
- Modify: `config/route.php`, `config/middleware.php`, `.env.example`

**Interfaces:**
- `TokenService::issueAccessToken(int $userId): array{token:string,expires_in:int}` stores `auth:access:<sha256>` in Redis.
- `TokenService::resolveAccessToken(string $token): ?int` resolves the authenticated user ID.
- Produces `POST /api/auth/login` and protected `GET /api/auth/me`.

- [x] Write and verify failing tests for correct login, wrong password, unknown identity, disabled users, token resolution, missing bearer token, invalid bearer token, and authenticated current-user output.
- [x] Implement the token service with an injectable Redis-like contract or callable adapter so unit tests do not depend on global Redis state.
- [x] Implement login Business/Controller and typed authenticated user entity.
- [x] Implement middleware that parses `Authorization: Bearer`, resolves the user, rejects invalid credentials, and attaches authenticated user ID to request context.
- [x] Wire explicit public/protected routes, run focused tests, then run the full suite and commit.

### Task 3: Device Refresh Rotation And Logout

**Files:**
- Create: `app/models/RefreshToken.php`
- Create: `app/businesses/RefreshSessionBusiness.php`
- Create: `app/businesses/LogoutBusiness.php`
- Create: `app/controllers/RefreshSessionController.php`
- Create: `app/controllers/LogoutController.php`
- Create: `tests/Feature/RefreshSessionControllerTest.php`
- Create: `tests/Feature/LogoutControllerTest.php`
- Modify: `app/businesses/LoginBusiness.php`, `config/route.php`

**Interfaces:**
- Login additionally returns `refresh_token` and `refresh_expires_in` and persists only `hash('sha256', token)` with device metadata.
- Produces `POST /api/auth/refresh` with one-time rotation and protected `POST /api/auth/logout`.

- [x] Write and verify failing tests for refresh persistence, plaintext absence, device metadata, valid rotation, reuse rejection, expiry rejection, logout revocation, and post-logout access denial.
- [x] Implement refresh-token model queries and transactionally rotate old/new refresh rows.
- [x] Extend login to issue a device-scoped refresh token.
- [x] Implement refresh and logout Businesses/Controllers and route wiring.
- [x] Run focused tests, the full suite, and commit.

### Task 4: Password Reset Persistence And Mail Boundary

**Files:**
- Create: `database/migrations/0004_create_password_reset_tokens.sql`
- Create: `app/models/PasswordResetToken.php`
- Create: `app/services/contracts/PasswordResetMail.php`
- Create: `app/services/LogPasswordResetMail.php`
- Create: `app/businesses/ForgotPasswordBusiness.php`
- Create: `app/businesses/ResetPasswordBusiness.php`
- Create: `app/controllers/ForgotPasswordController.php`
- Create: `app/controllers/ResetPasswordController.php`
- Create: `tests/Feature/PasswordResetTest.php`
- Modify: `config/route.php`, `.env.example`

**Interfaces:**
- `PasswordResetMail::send(string $email, string $plainToken): void` is replaceable and is the only boundary that sees the plaintext reset token.
- Produces `POST /api/auth/forgot-password` and `POST /api/auth/reset-password`.

- [x] Write and verify failing tests for neutral forgot-password responses, hashed token persistence, mail boundary invocation, valid reset, expired token, reused token, and login with the new password.
- [x] Add and run migration `0004`; verify it is idempotent through `scripts/migrate.php`.
- [x] Implement the model, mail contract, safe development adapter, Businesses, Controllers, and explicit routes.
- [x] Run focused tests, verify no plaintext reset token is persisted, then run the full suite and commit.

### Task 5: Stage Verification And Handoff Update

**Files:**
- Modify: `PROJECT_PLAN.md`
- Modify: `docs/superpowers/plans/2026-07-19-authentication.md`

**Interfaces:**
- Produces a completed stage-2 handoff and the next entry point: asynchronous AI card generation.

- [x] Run all migrations twice and verify the second run skips every applied file.
- [x] Run PHPUnit, Composer validation, PHP syntax checks for changed PHP files, Nginx validation, and `git diff --check`.
- [x] Restart Webman from the primary checkout or update the runtime path intentionally; smoke-test registration validation, login rejection, and protected-route rejection through `e.test`.
- [x] Mark every completed stage-2 item in `PROJECT_PLAN.md`, update the test baseline and current handoff point, and self-review for secrets.
- [x] Commit the stage documentation and present the branch for integration before starting stage 3.
