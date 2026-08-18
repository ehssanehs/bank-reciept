package com.bankpay.verification.sms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.util.Log
import com.bankpay.verification.App
import com.bankpay.verification.data.QueuedSms
import com.bankpay.verification.prefs.SecurePrefs
import com.bankpay.verification.worker.SmsUploadWorker
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Receives bank SMS and queues them locally (offline-first). On Android 4.4+
 * the app must be the default SMS handler to receive full message bodies;
 * this app targets private/sideloaded deployment on a dedicated phone and
 * never attempts to bypass platform restrictions.
 */
class SmsReceiver : BroadcastReceiver() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        if (action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION &&
            action != Telephony.Sms.Intents.SMS_DELIVERED_ACTION
        ) {
            return
        }

        val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return
        val app = context.applicationContext as App

        // Concatenate multipart messages sharing the same sender.
        val grouped = messages.groupBy { it.originatingAddress ?: "unknown" }
        val combined = grouped.map { (sender, parts) ->
            val body = parts.joinToString("") { it.displayMessageBody ?: "" }
            val timestamp = parts.firstOrNull()?.timestampMillis ?: System.currentTimeMillis()
            sender to (body to timestamp)
        }

        val patterns = (SecurePrefs.getString(app, SecurePrefs.KEY_SENDER_PATTERNS, DEFAULT_PATTERNS)
            .split(",").map { it.trim().lowercase() }.filter { it.isNotEmpty() })

        var matched = 0
        combined.forEach { (sender, data) ->
            val (body, ts) = data
            if (matches(patterns, sender)) {
                val receivedAt = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
                    .format(Date(ts))
                scope.launch {
                    val inserted = app.database.smsDao().insert(
                        QueuedSms(sender = sender, message = body, receivedAt = receivedAt)
                    )
                    if (inserted != -1L) matched++
                }
            }
        }

        // Kick the upload worker to transmit immediately.
        SmsUploadWorker.schedule(app)

        if (!isDefaultSmsHandler(context)) {
            Log.w(TAG, "App is not the default SMS handler; SMS bodies may be incomplete.")
        }
    }

    private fun matches(patterns: List<String>, sender: String): Boolean {
        val s = sender.lowercase()
        return patterns.any { s.contains(it) || it.contains(s) }
    }

    private fun isDefaultSmsHandler(context: Context): Boolean {
        return try {
            Telephony.Sms.getDefaultSmsPackage(context) == context.packageName
        } catch (_: Exception) {
            false
        }
    }

    companion object {
        private const val TAG = "SmsReceiver"
        private const val DEFAULT_PATTERNS = "bank,sepa,melli,teb,payam"
    }
}
