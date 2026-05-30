class AuthResponse {
  const AuthResponse({
    required this.token,
    required this.session,
    required this.user,
    required this.expiresAt,
  });

  final String token;
  final Map<String, dynamic> session;
  final AuthUser user;
  final String expiresAt;

  factory AuthResponse.fromJson(Map<String, dynamic> json) => AuthResponse(
        token: json['token'] as String,
        session: (json['session'] as Map<String, dynamic>?) ?? {},
        user: AuthUser.fromJson(json['user'] as Map<String, dynamic>),
        expiresAt: json['expires_at'] as String? ?? '',
      );
}

class AuthUser {
  const AuthUser({required this.id, required this.name, required this.mobile, required this.roles});

  final String id;
  final String name;
  final String mobile;
  final List<String> roles;

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        mobile: json['mobile'] as String? ?? '',
        roles: (json['roles'] as List<dynamic>?)?.cast<String>() ?? [],
      );
}
