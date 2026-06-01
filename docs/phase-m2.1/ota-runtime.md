# OTA Update Runtime — Phase M2.1

## Channels

| Channel | Audience | Update Cadence |
|---------|----------|---------------|
| `stable` | All users (default) | Major/minor releases |
| `beta` | Opt-in testers | Weekly releases |
| `canary` | Developers only | Daily builds |

The active channel is stored in `SecureTokenService` under key `updateChannel` and sent to the backend with every manifest request.

## API Endpoint

```
GET /api/mobile/ota/manifest?version={current}&channel={channel}
```

Response:
```json
{
  "data": {
    "update_available": true,
    "latest_version": "2.1.0",
    "mandatory": false,
    "download_url": "https://platform.example.com/releases/v2.1.0/client.zip",
    "checksum": "sha256hex",
    "release_notes": "..."
  }
}
```

## Check Schedule

- **On startup**: after a 10-second delay (does not block launch)
- **Periodic**: every 6 hours via `Timer.periodic`
- **Manual**: user can trigger from Settings screen

## Windows Update Flow

```
OtaUpdateService.checkForUpdates()
        │ manifest.updateAvailable == true
        ▼
WindowsUpdateRuntime.download(url, checksum)
        │ Dio streaming download → temp file
        ├── SHA-256 verification
        │       FAIL → delete file, emit error
        │       PASS → emit readyToInstall
        ▼
WindowsUpdateRuntime.showRestartPrompt(context, path)
        │ User confirms
        ▼
Process.start(installer, ['/SILENT'])
exit(0)
```

## Android Update Flow

```
OtaUpdateService.checkForUpdates()
        │ manifest.updateAvailable == true
        ▼
AndroidUpdateRuntime.downloadApk(url, checksum)
        │ Dio streaming download → external storage
        ├── SHA-256 verification
        │       FAIL → delete APK, emit error
        │       PASS → emit readyToInstall
        ▼
AndroidUpdateRuntime.showUpdatePrompt(context, apkPath, mandatory)
        │ User confirms
        ▼
Permission.requestInstallPackages.request()
        │ Granted
        ▼
OpenFile.open(apkPath, type: 'application/vnd.android.package-archive')
```

## Security

- All downloads are from the **platform OTA server only** — no external CDN or app store
- SHA-256 checksums are verified before any installer is launched
- Failed verification deletes the download immediately
- Downloads use existing Dio + Auth interceptor (Sanctum Bearer token)
