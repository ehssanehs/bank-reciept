package com.bankpay.verification

import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.bankpay.verification.databinding.ActivityMainBinding
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.btnSettings.setOnClickListener {
            startActivity(Intent(this, SettingsActivity::class.java))
        }

        val db = (application as App).database
        lifecycleScope.launch {
            combine(db.smsDao().observePendingCount(), db.smsDao().observeFailedCount()) { pending, failed ->
                pending to failed
            }.collect { (pending, failed) ->
                binding.tvCounts.text = getString(R.string.queue_counts, pending, failed)
            }
        }
    }
}
