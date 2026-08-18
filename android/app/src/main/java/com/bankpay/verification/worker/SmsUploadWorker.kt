package com.bankpay.verification.worker

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.bankpay.verification.App
import com.bankpay.verification.data.QueuedSms
import com.bankpay.verification.network.ApiClient
import com.bankpay.verification.network.SmsMessage
import com.bankpay.verification.network.UploadRequest
import com.bankpay.verification.prefs.SecurePrefs
import kotlinx.coroutines.flow.first
import java.util.concurrent.TimeUnit

/**
 * Uploads queued bank SMS to the backend with automatic retry and exponential
 * backoff. Offline messages are never lost — they stay in Room until a later
 * run succeeds.
 */
class SmsUploadWorker(
    context: Context,
    params: WorkerParameters,
) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        val app = applicationContext as App
        val dao = app.database.smsDao()

        // Guard: only run when credentials are configured.
        if (SecurePrefs.getString(app, SecurePrefs.KEY_API_KEY).isEmpty()) {
            return Result.retry()
        }

        val pending = dao.pendingToUpload()
        if (pending.isEmpty()) {
            dao.cleanUploaded()
            return Result.success()
        }

        val api = try {
            ApiClient.api(app)
        } catch (_: Exception) {
            return Result.retry()
        }

        var ok = true
        for (sms in pending) {
            try {
                dao.update(sms.copy(status = QueuedSms.STATUS_UPLOADING))
                val request = UploadRequest(
                    messages = listOf(
                        SmsMessage(
                            local_id = sms.localId,
                            sender = sms.sender,
                            message = sms.message,
                            received_at = sms.receivedAt,
                        )
                    )
                )
                val response = api.uploadSms(request)

                if (response.isSuccessful && (response.body()?.success == true)) {
                    dao.update(sms.copy(status = QueuedSms.STATUS_UPLOADED))
                    SecurePrefs.putLong(app, SecurePrefs.KEY_LAST_SYNC_AT, System.currentTimeMillis())
                } else {
                    // 4xx (e.g. duplicate, auth) should not be retried forever.
                    val code = response.code()
                    if (code in 400..499 && code != 408 && code != 429) {
                        dao.update(sms.copy(status = QueuedSms.STATUS_FAILED, attempts = sms.attempts + 1))
                    } else {
                        dao.update(sms.copy(status = QueuedSms.STATUS_FAILED, attempts = sms.attempts + 1))
                        ok = false
                    }
                }
            } catch (e: Exception) {
                dao.update(sms.copy(status = QueuedSms.STATUS_FAILED, attempts = sms.attempts + 1, lastError = e.message))
                ok = false
            }
        }

        return if (ok) Result.success() else Result.retry()
    }

    companion object {
        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<SmsUploadWorker>(15, TimeUnit.MINUTES)
                .setConstraints(constraints)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                "bankpay-sms-upload",
                ExistingPeriodicWorkPolicy.KEEP,
                request,
            )
        }

        fun runNow(context: Context) {
            val request = androidx.work.OneTimeWorkRequestBuilder<SmsUploadWorker>()
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(context).enqueue(request)
        }
    }
}
