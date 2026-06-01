class OtaManifest {
  const OtaManifest({
    required this.latestVersion,
    required this.currentVersion,
    required this.updateAvailable,
    required this.mandatory,
    required this.channel,
    required this.downloadUrl,
    this.checksum,
    required this.changelog,
    required this.minOsVersion,
  });

  final String latestVersion;
  final String currentVersion;
  final bool updateAvailable;
  final bool mandatory;
  final String channel;
  final String? downloadUrl;
  final String? checksum;
  final String changelog;
  final String? minOsVersion;

  factory OtaManifest.fromJson(Map<String, dynamic> json) => OtaManifest(
        latestVersion: json['latest_version'] as String? ?? '0.0.0',
        currentVersion: json['current_version'] as String? ?? '0.0.0',
        updateAvailable: json['update_available'] as bool? ?? false,
        mandatory: json['mandatory'] as bool? ?? false,
        channel: json['channel'] as String? ?? 'stable',
        downloadUrl: json['download_url'] as String?,
        checksum: json['checksum'] as String?,
        changelog: json['changelog'] as String? ?? '',
        minOsVersion: json['min_os_version'] as String?,
      );
}
