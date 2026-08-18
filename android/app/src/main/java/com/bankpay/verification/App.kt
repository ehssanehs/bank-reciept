package com.bankpay.verification

import android.app.Application
import com.bankpay.verification.data.AppDatabase
import com.bankpay.verification.worker.SmsUploadWorker

class App : Application() {

    val database: AppDatabase by lazy { AppDatabase.getInstance(this) }

    override fun onCreate() {
        super.onCreate()
        SmsUploadWorker.schedule(this)
    }
}
