// User roles come from backend — never hardcoded in client logic.
// The client treats these as opaque permission sets delivered in the auth response.

enum UserRole {
  platformAdmin,
  customerAdmin,
  supportAgent,
  endUser,
  unknown;

  static UserRole fromString(String raw) => switch (raw.toLowerCase()) {
        'platform_admin' || 'admin' || 'super_admin' => UserRole.platformAdmin,
        'customer_admin' || 'reseller_admin' => UserRole.customerAdmin,
        'support_agent' || 'agent' => UserRole.supportAgent,
        'user' || 'end_user' => UserRole.endUser,
        _ => UserRole.unknown,
      };

  String get label => switch (this) {
        UserRole.platformAdmin => 'Platform Admin',
        UserRole.customerAdmin => 'Customer Admin',
        UserRole.supportAgent => 'Support Agent',
        UserRole.endUser => 'User',
        UserRole.unknown => 'Unknown',
      };
}

// Permission helper — gates are defined by backend roles, not hardcoded UI flags.
class UserPermissions {
  const UserPermissions({required this.roles});
  final List<UserRole> roles;

  bool get canViewAllConversations =>
      roles.contains(UserRole.platformAdmin) || roles.contains(UserRole.customerAdmin) || roles.contains(UserRole.supportAgent);

  bool get canManageAgents =>
      roles.contains(UserRole.platformAdmin) || roles.contains(UserRole.customerAdmin);

  bool get canViewAnalytics =>
      roles.contains(UserRole.platformAdmin) || roles.contains(UserRole.customerAdmin);

  bool get canChangeSettings =>
      roles.contains(UserRole.platformAdmin) || roles.contains(UserRole.customerAdmin);

  static UserPermissions fromRoleStrings(List<String> rawRoles) =>
      UserPermissions(roles: rawRoles.map(UserRole.fromString).toList());
}
