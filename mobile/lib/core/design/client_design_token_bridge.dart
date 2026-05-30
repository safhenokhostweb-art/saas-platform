import 'package:flutter/material.dart';

// ─── ClientDesignTokenBridge ──────────────────────────────────────────────────
// Maps design-tokens.css --ui-* variables from the platform's visual authority
// to Flutter-native Color, spacing, and typography values.
//
// The canonical source is:
//   packages/saas-platform/core-utils/resources/css/design-tokens.css
//
// Update this bridge whenever --ui-* tokens change on the server.

class DesignTokens {
  const DesignTokens({
    required this.primaryColor,
    required this.secondaryColor,
    required this.surfaceColor,
    required this.errorColor,
    required this.borderColor,
    required this.radiusSm,
    required this.radiusMd,
    required this.radiusLg,
    required this.spacingSm,
    required this.spacingMd,
    required this.spacingLg,
    required this.fontFamilyBase,
  });

  final Color primaryColor;
  final Color secondaryColor;
  final Color surfaceColor;
  final Color errorColor;
  final Color borderColor;
  final double radiusSm;
  final double radiusMd;
  final double radiusLg;
  final double spacingSm;
  final double spacingMd;
  final double spacingLg;
  final String fontFamilyBase;
}

class ClientDesignTokenBridge {
  ClientDesignTokenBridge._();

  // Light mode — mirrors --ui-* light values from design-tokens.css
  static DesignTokens light() => const DesignTokens(
        primaryColor: Color(0xFF2563EB),      // --ui-color-primary
        secondaryColor: Color(0xFF7C3AED),    // --ui-color-secondary
        surfaceColor: Color(0xFFFFFFFF),      // --ui-color-surface
        errorColor: Color(0xFFDC2626),        // --ui-color-danger
        borderColor: Color(0xFFE5E7EB),       // --ui-color-border
        radiusSm: 4.0,                        // --ui-radius-sm
        radiusMd: 8.0,                        // --ui-radius-md
        radiusLg: 12.0,                       // --ui-radius-lg
        spacingSm: 8.0,                       // --ui-spacing-sm
        spacingMd: 16.0,                      // --ui-spacing-md
        spacingLg: 24.0,                      // --ui-spacing-lg
        fontFamilyBase: 'Vazir',              // matches @font-vazir utility
      );

  // Dark mode — mirrors --ui-* dark overrides from design-tokens.css
  static DesignTokens dark() => const DesignTokens(
        primaryColor: Color(0xFF60A5FA),
        secondaryColor: Color(0xFFA78BFA),
        surfaceColor: Color(0xFF1F2937),
        errorColor: Color(0xFFF87171),
        borderColor: Color(0xFF374151),
        radiusSm: 4.0,
        radiusMd: 8.0,
        radiusLg: 12.0,
        spacingSm: 8.0,
        spacingMd: 16.0,
        spacingLg: 24.0,
        fontFamilyBase: 'Vazir',
      );

  // Validate that local token values still match server-reported design tokens.
  // Called by ClientDiagnosticsService.
  static Map<String, dynamic> validate(Map<String, dynamic> serverTokens) {
    final issues = <String>[];

    // Compare primary color from server manifest (hex string)
    final serverPrimary = serverTokens['primary_color'] as String?;
    if (serverPrimary != null && serverPrimary.isNotEmpty) {
      final local = '#${light().primaryColor.value.toRadixString(16).substring(2).toUpperCase()}';
      if (local.toLowerCase() != serverPrimary.toLowerCase()) {
        issues.add('primary_color mismatch: local=$local server=$serverPrimary');
      }
    }

    return {
      'valid': issues.isEmpty,
      'issues': issues,
      'token_count': 12,
    };
  }
}
