<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  registro.php — CU-01: Registrarse
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
//
//  ESTRATEGIA (v2 WordPress):
//  Se usa wp_insert_user() para crear el usuario en wp_users
//  y wp_usermeta. El rol asignado por defecto es 'subscriber'
//  (equivalente a 'suscriptor' en el proyecto).
//
//  Flujo CU-01:
//    1. Recoger nombre, email, contraseña y alias (login).
//    2. Validar campos en servidor.
//    3. Comprobar que email y alias no existan ya en WP.
//    4. Crear usuario con wp_insert_user() → rol subscriber.
//    5. Guardar alias como display_name y en usermeta.
//    6. Devolver confirmación JSON.
// ============================================================

require_once __DIR__ . '/config.php';

gh_verificar_metodo('POST');
gh_verificar_tamaño_peticion();

// ── 1. RECOGIDA DE DATOS ──────────────────────────────────────
$datos    = gh_datos_peticion();
$nombre   = trim($datos['nombre']   ?? '');
$email    = trim($datos['email']    ?? '');
$password = trim($datos['password'] ?? '');
$alias    = trim($datos['alias']    ?? '');  // será el user_login en WP

// ── 2. VALIDACIÓN EN SERVIDOR ─────────────────────────────────
$errores = [];

if ($nombre === '') {
    $errores[] = 'El nombre es obligatorio.';
} elseif (strlen($nombre) > 100) {
    $errores[] = 'El nombre no puede superar los 100 caracteres.';
}

if ($email === '') {
    $errores[] = 'El email es obligatorio.';
} elseif (!is_email($email)) {
    // is_email() es la función nativa de WordPress (más estricta que filter_var)
    $errores[] = 'El formato del email no es válido.';
}

if ($password === '') {
    $errores[] = 'La contraseña es obligatoria.';
} elseif (strlen($password) < 8) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
}

if ($alias === '') {
    $errores[] = 'El alias es obligatorio.';
} elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,60}$/', $alias)) {
    $errores[] = 'El alias solo puede contener letras, números, guiones y guiones bajos (3-60 caracteres).';
}

if (!empty($errores)) {
    http_response_code(422);
    gh_responder(false, 'Hay errores en el formulario.', ['errores' => $errores]);
}

// ── 3. COMPROBAR QUE EMAIL Y ALIAS NO EXISTEN YA EN WORDPRESS ─
$errores = [];

if (email_exists($email)) {
    $errores[] = 'Ya existe una cuenta registrada con ese email.';
}
if (username_exists($alias)) {
    $errores[] = 'Ese alias ya está en uso, elige otro.';
}

if (!empty($errores)) {
    http_response_code(409);
    gh_responder(false, 'Los datos ya existen en el sistema.', ['errores' => $errores]);
}

// ── 4. CREAR USUARIO CON LA API DE WORDPRESS ──────────────────
// wp_insert_user() se encarga del hash de contraseña,
// la inserción en wp_users y wp_usermeta (rol, capabilities…)
$resultado = wp_insert_user([
    'user_login'   => $alias,          // alias → user_login (único en WP)
    'user_email'   => $email,
    'user_pass'    => $password,       // WP aplica su propio hash internamente
    'display_name' => $nombre,         // nombre visible públicamente
    'role'         => 'subscriber',    // suscriptor por defecto (CU-01)
]);

// ── 5. COMPROBAR RESULTADO ────────────────────────────────────
if (is_wp_error($resultado)) {
    http_response_code(500);
    gh_responder(false, 'Error al crear la cuenta: ' . $resultado->get_error_message());
}

$nuevo_id = (int) $resultado;

// Guardamos el nombre real en usermeta (display_name ya lo guarda WP,
// pero añadimos first_name para el perfil del dashboard)
update_user_meta($nuevo_id, 'first_name', $nombre);

// ── 6. RESPUESTA ──────────────────────────────────────────────
http_response_code(201);
gh_responder(true, '¡Cuenta creada correctamente! Ya puedes iniciar sesión.', [
    'usuario' => [
        'id'    => $nuevo_id,
        'alias' => $alias,
        'rol'   => 'suscriptor',
    ]
]);
