<?php

//  Verifica que haya sesión iniciada
function requiereLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['email'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

//  Verifica que el usuario está activo
function requiereActivo() {
    if (!isset($_SESSION['activo']) || $_SESSION['activo'] != 1) {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

//  Admin tiene acceso total automticamente
function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

//  Verifica permiso especfico (perm_*)
function requierePermiso($permiso) {

    if (esAdmin()) {
        return;
    }

    if (!isset($_SESSION[$permiso]) || $_SESSION[$permiso] != 1) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    // Planificador requiere permiso explicito y plan premium.
    if ($permiso === 'perm_planificador') {
        if (!isset($_SESSION['tipo_plan']) || $_SESSION['tipo_plan'] !== 'premium') {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

//  Verifica plan premium
function requierePremium() {

    if (esAdmin()) {
        return;
    }

    if (!isset($_SESSION['tipo_plan']) || $_SESSION['tipo_plan'] !== 'premium') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}
