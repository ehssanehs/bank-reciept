package com.bankpay.verification

import android.os.Bundle
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.bankpay.verification.databinding.ActivitySettingsBinding
import com.bankpay.verification.network.ApiClient
import com.bankpay.verification.prefs.SecurePrefs
import com.bankpay.verification.worker.SmsUploadWorker
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class SettingsActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySettingsBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySettingsBinding.inflate(layoutInflater)
        setContentView(binding.root)

        loadExisting()

        binding.btnSave.setOnClickListener {
            save()
            SmsUploadWorker.runNow(this)
            Toast.makeText(this, getString(R.string.settings_saved), Toast.LENGTH_SHORT).show()
        }

        binding.btnTest.setOnClickListener {
            save()
            testConnection()
        }

        binding.btnSync.setOnClickListener {
            SmsUploadWorker.runNow(this)
            Toast.makeText(this, getString(R.string.sync_started), Toast.LENGTH_SHORT).show()
        }

        refreshStatus()
    }

    private fun loadExisting() {
        binding.etBackendUrl.setText(SecurePrefs.getString(this, SecurePrefs.KEY_BACKEND_URL))
        binding.etDeviceId.setText(SecurePrefs.getString(this, SecurePrefs.KEY_DEVICE_ID))
        binding.etDeviceName.setText(SecurePrefs.getString(this, SecurePrefs.KEY_DEVICE_NAME))
        binding.etSenderPatterns.setText(SecurePrefs.getString(this, SecurePrefs.KEY_SENDER_PATTERNS, "bank,sepa,melli"))
        binding.etApiKey.setText(SecurePrefs.getString(this, SecurePrefs.KEY_API_KEY))
        binding.etSecret.setText(SecurePrefs.getString(this, SecurePrefs.KEY_SECRET))
    }

    private fun save() {
        SecurePrefs.putString(this, SecurePrefs.KEY_BACKEND_URL, binding.etBackendUrl.text.toString().trim())
        SecurePrefs.putString(this, SecurePrefs.KEY_DEVICE_ID, binding.etDeviceId.text.toString().trim())
        SecurePrefs.putString(this, SecurePrefs.KEY_DEVICE_NAME, binding.etDeviceName.text.toString().trim())
        SecurePrefs.putString(this, SecurePrefs.KEY_SENDER_PATTERNS, binding.etSenderPatterns.text.toString().trim())
        SecurePrefs.putString(this, SecurePrefs.KEY_API_KEY, binding.etApiKey.text.toString().trim())
        SecurePrefs.putString(this, SecurePrefs.KEY_SECRET, binding.etSecret.text.toString().trim())
    }

    private fun testConnection() {
        binding.tvStatus.text = getString(R.string.testing)
        CoroutineScope(Dispatchers.IO).launch {
            val result = runCatching {
                ApiClient.api(this@SettingsActivity).status().body()?.success == true
            }.getOrDefault(false)
            withContext(Dispatchers.Main) {
                binding.tvStatus.text = if (result) getString(R.string.connected) else getString(R.string.connection_failed)
            }
        }
    }

    private fun refreshStatus() {
        val lastSync = SecurePrefs.getLong(this, SecurePrefs.KEY_LAST_SYNC_AT)
        binding.tvLastSync.text = getString(
            R.string.last_sync,
            if (lastSync == 0L) getString(R.string.never) else java.text.DateFormat.getDateTimeInstance().format(java.util.Date(lastSync)),
        )
        val app = application as App
        CoroutineScope(Dispatchers.IO).launch {
            val pending = app.database.smsDao().observePendingCount().let { c -> c.first() }
            val failed = app.database.smsDao().observeFailedCount().let { c -> c.first() }
            withContext(Dispatchers.Main) {
                binding.tvPending.text = getString(R.string.pending_count, pending)
                binding.tvFailed.text = getString(R.string.failed_count, failed)
            }
        }
    }
}
