package com.bankpay.verification.sms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony

/**
 * Receives full SMS content when this app is the default SMS handler
 * (SMS_DELIVERED intent). Forwards to [SmsReceiver] logic.
 */
class SmsDeliveredReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Telephony.Sms.Intents.SMS_DELIVERED_ACTION) {
            SmsReceiver().onReceive(context, intent)
        }
    }
}
