<?php

return [
    // Copia este archivo a runtime_secrets.local.php o define las variables de entorno.
    'DB_HOST' => 'your-db-host',
    'DB_NAME' => 'your-db-name',
    'DB_USER' => 'your-db-user',
    'DB_PASS' => 'your-db-password',
    // Opcional si usas un DSN ODBC ya configurado en el servidor.
    'DB_DSN' => 'your-odbc-dsn',

    'APP_SMTP_HOST' => 'smtp.example.com',
    'APP_SMTP_USER' => 'user@example.com',
    'APP_SMTP_PASSWORD' => 'your-smtp-password',
    'APP_SMTP_PORT' => '465',
    'APP_SMTP_SECURE' => 'ssl',
    'APP_MAIL_FROM' => 'noreply@example.com',
    'APP_MAIL_FROM_NAME' => 'Application Mailer',
];
