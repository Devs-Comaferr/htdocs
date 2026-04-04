<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once BASE_PATH . '/config/app_config.php';
require_once BASE_PATH . '/vendor/autoload.php';

function altaClienteMailConfigValue(string $key, ?string $default = null): ?string
{
    return appConfigValue($key, $default);
}

function altaClienteMailRequiredConfig(): array
{
    return [
        'APP_SMTP_HOST' => altaClienteMailConfigValue('APP_SMTP_HOST'),
        'APP_SMTP_USER' => altaClienteMailConfigValue('APP_SMTP_USER'),
        'APP_SMTP_PASSWORD' => altaClienteMailConfigValue('APP_SMTP_PASSWORD'),
    ];
}

function altaClienteMailMissingConfigKeys(array $config): array
{
    $missing = [];
    foreach ($config as $key => $value) {
        if ($value === null || trim($value) === '') {
            $missing[] = $key;
        }
    }

    return $missing;
}

function altaClienteMailNormalizeSecure(?string $value): string
{
    $value = strtolower(trim((string)$value));
    if ($value === '' || $value === 'ssl') {
        return PHPMailer::ENCRYPTION_SMTPS;
    }

    if ($value === 'tls') {
        return PHPMailer::ENCRYPTION_STARTTLS;
    }

    if ($value === 'none') {
        return '';
    }

    return PHPMailer::ENCRYPTION_SMTPS;
}

function configurarMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $requiredConfig = altaClienteMailRequiredConfig();
    $missingKeys = altaClienteMailMissingConfigKeys($requiredConfig);
    if ($missingKeys !== []) {
        throw new RuntimeException(
            'SMTP no configurado. Faltan: ' . implode(', ', $missingKeys) .
            '. Define esas claves en variables de entorno o en config/runtime_secrets.local.php.'
        );
    }

    $smtpHost = (string)$requiredConfig['APP_SMTP_HOST'];
    $smtpUser = (string)$requiredConfig['APP_SMTP_USER'];
    $smtpPassword = (string)$requiredConfig['APP_SMTP_PASSWORD'];
    $smtpPort = (int)(altaClienteMailConfigValue('APP_SMTP_PORT', '465') ?? '465');
    $smtpSecure = altaClienteMailNormalizeSecure(
        altaClienteMailConfigValue('APP_SMTP_SECURE', PHPMailer::ENCRYPTION_SMTPS)
    );
    $mailFrom = altaClienteMailConfigValue('APP_MAIL_FROM', $smtpUser) ?? $smtpUser;
    $mailFromName = altaClienteMailConfigValue('APP_MAIL_FROM_NAME', 'Formulario Alta Cliente') ?? 'Formulario Alta Cliente';

    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort > 0 ? $smtpPort : 465;
    $mail->SMTPSecure = $smtpSecure;
    $mail->SMTPDebug = 0;
    $mail->setFrom($mailFrom, $mailFromName);

    return $mail;
}
