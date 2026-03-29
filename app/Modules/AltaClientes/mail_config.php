<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once BASE_PATH . '/config/app_config.php';
require_once BASE_PATH . '/vendor/autoload.php';

function configurarMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    // Variables esperadas:
    // - APP_SMTP_HOST
    // - APP_SMTP_USER
    // - APP_SMTP_PASSWORD
    // - APP_SMTP_PORT
    // - APP_SMTP_SECURE
    // - APP_MAIL_FROM
    // - APP_MAIL_FROM_NAME
    $smtpHost = appConfigValue('APP_SMTP_HOST');
    $smtpUser = appConfigValue('APP_SMTP_USER');
    $smtpPassword = appConfigValue('APP_SMTP_PASSWORD');
    $smtpPort = (int)(appConfigValue('APP_SMTP_PORT', '465') ?? '465');
    $smtpSecure = appConfigValue('APP_SMTP_SECURE', PHPMailer::ENCRYPTION_SMTPS);
    $mailFrom = appConfigValue('APP_MAIL_FROM', $smtpUser) ?? $smtpUser;
    $mailFromName = appConfigValue('APP_MAIL_FROM_NAME', 'Formulario Alta Cliente') ?? 'Formulario Alta Cliente';

    if ($smtpHost === null || $smtpUser === null || $smtpPassword === null) {
        throw new RuntimeException('SMTP no configurado. Define APP_SMTP_HOST, APP_SMTP_USER y APP_SMTP_PASSWORD en entorno o config externa.');
    }

    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPassword ?? '';
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port       = $smtpPort > 0 ? $smtpPort : 465;

    // $mail->addBCC('amolero@comaferr.es');
    $mail->SMTPDebug = 0; // Muestra información detallada de la conexión SMTP
    // $mail->Debugoutput = 'html'; // Para que sea legible en navegador

    $mail->setFrom($mailFrom, $mailFromName);

    return $mail;
}
