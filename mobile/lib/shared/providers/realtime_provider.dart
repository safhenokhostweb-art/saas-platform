import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/realtime/realtime_client_service.dart';

final realtimeServiceProvider = Provider<RealtimeClientService>((ref) {
  final service = ref.watch(realtimeClientServiceProvider);
  ref.onDispose(service.dispose);
  return service;
});
