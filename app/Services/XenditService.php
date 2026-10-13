<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.xendit.co';
    protected $maxRetries = 5;
    protected $retryDelayMs = 500;

    public function __construct()
    {
        $this->apiKey = env('XENDIT_SECRET_KEY');
    }

    /**
     * Build a pre-configured HTTP client for Xendit API calls.
     *
     * Forces TLS 1.2 to avoid intermittent connection resets caused by
     * TLS 1.3 negotiation issues between OpenSSL 3.5.7 (PHP 8.5) and
     * Xendit's Cloudflare proxy. Uses fresh connections and HTTP/1.1.
     */
    protected function buildClient(): PendingRequest
    {
        return Http::withoutVerifying()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withOptions([
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE => true,
                ],
            ])
            ->withBasicAuth($this->apiKey, '')
            ->timeout(30);
    }

    /**
     * Create a Xendit Invoice using the REST API directly.
     * This avoids the SDK's ObjectSerializer bug ("Only variables should be passed by reference")
     * that occurs with newer PHP versions.
     *
     * Includes manual retry logic to handle intermittent cURL error 35
     * (Connection reset) from Xendit's Cloudflare-fronted API.
     */
    public function createInvoice($data)
    {
        $payload = [
            'external_id' => isset($data['external_id']) ? (string) $data['external_id'] : (string) $data['reservation_id'],
            'description' => $data['description'] ?? ('Reservation: ' . $data['room_type'] . ' - Room #' . $data['room_number']),
            'amount' => (float) $data['total_amount'],
            'currency' => 'PHP',
            'success_redirect_url' => $data['success_redirect_url'] ?? (env('XENDIT_SUCCESS_URL', env('APP_URL') . '/booking/success') . '?res_id=' . $data['reservation_id']),
            'failure_redirect_url' => env('XENDIT_CANCEL_URL', env('APP_URL') . '/rooms'),
            'customer' => [
                'given_names' => $data['customer_name'] ?? 'Guest',
                'email' => $data['customer_email'] ?? '',
                'mobile_number' => $data['customer_phone'] ?? '',
            ],
            // Only allow: Credit/Debit Card, E-Wallets, and QR Payments
            'payment_methods' => [
                'CREDIT_CARD',   // Credit/Debit Card
                'GCASH',         // E-Wallet: GCash
                'PAYMAYA',       // E-Wallet: Maya (PayMaya)
                'GRABPAY',       // E-Wallet: GrabPay
                'SHOPEEPAY',     // E-Wallet: ShopeePay
                'QRPH',          // QR Payments (QR Ph)
            ],
            'fees' => [],
            'items' => [
                [
                    'name' => $data['item_name'] ?? ($data['room_type'] . ' - Room #' . $data['room_number']),
                    'quantity' => 1,
                    'price' => (float) $data['total_amount']
                ]
            ]
        ];

        // Remove empty mobile_number to avoid Xendit validation errors
        if (empty($payload['customer']['mobile_number'])) {
            unset($payload['customer']['mobile_number']);
        }

        Log::info('Xendit Invoice Request:', ['payload' => $payload]);

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->buildClient()
                    ->post($this->baseUrl . '/v2/invoices', $payload);

                if ($response->successful()) {
                    $result = $response->json();
                    Log::info('Xendit Invoice Created Successfully', [
                        'invoice_id' => $result['id'] ?? null,
                        'invoice_url' => $result['invoice_url'] ?? null,
                        'attempt' => $attempt,
                    ]);

                    return [
                        'status' => 'success',
                        'invoice_url' => $result['invoice_url'],
                        'id' => $result['id']
                    ];
                }

                Log::error('Xendit Invoice Creation Failed (HTTP ' . $response->status() . '): ' . $response->body());
                return [
                    'status' => 'error',
                    'message' => 'Xendit returned HTTP ' . $response->status() . ': ' . $response->body()
                ];
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("Xendit connection failed (attempt {$attempt}/{$this->maxRetries}): " . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000 * $attempt); // Progressive backoff
                }
            } catch (\Exception $e) {
                Log::error('Xendit Invoice Creation Exception: ' . $e->getMessage());
                return [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        // All retries exhausted
        Log::error('Xendit Invoice Creation Failed after ' . $this->maxRetries . ' attempts: ' . $lastException->getMessage());
        return [
            'status' => 'error',
            'message' => $lastException->getMessage()
        ];
    }

    /**
     * Get invoice status using the Invoice ID.
     *
     * Includes manual retry logic for intermittent connection resets.
     */
    public function getInvoice($invoiceId)
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->buildClient()
                    ->get($this->baseUrl . '/v2/invoices/' . $invoiceId);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Xendit Invoice Fetch Failed (HTTP ' . $response->status() . '): ' . $response->body());
                return null;
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("Xendit getInvoice connection failed (attempt {$attempt}/{$this->maxRetries}): " . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelayMs * 1000 * $attempt);
                }
            } catch (\Exception $e) {
                Log::error('Xendit Invoice Fetch Exception: ' . $e->getMessage());
                return null;
            }
        }

        Log::error('Xendit Invoice Fetch Failed after ' . $this->maxRetries . ' attempts: ' . $lastException->getMessage());
        return null;
    }
}


