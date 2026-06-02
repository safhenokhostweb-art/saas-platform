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
        token:     json['token'] as String,
        session:   (json['session'] as Map<String, dynamic>?) ?? {},
        user:      AuthUser.fromJson(json['user'] as Map<String, dynamic>),
        expiresAt: json['expires_at'] as String? ?? '',
      );
}

class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.mobile,
    required this.roles,
    this.isAdmin      = false,
    this.isSiteAdmin  = false,
    this.siteId,
  });

  final String id;
  final String name;
  final String mobile;
  final List<String> roles;
  final bool isAdmin;
  final bool isSiteAdmin;
  final int? siteId;

  /// ادمین کل پلتفرم
  bool get isPlatformAdmin => isAdmin;

  /// ادمین سایت مشتری
  bool get isSiteAdminUser => isSiteAdmin && !isAdmin;

  /// کاربر نهایی
  bool get isEndUser => !isAdmin && !isSiteAdmin;

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id:           json['id'].toString(),
        name:         json['name'] as String?   ?? '',
        mobile:       json['mobile'] as String? ?? '',
        roles:        (json['roles'] as List<dynamic>?)?.cast<String>() ?? [],
        isAdmin:      json['is_admin']      as bool? ?? false,
        isSiteAdmin:  json['is_site_admin'] as bool? ?? false,
        siteId:       json['site_id'] as int?,
      );
}
