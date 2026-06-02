import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/login_screen.dart';
import '../features/conversations/conversation_list_screen.dart';
import '../features/conversations/conversation_view_screen.dart';
import '../features/settings/settings_screen.dart';
import '../features/support/ticket_list_screen.dart';
import '../features/support/create_ticket_screen.dart';
import '../features/support/ticket_view_screen.dart';
import '../features/support/support_dashboard_screen.dart';
import '../shared/providers/auth_provider.dart';
import 'main_shell.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authStateProvider);

  return GoRouter(
    initialLocation: '/conversations',
    redirect: (context, state) {
      final isLoggedIn  = authState.isAuthenticated;
      final isLoginPage = state.matchedLocation == '/login';
      if (!isLoggedIn && !isLoginPage) return '/login';
      if (isLoggedIn  &&  isLoginPage) return '/conversations';
      return null;
    },
    routes: [
      // ── صفحه ورود (بدون shell) ──────────────────────────────────────────
      GoRoute(
        path: '/login',
        name: 'login',
        pageBuilder: (context, state) => const MaterialPage(child: LoginScreen()),
      ),

      // ── Shell با BottomNavigationBar ─────────────────────────────────────
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => MainShell(navigationShell: shell),
        branches: [

          // ── تب ۱: مکالمات ──────────────────────────────────────────────
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/conversations',
                name: 'conversations',
                pageBuilder: (context, state) =>
                    const MaterialPage(child: ConversationListScreen()),
                routes: [
                  GoRoute(
                    path: ':id',
                    name: 'conversation',
                    pageBuilder: (context, state) => MaterialPage(
                      child: ConversationViewScreen(
                        conversationId: state.pathParameters['id']!,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),

          // ── تب ۲: تیکت‌ها ───────────────────────────────────────────────
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/tickets',
                name: 'tickets',
                pageBuilder: (context, state) =>
                    const MaterialPage(child: TicketListScreen()),
                routes: [
                  GoRoute(
                    path: 'create',
                    name: 'ticket-create',
                    pageBuilder: (context, state) =>
                        const MaterialPage(child: CreateTicketScreen()),
                  ),
                  GoRoute(
                    path: ':id',
                    name: 'ticket-view',
                    pageBuilder: (context, state) => MaterialPage(
                      child: TicketViewScreen(
                        ticketId: int.parse(state.pathParameters['id']!),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),

          // ── تب ۳: داشبورد ───────────────────────────────────────────────
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/dashboard',
                name: 'dashboard',
                pageBuilder: (context, state) =>
                    const MaterialPage(child: SupportDashboardScreen()),
              ),
            ],
          ),

          // ── تب ۴: تنظیمات ───────────────────────────────────────────────
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/settings',
                name: 'settings',
                pageBuilder: (context, state) =>
                    const MaterialPage(child: SettingsScreen()),
              ),
            ],
          ),
        ],
      ),
    ],

    errorPageBuilder: (context, state) => MaterialPage(
      child: Scaffold(
        body: Center(child: Text('صفحه یافت نشد: ${state.error}')),
      ),
    ),
  );
});
