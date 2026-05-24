<?php
require_once __DIR__ . '/config.php';

gh_verificar_metodo('POST');
gh_verificar_tamaño_peticion();

if (!is_user_logged_in()) {
    http_response_code(401);
    gh_responder(false, 'Debes iniciar sesión para votar.');
}

$usuario = wp_get_current_user();
$datos = gh_datos_peticion();
$id_videojuego = (int)($datos['id_videojuego'] ?? 0);
$puntuacion = (int)($datos['puntuacion'] ?? 0);

if ($id_videojuego <= 0 || $puntuacion < 1 || $puntuacion > 10) {
    http_response_code(422);
    gh_responder(false, 'Datos no válidos.');
}

global $wpdb;

$existe = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM wp_gh_voto WHERE id_usuario = %d AND id_videojuego = %d",
    $usuario->ID, $id_videojuego
));

if ($existe) {
    http_response_code(409);
    gh_responder(false, 'Ya has votado este videojuego.');
}

$wpdb->insert('wp_gh_voto', [
    'id_usuario'    => $usuario->ID,
    'id_videojuego' => $id_videojuego,
    'puntuacion'    => $puntuacion,
], ['%d', '%d', '%d']);

if ($wpdb->last_error) {
    http_response_code(500);
    gh_responder(false, 'Error al guardar el voto.');
}

$nota_media = $wpdb->get_var($wpdb->prepare(
    "SELECT ROUND(AVG(puntuacion), 2) FROM wp_gh_voto WHERE id_videojuego = %d",
    $id_videojuego
));

gh_responder(true, 'Voto registrado correctamente.', [
    'nota_media' => (float)$nota_media
]);