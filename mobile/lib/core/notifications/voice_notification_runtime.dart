import 'dart:io';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

// ─── Provider ────────────────────────────────────────────────────────────────

final voiceNotificationRuntimeProvider = Provider<VoiceNotificationRuntime>((ref) {
  final runtime = VoiceNotificationRuntime();
  ref.onDispose(runtime.dispose);
  return runtime;
});

// ─── Sound types ─────────────────────────────────────────────────────────────

enum SoundType { incomingMessage, conversationAssigned, escalation }

// ─── Per-user sound preferences ──────────────────────────────────────────────

class VoiceSoundPreferences {
  const VoiceSoundPreferences({
    this.incomingMessage      = true,
    this.conversationAssigned = true,
    this.escalation           = true,
    this.volumePercent        = 80,
  });

  final bool incomingMessage;
  final bool conversationAssigned;
  final bool escalation;
  final int volumePercent; // 0–100

  VoiceSoundPreferences copyWith({
    bool? incomingMessage,
    bool? conversationAssigned,
    bool? escalation,
    int? volumePercent,
  }) =>
      VoiceSoundPreferences(
        incomingMessage:      incomingMessage      ?? this.incomingMessage,
        conversationAssigned: conversationAssigned ?? this.conversationAssigned,
        escalation:           escalation           ?? this.escalation,
        volumePercent:        volumePercent        ?? this.volumePercent,
      );
}

// ─── Sound asset map ─────────────────────────────────────────────────────────

const _soundAssets = {
  SoundType.incomingMessage:      'sounds/message_received.mp3',
  SoundType.conversationAssigned: 'sounds/assigned.mp3',
  SoundType.escalation:           'sounds/escalation.mp3',
};

const _notificationTypeToSound = {
  'message':    SoundType.incomingMessage,
  'assigned':   SoundType.conversationAssigned,
  'escalation': SoundType.escalation,
};

// ─── Runtime ─────────────────────────────────────────────────────────────────

class VoiceNotificationRuntime {
  final _player = AudioPlayer();
  VoiceSoundPreferences _prefs = const VoiceSoundPreferences();
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;
    await _player.setVolume(_prefs.volumePercent / 100);
  }

  // ── Play by notification type (matches ClientNotificationRuntime types) ───

  Future<void> playForType(String notificationType) async {
    final soundType = _notificationTypeToSound[notificationType];
    if (soundType == null) return;
    await play(soundType);
  }

  // ── Play a specific sound ─────────────────────────────────────────────────

  Future<void> play(SoundType type) async {
    if (!_isEnabled(type)) return;

    final asset = _soundAssets[type];
    if (asset == null) return;

    try {
      await _player.stop();
      await _player.setVolume(_prefs.volumePercent / 100);
      await _player.play(AssetSource(asset));
    } catch (_) {
      // Sound failure is non-critical
    }
  }

  // ── Update preferences ────────────────────────────────────────────────────

  void updatePreferences(VoiceSoundPreferences prefs) {
    _prefs = prefs;
  }

  VoiceSoundPreferences get preferences => _prefs;

  // ── Mute / unmute all ────────────────────────────────────────────────────

  void muteAll()   => updatePreferences(const VoiceSoundPreferences(
    incomingMessage: false,
    conversationAssigned: false,
    escalation: false,
  ));

  void unmuteAll() => updatePreferences(const VoiceSoundPreferences());

  void dispose()   => _player.dispose();

  // ── Check if sound type is enabled ───────────────────────────────────────

  bool _isEnabled(SoundType type) => switch (type) {
    SoundType.incomingMessage      => _prefs.incomingMessage,
    SoundType.conversationAssigned => _prefs.conversationAssigned,
    SoundType.escalation           => _prefs.escalation,
  };
}
