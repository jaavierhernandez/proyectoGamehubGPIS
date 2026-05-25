<?php
require_once __DIR__ . '/config.php';
gh_verificar_metodo('POST');
if (!is_user_logged_in()) { http_response_code(401); gh_responder(false, 'No autenticado.'); }
$usuario = wp_get_current_user();
$datos = gh_datos_peticion();
$id_videojuego = (int)($datos['id_videojuego'] ?? 0);
if ($id_videojuego <= 0) { http_response_code(422); gh_responder(false, 'ID no válido.'); }
global $wpdb;
$wpdb->delete('wp_gh_voto', ['id_usuario' => $usuario->ID, 'id_videojuego' => $id_videojuego], ['%d', '%d']);
gh_responder(true, 'Voto eliminado.');