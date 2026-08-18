package com.bankpay.verification.network

import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec

/**
 * HMAC-SHA256 request signing (must match the backend's DeviceSignature).
 * Signs:  METHOD\nPATH\nRAW_BODY\nTIMESTAMP
 */
object DeviceSigner {

    fun sign(secret: String, method: String, path: String, body: String, timestamp: String): String {
        val canonical = listOf(method.uppercase(), path, body, timestamp).joinToString("\n")
        val mac = Mac.getInstance("HmacSHA256")
        mac.init(SecretKeySpec(secret.toByteArray(Charsets.UTF_8), "HmacSHA256"))
        return mac.doFinal(canonical.toByteArray(Charsets.UTF_8))
            .joinToString("") { "%02x".format(it) }
    }
}
