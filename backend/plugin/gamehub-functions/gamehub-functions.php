<?php
/**
 * Plugin Name: GameHub Functions
 * Description: Funciones AJAX del backend GameHub
 * Version: 1.0
 */

add_action('wp_ajax_gh_mis_votos', function() {
    global $wpdb;
    $user_id = get_current_user_id();
    if (!$user_id) { wp_send_json(['votos' => []]); }
    $votos = $wpdb->get_results($wpdb->prepare("
        SELECT v.id_videojuego, v.puntuacion, p.post_title as nombre
        FROM wp_gh_voto v
        JOIN wp_posts p ON p.ID = v.id_videojuego
        WHERE v.id_usuario = %d
        ORDER BY v.puntuacion DESC
    ", $user_id));
    foreach ($votos as &$v) {
        $thumb = get_the_post_thumbnail_url($v->id_videojuego, 'medium');
        $v->img = $thumb ?: '';
    }
    wp_send_json(['votos' => $votos]);
});

add_action('wp_ajax_gh_get_role', function() {
    $role = 'none';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $role = in_array('administrator', $user->roles) ? 'admin' : 'subscriber';
    }
    wp_send_json(['role' => $role]);
});

add_action('wp_ajax_nopriv_gh_get_role', function() {
    wp_send_json(['role' => 'none']);
});