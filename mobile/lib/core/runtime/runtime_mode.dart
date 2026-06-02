/// PHASE M3.2 — Flutter Runtime Mode
///
/// Determines which set of features the app operates in after login.
/// The mode is resolved from the login response — never hardcoded.

enum RuntimeMode {
  /// PLATFORM_MODE: User is a platform-level actor (Admin, Platform Staff, Customer Admin).
  /// Features: Platform Inbox, Customer Admin Chat, Licensing, OTA, Customer Management.
  platformMode,

  /// TENANT_MODE: User is a tenant-level actor (End User, Support Agent, Tenant Admin).
  /// Features: Support Inbox, Customer Users, Assignments, SLA Dashboard, Presence, Notifications.
  tenantMode,
}

/// Features available in each mode — resolved at runtime, never hardcoded.
class RuntimeFeatureSet {
  const RuntimeFeatureSet._();

  static const Map<RuntimeMode, List<String>> features = {
    RuntimeMode.platformMode: [
      'platform_inbox',
      'customer_admin_chat',
      'licensing',
      'ota_updates',
      'customer_management',
      'platform_analytics',
    ],
    RuntimeMode.tenantMode: [
      'support_inbox',
      'customer_users',
      'assignments',
      'sla_dashboard',
      'presence',
      'notifications',
      'realtime_chat',
    ],
  };

  static bool hasFeature(RuntimeMode mode, String feature) {
    return features[mode]?.contains(feature) ?? false;
  }
}

/// The base URL target differs per runtime mode.
class RuntimeEndpoints {
  const RuntimeEndpoints._();

  static String resolveBaseUrl({
    required RuntimeMode mode,
    required String platformUrl,
    required String tenantUrl,
  }) {
    return switch (mode) {
      RuntimeMode.platformMode => platformUrl,
      RuntimeMode.tenantMode   => tenantUrl,
    };
  }
}
