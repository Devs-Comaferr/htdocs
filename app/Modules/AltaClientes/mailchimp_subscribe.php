<?php
// mailchimp_subscribe.php

/**
 * Suscribe (o actualiza) un email en Mailchimp (upsert).
 *
 * @param string $email
 * @param array|string $mergeFields  Puede ser string (nombre) o array asociativo ['FNAME'=>'...','NIF'=>'...']
 * @param string $apiKey
 * @param string $listId
 * @param bool $doubleOptIn
 * @return array
 */
function subscribeMailchimp(string $email, $mergeFields, string $apiKey, string $listId, bool $doubleOptIn = false): array
{
    $email = trim(strtolower($email));
    $subscriberHash = md5($email);

    if (strpos($apiKey, '-') === false) {
        return ['success' => false, 'status' => 0, 'body' => '', 'error' => 'API key inválida (sin data center)'];
    }
    $dc = substr(strrchr($apiKey, '-'), 1);
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$listId}/members/{$subscriberHash}";

    $status = $doubleOptIn ? 'pending' : 'subscribed';

    // Normalizar merge fields: si te pasan un string lo convertimos a FNAME
    if (is_string($mergeFields)) {
        $mergeFields = ['FNAME' => $mergeFields];
    } elseif (!is_array($mergeFields)) {
        $mergeFields = [];
    }

    $data = [
        'email_address' => $email,
        'status' => $status,
        'merge_fields' => $mergeFields
    ];

    $json = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // upsert
    curl_setopt($ch, CURLOPT_USERPWD, 'anystring:' . $apiKey);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'status' => 0, 'body' => '', 'error' => $err];
    }

    curl_close($ch);
    $body = json_decode($response, true);

    $success = in_array($httpCode, [200, 201]);

    return ['success' => $success, 'status' => $httpCode, 'body' => $body ?? $response, 'error' => $success ? null : ($body['detail'] ?? 'Error desconocido')];
}
