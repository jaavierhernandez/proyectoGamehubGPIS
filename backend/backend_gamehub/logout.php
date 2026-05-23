<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  logout.php — Cerrar sesión
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
// ============================================================

require_once __DIR__ . '/config.php';

// wp_logout() limpia la sesión y elimina las cookies de WP
wp_logout();

gh_responder(true, 'Sesión cerrada correctamente.', ['redirigir' => 'index.php']);
