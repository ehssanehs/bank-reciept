package com.bankpay.verification.data

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

@Dao
interface SmsDao {

    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insert(sms: QueuedSms): Long

    @Query("SELECT * FROM queued_sms WHERE status = 'pending' OR status = 'failed' ORDER BY receivedAt LIMIT 100")
    suspend fun pendingToUpload(): List<QueuedSms>

    @Query("SELECT * FROM queued_sms ORDER BY receivedAt DESC LIMIT 200")
    fun observeAll(): Flow<List<QueuedSms>>

    @Query("SELECT COUNT(*) FROM queued_sms WHERE status IN ('pending','uploading','failed')")
    fun observePendingCount(): Flow<Int>

    @Query("SELECT COUNT(*) FROM queued_sms WHERE status = 'failed'")
    fun observeFailedCount(): Flow<Int>

    @Update
    suspend fun update(sms: QueuedSms)

    @Query("DELETE FROM queued_sms WHERE status = 'uploaded'")
    suspend fun cleanUploaded()
}
