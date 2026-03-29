<?php

/**
 * altaClientes/mailchimp_sdk_subscribe.php
 *
 * Wrapper minimalista para suscribir (upsert) contactos en Mailchimp usando
 * el SDK oficial mailchimp/marketing. Diseado para ser incluido desde
 * alta_cliente.php.
 *
 * Requisitos:
 *  - composer require mailchimp/marketing
 *  - definir en el entorno MAILCHIMP_API_KEY y MAILCHIMP_LIST_ID
 *
 * Ruta del vendor/autoload.php: ajustar si tu estructura es distinta.
 */

declare(strict_types=1);

// Autoload (ajusta la ruta si tu vendor est en otra carpeta)
require_once BASE_PATH . '/config/app_config.php';
require_once BASE_PATH . '/vendor/autoload.php';

// Leer variables de entorno (usa getenv o sistema que prefieras)
$apiKey = appConfigValue('MAILCHIMP_API_KEY') ?? '';
$listId = appConfigValue('MAILCHIMP_LIST_ID') ?? '';
$doubleOptIn = strtolower((string)(appConfigValue('MAILCHIMP_DOUBLE_OPTIN', 'false') ?? 'false')) === 'true';

// Validaciones bsicas en arranque
if (!$apiKey || !$listId) {
    throw new \RuntimeException("MAILCHIMP_API_KEY y MAILCHIMP_LIST_ID deben estar definidas en el entorno.");
}

// Extraer servidor (data center) desde la API key (ej: us4)
$server = substr(strrchr($apiKey, '-'), 1);
if ($server === false) {
    throw new \RuntimeException("API key inválida. No se ha podido extraer el data center (parte tras '-').");
}

// Inicializar cliente
$mailchimp = new \MailchimpMarketing\ApiClient();
$mailchimp->setConfig([
    'apiKey' => $apiKey,
    'server' => $server
]);

/**
 * Upsert (crear o actualizar) un suscriptor en la lista de Mailchimp usando el SDK.
 *
 * @param string $email         Email del suscriptor
 * @param array|string $mergeFields  Merge fields (ej: ['FNAME'=>'Empresa','NIF'=>'B123...']) o string para FNAME
 * @param bool $doubleOptIn     Si true, status = 'pending' (doble opt-in); si false, 'subscribed'
 * @return array  ['success'=>bool,'response'=>mixed|null,'error'=>string|null,'status'=>int|null]
 */
function subscribeWithSdk(string $email, $mergeFields = [], bool $doubleOptIn = false): array
{
    // usar globals inicializados arriba
    global $mailchimp, $listId;

    $email = trim(strtolower($email));
    if ($email === '') {
        return ['success' => false, 'response' => null, 'error' => 'Email vaco', 'status' => null];
    }

    // validar formato bsico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'response' => null, 'error' => 'Email no vlido', 'status' => null];
    }

    $subscriberHash = md5($email);
    $status = $doubleOptIn ? 'pending' : 'subscribed';

    // Normalizar merge fields: si es string lo convertimos a FNAME
    if (is_string($mergeFields)) {
        $mergeFields = ['FNAME' => $mergeFields];
    } elseif (!is_array($mergeFields)) {
        $mergeFields = [];
    }

    $body = [
        'email_address' => $email,
        'status' => $status,
        'merge_fields' => $mergeFields
    ];

    try {
        /** @var \MailchimpMarketing\Api\ListsApi $lists */
        $lists = $mailchimp->lists; // anotación para Intelephense
        $response = $lists->setListMember($listId, $subscriberHash, $body);

        return [
            'success' => true,
            'response' => $response,
            'error' => null,
            'status' => 200
        ];
    } catch (\MailchimpMarketing\ApiException $e) {
        // ApiException del SDK: contiene cuerpo de respuesta con detalles
        $errBody = $e->getResponseBody() ?: $e->getMessage();
        return [
            'success' => false,
            'response' => null,
            'error' => is_array($errBody) ? json_encode($errBody) : (string)$errBody,
            'status' => $e->getCode() ?: 0
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'response' => null,
            'error' => $e->getMessage(),
            'status' => $e->getCode() ?: 0
        ];
    }
}
