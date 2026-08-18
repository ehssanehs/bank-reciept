package com.bankpay.verification.network

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

data class SmsMessage(
    val local_id: String,
    val sender: String,
    val message: String,
    val received_at: String,
)

data class UploadRequest(val messages: List<SmsMessage>)

data class UploadCounts(val accepted: Int = 0, val duplicates: Int = 0, val ignored: Int = 0, val parsed: Int = 0)

data class UploadResponse(val success: Boolean, val data: UploadCounts? = null)

data class ServerStatus(val success: Boolean, val data: Map<String, Any>? = null)

interface BackendApi {

    @POST("api/v1/android/sms")
    suspend fun uploadSms(@Body request: UploadRequest): retrofit2.Response<UploadResponse>

    @GET("api/v1/android/status")
    suspend fun status(): retrofit2.Response<ServerStatus>
}
