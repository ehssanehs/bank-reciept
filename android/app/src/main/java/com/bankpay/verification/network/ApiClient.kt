package com.bankpay.verification.network

import android.content.Context
import com.bankpay.verification.prefs.SecurePrefs
import kotlinx.coroutines.runBlocking
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.UUID
import java.util.concurrent.TimeUnit

/**
 * Builds a Retrofit client whose OkHttp interceptor signs every request with
 * the per-device secret (HMAC-SHA256) plus device_id / API key / timestamp /
 * nonce headers. HTTPS is enforced by the server.
 */
object ApiClient {

    @Volatile
    private var instance: BackendApi? = null

    fun api(context: Context): BackendApi {
        return instance ?: synchronized(this) {
            val baseUrl = normalizeBaseUrl(SecurePrefs.getString(context, SecurePrefs.KEY_BACKEND_URL, ""))
                ?: throw IllegalStateException("Backend URL not configured")

            val logging = HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BASIC
            }

            val client = OkHttpClient.Builder()
                .connectTimeout(15, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .writeTimeout(30, TimeUnit.SECONDS)
                .addInterceptor(logging)
                .addInterceptor { chain -> sign(context, chain) }
                .build()

            instance = Retrofit.Builder()
                .baseUrl(baseUrl)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(BackendApi::class.java)
            instance!!
        }
    }

    private fun sign(context: Context, chain: okhttp3.Interceptor.Chain): okhttp3.Response {
        val request = chain.request()
        val deviceId = SecurePrefs.getString(context, SecurePrefs.KEY_DEVICE_ID)
        val apiKey = SecurePrefs.getString(context, SecurePrefs.KEY_API_KEY)
        val secret = SecurePrefs.getString(context, SecurePrefs.KEY_SECRET)

        require(deviceId.isNotEmpty() && apiKey.isNotEmpty() && secret.isNotEmpty()) {
            "Device credentials not configured"
        }

        val body = request.body
        val bodyBytes = readBody(body)
        val bodyString = String(bodyBytes, Charsets.UTF_8)
        val timestamp = (System.currentTimeMillis() / 1000).toString()
        val path = request.url.encodedPath
        val nonce = UUID.randomUUID().toString()
        val signature = DeviceSigner.sign(secret, request.method, path, bodyString, timestamp)

        val signed = request.newBuilder()
            .header("X-Device-Id", deviceId)
            .header("X-API-Key", apiKey)
            .header("X-Timestamp", timestamp)
            .header("X-Signature", signature)
            .header("X-Nonce", nonce)
            .build()

        return chain.proceed(signed)
    }

    private fun readBody(body: RequestBody?): ByteArray {
        if (body == null) return ByteArray(0)
        val buffer = okio.Buffer()
        body.writeTo(buffer)
        return buffer.readByteArray()
    }

    private fun normalizeBaseUrl(url: String): String? {
        if (url.isBlank()) return null
        val trimmed = url.trim().removeSuffix("/")
        return "$trimmed/"
    }
}
