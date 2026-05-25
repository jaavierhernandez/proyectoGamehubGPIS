<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  comentar.php — CU-04: Comentar en blog
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
//
//  ESTRATEGIA (v2 WordPress):
//  Los comentarios se insertan en wp_comments con wp_new_comment().
//  WordPress gestiona automáticamente la relación con wp_posts
//  y con wp_users (si el usuario está logueado).
//  La moderación usa el campo comment_approved de WordPress.
//
//  Flujo CU-04:
//    1. Verificar que el usuario tiene sesión activa (cualquier rol).
//    2. Recoger id_noticia (= ID del post WP) y texto.
//    3. Validar los datos y el texto (spam básico).
//    4. Verificar que el post existe, está publicado y acepta comentarios.
//    5. Insertar con wp_new_comment().
//    6. Devolver confirmación JSON.
// ============================================================

require_once __DIR__ . '/config.php';

gh_verificar_metodo('POST');
gh_verificar_tamaño_peticion();

// ── 1. VERIFICAR SESIÓN ACTIVA ────────────────────────────────
// Cualquier usuario registrado puede comentar (CU-04)
if (!is_user_logged_in()) {
    http_response_code(401);
    gh_responder(false, 'Debes iniciar sesión para publicar un comentario.');
}

$usuario = wp_get_current_user();

// ── 2. RECOGIDA DE DATOS ──────────────────────────────────────
$datos      = gh_datos_peticion();
$id_noticia = (int)($datos['id_noticia'] ?? 0);
$texto      = trim($datos['texto'] ?? '');

// ── 3. VALIDACIÓN EN SERVIDOR ─────────────────────────────────
$errores = [];

if ($id_noticia <= 0) {
    $errores[] = 'ID de noticia no válido.';
}

if ($texto === '') {
    $errores[] = 'El comentario no puede estar vacío.';
} elseif (strlen($texto) < 3) {
    $errores[] = 'El comentario es demasiado corto (mínimo 3 caracteres).';
} elseif (strlen($texto) > 2000) {
    $errores[] = 'El comentario no puede superar los 2000 caracteres.';
}

// Detección básica de spam: demasiados enlaces (http:// o https://)
$num_enlaces = substr_count(strtolower($texto), 'http://') + substr_count(strtolower($texto), 'https://');
if ($num_enlaces > 2) {
    $errores[] = 'El comentario contiene demasiados enlaces y ha sido marcado como posible spam.';
}

if (!empty($errores)) {
    http_response_code(422);
    gh_responder(false, 'El comentario no es válido.', ['errores' => $errores]);
}

// ── 4. VERIFICAR QUE EL POST EXISTE, ESTÁ PUBLICADO Y ACEPTA COMENTARIOS ─
$post = get_post($id_noticia);

if (!$post) {
    http_response_code(404);
    gh_responder(false, 'La noticia no existe.');
}

if ($post->post_status !== 'publish') {
    http_response_code(403);
    gh_responder(false, 'No puedes comentar en una noticia que no está publicada.');
}

if ($post->comment_status !== 'open') {
    http_response_code(403);
    gh_responder(false, 'Los comentarios están desactivados en esta noticia.');
}

// ── 5. INSERTAR COMENTARIO CON LA API DE WORDPRESS ───────────
// wp_new_comment() inserta en wp_comments y wp_commentmeta,
// aplica los filtros de spam configurados en WP (Akismet, etc.)
// y actualiza el contador de comentarios del post.
$datos_comentario = [
    'comment_post_ID'      => $id_noticia,
    'comment_content'      => wp_kses($texto, []),  // sin HTML (texto plano)
    'user_id'              => $usuario->ID,
    'comment_author'       => $usuario->display_name,
    'comment_author_email' => $usuario->user_email,
    'comment_approved'     => 1,                    // aprobado automáticamente
                                                    // (cambiar a 0 si se quiere moderación)
];

$id_comentario = wp_new_comment($datos_comentario, true); // true → devuelve WP_Error

if (is_wp_error($id_comentario)) {
    http_response_code(500);
    gh_responder(false, 'Error al publicar el comentario: ' . $id_comentario->get_error_message());
}

if ($id_comentario === false) {
    // WordPress devuelve false si detecta un comentario duplicado
    http_response_code(409);
    gh_responder(false, 'Parece que ya publicaste este mismo comentario.');
}

// ── 6. RESPUESTA ──────────────────────────────────────────────
http_response_code(201);
gh_responder(true, 'Comentario publicado correctamente.', [
    'comentario' => [
        'id'         => (int)$id_comentario,
        'id_noticia' => $id_noticia,
        'autor'      => $usuario->display_name,
        'texto'      => $texto,
    ]
]);
