<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;

function altaClienteStorageLogsDir(): string
{
    $storageLogsDir = realpath(BASE_PATH . '/storage/logs');
    if ($storageLogsDir === false) {
        $storageLogsDir = BASE_PATH . '/storage/logs';
    }
    if (!is_dir($storageLogsDir)) {
        @mkdir($storageLogsDir, 0775, true);
    }

    return $storageLogsDir;
}

function altaClienteMailchimpLogPath(): string
{
    return altaClienteStorageLogsDir() . DIRECTORY_SEPARATOR . 'mailchimp_log.txt';
}

function altaClienteErrorLogPath(): string
{
    return altaClienteStorageLogsDir() . DIRECTORY_SEPARATOR . 'altaClientes_error_log.txt';
}

function altaClienteRequestData(): array
{
    return [
        'empresa' => mb_strtoupper(trim((string)($_POST['empresa'] ?? '')), 'UTF-8'),
        'razon_social' => mb_strtoupper(trim((string)($_POST['razon_social'] ?? '')), 'UTF-8'),
        'nif' => trim((string)($_POST['nif'] ?? '')),
        'tipo_empresa' => trim((string)($_POST['tipo_empresa'] ?? '')),
        'tipo_cliente' => trim((string)($_POST['tipo_cliente'] ?? '')),
        'tarifa' => trim((string)($_POST['tarifa'] ?? '')),
        'cod_vendedor_asociado' => trim((string)($_POST['cod_vendedor_asociado'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'telefono' => trim((string)($_POST['telefono'] ?? '')),
        'direccion_comercial' => trim((string)($_POST['direccion_comercial'] ?? '')),
        'direccion_logistica' => trim((string)($_POST['direccion_logistica'] ?? '')),
        'poblacion' => trim((string)($_POST['poblacion'] ?? '')),
        'provincia' => trim((string)($_POST['provincia'] ?? '')),
        'cp' => trim((string)($_POST['cp'] ?? '')),
        'web' => trim((string)($_POST['web'] ?? '')),
        'iva' => trim((string)($_POST['iva'] ?? '')),
        'forma_pago' => trim((string)($_POST['forma_pago'] ?? '')),
        'imprime_factura' => trim((string)($_POST['imprime_factura'] ?? 'No')),
        'email_factura' => trim((string)($_POST['email_factura'] ?? '')),
        'imprime_albaran' => trim((string)($_POST['imprime_albaran'] ?? 'No')),
        'email_albaran' => trim((string)($_POST['email_albaran'] ?? '')),
        'imprime_pedido' => trim((string)($_POST['imprime_pedido'] ?? 'No')),
        'email_pedido' => trim((string)($_POST['email_pedido'] ?? '')),
        'imprime_presupuesto' => trim((string)($_POST['imprime_presupuesto'] ?? 'No')),
        'email_presupuesto' => trim((string)($_POST['email_presupuesto'] ?? '')),
        'banco' => trim((string)($_POST['banco'] ?? '')),
        'cuenta' => altaClienteFormatearCuentaEspanola((string)($_POST['cuenta'] ?? '')),
        'comentarios' => trim((string)($_POST['comentarios'] ?? '')),
        'accesoWeb' => isset($_POST['accesoWeb']),
        'acepta_comunicaciones' => isset($_POST['acepta_comunicaciones']) && (string)($_POST['acepta_comunicaciones'] ?? '') !== '',
        'proteccion_datos' => isset($_POST['proteccion_datos']),
        'contactos' => altaClienteRequestContactos(),
    ];
}

function altaClienteContactoFilaVacia(): array
{
    return [
        'nombre' => '',
        'departamento' => '',
        'cargo' => '',
        'telefono' => '',
        'movil' => '',
        'email' => '',
    ];
}

function altaClienteRequestContactos(): array
{
    $nombres = $_POST['contactos_nombre'] ?? [];
    $departamentos = $_POST['contactos_departamento'] ?? [];
    $cargos = $_POST['contactos_cargo'] ?? [];
    $telefonos = $_POST['contactos_telefono'] ?? [];
    $moviles = $_POST['contactos_movil'] ?? [];
    $emails = $_POST['contactos_email'] ?? [];

    $max = max(
        is_array($nombres) ? count($nombres) : 0,
        is_array($departamentos) ? count($departamentos) : 0,
        is_array($cargos) ? count($cargos) : 0,
        is_array($telefonos) ? count($telefonos) : 0,
        is_array($moviles) ? count($moviles) : 0,
        is_array($emails) ? count($emails) : 0
    );

    $contactos = [];
    for ($i = 0; $i < $max; $i++) {
        $fila = [
            'nombre' => mb_strtoupper(trim((string)($nombres[$i] ?? '')), 'UTF-8'),
            'departamento' => trim((string)($departamentos[$i] ?? '')),
            'cargo' => trim((string)($cargos[$i] ?? '')),
            'telefono' => trim((string)($telefonos[$i] ?? '')),
            'movil' => trim((string)($moviles[$i] ?? '')),
            'email' => trim((string)($emails[$i] ?? '')),
        ];

        $tieneContenido = false;
        foreach ($fila as $valor) {
            if ($valor !== '') {
                $tieneContenido = true;
                break;
            }
        }

        if ($tieneContenido) {
            $contactos[] = $fila;
        }
    }

    if ($contactos === []) {
        $contactos[] = altaClienteContactoFilaVacia();
    }

    return $contactos;
}

function altaClienteListaCorreosEsValida(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }

    $partes = preg_split('/\s*;\s*/', $value) ?: [];
    foreach ($partes as $parte) {
        $correo = trim((string)$parte);
        if ($correo === '') {
            continue;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
    }

    return true;
}

function altaClienteCorreoSimpleEsValido(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }

    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function altaClienteNormalizarCuenta(string $value): string
{
    $value = strtoupper(trim($value));
    return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
}

function altaClienteFormatearCuentaEspanola(string $value): string
{
    $normalizada = altaClienteNormalizarCuenta($value);
    if ($normalizada === '') {
        return '';
    }

    return trim((string)preg_replace('/(.{4})/', '$1 ', $normalizada));
}

function altaClienteIbanMod97(string $iban): int
{
    $iban = strtoupper($iban);
    $reordenado = substr($iban, 4) . substr($iban, 0, 4);
    $expandido = '';

    $length = strlen($reordenado);
    for ($i = 0; $i < $length; $i++) {
        $char = $reordenado[$i];
        if ($char >= 'A' && $char <= 'Z') {
            $expandido .= (string)(ord($char) - 55);
        } else {
            $expandido .= $char;
        }
    }

    $resto = 0;
    $expandidoLength = strlen($expandido);
    for ($i = 0; $i < $expandidoLength; $i++) {
        $resto = (($resto * 10) + (int)$expandido[$i]) % 97;
    }

    return $resto;
}

function altaClienteCuentaEsValida(string $value): bool
{
    $normalizada = altaClienteNormalizarCuenta($value);
    if ($normalizada === '') {
        return true;
    }

    if (!preg_match('/^ES\d{22}$/', $normalizada)) {
        return false;
    }

    return altaClienteIbanMod97($normalizada) === 1;
}

function altaClienteValidarPreferenciasDocumentos(array $data): array
{
    $mapaEnvio = [
        'factura' => 'Factura',
        'albaran' => 'Albarán',
    ];

    foreach ($mapaEnvio as $clave => $label) {
        $imprime = trim((string)($data['imprime_' . $clave] ?? 'No'));
        $email = trim((string)($data['email_' . $clave] ?? ''));
        if ($imprime !== 'Si' && $email === '') {
            return [
                'ok' => false,
                'message' => 'Si ' . $label . ' no se imprime, debes indicar un email de envío.',
            ];
        }
    }

    $mapaCorreos = [
        'email_factura' => 'Email factura',
        'email_albaran' => 'Email albarán',
        'email_pedido' => 'Email pedido',
        'email_presupuesto' => 'Email presupuesto',
    ];

    foreach ($mapaCorreos as $campo => $label) {
        $valor = trim((string)($data[$campo] ?? ''));
        if ($valor !== '' && !altaClienteListaCorreosEsValida($valor)) {
            return [
                'ok' => false,
                'message' => $label . ' no tiene un formato válido. Usa uno o varios correos separados por ;',
            ];
        }
    }

    $cuenta = trim((string)($data['cuenta'] ?? ''));
    if ($cuenta !== '' && !altaClienteCuentaEsValida($cuenta)) {
        return [
            'ok' => false,
            'message' => 'Número de cuenta no tiene un formato válido de IBAN español.',
        ];
    }

    return ['ok' => true];
}

function altaClienteValidarContactos(array $contactos): array
{
    foreach ($contactos as $index => $contacto) {
        if (!is_array($contacto)) {
            continue;
        }

        $email = trim((string)($contacto['email'] ?? ''));
        if ($email === '') {
            continue;
        }

        if (!altaClienteCorreoSimpleEsValido($email)) {
            $nombre = trim((string)($contacto['nombre'] ?? ''));
            $sufijo = $nombre !== '' ? ' (' . $nombre . ')' : ' #' . ($index + 1);

            return [
                'ok' => false,
                'message' => 'El email del contacto' . $sufijo . ' no tiene un formato válido.',
            ];
        }
    }

    return ['ok' => true];
}

function altaClienteObtenerTiposCliente($conn): array
{
    $sql = "SELECT * FROM [integral].[dbo].[tipo_cliente]";
    $result = @odbc_exec($conn, $sql);
    if (!$result) {
        return [];
    }

    $tipos = [];
    while ($row = odbc_fetch_array($result)) {
        if (!is_array($row) || $row === []) {
            continue;
        }

        $value = '';
        $label = '';

        foreach (['cod_tipo_cliente', 'COD_TIPO_CLIENTE', 'cod_tipo', 'COD_TIPO', 'codigo', 'CODIGO', 'cod', 'COD', 'id', 'ID', 'tipo_cliente', 'TIPO_CLIENTE'] as $candidate) {
            if (isset($row[$candidate]) && trim((string)$row[$candidate]) !== '') {
                $value = trim((string)$row[$candidate]);
                break;
            }
        }

        foreach (['descripcion', 'DESCRIPCION', 'nombre', 'NOMBRE', 'denominacion', 'DENOMINACION', 'tipo_cliente', 'TIPO_CLIENTE'] as $candidate) {
            if (isset($row[$candidate]) && trim((string)$row[$candidate]) !== '') {
                $label = trim((string)$row[$candidate]);
                break;
            }
        }

        if ($value === '' || $label === '') {
            $keys = array_keys($row);
            if ($value === '' && isset($keys[0])) {
                $value = trim((string)($row[$keys[0]] ?? ''));
            }
            if ($label === '') {
                $labelKey = $keys[1] ?? $keys[0] ?? null;
                if ($labelKey !== null) {
                    $label = trim((string)($row[$labelKey] ?? ''));
                }
            }
        }

        if ($value === '' || $label === '') {
            continue;
        }

        $tipos[] = [
            'value' => $value,
            'label' => function_exists('toUTF8') ? toUTF8($label) : $label,
        ];
    }

    usort($tipos, static function (array $a, array $b): int {
        return strcasecmp((string)$a['label'], (string)$b['label']);
    });

    return $tipos;
}

function altaClienteObtenerTarifas($conn): array
{
    $sql = "
        SELECT cod_tarifa, descripcion
        FROM [integral].[dbo].[tarifas_venta_cabecera]
    ";
    $result = @odbc_exec($conn, $sql);
    if (!$result) {
        return [];
    }

    $tarifas = [];
    while ($row = odbc_fetch_array($result)) {
        $value = trim((string)($row['cod_tarifa'] ?? $row['COD_TARIFA'] ?? ''));
        $label = trim((string)($row['descripcion'] ?? $row['DESCRIPCION'] ?? ''));
        if ($value === '' || $label === '') {
            continue;
        }

        $tarifas[] = [
            'value' => $value,
            'label' => function_exists('toUTF8') ? toUTF8($label) : $label,
        ];
    }

    usort($tarifas, static function (array $a, array $b): int {
        return strcasecmp((string)$a['label'], (string)$b['label']);
    });

    return $tarifas;
}

function altaClienteObtenerFormasLiquidacion($conn): array
{
    return altaClienteObtenerFormasLiquidacionPorTipo($conn, null);
}

function altaClienteObtenerColumnaTipoClienteClientes($conn): ?string
{
    $stmt = @odbc_exec($conn, "SELECT TOP 0 * FROM [integral].[dbo].[clientes]");
    if (!$stmt) {
        return null;
    }

    $numFields = @odbc_num_fields($stmt);
    if (!is_int($numFields) || $numFields <= 0) {
        return null;
    }

    $columnas = [];
    for ($i = 1; $i <= $numFields; $i++) {
        $name = @odbc_field_name($stmt, $i);
        if (is_string($name) && trim($name) !== '') {
            $columnas[strtolower(trim($name))] = trim($name);
        }
    }

    foreach (['cod_tipo_cliente', 'tipo_cliente', 'cod_tipo', 'cod_tipocliente'] as $candidate) {
        if (isset($columnas[$candidate])) {
            return $columnas[$candidate];
        }
    }

    return null;
}

function altaClienteObtenerFormasLiquidacionPorTipo($conn, ?string $tipoCliente): array
{
    $columnaTipoCliente = altaClienteObtenerColumnaTipoClienteClientes($conn);
    $sql = "
        SELECT DISTINCT
            fl.cod_forma_liquidacion,
            fl.descripcion
        FROM [integral].[dbo].[formas_liquidacion] fl
        INNER JOIN [integral].[dbo].[clientes] c
            ON c.cod_forma_liquidacion = fl.cod_forma_liquidacion
        WHERE fl.descripcion IS NOT NULL AND LTRIM(RTRIM(fl.descripcion)) <> ''
          AND c.cod_forma_liquidacion IS NOT NULL AND LTRIM(RTRIM(c.cod_forma_liquidacion)) <> ''
    ";

    $params = [];
    $tipoCliente = trim((string)$tipoCliente);
    if ($tipoCliente !== '' && $columnaTipoCliente !== null) {
        $sql .= " AND LTRIM(RTRIM(ISNULL(c.{$columnaTipoCliente}, ''))) = ?";
        $params[] = $tipoCliente;
    }

    $stmt = @odbc_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    $ok = $params === [] ? @odbc_execute($stmt, []) : @odbc_execute($stmt, $params);
    if (!$ok) {
        return [];
    }

    $result = $stmt;
    if (!$result) {
        return [];
    }

    $formas = [];
    while ($row = odbc_fetch_array($result)) {
        $value = trim((string)($row['cod_forma_liquidacion'] ?? $row['COD_FORMA_LIQUIDACION'] ?? ''));
        $label = trim((string)($row['descripcion'] ?? $row['DESCRIPCION'] ?? ''));
        if ($value === '' || $label === '') {
            continue;
        }

        $formas[] = [
            'value' => $value,
            'label' => function_exists('toUTF8') ? toUTF8($label) : $label,
        ];
    }

    usort($formas, static function (array $a, array $b): int {
        return strcasecmp((string)$a['label'], (string)$b['label']);
    });

    return $formas;
}

function altaClienteObtenerVendedorPorCodigo($conn, string $codigo): ?array
{
    $codigo = trim($codigo);
    if ($codigo === '' || !ctype_digit($codigo)) {
        return null;
    }

    $sql = "
        SELECT TOP 1 cod_vendedor, nombre
        FROM [integral].[dbo].[vendedores]
        WHERE cod_vendedor = ?
    ";
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, [$codigo])) {
        return null;
    }

    $row = odbc_fetch_array($stmt);
    return $row ?: null;
}

function altaClienteObtenerVendedores($conn): array
{
    $sql = "
        SELECT cod_vendedor, nombre
        FROM [integral].[dbo].[vendedores]
        WHERE nombre IS NOT NULL AND LTRIM(RTRIM(nombre)) <> ''
        ORDER BY nombre ASC
    ";
    $result = @odbc_exec($conn, $sql);
    if (!$result) {
        return [];
    }

    $vendedores = [];
    while ($row = odbc_fetch_array($result)) {
        $codigo = trim((string)($row['cod_vendedor'] ?? $row['COD_VENDEDOR'] ?? ''));
        $nombre = trim((string)($row['nombre'] ?? $row['NOMBRE'] ?? ''));
        if ($codigo === '' || $nombre === '') {
            continue;
        }

        $vendedores[] = [
            'value' => $codigo,
            'label' => function_exists('toUTF8') ? toUTF8($nombre) : $nombre,
        ];
    }

    return $vendedores;
}

function altaClienteNormalizarDocumento(string $value): string
{
    $value = strtoupper(trim($value));
    $value = str_replace(['-', ' '], '', $value);
    return $value;
}

function altaClienteLetraNifPorNumero(int $numero): string
{
    $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    return $letras[$numero % 23];
}

function altaClienteValidarDocumento(string $value): array
{
    $documento = altaClienteNormalizarDocumento($value);
    if ($documento === '') {
        return [
            'valid' => false,
            'type' => null,
            'normalized' => '',
            'message' => 'El NIF - CIF es obligatorio.',
        ];
    }

    if (preg_match('/^\d{8}[A-Z]$/', $documento) === 1) {
        $numero = (int)substr($documento, 0, 8);
        $letraEsperada = altaClienteLetraNifPorNumero($numero);
        $letraActual = substr($documento, -1);

        return [
            'valid' => $letraActual === $letraEsperada,
            'type' => 'NIF',
            'normalized' => $documento,
            'message' => $letraActual === $letraEsperada ? '' : 'La letra del NIF no es correcta.',
        ];
    }

    if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $documento) === 1) {
        $mapa = ['X' => '0', 'Y' => '1', 'Z' => '2'];
        $numeroBase = $mapa[$documento[0]] . substr($documento, 1, 7);
        $letraEsperada = altaClienteLetraNifPorNumero((int)$numeroBase);
        $letraActual = substr($documento, -1);

        return [
            'valid' => $letraActual === $letraEsperada,
            'type' => 'NIE',
            'normalized' => $documento,
            'message' => $letraActual === $letraEsperada ? '' : 'La letra del NIE no es correcta.',
        ];
    }

    if (preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $documento) === 1) {
        $letraInicial = $documento[0];
        $digitos = substr($documento, 1, 7);
        $controlActual = substr($documento, -1);

        $sumaPar = 0;
        $sumaImpar = 0;
        for ($i = 0; $i < 7; $i++) {
            $digito = (int)$digitos[$i];
            if ($i % 2 === 0) {
                $doble = $digito * 2;
                $sumaImpar += intdiv($doble, 10) + ($doble % 10);
            } else {
                $sumaPar += $digito;
            }
        }

        $sumaTotal = $sumaPar + $sumaImpar;
        $controlNumero = (10 - ($sumaTotal % 10)) % 10;
        $controlLetra = 'JABCDEFGHI'[$controlNumero];

        $soloNumero = in_array($letraInicial, ['A', 'B', 'E', 'H'], true);
        $soloLetra = in_array($letraInicial, ['K', 'P', 'Q', 'S', 'N', 'W'], true);

        $esValido = false;
        if ($soloNumero) {
            $esValido = $controlActual === (string)$controlNumero;
        } elseif ($soloLetra) {
            $esValido = $controlActual === $controlLetra;
        } else {
            $esValido = $controlActual === (string)$controlNumero || $controlActual === $controlLetra;
        }

        return [
            'valid' => $esValido,
            'type' => 'CIF',
            'normalized' => $documento,
            'message' => $esValido ? '' : 'El codigo de control del CIF no es correcto.',
        ];
    }

    return [
        'valid' => false,
        'type' => null,
        'normalized' => $documento,
        'message' => 'El documento no tiene un formato válido de NIF, NIE o CIF.',
    ];
}

function altaClienteTipoEmpresaDesdeTipoDocumento(?string $tipoDocumento): string
{
    $tipoDocumento = strtoupper(trim((string)$tipoDocumento));
    if ($tipoDocumento === 'CIF') {
        return 'Juridica';
    }

    if ($tipoDocumento === 'NIF' || $tipoDocumento === 'NIE') {
        return 'Fisica';
    }

    return '';
}

function altaClienteBuscarClienteExistentePorNif($conn, string $nif): ?array
{
    $nifNormalizado = altaClienteNormalizarDocumento($nif);
    if ($nifNormalizado === '') {
        return null;
    }

    $sql = "
        SELECT TOP 1
            cod_cliente,
            cod_vendedor,
            nombre_comercial,
            cif,
            poblacion,
            provincia
        FROM [integral].[dbo].[clientes]
        WHERE UPPER(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(cif, ''))), '-', ''), ' ', '')) = ?
        ORDER BY cod_cliente ASC
    ";

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    if (!odbc_execute($stmt, [$nifNormalizado])) {
        return null;
    }

    $row = odbc_fetch_array($stmt);
    return $row ?: null;
}

function altaClienteBuscarLocalidadPorCp($conn, string $cp): array
{
    $cp = trim($cp);
    if ($cp === '') {
        return [
            'ok' => false,
            'found' => false,
            'ambiguous' => false,
            'poblacion' => '',
            'provincia' => '',
            'options' => [],
        ];
    }

    $sql = "
        SELECT DISTINCT
            LTRIM(RTRIM(ISNULL(poblacion, ''))) AS poblacion,
            LTRIM(RTRIM(ISNULL(provincia, ''))) AS provincia
        FROM [integral].[dbo].[poblaciones]
        WHERE LTRIM(RTRIM(ISNULL(cp, ''))) = ?
          AND LTRIM(RTRIM(ISNULL(poblacion, ''))) <> ''
          AND LTRIM(RTRIM(ISNULL(provincia, ''))) <> ''
    ";
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt || !odbc_execute($stmt, [$cp])) {
        return [
            'ok' => false,
            'found' => false,
            'ambiguous' => false,
            'poblacion' => '',
            'provincia' => '',
            'options' => [],
        ];
    }

    $resultados = [];
    while ($row = odbc_fetch_array($stmt)) {
        $poblacion = trim((string)($row['poblacion'] ?? $row['POBLACION'] ?? ''));
        $provincia = trim((string)($row['provincia'] ?? $row['PROVINCIA'] ?? ''));
        if ($poblacion === '' || $provincia === '') {
            continue;
        }

        $clave = mb_strtoupper($poblacion . '|' . $provincia, 'UTF-8');
        $resultados[$clave] = [
            'poblacion' => function_exists('toUTF8') ? toUTF8($poblacion) : $poblacion,
            'provincia' => function_exists('toUTF8') ? toUTF8($provincia) : $provincia,
        ];
    }

    if ($resultados === []) {
        return [
            'ok' => true,
            'found' => false,
            'ambiguous' => false,
            'poblacion' => '',
            'provincia' => '',
            'options' => [],
        ];
    }

    if (count($resultados) > 1) {
        $options = array_values(array_map(static function (array $item): array {
            return [
                'poblacion' => (string)($item['poblacion'] ?? ''),
                'provincia' => (string)($item['provincia'] ?? ''),
            ];
        }, $resultados));

        usort($options, static function (array $a, array $b): int {
            return strcasecmp(
                (string)($a['poblacion'] ?? ''),
                (string)($b['poblacion'] ?? '')
            );
        });

        return [
            'ok' => true,
            'found' => false,
            'ambiguous' => true,
            'poblacion' => '',
            'provincia' => '',
            'options' => $options,
        ];
    }

    $unico = reset($resultados);
    return [
        'ok' => true,
        'found' => true,
        'ambiguous' => false,
        'poblacion' => (string)($unico['poblacion'] ?? ''),
        'provincia' => (string)($unico['provincia'] ?? ''),
        'options' => [],
    ];
}

function altaClienteNormalizarTelefono(string $telefono): string
{
    $soloDigitos = preg_replace('/\D+/', '', trim($telefono)) ?? '';
    if ($soloDigitos === '') {
        return '';
    }

    if (strlen($soloDigitos) > 9) {
        return substr($soloDigitos, -9);
    }

    return $soloDigitos;
}

function altaClienteNormalizarEmail(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

function altaClienteBuscarCoincidenciasTelefono($conn, string $telefono): array
{
    $telefonoNormalizado = altaClienteNormalizarTelefono($telefono);
    if ($telefonoNormalizado === '') {
        return [];
    }

    $queries = [
        [
            'sql' => "
                SELECT TOP 10
                    c.cod_cliente,
                    c.nombre_comercial,
                    'Cliente' AS origen_tabla,
                    'telefono' AS origen_campo,
                    c.telefono AS valor_original,
                    '' AS nombre_contacto,
                    '' AS departamento_contacto,
                    '' AS cargo_contacto
                FROM [integral].[dbo].[clientes] c
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(c.telefono, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    c.cod_cliente,
                    c.nombre_comercial,
                    'Cliente' AS origen_tabla,
                    'fax' AS origen_campo,
                    c.fax AS valor_original,
                    '' AS nombre_contacto,
                    '' AS departamento_contacto,
                    '' AS cargo_contacto
                FROM [integral].[dbo].[clientes] c
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(c.fax, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    c.cod_cliente,
                    c.nombre_comercial,
                    'Cliente' AS origen_tabla,
                    'telefono2' AS origen_campo,
                    c.telefono2 AS valor_original,
                    '' AS nombre_contacto,
                    '' AS departamento_contacto,
                    '' AS cargo_contacto
                FROM [integral].[dbo].[clientes] c
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(c.telefono2, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    c.cod_cliente,
                    c.nombre_comercial,
                    'Cliente' AS origen_tabla,
                    'telefono3' AS origen_campo,
                    c.telefono3 AS valor_original,
                    '' AS nombre_contacto,
                    '' AS departamento_contacto,
                    '' AS cargo_contacto
                FROM [integral].[dbo].[clientes] c
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(c.telefono3, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    cc.cod_cliente,
                    c.nombre_comercial,
                    'Contacto' AS origen_tabla,
                    'telefono' AS origen_campo,
                    cc.telefono AS valor_original,
                    cc.nombre AS nombre_contacto,
                    cc.departamento AS departamento_contacto,
                    cc.cargo AS cargo_contacto
                FROM [integral].[dbo].[contactos_cliente] cc
                INNER JOIN [integral].[dbo].[clientes] c ON c.cod_cliente = cc.cod_cliente
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(cc.telefono, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    cc.cod_cliente,
                    c.nombre_comercial,
                    'Contacto' AS origen_tabla,
                    'fax' AS origen_campo,
                    cc.fax AS valor_original,
                    cc.nombre AS nombre_contacto,
                    cc.departamento AS departamento_contacto,
                    cc.cargo AS cargo_contacto
                FROM [integral].[dbo].[contactos_cliente] cc
                INNER JOIN [integral].[dbo].[clientes] c ON c.cod_cliente = cc.cod_cliente
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(cc.fax, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
        [
            'sql' => "
                SELECT TOP 10
                    cc.cod_cliente,
                    c.nombre_comercial,
                    'Contacto' AS origen_tabla,
                    'telefono_movil' AS origen_campo,
                    cc.telefono_movil AS valor_original,
                    cc.nombre AS nombre_contacto,
                    cc.departamento AS departamento_contacto,
                    cc.cargo AS cargo_contacto
                FROM [integral].[dbo].[contactos_cliente] cc
                INNER JOIN [integral].[dbo].[clientes] c ON c.cod_cliente = cc.cod_cliente
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(cc.telefono_movil, ''))), ' ', ''), '-', ''), '.', ''), '/', ''), '(', ''), ')', ''), '+', ''), 9) = ?
            ",
        ],
    ];

    $matches = [];
    $seen = [];
    foreach ($queries as $query) {
        $stmt = @odbc_prepare($conn, $query['sql']);
        if (!$stmt || !@odbc_execute($stmt, [$telefonoNormalizado])) {
            continue;
        }

        while ($row = odbc_fetch_array($stmt)) {
            $match = [
                'cod_cliente' => (string)($row['cod_cliente'] ?? $row['COD_CLIENTE'] ?? ''),
                'nombre_comercial' => function_exists('toUTF8') ? toUTF8(trim((string)($row['nombre_comercial'] ?? $row['NOMBRE_COMERCIAL'] ?? ''))) : trim((string)($row['nombre_comercial'] ?? $row['NOMBRE_COMERCIAL'] ?? '')),
                'origen_tabla' => (string)($row['origen_tabla'] ?? $row['ORIGEN_TABLA'] ?? ''),
                'origen_campo' => (string)($row['origen_campo'] ?? $row['ORIGEN_CAMPO'] ?? ''),
                'valor_original' => (string)($row['valor_original'] ?? $row['VALOR_ORIGINAL'] ?? ''),
                'nombre_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['nombre_contacto'] ?? $row['NOMBRE_CONTACTO'] ?? ''))) : trim((string)($row['nombre_contacto'] ?? $row['NOMBRE_CONTACTO'] ?? '')),
                'departamento_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['departamento_contacto'] ?? $row['DEPARTAMENTO_CONTACTO'] ?? ''))) : trim((string)($row['departamento_contacto'] ?? $row['DEPARTAMENTO_CONTACTO'] ?? '')),
                'cargo_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['cargo_contacto'] ?? $row['CARGO_CONTACTO'] ?? ''))) : trim((string)($row['cargo_contacto'] ?? $row['CARGO_CONTACTO'] ?? '')),
            ];

            $key = implode('|', $match);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $matches[] = $match;
        }
    }

    return array_slice($matches, 0, 10);
}

function altaClienteBuscarCoincidenciasEmail($conn, string $email): array
{
    $emailNormalizado = altaClienteNormalizarEmail($email);
    if ($emailNormalizado === '') {
        return [];
    }

    $queries = [
        "
            SELECT TOP 10
                c.cod_cliente,
                c.nombre_comercial,
                'Cliente' AS origen_tabla,
                'e_mail' AS origen_campo,
                c.e_mail AS valor_original,
                '' AS nombre_contacto,
                '' AS departamento_contacto,
                '' AS cargo_contacto
            FROM [integral].[dbo].[clientes] c
            WHERE LOWER(LTRIM(RTRIM(ISNULL(c.e_mail, '')))) = ?
        ",
        "
            SELECT TOP 10
                cc.cod_cliente,
                c.nombre_comercial,
                'Contacto' AS origen_tabla,
                'e_mail' AS origen_campo,
                cc.e_mail AS valor_original,
                cc.nombre AS nombre_contacto,
                cc.departamento AS departamento_contacto,
                cc.cargo AS cargo_contacto
            FROM [integral].[dbo].[contactos_cliente] cc
            INNER JOIN [integral].[dbo].[clientes] c ON c.cod_cliente = cc.cod_cliente
            WHERE LOWER(LTRIM(RTRIM(ISNULL(cc.e_mail, '')))) = ?
        ",
    ];

    $matches = [];
    $seen = [];
    foreach ($queries as $sql) {
        $stmt = @odbc_prepare($conn, $sql);
        if (!$stmt || !@odbc_execute($stmt, [$emailNormalizado])) {
            continue;
        }

        while ($row = odbc_fetch_array($stmt)) {
            $match = [
                'cod_cliente' => (string)($row['cod_cliente'] ?? $row['COD_CLIENTE'] ?? ''),
                'nombre_comercial' => function_exists('toUTF8') ? toUTF8(trim((string)($row['nombre_comercial'] ?? $row['NOMBRE_COMERCIAL'] ?? ''))) : trim((string)($row['nombre_comercial'] ?? $row['NOMBRE_COMERCIAL'] ?? '')),
                'origen_tabla' => (string)($row['origen_tabla'] ?? $row['ORIGEN_TABLA'] ?? ''),
                'origen_campo' => (string)($row['origen_campo'] ?? $row['ORIGEN_CAMPO'] ?? ''),
                'valor_original' => (string)($row['valor_original'] ?? $row['VALOR_ORIGINAL'] ?? ''),
                'nombre_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['nombre_contacto'] ?? $row['NOMBRE_CONTACTO'] ?? ''))) : trim((string)($row['nombre_contacto'] ?? $row['NOMBRE_CONTACTO'] ?? '')),
                'departamento_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['departamento_contacto'] ?? $row['DEPARTAMENTO_CONTACTO'] ?? ''))) : trim((string)($row['departamento_contacto'] ?? $row['DEPARTAMENTO_CONTACTO'] ?? '')),
                'cargo_contacto' => function_exists('toUTF8') ? toUTF8(trim((string)($row['cargo_contacto'] ?? $row['CARGO_CONTACTO'] ?? ''))) : trim((string)($row['cargo_contacto'] ?? $row['CARGO_CONTACTO'] ?? '')),
            ];

            $key = implode('|', $match);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $matches[] = $match;
        }
    }

    return array_slice($matches, 0, 10);
}

function altaClienteEmailEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function altaClienteResolveSelectLabel(array $options, ?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    foreach ($options as $option) {
        if ((string)($option['value'] ?? '') === $value) {
            return trim((string)($option['label'] ?? $value));
        }
    }

    return $value;
}

function altaClienteDocumentoImpresionLabel(?string $value): string
{
    return trim((string)$value) === 'Si' ? 'Imprimir' : 'No imprimir';
}

function altaClienteEnrichEmailData(array $data): array
{
    $conn = db();

    $data['tipo_cliente_label'] = altaClienteResolveSelectLabel(
        altaClienteObtenerTiposCliente($conn),
        (string)($data['tipo_cliente'] ?? '')
    );
    $data['tarifa_label'] = altaClienteResolveSelectLabel(
        altaClienteObtenerTarifas($conn),
        (string)($data['tarifa'] ?? '')
    );
    $data['forma_pago_label'] = altaClienteResolveSelectLabel(
        altaClienteObtenerFormasLiquidacionPorTipo($conn, null),
        (string)($data['forma_pago'] ?? '')
    );

    return $data;
}

function altaClienteBuildEmailFields(array $data): array
{
    return [
        'Vendedor' => $data['vendedor_asociado_nombre'] ?? $data['cod_vendedor_asociado'],
        'Nombre comercial' => $data['empresa'],
        'Razón social' => $data['razon_social'],
        'NIF' => $data['nif'],
        'Tipo de empresa' => $data['tipo_empresa'],
        'Tipo de cliente' => $data['tipo_cliente_label'] ?? $data['tipo_cliente'],
        'Tarifa' => $data['tarifa_label'] ?? $data['tarifa'],
        'Email' => $data['email'],
        'Teléfono' => $data['telefono'],
        'Dirección Comercial' => $data['direccion_comercial'],
        'Dirección Logística' => $data['direccion_logistica'],
        'Población' => $data['poblacion'],
        'Provincia' => $data['provincia'],
        'CP' => $data['cp'],
        'Web' => $data['web'],
        'IVA' => $data['iva'],
        'Forma de Pago' => $data['forma_pago_label'] ?? $data['forma_pago'],
        'Factura' => altaClienteDocumentoImpresionLabel((string)($data['imprime_factura'] ?? 'No')),
        'Email factura' => $data['email_factura'],
        'Albarán' => altaClienteDocumentoImpresionLabel((string)($data['imprime_albaran'] ?? 'No')),
        'Email albarán' => $data['email_albaran'],
        'Email pedido' => $data['email_pedido'],
        'Email presupuesto' => $data['email_presupuesto'],
        'Banco' => $data['banco'],
        'Cuenta' => $data['cuenta'],
        'Acceso Web' => $data['accesoWeb'] ? 'SI' : 'NO',
        'Acepta comunicaciones' => $data['acepta_comunicaciones'] ? 'SI' : 'NO',
        'Fecha consentimiento' => $data['acepta_comunicaciones'] ? date('Y-m-d H:i:s') : '',
        'Comentarios' => $data['comentarios'],
        'Fecha de Alta' => date('Y-m-d H:i:s'),
    ];
}

function altaClienteBuildEmailBody(array $data): string
{
    $campos = altaClienteBuildEmailFields($data);
    $mensaje = "Datos recibidos del formulario de alta:\n\n";
    foreach ($campos as $clave => $valor) {
        $mensaje .= $clave . ': ' . $valor . "\n";
    }

    $contactos = $data['contactos'] ?? [];
    if (is_array($contactos) && $contactos !== []) {
        $lineasContactos = [];
        foreach ($contactos as $contacto) {
            if (!is_array($contacto)) {
                continue;
            }
            $partes = [];
            foreach ([
                'Nombre' => (string)($contacto['nombre'] ?? ''),
                'Departamento' => (string)($contacto['departamento'] ?? ''),
                'Cargo' => (string)($contacto['cargo'] ?? ''),
                'Teléfono' => (string)($contacto['telefono'] ?? ''),
                'Móvil' => (string)($contacto['movil'] ?? ''),
                'Email' => (string)($contacto['email'] ?? ''),
            ] as $clave => $valor) {
                $valor = trim($valor);
                if ($valor !== '') {
                    $partes[] = $clave . ': ' . $valor;
                }
            }

            if ($partes !== []) {
                $lineasContactos[] = '- ' . implode(' | ', $partes);
            }
        }

        if ($lineasContactos !== []) {
            $mensaje .= "\nCONTACTOS:\n" . implode("\n", $lineasContactos) . "\n";
        }
    }

    return $mensaje;
}

function altaClienteBuildEmailSection(string $title, array $fields): string
{
    $rows = '';
    foreach ($fields as $label => $value) {
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }

        $rows .= '<tr>'
            . '<td style="width: 220px; padding: 10px 14px; border-bottom: 1px solid #e7ebf0; color: #52606d; font-weight: 600;">' . altaClienteEmailEsc((string)$label) . '</td>'
            . '<td style="padding: 10px 14px; border-bottom: 1px solid #e7ebf0; color: #102a43;">' . nl2br(altaClienteEmailEsc($value)) . '</td>'
            . '</tr>';
    }

    if ($rows === '') {
        return '';
    }

    return '<div style="margin: 0 0 18px;">'
        . '<div style="padding: 0 0 8px; color: #1f3c88; font-size: 15px; font-weight: 700;">' . altaClienteEmailEsc($title) . '</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e7ebf0; border-radius: 10px; overflow: hidden; background: #ffffff;">'
        . $rows
        . '</table>'
        . '</div>';
}

function altaClienteBuildEmailContactosHtml(array $contactos): string
{
    $rows = '';
    foreach ($contactos as $contacto) {
        if (!is_array($contacto)) {
            continue;
        }

        $nombre = trim((string)($contacto['nombre'] ?? ''));
        $departamento = trim((string)($contacto['departamento'] ?? ''));
        $cargo = trim((string)($contacto['cargo'] ?? ''));
        $telefono = trim((string)($contacto['telefono'] ?? ''));
        $movil = trim((string)($contacto['movil'] ?? ''));
        $email = trim((string)($contacto['email'] ?? ''));

        if ($nombre === '' && $departamento === '' && $cargo === '' && $telefono === '' && $movil === '' && $email === '') {
            continue;
        }

        $rows .= '<tr>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($nombre) . '</td>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($departamento) . '</td>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($cargo) . '</td>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($telefono) . '</td>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($movil) . '</td>'
            . '<td style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">' . altaClienteEmailEsc($email) . '</td>'
            . '</tr>';
    }

    if ($rows === '') {
        return '';
    }

    return '<div style="margin: 0 0 18px;">'
        . '<div style="padding: 0 0 8px; color: #1f3c88; font-size: 15px; font-weight: 700;">Contactos</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e7ebf0; border-radius: 10px; overflow: hidden; background: #ffffff; border-collapse: collapse;">'
        . '<thead>'
        . '<tr style="background: #f6f9fc; color: #334e68; text-align: left;">'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Nombre</th>'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Departamento</th>'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Cargo</th>'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Teléfono</th>'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Móvil</th>'
        . '<th style="padding: 10px 12px; border-bottom: 1px solid #e7ebf0;">Email</th>'
        . '</tr>'
        . '</thead>'
        . '<tbody>'
        . $rows
        . '</tbody>'
        . '</table>'
        . '</div>';
}

function altaClienteBuildEmailHtml(array $data): string
{
    $documentos = [
        'Factura' => altaClienteDocumentoImpresionLabel((string)($data['imprime_factura'] ?? 'No')) . (($data['email_factura'] ?? '') !== '' ? ' | Email: ' . (string)$data['email_factura'] : ''),
        'Albarán' => altaClienteDocumentoImpresionLabel((string)($data['imprime_albaran'] ?? 'No')) . (($data['email_albaran'] ?? '') !== '' ? ' | Email: ' . (string)$data['email_albaran'] : ''),
        'Pedido' => (string)($data['email_pedido'] ?? ''),
        'Presupuesto' => (string)($data['email_presupuesto'] ?? ''),
    ];

    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body style="margin:0; padding:24px; background:#ffffff; font-family:Segoe UI, Arial, sans-serif; color:#102a43;">'
        . '<div style="max-width:900px; margin:0 auto;">'
        . '<div style="background:#ffffff; border:1px solid #d9e2ec; border-radius:14px; padding:24px 28px; box-shadow:0 6px 18px rgba(15,23,42,0.05);">'
        . '<div style="margin:0 0 18px;">'
        . '<div style="font-size:24px; font-weight:700; color:#102a43;">Alta de nuevo cliente</div>'
        . '<div style="margin-top:6px; color:#52606d; font-size:14px;">Solicitud recibida desde la web interna de Comaferr.</div>'
        . '</div>';

    $html .= altaClienteBuildEmailSection('Resumen', [
        'Fecha de alta' => date('Y-m-d H:i:s'),
        'Vendedor' => (string)($data['vendedor_asociado_nombre'] ?? $data['cod_vendedor_asociado'] ?? ''),
        'Acceso Web' => $data['accesoWeb'] ? 'SI' : 'NO',
        'Acepta comunicaciones' => $data['acepta_comunicaciones'] ? 'SI' : 'NO',
    ]);

    $html .= altaClienteBuildEmailSection('Datos del cliente', [
        'Nombre comercial' => (string)($data['empresa'] ?? ''),
        'Razón social' => (string)($data['razon_social'] ?? ''),
        'NIF' => (string)($data['nif'] ?? ''),
        'Tipo de empresa' => (string)($data['tipo_empresa'] ?? ''),
        'Tipo de cliente' => (string)($data['tipo_cliente_label'] ?? $data['tipo_cliente'] ?? ''),
        'Tarifa' => (string)($data['tarifa_label'] ?? $data['tarifa'] ?? ''),
        'Teléfono' => (string)($data['telefono'] ?? ''),
        'Email' => (string)($data['email'] ?? ''),
        'Web' => (string)($data['web'] ?? ''),
    ]);

    $html .= altaClienteBuildEmailContactosHtml((array)($data['contactos'] ?? []));

    $html .= altaClienteBuildEmailSection('Direcciones', [
        'Dirección comercial' => (string)($data['direccion_comercial'] ?? ''),
        'Dirección logística' => (string)($data['direccion_logistica'] ?? ''),
        'Población' => (string)($data['poblacion'] ?? ''),
        'Provincia' => (string)($data['provincia'] ?? ''),
        'CP' => (string)($data['cp'] ?? ''),
    ]);

    $html .= altaClienteBuildEmailSection('Condiciones comerciales', [
        'IVA' => (string)($data['iva'] ?? ''),
        'Forma de pago' => (string)($data['forma_pago_label'] ?? $data['forma_pago'] ?? ''),
        'Banco' => (string)($data['banco'] ?? ''),
        'Cuenta' => (string)($data['cuenta'] ?? ''),
    ]);

    $html .= altaClienteBuildEmailSection('Documentos', $documentos);
    $html .= altaClienteBuildEmailSection('Comentarios', [
        'Observaciones' => (string)($data['comentarios'] ?? ''),
    ]);

    $html .= '</div></div></body></html>';

    return $html;
}

function altaClienteProcessSubmission(array $data): array
{
    require_once BASE_PATH . '/app/Modules/AltaClientes/mail_config.php';

    $data = altaClienteEnrichEmailData($data);
    $mensaje = altaClienteBuildEmailBody($data);
    $mensajeHtml = altaClienteBuildEmailHtml($data);
    $mailchimpLogPath = altaClienteMailchimpLogPath();
    $altaClientesErrorLogPath = altaClienteErrorLogPath();

    try {
        $mail = configurarMailer();
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->addAddress('clientes@comaferr.es', 'Clientes Comaferr');
        $mail->addBCC('amolero@comaferr.es', 'Antonio Molero');
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Alta de nuevo cliente desde la web') . '?=';
        $mail->Body = $mensajeHtml;
        $mail->AltBody = $mensaje;
        $mail->send();

        if ($data['acepta_comunicaciones']) {
            $emailToSubscribe = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
            if ($emailToSubscribe) {
                try {
                    require_once BASE_PATH . '/app/Modules/AltaClientes/mailchimp_sdk_subscribe.php';

                    $merge = ['FNAME' => $data['empresa']];
                    if ($data['nif'] !== '') {
                        $merge['NIF'] = $data['nif'];
                    }

                    $doubleOptIn = strtolower((string)(appConfigValue('MAILCHIMP_DOUBLE_OPTIN', 'false') ?? 'false')) === 'true';
                    $res = subscribeWithSdk($emailToSubscribe, $merge, $doubleOptIn);
                    file_put_contents($mailchimpLogPath, date('c') . ' - Mailchimp SDK: ' . json_encode($res) . PHP_EOL, FILE_APPEND);
                } catch (\Throwable $ex) {
                    file_put_contents($mailchimpLogPath, date('c') . ' - Mailchimp error (incluir/ejecutar): ' . $ex->getMessage() . PHP_EOL, FILE_APPEND);
                }
            } else {
                file_put_contents($mailchimpLogPath, date('c') . ' - Mailchimp: email invalido: ' . ($data['email'] ?? '') . PHP_EOL, FILE_APPEND);
            }
        }

        return [
            'ok' => true,
            'message' => 'Formulario enviado correctamente.',
        ];
    } catch (PHPMailerException|\RuntimeException|\Throwable $e) {
        $errorInfo = isset($mail) && property_exists($mail, 'ErrorInfo') ? (string)$mail->ErrorInfo : '';
        $detalle = $errorInfo !== '' ? $errorInfo : $e->getMessage();
        file_put_contents(
            $altaClientesErrorLogPath,
            date('Y-m-d H:i:s') . ' - Error alta cliente: ' . $detalle . PHP_EOL,
            FILE_APPEND
        );

        $mensajeUsuario = 'Error al enviar el correo. Revisa los logs.';
        if (stripos($detalle, 'SMTP no configurado') !== false) {
            $mensajeUsuario = 'No está configurado el correo saliente (SMTP) en este entorno. Hay que indicar APP_SMTP_HOST, APP_SMTP_USER y APP_SMTP_PASSWORD antes de probar el envío.';
        } elseif (stripos($detalle, 'Could not connect to SMTP host') !== false || stripos($detalle, 'Failed to connect to server') !== false || stripos($detalle, '10061') !== false) {
            $mensajeUsuario = 'No se ha podido conectar con el servidor SMTP configurado. Revisa host, puerto, seguridad y credenciales.';
        }

        return [
            'ok' => false,
            'message' => $mensajeUsuario,
        ];
    }
}
