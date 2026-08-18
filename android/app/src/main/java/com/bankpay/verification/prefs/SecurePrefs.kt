package com.bankpay.verification.prefs

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Wraps [EncryptedSharedPreferences] so device credentials and settings are
 * stored encrypted at rest. Secret API credentials are never stored in plain
 * SharedPreferences or in source code.
 */
object SecurePrefs {

    private const val FILE = "bankpay_secure"

    private fun prefs(context: Context): SharedPreferences {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        return EncryptedSharedPreferences.create(
            context,
            FILE,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    fun putString(context: Context, key: String, value: String) =
        prefs(context).edit().putString(key, value).apply()

    fun getString(context: Context, key: String, default: String = ""): String =
        prefs(context).getString(key, default) ?: default

    fun putLong(context: Context, key: String, value: Long) =
        prefs(context).edit().putLong(key, value).apply()

    fun getLong(context: Context, key: String, default: Long = 0): Long =
        prefs(context).getLong(key, default)

    fun putInt(context: Context, key: String, value: Int) =
        prefs(context).edit().putInt(key, value).apply()

    fun getInt(context: Context, key: String, default: Int = 0): Int =
        prefs(context).getInt(key, default)

    const val KEY_BACKEND_URL = "backend_url"
    const val KEY_DEVICE_ID = "device_id"
    const val KEY_DEVICE_NAME = "device_name"
    const val KEY_API_KEY = "api_key"
    const val KEY_SECRET = "secret"
    const val KEY_SENDER_PATTERNS = "sender_patterns"
    const val KEY_LAST_SYNC_AT = "last_sync_at"
}
