<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  publicar_noticia.php — CU-03: Publicar noticia
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
//
//  ESTRATEGIA (v2 WordPress):
//  Las noticias son posts de WordPress (post_type = 'post').
//  Se usa wp_insert_post() para crearlas en wp_posts.
//  Las etiquetas se asignan con wp_set_post_tags().
//  La verificación de rol usa current_user_can('publish_posts'):
//    → administrator y editor tienen esta capacidad en WP.
//    → equivale a 'administrador' y 'redactor' del proyecto.
//
//  Flujo CU-03:
//    1. Verificar sesión activa y rol (redactor o administrador).
//    2. Recoger y validar título, contenido, imagen, estado.
//    3. Insertar el post con wp_insert_post().
//    4. Asignar etiquetas con wp_set_post_tags().
//    5. Si hay imagen URL, guardarla en postmeta.
//    6. Devolver confirmación JSON.
// ============================================================

require_once __DIR__ . '/config.php';

gh_verificar_metodo('POST');
gh_verificar_tamaño_peticion();

// ── 1. AUTENTICACIÓN Y AUTORIZACIÓN ──────────────────────────
// Solo redactor (editor en WP) y administrador pueden publicar
// current_user_can('publish_posts') cubre ambos roles en WP
if (!is_user_logged_in()) {
    http_response_code(401);
    gh_responder(false, 'Debes iniciar sesión para realizar esta acción.');
}

if (!current_user_can('publish_posts')) {
    http_response_code(403);
    gh_responder(false, 'No tienes permisos para publicar noticias. Se requiere rol Redactor o Administrador.');
}

$autor = wp_get_current_user();

// ── 2. RECOGIDA Y VALIDACIÓN DE DATOS ────────────────────────
$datos               = gh_datos_peticion();
$titulo              = trim($datos['titulo']    ?? '');
$contenido           = trim($datos['contenido'] ?? '');
$imagen_url          = trim($datos['imagen_url'] ?? '');
$estado              = trim($datos['estado']    ?? 'borrador');
$comentarios_activos = isset($datos['comentarios_activos'])
                          ? (bool)$datos['comentarios_activos']
                          : true;
$etiquetas           = $datos['etiquetas'] ?? [];   // array de nombres de etiqueta

$errores = [];

if ($titulo === '') {
    $errores[] = 'El título es obligatorio.';
} elseif (strlen($titulo) > 255) {
    $errores[] = 'El título no puede superar los 255 caracteres.';
}

if ($contenido === '') {
    $errores[] = 'El contenido de la noticia es obligatorio.';
}

// Mapear estado del proyecto → post_status de WordPress
$mapa_estado = [
    'borrador'   => 'draft',
    'publicada'  => 'publish',
    'archivada'  => 'private',
];

if (!array_key_exists($estado, $mapa_estado)) {
    $errores[] = 'Estado no válido. Usa: borrador, publicada o archivada.';
}

if ($imagen_url !== '' && !filter_var($imagen_url, FILTER_VALIDATE_URL)) {
    $errores[] = 'La URL de la imagen no tiene un formato válido.';
}

if (!is_array($etiquetas)) {
    $errores[] = 'Las etiquetas deben ser un array.';
}

if (!empty($errores)) {
    http_response_code(422);
    gh_responder(false, 'Hay errores en el formulario.', ['errores' => $errores]);
}

// ── 3. INSERTAR POST EN WORDPRESS ────────────────────────────
$post_status     = $mapa_estado[$estado];
$comment_status  = $comentarios_activos ? 'open' : 'closed';

$id_post = wp_insert_post([
    'post_title'     => sanitize_text_field($titulo),
    'post_content'   => wp_kses_post($contenido),  // permite HTML seguro
    'post_status'    => $post_status,
    'post_type'      => 'post',                    // tipo nativo de WordPress
    'post_author'    => $autor->ID,
    'comment_status' => $comment_status,
], true); // true → devuelve WP_Error en caso de fallo

// ── 4. COMPROBAR RESULTADO ────────────────────────────────────
if (is_wp_error($id_post)) {
    http_response_code(500);
    gh_responder(false, 'Error al guardar la noticia: ' . $id_post->get_error_message());
}

// ── 5. ASIGNAR ETIQUETAS ──────────────────────────────────────
// wp_set_post_tags() crea las etiquetas si no existen
// y las asigna al post (relación N:M en wp_term_relationships)
if (!empty($etiquetas)) {
    $etiquetas_limpias = array_map('sanitize_text_field', (array)$etiquetas);
    wp_set_post_tags($id_post, $etiquetas_limpias, false);
}

// ── 6. GUARDAR URL DE IMAGEN EN POSTMETA ─────────────────────
// WordPress usa wp_postmeta para campos extra del post
if ($imagen_url !== '') {
    update_post_meta($id_post, '_gh_imagen_url', esc_url_raw($imagen_url));
}

// ── 7. RESPUESTA ──────────────────────────────────────────────
$codigo_http = ($estado === 'publicada') ? 201 : 200;
$mensaje     = ($estado === 'publicada')
    ? 'Noticia publicada correctamente.'
    : 'Noticia guardada como borrador.';

http_response_code($codigo_http);
gh_responder(true, $mensaje, [
    'noticia' => [
        'id'     => $id_post,
        'titulo' => $titulo,
        'estado' => $estado,
        'autor'  => $autor->user_login,
    ]
]);
