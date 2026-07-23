final class AuthenticatedUser {
  const AuthenticatedUser({
    required this.id,
    required this.email,
    required this.username,
    required this.timezone,
  });

  final int id;
  final String? email;
  final String? username;
  final String timezone;

  factory AuthenticatedUser.fromJson(Map<String, Object?> json) {
    final rawId = json['id'];
    final id = rawId is num ? rawId.toInt() : 0;
    final email = json['email'];
    final username = json['username'];
    final timezone = json['timezone'] ?? 'Asia/Shanghai';

    if (id <= 0 ||
        (email != null && email is! String) ||
        (username != null && username is! String) ||
        timezone is! String ||
        timezone.trim().isEmpty) {
      throw const FormatException('Invalid authenticated user.');
    }

    return AuthenticatedUser(
      id: id,
      email: email as String?,
      username: username as String?,
      timezone: timezone,
    );
  }
}
