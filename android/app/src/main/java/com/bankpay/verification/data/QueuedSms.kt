package com.bankpay.verification.data

import androidx.room.Entity
import androidx.room.PrimaryKey
import java.util.UUID

/**
 * A bank SMS persisted locally before transmission (offline-first queue).
 */
@Entity(tableName = "queued_sms")
data class QueuedSms(
    @PrimaryKey val localId: String = UUID.randomUUID().toString(),
    val sender: String,
    val message: String,
    val receivedAt: String,
    val status: String = STATUS_PENDING, // pending | uploading | uploaded | failed
    val attempts: Int = 0,
    val lastError: String? = null,
) {
    companion object {
        const val STATUS_PENDING = "pending"
        const val STATUS_UPLOADING = "uploading"
        const val STATUS_UPLOADED = "uploaded"
        const val STATUS_FAILED = "failed"
    }
}
