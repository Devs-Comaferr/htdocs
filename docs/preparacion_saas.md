# Preparación SaaS del proyecto APP Comerciales

Analisis arquitectonico orientado a evolucion SaaS para empresas que usen Control Integral.
Alcance: solo revision de codigo y estructura actual. Sin cambios de codigo ni de base de datos.

## 1. Elementos actualmente acoplados a COMAFERR

### Branding y nombre de empresa

- Valor por defecto hardcodeado de nombre del sistema: `COMAFERR`.
  - `funciones.php` (`obtenerConfiguracionApp`)
  - `header.php` (fallback de `nombre_sistema`)
- Branding visual con rutas/logo por defecto fijas:
  - `logo_path` default `/imagenes/logo.png`
  - favicon e iconos en `header.php`
- Referencia explicita en frontend:
  - `altaClientes/alta_cliente.php` (`alt="Logo Comaferr"`).

### Datos de contacto y comunicaciones corporativas

- Correos corporativos hardcodeados:
  - `altaClientes/mail_config.php` (`informatica@comaferr.es`)
  - `altaClientes/alta_cliente.php` (`clientes@comaferr.es`, BCC `amolero@comaferr.es`)
- Nombre de destinatario hardcodeado:
  - `altaClientes/alta_cliente.php` (`Juan Amaya`).
- Texto legal con direccion y contacto de COMAFERR en `altaClientes/alta_cliente.php`.

### Reglas de negocio especificas del cliente actual

- Reglas especiales por comercial concreto (`cod_comercial === '30'`) en:
  - `cliente_detalles.php`
  - `seccion_detalles.php`
  - `historico.php`
- Comentarios/condiciones de bloqueo por persona concreta en varios puntos del dominio comercial.

### Modelo de permisos/planes parcialmente hardcodeado

- Permisos fijados por claves concretas de sesion: `perm_productos`, `perm_estadisticas`, `perm_planificador`, etc.
- Planes fijos `free` / `premium` y validacion especial de `perm_planificador` en `includes/control_acceso.php`.
- Dependencia directa de `rol = admin` en varias pantallas de configuracion.

### Acoplamiento a estructuras de datos concretas

- Uso intensivo de tablas y campos concretos de Control Integral (`hist_ventas_cabecera`, `cmf_visitas_comerciales`, `cmf_asignacion_zonas_clientes`, etc.).
- Dependencia funcional de tablas `cmf_*` propias de esta implantacion (`cmf_vendedores_user`, `cmf_configuracion_app`, etc.).

---

## 2. Elementos que ya son reutilizables

### Autenticacion y control de acceso

- Flujo base reusable:
  - `login.php` -> `procesar_login.php` -> sesion -> `includes/auth_bootstrap.php` -> `includes/control_acceso.php`.
- API de control de acceso clara (`requiereLogin`, `requiereActivo`, `requierePermiso`, `requierePremium`).

### Navegacion y layout modular

- `header.php` centraliza navegacion y puntos de entrada por modulo.
- Estructura de modulos funcionales separada por archivos/paginas: visitas, calendario, rutas, clientes, productos, estadisticas, configuracion.

### Capa funcional reutilizable por dominio

- `includes/funciones.php` concentra utilidades comunes.
- `includes/funciones_estadisticas.php` concentra logica estadistica especializada.
- `funciones_planificacion_rutas.php` concentra reglas de planificacion/asignacion.

### Configuracion de aplicacion (inicio de multi-tenant basico)

- `configuracion/aplicacion.php` + `obtenerConfiguracionApp()` permiten parametrizar:
  - `nombre_sistema`
  - `color_primary`
  - `logo_path`
- Esta via reduce hardcode en UI y es base valida para branding por cliente.

### Capa AJAX y endpoints por responsabilidad

- Endpoints por dominio relativamente claros (`ajax/estadisticas_*`, `get_*`, `obtener_*`, etc.).
- Facilita encapsular contratos de datos por modulo sin romper toda la UI.

---

## 3. Riesgos para evolucionar a SaaS

- Archivos grandes con responsabilidades mezcladas (presentacion + SQL + reglas + control de flujo).
- Reglas de negocio especificas incrustadas (por ejemplo filtros por `cod_comercial` concreto).
- Textos corporativos y datos de contacto hardcodeados en formularios/plantillas.
- Modelo de permisos poco abstracto (claves de sesion acopladas a permisos concretos).
- Dependencia fuerte a nomenclatura/tablas concretas del ERP y a extensiones `cmf_*` actuales.
- Arquitectura hibrida (core activo + legacy + capas alternativas parcialmente adoptadas).
- Endpoints con heterogeneidad en proteccion/autorizacion y varios utilitarios sin uso claro.

---

## 4. Recomendaciones de arquitectura para evolucion SaaS

Sin implementacion en esta fase; lineas de evolucion recomendadas:

- Separar configuracion de empresa en una capa de parametros (branding, datos legales, emails, textos de plantilla) con lectura centralizada.
- Centralizar branding y contenido corporativo para eliminar literales de empresa en vistas.
- Parametrizar navegacion y modulos habilitados por tenant (no solo por rol/permiso actual).
- Consolidar permisos en un modelo mas declarativo (matriz modulo/accion), manteniendo compatibilidad con el esquema actual.
- Aislar reglas de negocio especificas en funciones/politicas por cliente (evitar condiciones hardcode tipo `cod_comercial === '30'` en vistas).
- Definir explicitamente nucleo comun SaaS vs capa de personalizacion por cliente.
- Unificar capa de acceso a datos para reducir SQL disperso y facilitar observabilidad/seguridad.
- Mantener restriccion clave del proyecto: evolucionar sin requerir cambios de esquema en Control Integral.

---

## 5. Veredicto

**B) está parcialmente preparada.**

Motivo:

- Ya existe una base reutilizable razonable (auth, permisos, modulos, configuracion visual inicial).
- Pero persiste acoplamiento relevante a COMAFERR y a reglas de negocio locales hardcodeadas.
- Con consolidacion arquitectonica (configuracion por tenant, desacople de reglas especificas y normalizacion de permisos/datos), la base puede evolucionar a producto SaaS reusable.
