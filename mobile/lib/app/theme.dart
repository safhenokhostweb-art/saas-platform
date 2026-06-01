import 'package:flutter/material.dart';
import '../core/design/client_design_token_bridge.dart';

class AppTheme {
  AppTheme._();

  static ThemeData light() {
    final tokens = ClientDesignTokenBridge.light();
    return _build(tokens, Brightness.light);
  }

  static ThemeData dark() {
    final tokens = ClientDesignTokenBridge.dark();
    return _build(tokens, Brightness.dark);
  }

  static ThemeData _build(DesignTokens tokens, Brightness brightness) {
    final cs = ColorScheme.fromSeed(
      seedColor: tokens.primaryColor,
      brightness: brightness,
      primary: tokens.primaryColor,
      secondary: tokens.secondaryColor,
      surface: tokens.surfaceColor,
      error: tokens.errorColor,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: cs,
      fontFamily: 'Vazir',
      textTheme: _textTheme(tokens),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: tokens.primaryColor,
          foregroundColor: Colors.white,
          minimumSize: const Size(double.infinity, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(tokens.radiusMd),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(tokens.radiusMd),
        ),
        filled: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(tokens.radiusLg),
          side: BorderSide(color: tokens.borderColor, width: 1),
        ),
      ),
      dividerTheme: DividerThemeData(color: tokens.borderColor, space: 1),
      appBarTheme: AppBarTheme(
        elevation: 0,
        centerTitle: false,
        backgroundColor: cs.surface,
        foregroundColor: cs.onSurface,
        surfaceTintColor: Colors.transparent,
      ),
    );
  }

  static TextTheme _textTheme(DesignTokens tokens) {
    return const TextTheme(
      headlineLarge: TextStyle(fontWeight: FontWeight.w700, fontSize: 28),
      headlineMedium: TextStyle(fontWeight: FontWeight.w700, fontSize: 22),
      titleLarge: TextStyle(fontWeight: FontWeight.w600, fontSize: 18),
      titleMedium: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
      bodyLarge: TextStyle(fontSize: 15),
      bodyMedium: TextStyle(fontSize: 13),
      labelLarge: TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
    );
  }
}
