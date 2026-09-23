<?php
include '../constant.php';
include '../session.php';
// gst_lookup.php

header('Content-Type: application/json');


if (!defined('GSTIN_API_KEY') || GSTIN_API_KEY === '' || GSTIN_API_KEY === 'PASTE_YOUR_API_KEY_HERE') {
    echo json_encode([
        'success' => false,
        'message' => 'GST API key is not configured. Add your GSTINAPI key in constant.php.'
    ]);
    exit;
}

$gstin = strtoupper(trim($_GET['gstin'] ?? ''));

$pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';

if (!preg_match($pattern, $gstin)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid GSTIN format. Please enter a valid 15-character GSTIN.'
    ]);
    exit;
}

$url = 'https://www.gstinapi.in/v1/gstin/' . rawurlencode($gstin) . '?include=profile';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'x-api-key: ' . GSTIN_API_KEY,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not connect to GST API. ' . $curlError
    ]);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid response received from GST API.'
    ]);
    exit;
}

if (($data['success'] ?? false) !== true) {

    $message = $data['error'] ?? 'GST lookup failed.';

    if ($httpCode === 401) {
        $message = 'GST API key is invalid or missing.';
    } elseif ($httpCode === 402) {
        $message = 'GST API credits are exhausted. Please add more credits.';
    } elseif ($httpCode === 403) {
        $message = 'GST API account is deactivated.';
    } elseif ($httpCode === 404) {
        $message = 'This GSTIN is not registered in the GST database.';
    } elseif ($httpCode === 429) {
        $message = 'GST API rate limit reached. Please try again shortly.';
    } elseif ($httpCode === 502) {
        $message = 'GST provider is temporarily unavailable. Please try again.';
    }

    echo json_encode([
        'success' => false,
        'message' => $message,
        'http_code' => $httpCode
    ]);
    exit;
}

$customer = $data['data'] ?? [];
$addressDetails = $customer['address_details'] ?? [];

echo json_encode([
    'success' => true,
    'credits_remaining' => $data['credits_remaining'] ?? null,
    'data' => [
        'gstin' => $customer['gstin'] ?? $gstin,
        'legal_name' => $customer['legal_name'] ?? '',
        'trade_name' => $customer['trade_name'] ?? '',
        'status' => $customer['status'] ?? '',
        'taxpayer_type' => $customer['taxpayer_type'] ?? '',
        'registration_date' => $customer['registration_date'] ?? '',
        'address' => $customer['address'] ?? '',
        'city' => $customer['city'] ?? ($addressDetails['city'] ?? ''),
        'state' => $addressDetails['state'] ?? '',
        'pincode' => $customer['pincode'] ?? ($addressDetails['pincode'] ?? '')
    ]
]);
