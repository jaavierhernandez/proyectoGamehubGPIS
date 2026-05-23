<?php
// ============================================================
//  GAME-HUB & SERVICES ECOSYSTEM
//  config.php — Carga de WordPress y funciones helpers
//  WBS-3.1.2  Desarrollo del código Backend / API
//  Asignado a : Tomás
//
//  ESTRATEGIA (v2 WordPress):
//  Todos los archivos cargan WordPress mediante wp-load.php.
//  Usuarios, posts y comentarios se gestionan con la API de WP.
//  Las tablas custom (wp_gh_voto, wp_gh_ranking, wp_gh_evento,
//  wp_gh_multimedia) siguen accesibles via $wpdb.
// ============================================================

// ── CARGAR WORDPRESS ─────────────────────────────────────────
// Subimos niveles desde /wp-content/themes/gamehub/backend/
// hasta la raíz de WordPress donde está wp-load.php
$wp_load = __DIR__ . '/../../../../wp-load.php';

if (!file_exists($wp_load)) {
    http_response_code(500);
    die(json_encode([
        'ok'      => false,
        'mensaje' => 'No se encontró wp-load.php. Revisa la ruta en config.php.'
    ]));
}

require_once $wp_load;

// ── CONSTANTES DE CONFIGURACIÓN ──────────────────────────────
// Número máximo de intentos de login antes de bloquear
define('GH_MAX_LOGIN_INTENTOS', 5);

// Mapeo de roles del proyecto → roles nativos de WordPress
// (administrador→administrator, redactor→editor,
//  colaborador→author, suscriptor→subscriber)
define('GH_ROLES', [
    'administrador' => 'administrator',
    'redactor'      => 'editor',
    'colaborador'   => 'author',
    'suscriptor'    => 'subscriber',
]);

// ── HELPERS ───────────────────────────────────────────────────

/**
 * Devuelve la respuesta en JSON y termina la ejecución.
 */
function gh_responder(bool $ok, string $mensaje, array $datos = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['ok' => $ok, 'mensaje' => $mensaje], $datos),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Verifica que la petición sea del método HTTP indicado.
 * Si no coincide, termina con 405 Method Not Allowed.
 */
function gh_verificar_metodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($metodo)) {
        http_response_code(405);
        gh_responder(false, 'Método no permitido.');
    }
}

/**
 * Verifica que el tamaño de la petición no exceda el límite.
 * Límite máximo: 1 MB para evitar DoS.
 */
function gh_verificar_tamaño_peticion(int $max_bytes = 1048576): void
{
    $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    
    if ($content_length > $max_bytes) {
        http_response_code(413);
        gh_responder(false, 'La solicitud es demasiado grande. Límite máximo: ' . ($max_bytes / 1024 / 1024) . ' MB.');
    }
}

/**
 * Recoge los datos del cuerpo de la petición.
 * Soporta JSON (fetch) y formularios HTML (POST).
 */
function gh_datos_peticion(): array
{
    $contenido = file_get_contents('php://input');
    
    // Intentar decodificar como JSON
    if (!empty($contenido)) {
        $json = json_decode($contenido, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }
    }
    
    // Si no es JSON válido, intentar con $_POST
    return is_array($_POST) ? $_POST : [];
}

/**
 * Comprueba que hay un usuario de WordPress con sesión activa
 * y que tiene uno de los roles (del proyecto) indicados.
 * Devuelve el objeto WP_User o termina con 401/403.
 *
 * @param string[] $roles_proyecto  Roles del proyecto:
 *   'administrador', 'redactor', 'colaborador', 'suscriptor'
 */
function gh_requerir_rol(array $roles_proyecto): WP_User
{
    // ¿Está el usuario logueado en WordPress?
    if (!is_user_logged_in()) {
        http_response_code(401);
        gh_responder(false, 'Debes iniciar sesión para realizar esta acción.');
    }

    $usuario = wp_get_current_user();

    // Convertir roles del proyecto a roles de WordPress
    $roles_wp = array_map(
        fn($r) => GH_ROLES[$r] ?? $r,
        $roles_proyecto
    );

    // Comprobar si el usuario tiene alguno de esos roles
    $tiene_rol = !empty(array_intersect($usuario->roles, $roles_wp));

    if (!$tiene_rol) {
        http_response_code(403);
        gh_responder(false, 'No tienes permisos para realizar esta acción.');
    }

    return $usuario;
}
