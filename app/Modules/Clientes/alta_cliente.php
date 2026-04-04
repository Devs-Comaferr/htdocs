<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/auth.php';
require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';
require_once BASE_PATH . '/app/Modules/Clientes/services/alta_cliente_service.php';

header('Content-Type: text/html; charset=utf-8');

$pageTitle = 'Solicitud de alta de cliente';
$ui_version = 'bs5';
$ui_requires_jquery = false;

$formData = [
    'empresa' => '',
    'razon_social' => '',
    'nif' => '',
    'tipo_empresa' => '',
    'tipo_cliente' => '',
    'tarifa' => '',
    'cod_vendedor_asociado' => '',
    'email' => '',
    'telefono' => '',
    'direccion_comercial' => '',
    'direccion_logistica' => '',
    'poblacion' => '',
    'provincia' => '',
    'cp' => '',
    'web' => '',
    'iva' => '',
    'forma_pago' => '',
    'imprime_factura' => 'No',
    'email_factura' => '',
    'imprime_albaran' => 'Si',
    'email_albaran' => '',
    'imprime_pedido' => 'No',
    'email_pedido' => '',
    'imprime_presupuesto' => 'No',
    'email_presupuesto' => '',
    'banco' => '',
    'cuenta' => '',
    'comentarios' => '',
    'accesoWeb' => false,
    'acepta_comunicaciones' => true,
    'proteccion_datos' => false,
    'contactos' => [altaClienteContactoFilaVacia()],
];

$flashMessage = null;
$flashType = 'ok';
$clienteExistente = null;
$documentoValidacion = null;
$connTiposCliente = db();
$tiposCliente = altaClienteObtenerTiposCliente($connTiposCliente);
$tarifasVenta = altaClienteObtenerTarifas($connTiposCliente);
$formasLiquidacion = altaClienteObtenerFormasLiquidacionPorTipo($connTiposCliente, (string)($formData['tipo_cliente'] ?? ''));
$codigoSesionVendedor = trim((string)($_SESSION['codigo'] ?? ''));
$vendedorSesion = altaClienteObtenerVendedorPorCodigo($connTiposCliente, $codigoSesionVendedor);
$vendedoresDisponibles = $vendedorSesion ? [] : altaClienteObtenerVendedores($connTiposCliente);

if ($vendedorSesion) {
    $formData['cod_vendedor_asociado'] = (string)($vendedorSesion['cod_vendedor'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfValidateRequest('clientes.alta_cliente');
    $formData = array_merge($formData, altaClienteRequestData());
    if ($vendedorSesion) {
        $formData['cod_vendedor_asociado'] = (string)($vendedorSesion['cod_vendedor'] ?? '');
        $formData['vendedor_asociado_nombre'] = (string)($vendedorSesion['nombre'] ?? '');
    } else {
        foreach ($vendedoresDisponibles as $vendedorDisponible) {
            if ((string)$vendedorDisponible['value'] === (string)$formData['cod_vendedor_asociado']) {
                $formData['vendedor_asociado_nombre'] = (string)$vendedorDisponible['label'];
                break;
            }
        }
    }
    $documentoValidacion = altaClienteValidarDocumento((string)$formData['nif']);
    $formData['tipo_empresa'] = altaClienteTipoEmpresaDesdeTipoDocumento($documentoValidacion['type'] ?? null);

    if (trim((string)$formData['cod_vendedor_asociado']) === '') {
        $flashType = 'error';
        $flashMessage = 'Debes indicar el vendedor asociado.';
    } elseif (empty($documentoValidacion['valid'])) {
        $flashType = 'error';
        $flashMessage = (string)($documentoValidacion['message'] ?? 'El NIF - CIF no es válido.');
    } else {
        $formData['nif'] = (string)($documentoValidacion['normalized'] ?? $formData['nif']);
        $validacionDocumentos = altaClienteValidarPreferenciasDocumentos($formData);
        if (empty($validacionDocumentos['ok'])) {
            $flashType = 'error';
            $flashMessage = (string)($validacionDocumentos['message'] ?? 'Revisa la configuración de envío de documentos.');
        } else {
            $validacionContactos = altaClienteValidarContactos((array)($formData['contactos'] ?? []));
            if (empty($validacionContactos['ok'])) {
                $flashType = 'error';
                $flashMessage = (string)($validacionContactos['message'] ?? 'Revisa los datos de contacto.');
            } else {
                $conn = db();
                $clienteExistente = altaClienteBuscarClienteExistentePorNif($conn, (string)$formData['nif']);

                if ($clienteExistente) {
                    $flashType = 'error';
                    $flashMessage = 'Ya existe un cliente con ese NIF - CIF. Revisa la ficha antes de enviar una nueva solicitud.';
                } else {
                    $resultado = altaClienteProcessSubmission($formData);
                    $flashMessage = (string)($resultado['message'] ?? '');
                    $flashType = !empty($resultado['ok']) ? 'ok' : 'error';

                    if (!empty($resultado['ok'])) {
                        $formData = [
                            'empresa' => '',
                            'razon_social' => '',
                            'nif' => '',
                            'tipo_empresa' => '',
                            'tipo_cliente' => '',
                            'tarifa' => '',
                            'cod_vendedor_asociado' => $vendedorSesion ? (string)($vendedorSesion['cod_vendedor'] ?? '') : '',
                            'email' => '',
                            'telefono' => '',
                            'direccion_comercial' => '',
                            'direccion_logistica' => '',
                            'poblacion' => '',
                            'provincia' => '',
                            'cp' => '',
                            'web' => '',
                            'iva' => '',
                            'forma_pago' => '',
                            'imprime_factura' => 'No',
                            'email_factura' => '',
                            'imprime_albaran' => 'Si',
                            'email_albaran' => '',
                            'imprime_pedido' => 'No',
                            'email_pedido' => '',
                            'imprime_presupuesto' => 'No',
                            'email_presupuesto' => '',
                            'banco' => '',
                            'cuenta' => '',
                            'comentarios' => '',
                            'accesoWeb' => false,
                            'acepta_comunicaciones' => true,
                            'proteccion_datos' => false,
                            'contactos' => [altaClienteContactoFilaVacia()],
                        ];
                        $documentoValidacion = null;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de alta de cliente</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/alta-cliente-form.css">
</head>
<body class="alta-cliente-page">
    <?php include BASE_PATH . '/resources/views/layouts/header.php'; ?>
    <main class="alta-cliente-wrap">
        <section class="alta-cliente-card">
            <div class="alta-cliente-head">
                <div>
                    <h1>Solicitud de alta de cliente</h1>
                    <p>Formulario interno para que el comercial envíe los datos de captación por email.</p>
                </div>
                <div class="alta-head-badges">
                    <span class="alta-badge"><i class="fa fa-envelope"></i> Envío interno por correo</span>
                    <span class="alta-badge alta-badge-soft"><i class="fa fa-bullhorn"></i> Marketing solo si se marca</span>
                </div>
            </div>

            <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
                <div class="alta-flash alta-flash-<?= $flashType === 'error' ? 'error' : 'ok' ?>">
                    <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" class="alta-cliente-form">
                <?= csrfInput() ?>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <div>
                            <h2>Datos del cliente</h2>
                            <p>Identificación y datos de contacto del nuevo cliente.</p>
                        </div>
                        <div class="alta-section-context">
                            <span class="alta-section-context-label">Vendedor <span>*</span></span>
                            <?php if ($vendedorSesion): ?>
                                <strong><?= htmlspecialchars(function_exists('toUTF8') ? toUTF8((string)($vendedorSesion['nombre'] ?? '')) : (string)($vendedorSesion['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <input type="hidden" name="cod_vendedor_asociado" value="<?= htmlspecialchars((string)($vendedorSesion['cod_vendedor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <select id="cod_vendedor_asociado" name="cod_vendedor_asociado" required>
                                    <option value="">Seleccione un vendedor</option>
                                    <?php foreach ($vendedoresDisponibles as $vendedorDisponible): ?>
                                        <option value="<?= htmlspecialchars((string)$vendedorDisponible['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string)$formData['cod_vendedor_asociado'] === (string)$vendedorDisponible['value'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$vendedorDisponible['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 full">
                            <label for="empresa">Nombre comercial <span>*</span></label>
                            <input id="empresa" type="text" name="empresa" required value="<?= htmlspecialchars((string)$formData['empresa'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 full">
                            <label for="razon_social">Razón social <span>*</span></label>
                            <input id="razon_social" type="text" name="razon_social" required value="<?= htmlspecialchars((string)$formData['razon_social'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 half mb-3-doc">
                            <label for="nif">NIF - CIF <span>*</span></label>
                            <input id="nif" type="text" name="nif" maxlength="15" required value="<?= htmlspecialchars((string)$formData['nif'], ENT_QUOTES, 'UTF-8') ?>">
                            <div id="nif-validation-warning" class="field-warning field-warning-error<?= (!is_array($documentoValidacion) || !empty($documentoValidacion['valid'])) ? ' is-hidden' : '' ?>">
                                <?php if (is_array($documentoValidacion) && empty($documentoValidacion['valid'])): ?>
                                    <?= htmlspecialchars((string)($documentoValidacion['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div id="nif-duplicate-warning" class="field-warning<?= $clienteExistente ? '' : ' is-hidden' ?>">
                                <?php if ($clienteExistente): ?>
                                    Ya existe: <strong><?= htmlspecialchars((string)($clienteExistente['nombre_comercial'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php
                                    $puedeVerFichaExistente = (
                                        (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')
                                        || (
                                            isset($_SESSION['codigo'], $clienteExistente['cod_vendedor'])
                                            && (string)$_SESSION['codigo'] !== ''
                                            && (string)$_SESSION['codigo'] === (string)$clienteExistente['cod_vendedor']
                                        )
                                    );
                                    ?>
                                    <?php if ($puedeVerFichaExistente && !empty($clienteExistente['cod_cliente'])): ?>
                                        <a href="<?= BASE_URL ?>/cliente_detalles.php?cod_cliente=<?= urlencode((string)$clienteExistente['cod_cliente']) ?>" target="_blank" rel="noopener">Ver ficha</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3 half mb-3-tipo-empresa">
                            <label for="tipo_empresa">Tipo de empresa</label>
                            <input id="tipo_empresa" type="text" name="tipo_empresa" readonly tabindex="-1" value="<?= htmlspecialchars((string)$formData['tipo_empresa'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3 half mb-3-tipo-cliente">
                            <label for="tipo_cliente">Tipo de cliente <span>*</span></label>
                            <select id="tipo_cliente" name="tipo_cliente" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach ($tiposCliente as $tipo): ?>
                                    <option value="<?= htmlspecialchars((string)$tipo['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string)$formData['tipo_cliente'] === (string)$tipo['value'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$tipo['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 half mb-3-telefono-empresa">
                            <label for="telefono">Teléfono <span>*</span></label>
                            <input id="telefono" type="text" name="telefono" required value="<?= htmlspecialchars((string)$formData['telefono'], ENT_QUOTES, 'UTF-8') ?>">
                            <div id="telefono-warning" class="field-warning is-hidden"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 half">
                            <label for="email">Correo electrónico <span>*</span></label>
                            <input id="email" type="email" name="email" required value="<?= htmlspecialchars((string)$formData['email'], ENT_QUOTES, 'UTF-8') ?>">
                            <div id="email-warning" class="field-warning is-hidden"></div>
                        </div>
                        <div class="mb-3 half">
                            <label for="web">Web</label>
                            <input id="web" type="text" name="web" value="<?= htmlspecialchars((string)$formData['web'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <h2>Contactos</h2>
                        <p>Añade las personas de contacto del cliente. Puedes dejarlo vacio o registrar tantas como necesites.</p>
                    </div>

                    <div class="contactos-table-wrap">
                        <table class="contactos-table" id="contactos_table">
                            <colgroup>
                                <col class="contactos-col-action">
                                <col class="contactos-col-nombre">
                                <col class="contactos-col-departamento">
                                <col class="contactos-col-cargo">
                                <col class="contactos-col-telefono">
                                <col class="contactos-col-movil">
                                <col class="contactos-col-email">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="contactos-col-action"></th>
                                    <th>Nombre</th>
                                    <th>Departamento</th>
                                    <th>Cargo</th>
                                    <th>Teléfono</th>
                                    <th>Móvil</th>
                                    <th>E-mail</th>
                                </tr>
                            </thead>
                            <tbody id="contactos_table_body">
                                <?php foreach (($formData['contactos'] ?? [altaClienteContactoFilaVacia()]) as $contacto): ?>
                                    <tr class="contactos-row">
                                        <td class="contactos-col-action">
                                            <button type="button" class="contactos-remove" aria-label="Eliminar contacto">×</button>
                                        </td>
                                        <td><input type="text" name="contactos_nombre[]" value="<?= htmlspecialchars((string)($contacto['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input type="text" name="contactos_departamento[]" value="<?= htmlspecialchars((string)($contacto['departamento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input type="text" name="contactos_cargo[]" value="<?= htmlspecialchars((string)($contacto['cargo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input type="text" name="contactos_telefono[]" value="<?= htmlspecialchars((string)($contacto['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input type="text" name="contactos_movil[]" value="<?= htmlspecialchars((string)($contacto['movil'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                        <td><input type="email" name="contactos_email[]" value="<?= htmlspecialchars((string)($contacto['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" inputmode="email" autocomplete="email"></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="contactos-add-row">
                                    <td class="contactos-col-action">
                                        <button type="button" class="contactos-add" id="contactos_add_button" aria-label="Añadir contacto">+</button>
                                    </td>
                                    <td colspan="6" class="contactos-add-hint">Añadir otro contacto</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <h2>Direcciones</h2>
                        <p>Ubicación comercial y logística para que administración pueda tramitar el alta.</p>
                    </div>

                    <div class="mb-3">
                        <label for="direccion_comercial">Dirección comercial <span>*</span></label>
                        <input id="direccion_comercial" type="text" name="direccion_comercial" required value="<?= htmlspecialchars((string)$formData['direccion_comercial'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="direccion_logistica">Dirección logística</label>
                        <input id="direccion_logistica" type="text" name="direccion_logistica" value="<?= htmlspecialchars((string)$formData['direccion_logistica'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-row form-row-direccion-secundaria">
                        <div class="mb-3 mb-3-cp">
                            <label for="cp">Código postal <span>*</span></label>
                            <input id="cp" type="text" name="cp" maxlength="5" required value="<?= htmlspecialchars((string)$formData['cp'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3 mb-3-poblacion">
                            <label for="poblacion">Población <span>*</span></label>
                            <input id="poblacion" type="text" name="poblacion" required value="<?= htmlspecialchars((string)$formData['poblacion'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3 mb-3-provincia">
                            <label for="provincia">Provincia <span>*</span></label>
                            <input id="provincia" type="text" name="provincia" required value="<?= htmlspecialchars((string)$formData['provincia'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <h2>Condiciones comerciales</h2>
                        <p>Condiciones básicas para que el equipo interno pueda revisar y tramitar el alta.</p>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 half mb-3-tarifa">
                            <label for="tarifa">Tarifa <span>*</span></label>
                            <select id="tarifa" name="tarifa" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach ($tarifasVenta as $tarifa): ?>
                                    <option value="<?= htmlspecialchars((string)$tarifa['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string)$formData['tarifa'] === (string)$tarifa['value'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$tarifa['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 half mb-3-iva">
                            <label for="iva">Régimen de IVA <span>*</span></label>
                            <select id="iva" name="iva" required>
                                <option value="">Seleccione una opción</option>
                                <option value="Exento" <?= $formData['iva'] === 'Exento' ? 'selected' : '' ?>>Exento</option>
                                <option value="General" <?= $formData['iva'] === 'General' ? 'selected' : '' ?>>General</option>
                                <option value="Recargo" <?= $formData['iva'] === 'Recargo' ? 'selected' : '' ?>>Recargo</option>
                                <option value="Especial" <?= $formData['iva'] === 'Especial' ? 'selected' : '' ?>>Especial</option>
                            </select>
                        </div>
                        <div class="mb-3 half mb-3-forma-pago">
                            <label for="forma_pago">Forma de pago <span>*</span></label>
                            <select id="forma_pago" name="forma_pago" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach ($formasLiquidacion as $forma): ?>
                                    <option value="<?= htmlspecialchars((string)$forma['value'], ENT_QUOTES, 'UTF-8') ?>" <?= (string)$formData['forma_pago'] === (string)$forma['value'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$forma['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="mb-3 half">
                            <label for="banco">Banco</label>
                            <input id="banco" type="text" name="banco" value="<?= htmlspecialchars((string)$formData['banco'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3 half">
                            <label for="cuenta">Número de cuenta</label>
                            <input id="cuenta" type="text" name="cuenta" maxlength="29" value="<?= htmlspecialchars((string)$formData['cuenta'], ENT_QUOTES, 'UTF-8') ?>">
                            <div id="cuenta-warning" class="field-warning field-warning-error is-hidden"></div>
                        </div>
                    </div>

                </div>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <h2>Documentos</h2>
                        <p>Configura cómo quiere recibir el cliente sus documentos habituales.</p>
                    </div>

                    <div class="form-row">
                        <div class="alta-subsection alta-subsection-print">
                            <div class="alta-subsection-head">
                                <h3>Imprimir y/o enviar por email</h3>
                                <p>Define por documento si se imprime en la empresa para su envío físico y si además se envía por email cada vez que se genere.</p>
                            </div>

                            <table class="print-table">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Imprimir y enviar físicamente</th>
                                        <th>Enviar por email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="print-table-doc">
                                            <label for="imprime_factura">Factura</label>
                                        </td>
                                        <td class="print-table-check">
                                            <label class="ios-check" for="imprime_factura">
                                                <span class="print-toggle-label print-toggle-no">No</span>
                                                <input id="imprime_factura" type="checkbox" name="imprime_factura" value="Si" <?= $formData['imprime_factura'] === 'Si' ? 'checked' : '' ?>>
                                                <span class="ios-check-ui"></span>
                                                <span class="print-toggle-label print-toggle-si">Si</span>
                                            </label>
                                        </td>
                                        <td class="print-table-email">
                                            <input id="email_factura" type="text" name="email_factura" data-required-placeholder="Obligatorio si no quiere recibir factura física" placeholder="Obligatorio si no quiere recibir factura física" value="<?= htmlspecialchars((string)$formData['email_factura'], ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="print-table-doc">
                                            <label for="imprime_albaran">Albarán</label>
                                        </td>
                                        <td class="print-table-check">
                                            <label class="ios-check" for="imprime_albaran">
                                                <span class="print-toggle-label print-toggle-no">No</span>
                                                <input id="imprime_albaran" type="checkbox" name="imprime_albaran" value="Si" <?= $formData['imprime_albaran'] === 'Si' ? 'checked' : '' ?>>
                                                <span class="ios-check-ui"></span>
                                                <span class="print-toggle-label print-toggle-si">Si</span>
                                            </label>
                                        </td>
                                        <td class="print-table-email">
                                            <input id="email_albaran" type="text" name="email_albaran" data-required-placeholder="Obligatorio si no quiere recibir albarán físico" placeholder="Obligatorio si no quiere recibir albarán físico" value="<?= htmlspecialchars((string)$formData['email_albaran'], ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="alta-subsection-head alta-subsection-head-secondary">
                                <h3>Copias por email</h3>
                                <p>Use estos correos si el cliente quiere recibir siempre una copia del pedido o del presupuesto.</p>
                            </div>

                            <table class="print-table print-table-email-only">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Email de envío</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="print-table-doc">
                                            <label for="email_pedido">Pedido</label>
                                        </td>
                                        <td class="print-table-email">
                                            <input id="email_pedido" type="text" name="email_pedido" value="<?= htmlspecialchars((string)$formData['email_pedido'], ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="print-table-doc">
                                            <label for="email_presupuesto">Presupuesto</label>
                                        </td>
                                        <td class="print-table-email">
                                            <input id="email_presupuesto" type="text" name="email_presupuesto" value="<?= htmlspecialchars((string)$formData['email_presupuesto'], ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="alta-section">
                    <div class="alta-section-head">
                        <h2>Comentarios</h2>
                        <p>Información adicional que pueda ayudar a revisar y tramitar la solicitud.</p>
                    </div>

                    <div class="mb-3">
                        <label for="comentarios">Comentarios</label>
                        <textarea id="comentarios" name="comentarios" placeholder="Datos adicionales que puedan ayudar a tramitar la solicitud."><?= htmlspecialchars((string)$formData['comentarios'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <div class="alta-section alta-section-soft">
                    <div class="alta-section-head">
                        <h2>Opciones y consentimiento</h2>
                        <p>El correo se envía siempre. El envío a marketing solo se activa si se marca expresamente esa casilla.</p>
                    </div>

                    <div class="proteccionDatos">
                        <strong>Cláusula de Protección de Datos para Alta de Cliente</strong>
                        <p>En cumplimiento de lo establecido en el RGPD (UE) 2016/679 y la LOPDGDD 3/2018, le informamos de lo siguiente:</p>
                        <p><strong>Responsable del tratamiento:</strong> Comercial de Maquinaria y Ferretería, S.A., con CIF A14226989, domicilio en Ctra. Cabra, Km. 0, 14900, Lucena (Córdoba), y email de contacto <a href="mailto:clientes@comaferr.es">clientes@comaferr.es</a>.</p>
                        <p><strong>Finalidad:</strong> Gestionar la solicitud de alta de cliente, la futura relación comercial, la prestación de servicios, la facturación y, en su caso, el envío de información sobre productos y servicios.</p>
                        <p><strong>Legitimación:</strong> La base legal para el tratamiento de sus datos es la aplicación de medidas precontractuales, la ejecución de la relación comercial y, en su caso, el consentimiento expreso prestado para comunicaciones comerciales.</p>
                        <p><strong>Conservación:</strong> Los datos se conservarán durante el tiempo necesario para atender esta solicitud, durante la vigencia de la relación contractual si llega a formalizarse, y durante los plazos legales exigibles para atender posibles responsabilidades.</p>
                        <p><strong>Destinatarios:</strong> Los datos no se cederán a terceros salvo obligación legal o cuando resulte necesario para la correcta prestación del servicio.</p>
                        <p><strong>Derechos:</strong> Puede ejercer sus derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad enviando un correo a <a href="mailto:clientes@comaferr.es">clientes@comaferr.es</a>. Asimismo, puede presentar una reclamación ante la Agencia Española de Protección de Datos.</p>
                    </div>

                    <div class="mb-3 alta-checkboxes">
                        <label class="alta-check-card">
                            <input type="checkbox" name="accesoWeb" <?= !empty($formData['accesoWeb']) ? 'checked' : '' ?>>
                            <span class="alta-check-ui" aria-hidden="true"></span>
                            <span class="alta-check-copy">Solicitar acceso a la web para el cliente.</span>
                        </label>
                        <label class="alta-check-card">
                            <input type="checkbox" name="acepta_comunicaciones" value="1" <?= !empty($formData['acepta_comunicaciones']) ? 'checked' : '' ?>>
                            <span class="alta-check-ui" aria-hidden="true"></span>
                            <span class="alta-check-copy">El cliente acepta recibir comunicaciones comerciales.</span>
                        </label>
                        <label class="alta-check-card alta-check-card-required">
                            <input type="checkbox" name="proteccion_datos" required <?= !empty($formData['proteccion_datos']) ? 'checked' : '' ?>>
                            <span class="alta-check-ui" aria-hidden="true"></span>
                            <span class="alta-check-copy">El cliente ha sido informado y acepta la política de protección de datos. <strong>*</strong></span>
                        </label>
                    </div>
                </div>

                <div class="alta-submit-bar">
                    <div class="alta-submit-note">Los campos marcados con <strong>*</strong> son obligatorios.</div>
                    <button id="alta-submit-button" type="submit"<?= $clienteExistente ? ' disabled' : '' ?>>Enviar solicitud</button>
                </div>
            </form>
        </section>
    </main>
    <div id="poblacion_modal_backdrop" class="alta-modal-backdrop is-hidden"></div>
    <div id="poblacion_modal" class="alta-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="poblacion_modal_title">
        <div class="alta-modal-card">
            <div class="alta-modal-head">
                <h3 id="poblacion_modal_title">Seleccione poblacion</h3>
                <button id="poblacion_modal_close" type="button" class="alta-modal-close" aria-label="Cerrar">&times;</button>
            </div>
            <div id="poblacion_modal_options" class="alta-modal-options"></div>
        </div>
    </div>
    <script>
    (function () {
        const empresaInput = document.getElementById('empresa');
        const razonSocialInput = document.getElementById('razon_social');
        const nifInput = document.getElementById('nif');
        const tipoClienteInput = document.getElementById('tipo_cliente');
        const formaPagoInput = document.getElementById('forma_pago');
        const cpInput = document.getElementById('cp');
        const poblacionInput = document.getElementById('poblacion');
        const provinciaInput = document.getElementById('provincia');
        const telefonoInput = document.getElementById('telefono');
        const emailInput = document.getElementById('email');
        const cuentaInput = document.getElementById('cuenta');
        const imprimeFacturaInput = document.getElementById('imprime_factura');
        const emailFacturaInput = document.getElementById('email_factura');
        const imprimeAlbaranInput = document.getElementById('imprime_albaran');
        const emailAlbaranInput = document.getElementById('email_albaran');
        const emailPedidoInput = document.getElementById('email_pedido');
        const emailPresupuestoInput = document.getElementById('email_presupuesto');
        const telefonoWarning = document.getElementById('telefono-warning');
        const emailWarning = document.getElementById('email-warning');
        const cuentaWarning = document.getElementById('cuenta-warning');
        const poblacionModal = document.getElementById('poblacion_modal');
        const poblacionModalBackdrop = document.getElementById('poblacion_modal_backdrop');
        const poblacionModalOptions = document.getElementById('poblacion_modal_options');
        const poblacionModalClose = document.getElementById('poblacion_modal_close');
        const tipoEmpresaInput = document.getElementById('tipo_empresa');
        const validationWarning = document.getElementById('nif-validation-warning');
        const warning = document.getElementById('nif-duplicate-warning');
        const submitButton = document.getElementById('alta-submit-button');
        const contactosTableBody = document.getElementById('contactos_table_body');
        const contactosAddButton = document.getElementById('contactos_add_button');
        if (!empresaInput || !razonSocialInput || !nifInput || !tipoClienteInput || !formaPagoInput || !cpInput || !poblacionInput || !provinciaInput || !telefonoInput || !emailInput || !cuentaInput || !imprimeFacturaInput || !emailFacturaInput || !imprimeAlbaranInput || !emailAlbaranInput || !emailPedidoInput || !emailPresupuestoInput || !telefonoWarning || !emailWarning || !cuentaWarning || !poblacionModal || !poblacionModalBackdrop || !poblacionModalOptions || !poblacionModalClose || !tipoEmpresaInput || !validationWarning || !warning || !submitButton || !contactosTableBody || !contactosAddButton) {
            return;
        }

        let currentController = null;
        let currentCpController = null;
        let currentContactoController = null;
        let currentFormasController = null;
        let cpOptionsMap = {};
        let cpResolvedValue = String(cpInput.value || '').trim();
        let pendingPoblacionOptions = [];
        let poblacionPickerActive = false;
        let hasValidationError = !validationWarning.classList.contains('is-hidden');
        let hasDuplicate = !warning.classList.contains('is-hidden');
        let hasCuentaError = !cuentaWarning.classList.contains('is-hidden');
        const contactosTemplate = '' +
            '<tr class="contactos-row">' +
            '<td class="contactos-col-action"><button type="button" class="contactos-remove" aria-label="Eliminar contacto">×</button></td>' +
            '<td><input type="text" name="contactos_nombre[]" value=""></td>' +
            '<td><input type="text" name="contactos_departamento[]" value=""></td>' +
            '<td><input type="text" name="contactos_cargo[]" value=""></td>' +
            '<td><input type="text" name="contactos_telefono[]" value=""></td>' +
            '<td><input type="text" name="contactos_movil[]" value=""></td>' +
            '<td><input type="email" name="contactos_email[]" value="" inputmode="email" autocomplete="email"></td>' +
            '</tr>';

        let empresaAutocompletaRazon = razonSocialInput.value.trim() === '';
        let razonAutocompletaEmpresa = empresaInput.value.trim() === '';

        razonSocialInput.addEventListener('input', function (event) {
            if (event.isTrusted && empresaInput.value.trim() !== '') {
                empresaAutocompletaRazon = false;
            }
            if (razonAutocompletaEmpresa) {
                empresaInput.value = razonSocialInput.value;
            }
        });

        empresaInput.addEventListener('input', function (event) {
            if (event.isTrusted && razonSocialInput.value.trim() !== '') {
                razonAutocompletaEmpresa = false;
            }
            if (empresaAutocompletaRazon) {
                razonSocialInput.value = empresaInput.value;
            }
        });

        razonSocialInput.addEventListener('focus', function () {
            if (empresaInput.value.trim() !== '') {
                empresaAutocompletaRazon = false;
            }
        });

        empresaInput.addEventListener('focus', function () {
            if (razonSocialInput.value.trim() !== '') {
                razonAutocompletaEmpresa = false;
            }
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizarDocumento(value) {
            return String(value || '').toUpperCase().replace(/[-\s]/g, '').trim();
        }

        function letraNif(numero) {
            return 'TRWAGMYFPDXBNJZSQVHLCKE'.charAt(numero % 23);
        }

        function validarDocumento(value) {
            const documento = normalizarDocumento(value);
            if (!documento) {
                return { valid: false, message: 'El NIF - CIF es obligatorio.' };
            }

            if (/^\d{8}[A-Z]$/.test(documento)) {
                const numero = parseInt(documento.slice(0, 8), 10);
                return documento.slice(-1) === letraNif(numero)
                    ? { valid: true, normalized: documento, type: 'NIF' }
                    : { valid: false, message: 'La letra del NIF no es correcta.' };
            }

            if (/^[XYZ]\d{7}[A-Z]$/.test(documento)) {
                const mapa = { X: '0', Y: '1', Z: '2' };
                const numero = parseInt(mapa[documento[0]] + documento.slice(1, 8), 10);
                return documento.slice(-1) === letraNif(numero)
                    ? { valid: true, normalized: documento, type: 'NIE' }
                    : { valid: false, message: 'La letra del NIE no es correcta.' };
            }

            if (/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/.test(documento)) {
                const letraInicial = documento[0];
                const digitos = documento.slice(1, 8);
                const controlActual = documento.slice(-1);
                let sumaPar = 0;
                let sumaImpar = 0;

                for (let i = 0; i < digitos.length; i += 1) {
                    const digito = parseInt(digitos[i], 10);
                    if (i % 2 === 0) {
                        const doble = digito * 2;
                        sumaImpar += Math.floor(doble / 10) + (doble % 10);
                    } else {
                        sumaPar += digito;
                    }
                }

                const controlNumero = (10 - ((sumaPar + sumaImpar) % 10)) % 10;
                const controlLetra = 'JABCDEFGHI'.charAt(controlNumero);
                const soloNumero = ['A', 'B', 'E', 'H'].includes(letraInicial);
                const soloLetra = ['K', 'P', 'Q', 'S', 'N', 'W'].includes(letraInicial);
                const valido = soloNumero
                    ? controlActual === String(controlNumero)
                    : soloLetra
                        ? controlActual === controlLetra
                        : controlActual === String(controlNumero) || controlActual === controlLetra;

                return valido
                    ? { valid: true, normalized: documento, type: 'CIF' }
                    : { valid: false, message: 'El codigo de control del CIF no es correcto.' };
            }

            return { valid: false, message: 'El documento no tiene un formato válido de NIF, NIE o CIF.' };
        }

        function syncSubmitState() {
            submitButton.disabled = hasValidationError || hasDuplicate || hasCuentaError;
        }

        function normalizarCuenta(value) {
            return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
        }

        function formatearCuenta(value) {
            return normalizarCuenta(value).replace(/(.{4})/g, '$1 ').trim();
        }

        function ibanResto97(value) {
            const reordenado = value.slice(4) + value.slice(0, 4);
            let expandido = '';

            for (let i = 0; i < reordenado.length; i += 1) {
                const char = reordenado.charAt(i);
                if (char >= 'A' && char <= 'Z') {
                    expandido += String(char.charCodeAt(0) - 55);
                } else {
                    expandido += char;
                }
            }

            let resto = 0;
            for (let i = 0; i < expandido.length; i += 1) {
                resto = ((resto * 10) + parseInt(expandido.charAt(i), 10)) % 97;
            }

            return resto;
        }

        function validarCuenta(value) {
            const normalizada = normalizarCuenta(value);
            if (!normalizada) {
                return { valid: true, formatted: '' };
            }

            if (!/^ES\d{22}$/.test(normalizada)) {
                return {
                    valid: false,
                    formatted: formatearCuenta(normalizada),
                    message: 'Introduce un IBAN español con formato ES12 1234 1234 12 1234567890.',
                };
            }

            if (ibanResto97(normalizada) !== 1) {
                return {
                    valid: false,
                    formatted: formatearCuenta(normalizada),
                    message: 'El número de cuenta no tiene un IBAN español válido.',
                };
            }

            return {
                valid: true,
                formatted: formatearCuenta(normalizada),
            };
        }

        function setCuentaState(result) {
            if (!result || result.valid) {
                hasCuentaError = false;
                cuentaWarning.classList.add('is-hidden');
                cuentaWarning.textContent = '';
                syncSubmitState();
                return;
            }

            hasCuentaError = true;
            cuentaWarning.textContent = result.message || 'Número de cuenta no válido.';
            cuentaWarning.classList.remove('is-hidden');
            syncSubmitState();
        }

        function tipoEmpresaDesdeTipoDocumento(tipoDocumento) {
            if (tipoDocumento === 'CIF') {
                return 'Juridica';
            }
            if (tipoDocumento === 'NIF' || tipoDocumento === 'NIE') {
                return 'Fisica';
            }
            return '';
        }

        function setValidationState(result) {
            if (!result || result.valid) {
                hasValidationError = false;
                validationWarning.classList.add('is-hidden');
                validationWarning.textContent = '';
                tipoEmpresaInput.value = tipoEmpresaDesdeTipoDocumento(result && result.type ? result.type : '');
                syncSubmitState();
                return;
            }

            hasValidationError = true;
            validationWarning.textContent = result.message || 'El NIF - CIF no es válido.';
            validationWarning.classList.remove('is-hidden');
            tipoEmpresaInput.value = tipoEmpresaDesdeTipoDocumento(result.type || '');
            syncSubmitState();
        }

        function setDuplicateState(cliente) {
            if (!cliente) {
                hasDuplicate = false;
                warning.classList.add('is-hidden');
                warning.innerHTML = '';
                syncSubmitState();
                return;
            }

            hasDuplicate = true;
            const nombre = escapeHtml(cliente.nombre_comercial || '');
            const codCliente = cliente.cod_cliente || '';
            const canView = !!cliente.can_view;
            const link = (canView && codCliente)
                ? ' <a href="' + BASE_URL + '/cliente_detalles.php?cod_cliente=' + encodeURIComponent(codCliente) + '" target="_blank" rel="noopener">Ver ficha</a>'
                : '';

            warning.innerHTML = 'Ya existe: <strong>' + nombre + '</strong>' + link;
            warning.classList.remove('is-hidden');
            syncSubmitState();
        }

        function renderSoftWarningLines(items) {
            const seen = new Set();

            return items.map(function (item) {
                const nombre = String(item.nombre_comercial || '').trim();
                const origenTabla = String(item.origen_tabla || '').trim();
                const nombreContacto = String(item.nombre_contacto || '').trim();
                const departamento = String(item.departamento_contacto || '').trim();
                const cargo = String(item.cargo_contacto || '').trim();
                const clave = nombre + '|' + origenTabla + '|' + nombreContacto + '|' + departamento + '|' + cargo;
                if (!nombre || seen.has(clave)) {
                    return '';
                }
                seen.add(clave);

                let detalle = '';
                if (origenTabla === 'Contacto') {
                    const detallePartes = [];
                    if (nombreContacto) {
                        detallePartes.push(nombreContacto);
                    }
                    if (departamento) {
                        let departamentoTexto = departamento;
                        if (cargo) {
                            departamentoTexto += ' (' + cargo + ')';
                        }
                        detallePartes.push(departamentoTexto);
                    } else if (cargo) {
                        detallePartes.push(cargo);
                    }

                    if (detallePartes.length) {
                        detalle = '<div class="soft-warning-detail">' + escapeHtml(detallePartes.join(' - ')) + '</div>';
                    }
                }

                return '<li><strong>' + escapeHtml(nombre) + '</strong>' + detalle + '</li>';
            }).join('');
        }

        function setSoftWarning(element, title, matches) {
            if (!matches || !matches.length) {
                element.innerHTML = '';
                element.classList.add('is-hidden');
                return;
            }

            element.innerHTML = title + '<ul class="soft-warning-list">' + renderSoftWarningLines(matches) + '</ul>';
            element.classList.remove('is-hidden');
        }

        function listaCorreosEsValida(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return true;
            }

            return raw
                .split(';')
                .map(function (item) { return item.trim(); })
                .filter(Boolean)
                .every(function (item) {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(item);
                });
        }

        function validarCampoListaCorreos(input, label) {
            const valor = String(input.value || '').trim();
            if (!valor) {
                input.setCustomValidity('');
                return true;
            }

            if (!listaCorreosEsValida(valor)) {
                input.setCustomValidity(label + ' no tiene un formato válido. Si son varios, sepáralos por ;');
                input.reportValidity();
                return false;
            }

            input.setCustomValidity('');
            return true;
        }

        function validarEmailContactoInput(input) {
            const valor = String(input.value || '').trim();
            if (!valor) {
                input.setCustomValidity('');
                return true;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
                input.setCustomValidity('El email del contacto no tiene un formato válido.');
                input.reportValidity();
                return false;
            }

            input.setCustomValidity('');
            return true;
        }

        function validarEmailsContactos() {
            const inputs = contactosTableBody.querySelectorAll('input[name="contactos_email[]"]');
            for (let i = 0; i < inputs.length; i += 1) {
                if (!validarEmailContactoInput(inputs[i])) {
                    return false;
                }
            }

            return true;
        }

        function syncDocumentoEmailState(checkbox, input) {
            const required = !checkbox.checked;
            const requiredPlaceholder = input.getAttribute('data-required-placeholder') || '';
            input.required = required;
            input.classList.toggle('is-required-soft', required && String(input.value || '').trim() === '');
            input.placeholder = required ? requiredPlaceholder : '';
            input.setAttribute('aria-required', required ? 'true' : 'false');
        }

        function validarBloqueDocumentos() {
            const reglas = [
                { checkbox: imprimeFacturaInput, input: emailFacturaInput, label: 'Factura' },
                { checkbox: imprimeAlbaranInput, input: emailAlbaranInput, label: 'Albarán' },
            ];

            for (let i = 0; i < reglas.length; i += 1) {
                const regla = reglas[i];
                const valor = String(regla.input.value || '').trim();
                if (!regla.checkbox.checked && !valor) {
                    regla.input.reportValidity();
                    return false;
                }
            }

            const correos = [
                { input: emailFacturaInput, label: 'Email factura' },
                { input: emailAlbaranInput, label: 'Email albarán' },
                { input: emailPedidoInput, label: 'Email pedido' },
                { input: emailPresupuestoInput, label: 'Email presupuesto' },
            ];

            for (let i = 0; i < correos.length; i += 1) {
                const correo = correos[i];
                if (!validarCampoListaCorreos(correo.input, correo.label)) {
                    return false;
                }
            }

            return true;
        }

        function renderFormasPagoOptions(formas, selectedValue) {
            formaPagoInput.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccione una opción';
            formaPagoInput.appendChild(placeholder);

            formas.forEach(function (forma) {
                const option = document.createElement('option');
                option.value = String(forma.value || '');
                option.textContent = String(forma.label || '');
                if (selectedValue && option.value === selectedValue) {
                    option.selected = true;
                }
                formaPagoInput.appendChild(option);
            });
        }

        async function refrescarFormasPagoPorTipo() {
            const tipoCliente = String(tipoClienteInput.value || '').trim();
            const selectedActual = String(formaPagoInput.value || '').trim();

            if (currentFormasController) {
                currentFormasController.abort();
            }
            currentFormasController = new AbortController();

            try {
                const response = await fetch(
                    BASE_URL + '/ajax/formas_liquidacion_cliente.php?tipo_cliente=' + encodeURIComponent(tipoCliente),
                    {
                        credentials: 'same-origin',
                        signal: currentFormasController.signal,
                        headers: { 'Accept': 'application/json' }
                    }
                );
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const formas = data && data.ok && Array.isArray(data.formas) ? data.formas : [];
                const sigueExistiendo = formas.some(function (forma) {
                    return String(forma.value || '') === selectedActual;
                });
                renderFormasPagoOptions(formas, sigueExistiendo ? selectedActual : '');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        async function comprobarContactoExistente() {
            const telefono = String(telefonoInput.value || '').trim();
            const email = String(emailInput.value || '').trim();
            if (!telefono && !email) {
                setSoftWarning(telefonoWarning, '', []);
                setSoftWarning(emailWarning, '', []);
                return;
            }

            if (currentContactoController) {
                currentContactoController.abort();
            }
            currentContactoController = new AbortController();

            try {
                const response = await fetch(
                    BASE_URL + '/ajax/buscar_contacto_existente.php?telefono=' + encodeURIComponent(telefono) + '&email=' + encodeURIComponent(email),
                    {
                        credentials: 'same-origin',
                        signal: currentContactoController.signal,
                        headers: { 'Accept': 'application/json' }
                    }
                );
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const telefonoMatches = data && data.ok && data.telefono && Array.isArray(data.telefono.matches) ? data.telefono.matches : [];
                const emailMatches = data && data.ok && data.email && Array.isArray(data.email.matches) ? data.email.matches : [];
                setSoftWarning(telefonoWarning, '⚠ Este telefono ya existe en:', telefonoMatches);
                setSoftWarning(emailWarning, '⚠ Este correo ya existe en:', emailMatches);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        cuentaInput.addEventListener('input', function () {
            const inicio = cuentaInput.selectionStart || 0;
            const longitudAnterior = cuentaInput.value.length;
            cuentaInput.value = formatearCuenta(cuentaInput.value);
            const diferencia = cuentaInput.value.length - longitudAnterior;
            const nuevaPosicion = Math.max(0, inicio + diferencia);
            try {
                cuentaInput.setSelectionRange(nuevaPosicion, nuevaPosicion);
            } catch (error) {
                // noop
            }

            if (!String(cuentaInput.value || '').trim()) {
                setCuentaState({ valid: true });
            }
        });

        cuentaInput.addEventListener('blur', function () {
            const result = validarCuenta(cuentaInput.value);
            cuentaInput.value = result.formatted || '';
            setCuentaState(result);
        });

        function resetPoblacionSelector() {
            cpOptionsMap = {};
            pendingPoblacionOptions = [];
            poblacionModalOptions.innerHTML = '';
            poblacionModal.classList.add('is-hidden');
            poblacionModalBackdrop.classList.add('is-hidden');
            poblacionPickerActive = false;
            poblacionInput.readOnly = false;
        }

        function ensureAtLeastOneContactoRow() {
            if (contactosTableBody.querySelector('.contactos-row')) {
                return;
            }
            contactosAddButton.closest('.contactos-add-row').insertAdjacentHTML('beforebegin', contactosTemplate);
        }

        function openPoblacionModal() {
            if (!poblacionPickerActive) {
                return;
            }
            poblacionModal.classList.remove('is-hidden');
            poblacionModalBackdrop.classList.remove('is-hidden');
        }

        function closePoblacionModal() {
            poblacionModal.classList.add('is-hidden');
            poblacionModalBackdrop.classList.add('is-hidden');
        }

        function activarSelectorPoblacion(options) {
            resetPoblacionSelector();
            pendingPoblacionOptions = options.slice();
            poblacionModalOptions.innerHTML = '';
            options.forEach(function (item) {
                const poblacion = String(item.poblacion || '').trim();
                const provincia = String(item.provincia || '').trim();
                if (!poblacion) {
                    return;
                }

                cpOptionsMap[poblacion] = provincia;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'poblacion-picker-option';
                button.textContent = poblacion;
                button.addEventListener('click', function () {
                    poblacionInput.value = poblacion;
                    provinciaInput.value = provincia;
                    closePoblacionModal();
                    resetPoblacionSelector();
                });
                poblacionModalOptions.appendChild(button);
            });
            poblacionInput.value = '';
            provinciaInput.value = '';
            poblacionInput.readOnly = true;
            poblacionPickerActive = true;
        }

        async function completarCp() {
            const cp = String(cpInput.value || '').trim();
            if (cp.length !== 5) {
                cpResolvedValue = cp;
                resetPoblacionSelector();
                return;
            }

            if (cpResolvedValue === cp) {
                return;
            }
            cpResolvedValue = cp;

            if (currentCpController) {
                currentCpController.abort();
            }
            currentCpController = new AbortController();

            try {
                const response = await fetch(
                    BASE_URL + '/ajax/buscar_cp.php?cp=' + encodeURIComponent(cp),
                    {
                        credentials: 'same-origin',
                        signal: currentCpController.signal,
                        headers: { 'Accept': 'application/json' }
                    }
                );
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (data && data.ok && data.found) {
                    resetPoblacionSelector();
                    if (!poblacionInput.value.trim()) {
                        poblacionInput.value = data.poblacion || '';
                    }
                    if (!provinciaInput.value.trim()) {
                        provinciaInput.value = data.provincia || '';
                    }
                    return;
                }

                if (data && data.ok && data.ambiguous) {
                    const options = Array.isArray(data.options) ? data.options : [];
                    activarSelectorPoblacion(options);
                    if (document.activeElement === cpInput) {
                        cpInput.blur();
                        window.setTimeout(openPoblacionModal, 120);
                    } else {
                        openPoblacionModal();
                    }
                    return;
                }

                resetPoblacionSelector();
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        poblacionInput.addEventListener('focus', function () {
            if (poblacionPickerActive) {
                poblacionInput.blur();
            }
        });
        poblacionInput.addEventListener('click', function () {
            if (poblacionPickerActive) {
                openPoblacionModal();
            }
        });
        poblacionModalClose.addEventListener('click', closePoblacionModal);
        poblacionModalBackdrop.addEventListener('click', closePoblacionModal);
        contactosAddButton.addEventListener('click', function () {
            contactosAddButton.closest('.contactos-add-row').insertAdjacentHTML('beforebegin', contactosTemplate);
        });
        contactosTableBody.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.contactos-remove');
            if (!removeButton) {
                return;
            }
            const row = removeButton.closest('.contactos-row');
            if (row) {
                row.remove();
                ensureAtLeastOneContactoRow();
            }
        });

        async function comprobarNif() {
            const result = validarDocumento(nifInput.value);
            if (!result.valid) {
                setValidationState(result);
                setDuplicateState(null);
                return;
            }

            nifInput.value = result.normalized || nifInput.value;
            setValidationState(result);

            const nif = nifInput.value.trim();
            if (currentController) {
                currentController.abort();
            }
            currentController = new AbortController();

            try {
                const response = await fetch(
                    BASE_URL + '/ajax/buscar_cliente_existente.php?nif=' + encodeURIComponent(nif),
                    {
                        credentials: 'same-origin',
                        signal: currentController.signal,
                        headers: { 'Accept': 'application/json' }
                    }
                );
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (data && data.ok && data.exists && data.cliente) {
                    setDuplicateState(data.cliente);
                } else {
                    setDuplicateState(null);
                }
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        nifInput.addEventListener('input', function () {
            hasDuplicate = false;
            warning.classList.add('is-hidden');
            warning.innerHTML = '';
            const currentValue = nifInput.value.trim();
            if (currentValue === '') {
                setValidationState(null);
                return;
            }
            syncSubmitState();
        });
        cpInput.addEventListener('input', function () {
            cpResolvedValue = '';
            const cp = String(cpInput.value || '').replace(/\D/g, '').slice(0, 5);
            if (cpInput.value !== cp) {
                cpInput.value = cp;
            }

            if (cp.length < 5) {
                resetPoblacionSelector();
                return;
            }

            completarCp();
        });
        nifInput.addEventListener('blur', comprobarNif);
        nifInput.addEventListener('change', comprobarNif);
        telefonoInput.addEventListener('blur', comprobarContactoExistente);
        telefonoInput.addEventListener('change', comprobarContactoExistente);
        emailInput.addEventListener('blur', comprobarContactoExistente);
        emailInput.addEventListener('change', comprobarContactoExistente);
        tipoClienteInput.addEventListener('change', refrescarFormasPagoPorTipo);
        [emailFacturaInput, emailAlbaranInput, emailPedidoInput, emailPresupuestoInput].forEach(function (input) {
            input.addEventListener('input', function () {
                input.setCustomValidity('');
                if (input === emailFacturaInput) {
                    syncDocumentoEmailState(imprimeFacturaInput, emailFacturaInput);
                }
                if (input === emailAlbaranInput) {
                    syncDocumentoEmailState(imprimeAlbaranInput, emailAlbaranInput);
                }
            });
            input.addEventListener('blur', function () {
                let label = 'Email';
                if (input === emailFacturaInput) {
                    label = 'Email factura';
                } else if (input === emailAlbaranInput) {
                    label = 'Email albaran';
                } else if (input === emailPedidoInput) {
                    label = 'Email pedido';
                } else if (input === emailPresupuestoInput) {
                    label = 'Email presupuesto';
                }
                validarCampoListaCorreos(input, label);
            });
        });
        document.addEventListener('input', function (event) {
            if (event.target && event.target.name === 'contactos_email[]') {
                event.target.setCustomValidity('');
            }
        });
        document.addEventListener('focusout', function (event) {
            if (event.target && event.target.name === 'contactos_email[]') {
                validarEmailContactoInput(event.target);
            }
        }, true);
        [imprimeFacturaInput, imprimeAlbaranInput].forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                syncDocumentoEmailState(imprimeFacturaInput, emailFacturaInput);
                syncDocumentoEmailState(imprimeAlbaranInput, emailAlbaranInput);
            });
        });
        cpInput.addEventListener('blur', function () {
            completarCp();
        });
        cpInput.addEventListener('change', completarCp);
        document.querySelector('.alta-cliente-form').addEventListener('submit', function (event) {
            if (!validarEmailsContactos() || !validarBloqueDocumentos()) {
                event.preventDefault();
            }
        });
        document.addEventListener('click', function (event) {
            if (!poblacionPickerActive) {
                return;
            }

            if (poblacionPicker.contains(event.target) || event.target === poblacionInput) {
                return;
            }

            resetPoblacionSelector();
        });
        syncDocumentoEmailState(imprimeFacturaInput, emailFacturaInput);
        syncDocumentoEmailState(imprimeAlbaranInput, emailAlbaranInput);
        ensureAtLeastOneContactoRow();
        syncSubmitState();
    }());
    </script>
</body>
</html>
