# Android App — Bank SMS Relay

Native Kotlin app that runs on a dedicated Android phone, receives bank SMS,
queues them locally, and uploads them securely to the backend.

## Architecture

- **`sms/SmsReceiver`** — receives SMS (`SMS_RECEIVED` / `SMS_DELIVERED`),
  concatenates multipart parts, filters by configurable sender patterns, and
  persists to a Room local queue.
- **`data/`** — Room database with `QueuedSms` (offline-first queue).
- **`worker/SmsUploadWorker`** — WorkManager periodic worker with exponential
  backoff; transmits pending/failed messages via Retrofit; marks them
  `uploaded`/`failed`.
- **`network/`** — Retrofit + OkHttp; every request is signed with
  HMAC-SHA256 (`X-Device-Id`, `X-API-Key`, `X-Timestamp`, `X-Signature`,
  `X-Nonce`) and delivered over HTTPS.
- **`prefs/SecurePrefs`** — EncryptedSharedPreferences for credentials and
  settings (secrets never stored in plaintext).
- **`SettingsActivity`** — backend URL, device name/ID, sender patterns,
  connection test, manual sync, pending/failed counters.

## SMS permission / policy (important)

On **Android 4.4+** a third-party app receives **full SMS bodies only if it is
the default SMS handler**. `SMS_RECEIVED` broadcasts deliver the sender but
the body is redacted for non-default handlers.

This app targets **private / sideloaded deployment on a dedicated phone**, per
the project requirements. It does **not** bypass Android security:

- Use `adb` to set it as the default SMS handler:
  `adb shell imessaging.set_sms_default_package` (or use the system UI).
- Or install with privileged broadcast permissions on a rooted/managed device.

The app checks whether it is the default handler and logs a warning if not.

## Registering the device (get credentials)

1. Log into the admin panel.
2. Register the device (creates a `device_id`, `api_key`, and `secret`).
3. Enter those credentials in `SettingsActivity` along with the backend URL
   and the bank sender patterns.

## Build

```bash
cd android
./gradlew assembleDebug
```

Requires Android Studio / JDK 17.
