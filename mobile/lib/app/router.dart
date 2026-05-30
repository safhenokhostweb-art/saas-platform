import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/login_screen.dart';
import '../features/conversations/conversation_list_screen.dart';
import '../features/conversations/conversation_view_screen.dart';
import '../features/settings/settings_screen.dart';
import '../shared/providers/auth_provider.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authStateProvider);

  return GoRouter(
    initialLocation: '/conversations',
    redirect: (context, state) {
      final isLoggedIn = authState.isAuthenticated;
      final isLoginPage = state.matchedLocation == '/login';

      if (!isLoggedIn && !isLoginPage) return '/login';
      if (isLoggedIn && isLoginPage) return '/conversations';
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        name: 'login',
        pageBuilder: (context, state) => const MaterialPage(child: LoginScreen()),
      ),
      GoRoute(
        path: '/conversations',
        name: 'conversations',
        pageBuilder: (context, state) => const MaterialPage(child: ConversationListScreen()),
        routes: [
          GoRoute(
            path: ':id',
            name: 'conversation',
            pageBuilder: (context, state) => MaterialPage(
              child: ConversationViewScreen(conversationId: state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: '/settings',
        name: 'settings',
        pageBuilder: (context, state) => const MaterialPage(child: SettingsScreen()),
      ),
    ],
    errorPageBuilder: (context, state) => MaterialPage(
      child: Scaffold(
        body: Center(child: Text('Page not found: ${state.error}')),
      ),
    ),
  );
});
