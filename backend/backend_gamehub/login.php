<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  login.php — CU-02: Iniciar sesión
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
//
//  ESTRATEGIA (v2 WordPress):
//  Se usa wp_signon() para autenticar contra wp_users.
//  Si tiene éxito, wp_set_auth_cookie() inicia la sesión WP.
//  La redirección se calcula según el rol del usuario.
//
//  Flujo CU-02:
//    1. Recoger email/alias y contraseña.
//    2. Validar campos básicos.
//    3. Controlar intentos fallidos (transients de WP).
//    4. Autenticar con wp_signon().
//    5. Establecer cookie de sesión y redirigir según rol.
// ============================================================

require_once __DIR__ . '/config.php';

gh_verificar_metodo('POST');
gh_verificar_tamaño_peticion();

// ── 1. RECOGIDA DE DATOS ──────────────────────────────────────
$datos         = gh_datos_peticion();
$login         = trim($datos['login']    ?? '');   // acepta email O alias
$password      = trim($datos['password'] ?? '');

// ── 2. VALIDACIÓN BÁSICA ──────────────────────────────────────
$errores = [];
if ($login === '') {
    $errores[] = 'El email o alias es obligatorio.';
}
if ($password === '') {
    $errores[] = 'La contraseña es obligatoria.';
}
if (!empty($errores)) {
    http_response_code(422);
    gh_responder(false, 'Datos incorrectos.', ['errores' => $errores]);
}

// ── 3. CONTROL DE INTENTOS FALLIDOS (con Transients de WP) ───
// Los transients se guardan en wp_options con caducidad automática
$clave_intentos = 'gh_login_intentos_' . md5($login);
$intentos       = (int) get_transient($clave_intentos);

if ($intentos >= GH_MAX_LOGIN_INTENTOS) {
    http_response_code(429);
    gh_responder(false, 'Has superado el número máximo de intentos. Espera unos minutos e inténtalo de nuevo.');
}

// ── 4. AUTENTICACIÓN CON WORDPRESS ───────────────────────────
// wp_signon() acepta tanto user_login como user_email en 'user_login'
$resultado = wp_signon([
    'user_login'    => $login,
    'user_password' => $password,
    'remember'      => false,
], false); // false = no usar cookie segura (HTTP, localhost)

if (is_wp_error($resultado)) {
    // Incrementar contador de intentos (caduca en 15 minutos)
    set_transient($clave_intentos, $intentos + 1, 15 * MINUTE_IN_SECONDS);

    http_response_code(401);
    gh_responder(false, 'Email/alias o contraseña incorrectos.');
}

// ── 5. COMPROBAR QUE LA CUENTA ESTÉ ACTIVA ───────────────────
// En WordPress, las cuentas suspendidas se marcan quitando
// todos los roles (capabilities vacías)
if (empty($resultado->roles)) {
    http_response_code(403);
    gh_responder(false, 'Tu cuenta está suspendida. Contacta con el administrador.');
}

// ── 6. ESTABLECER SESIÓN Y CALCULAR REDIRECCIÓN ──────────────
// Limpiar intentos fallidos
delete_transient($clave_intentos);

// Establecer la cookie de autenticación de WordPress
wp_set_auth_cookie($resultado->ID, false);
wp_set_current_user($resultado->ID);

// Mapeo rol WP → ruta del dashboard del proyecto
$rutas_por_rol = [
    'administrator' => 'dashboard-admin.php',
    'editor'        => 'dashboard-redactor.php',
    'author'        => 'dashboard-colaborador.php',
    'subscriber'    => 'dashboard-suscriptor.php',
];

// Tomar el primer rol del usuario (WP puede tener varios)
$rol_wp      = $resultado->roles[0] ?? 'subscriber';
$redirigir   = $rutas_por_rol[$rol_wp] ?? 'index.php';

// Rol en términos del proyecto (para la respuesta JSON)
$rol_proyecto = array_search($rol_wp, GH_ROLES) ?: 'suscriptor';

gh_responder(true, 'Sesión iniciada correctamente.', [
    'usuario' => [
        'id'    => $resultado->ID,
        'alias' => $resultado->user_login,
        'rol'   => $rol_proyecto,
    ],
    'redirigir' => $redirigir,
]);
