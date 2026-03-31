<?php

declare(strict_types=1);
require_once BASE_PATH . '/bootstrap/auth.php';

require_once BASE_PATH . '/app/Support/functions.php';

// Verificar si el usuario ha iniciado sesión


use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/html; charset=utf-8');

$storageLogsDir = realpath(BASE_PATH . '/storage/logs');
if ($storageLogsDir === false) {
    $storageLogsDir = BASE_PATH . '/storage/logs';
}
if (!is_dir($storageLogsDir)) {
    @mkdir($storageLogsDir, 0775, true);
}
$mailchimpLogPath = $storageLogsDir . DIRECTORY_SEPARATOR . 'mailchimp_log.txt';
$altaClientesErrorLogPath = $storageLogsDir . DIRECTORY_SEPARATOR . 'altaClientes_error_log.txt';

// Si viene una petición POST, procesar datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar campos básicos (simple)
    $empresa = trim($_POST['empresa'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion_comercial = trim($_POST['direccion_comercial'] ?? '');
    $direccion_logistica = trim($_POST['direccion_logistica'] ?? '');
    $poblacion = trim($_POST['poblacion'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $cp = trim($_POST['cp'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $iva = trim($_POST['iva'] ?? '');
    $forma_pago = trim($_POST['forma_pago'] ?? '');
    $banco = trim($_POST['banco'] ?? '');
    $cuenta = trim($_POST['cuenta'] ?? '');
    $accesoWeb = isset($_POST['accesoWeb']) ? 'SI' : 'NO';
    $acepta_comunicaciones = isset($_POST['acepta_comunicaciones']) ? 'SI' : 'NO';
    $fecha_consentimiento = isset($_POST['acepta_comunicaciones']) ? date('Y-m-d H:i:s') : '';
    $comentarios = trim($_POST['comentarios'] ?? '');

    $campos = [
        'Cod. Comisionista' => $_SESSION['codigo'] ?? '',
        'Empresa' => $empresa,
        'NIF' => $nif,
        'Email' => $email,
        'Teléfono' => $telefono,
        'Dirección Comercial' => $direccion_comercial,
        'Dirección Logística' => $direccion_logistica,
        'Población' => $poblacion,
        'Provincia' => $provincia,
        'CP' => $cp,
        'Web' => $web,
        'IVA' => $iva,
        'Forma de Pago' => $forma_pago,
        'Banco' => $banco,
        'Cuenta' => $cuenta,
        'Acceso Web' => $accesoWeb,
        'Acepta comunicaciones' => $acepta_comunicaciones,
        'Fecha consentimiento' => $fecha_consentimiento,
        'Comentarios' => $comentarios,
        'Fecha de Alta' => date('Y-m-d H:i:s'), //  añadimos la fecha de alta
    ];

    $mensaje = " Datos recibidos del formulario de alta:\n\n";
    foreach ($campos as $clave => $valor) {
        $mensaje .= "$clave: $valor\n";
    }

    require_once BASE_PATH . '/app/Modules/AltaClientes/mail_config.php';

    try {
        $mail = configurarMailer();
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true); // activa HTML

        $mail->addAddress('clientes@comaferr.es', 'Juan Amaya');
        $mail->addBCC('amolero@comaferr.es', 'Copia Oculta');

        $mail->Subject = '=?UTF-8?B?' . base64_encode(' Alta de nuevo cliente desde la web') . '?=';
        $mail->Body = nl2br(htmlspecialchars($mensaje));
        $mail->send();

        // Si el envío ha ido bien, intentamos suscribir en Mailchimp si marcó la casilla
        if (isset($_POST['acepta_comunicaciones']) && $_POST['acepta_comunicaciones']) {
            // validar email
            $emailToSubscribe = filter_var($email, FILTER_VALIDATE_EMAIL);
            if ($emailToSubscribe) {
                // incluir la librería SDK wrapper (asegúrate de la ruta)
                // El archivo mailchimp_sdk_subscribe.php debe existir y estar configurado
                try {
                    require_once __DIR__ . '/mailchimp_sdk_subscribe.php';

                    // Construir merge fields: FNAME (empresa) y NIF (si existe)
                    $merge = ['FNAME' => $empresa];
                    if ($nif !== '') {
                        $merge['NIF'] = $nif;
                    }

                    // Llamada al wrapper (retorna array con success/response/error)
                    $doubleOptIn = strtolower((string)(appConfigValue('MAILCHIMP_DOUBLE_OPTIN', 'false') ?? 'false')) === 'true';
                    $res = subscribeWithSdk($emailToSubscribe, $merge, $doubleOptIn);

                    // Log para depuración
                    file_put_contents($mailchimpLogPath, date('c') . " - Mailchimp SDK: " . json_encode($res) . PHP_EOL, FILE_APPEND);
                } catch (\Throwable $ex) {
                    // Si falla la inclusión o la llamada, lo logueamos
                    file_put_contents($mailchimpLogPath, date('c') . " - Mailchimp error (incluir/ejecutar): " . $ex->getMessage() . PHP_EOL, FILE_APPEND);
                }
            } else {
                file_put_contents($mailchimpLogPath, date('c') . " - Mailchimp: email inválido: " . ($email ?? '') . PHP_EOL, FILE_APPEND);
            }
        }

        echo "<script>alert('Formulario enviado correctamente');</script>";
    } catch (\Exception $e) {
        $errorMensaje = date('Y-m-d H:i:s') . " - Error PHPMailer: " . ($mail->ErrorInfo ?? $e->getMessage()) . "\n";
        file_put_contents($altaClientesErrorLogPath, $errorMensaje, FILE_APPEND);
        echo "<script>alert('Error al enviar el correo. Revisa error_log.txt');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Cliente</title>
    <link rel="stylesheet" href="formulario.css" />
    <style>
      @media (max-width: 1024px) {
        body.alta-cliente-page {
          padding-top: calc(50px + env(safe-area-inset-top)) !important;
        }
      }

      .mb-3 {
        margin-bottom: 1rem;
      }
    </style>
</head>

<body class="alta-cliente-page">
    <?php
    $pageTitle = "Formulario Alta de Cliente"; // necesario para que header.php tenga título
    include BASE_PATH . '/resources/views/layouts/header.php';
    ?>
    <div class="container">
        <!-- <div class="form-header">
            <img src="../imagenes/logo nombre derecha.png" alt="Logo Comaferr" class="logo">
            <h1>Formulario de Alta de Cliente</h1>
        </div> -->

        <form method="post">

            <!-- Datos básicos -->
            <div class="form-row">
                <div class="mb-3 half">
                    <label>Nombre de la Empresa o Persona física: *</label>
                    <input type="text" name="empresa" required>
                </div>
                <div class="mb-3 half">
                    <label>NIF - CIF: *</label>
                    <input type="text" name="nif" required>
                </div>
            </div>

            <div class="form-row">
                <div class="mb-3 half">
                    <label>Correo electrónico: *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="mb-3 half">
                    <label>Teléfono: *</label>
                    <input type="text" name="telefono" required>
                </div>
            </div>

            <div class="mb-3">
                <label>Dirección Comercial: *</label>
                <input type="text" name="direccion_comercial" required>
            </div>

            <div class="mb-3">
                <label>Dirección Logística:</label>
                <input type="text" name="direccion_logistica">
            </div>

            <!-- Localización -->
            <div class="form-row">
                <div class="mb-3 half">
                    <label>Población: *</label>
                    <input type="text" name="poblacion" required>
                </div>
                <div class="mb-3 half">
                    <label>Provincia: *</label>
                    <input type="text" name="provincia" required>
                </div>
            </div>

            <div class="form-row">
                <div class="mb-3 half">
                    <label>Código Postal: *</label>
                    <input type="text" name="cp" required>
                </div>
                <div class="mb-3 half">
                    <label>Web:</label>
                    <input type="text" name="web">
                </div>
            </div>

            <!-- Datos fiscales y bancarios -->
            <div class="form-row">
                <div class="mb-3 half">
                    <label>Régimen de IVA: *</label>
                    <select name="iva" required>
                        <option value="">Seleccione una opción</option>
                        <option value="Exento">Exento</option>
                        <option value="General">General</option>
                        <option value="Recargo">Recargo</option>
                        <option value="Especial">Especial</option>
                    </select>
                </div>
                <div class="mb-3 half">
                    <label>Forma de Pago: *</label>
                    <input type="text" name="forma_pago" maxlength="30" required>
                </div>
            </div>

            <div class="form-row">
                <div class="mb-3 half">
                    <label>Banco:</label>
                    <input type="text" name="banco">
                </div>
                <div class="mb-3 half">
                    <label>Número de Cuenta:</label>
                    <input type="text" name="cuenta">
                </div>
            </div>

            <!-- Otros -->
            <div class="mb-3">
                <label>Comentarios:</label>
                <textarea name="comentarios"></textarea>
            </div>

            <!-- Protección de datos -->
            <div class="mb-3 proteccionDatos">
                <strong>ï¸ Protección de datos:</strong>
                <p>
                    En cumplimiento de la normativa vigente en materia de Protección de Datos de Carácter Personal, le informamos que los datos personales que nos proporciona serán tratados por <strong>COMERCIAL DE MAQUINARIA Y FERRETERIA, S.A.</strong> con la finalidad de asegurar la correcta gestión de los servicios y/o productos solicitados y las tareas administrativas derivadas de la misma. Y en su caso, para el envío de información sobre productos y/o servicios de su interés.
                </p>
                <p>
                    Los datos personales proporcionados se conservarán mientras se mantenga la relación comercial y/o prestación de servicios, no se solicite su supresión por el interesado o durante el plazo que fije la normativa aplicable en la materia. La legitimación para el tratamiento de datos se basa en la ejecución de un contrato, en la obligación legal del responsable, y en su caso en el consentimiento de la persona interesada. No se cederán datos salvo obligación legal.
                </p>
                <p>
                    La persona interesada puede ejercer los derechos de acceso a sus datos personales, rectificación, supresión, limitación de tratamiento, oposición, portabilidad, derecho a no ser objeto de decisiones individuales automatizadas, así como la revocación del consentimiento prestado. Para ello podrá dirigir un escrito a <strong>Ctra. de Cabra, Km 0 - 14900 Lucena (Córdoba)</strong>. E-mail: <a href="mailto:clientes@comaferr.es">clientes@comaferr.es</a>, adjuntando documento que acredite su identidad. Además, puede dirigirse a la Autoridad de Control en materia de Protección de Datos competente para obtener información adicional o presentar una reclamación.
                </p>
            </div>


            <!-- Protección de datos -->
            <div class="mb-3">
                <label>
                    <input type="checkbox" name="accesoWeb">
                    Quiero solicitar acceso a la web.
                </label>
                <label>
                    <input type="checkbox" name="acepta_comunicaciones" value="1" checked>
                    Deseo recibir comunicaciones comerciales por correo electrónico.
                </label>
                <label>
                    <input type="checkbox" name="proteccion_datos" required>
                    He leído y acepto la política de protección de datos.*
                </label>
            </div>

            <button type="submit">Enviar</button>
        </form>
    </div>
</body>


</html>

