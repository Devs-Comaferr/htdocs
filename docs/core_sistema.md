# Nucleo tecnico del sistema (CORE)

Este documento define los archivos que forman el nucleo estable del proyecto.
Estos archivos no deben eliminarse ni modificarse estructuralmente sin revision.

---

## 1. Autenticacion

Archivos responsables de control de acceso y sesiones.

- includes/auth_bootstrap.php
- includes/control_acceso.php
- login.php
- procesar_login.php
- logout.php

---

## 2. Conexion a base de datos

- config/db_connection.php

---

## 3. Funciones globales

- includes/funciones.php
- funciones.php (wrapper de compatibilidad)

---

## 4. Layout compartido

- header.php

---

## 5. Modulos base de logica

Funciones utilizadas por varios modulos del sistema.

- includes/funciones_estadisticas.php
- funciones_planificacion_rutas.php

---

## 6. Elementos NO CORE

Archivos que no forman parte del nucleo estructural:

- carpetas legacy/*
- backups/*
- storage/_manual_tests/*
- includes/bootstrap/*
- includes/db.php (capa DB alternativa no adoptada)

---

## 7. Notas

El objetivo de este documento es evitar que futuras limpiezas o refactors eliminen componentes criticos del sistema.
