-- ============================================================
--  GAME-HUB & SERVICES ECOSYSTEM
--  WBS-3.1.3  Creación de scripts de Base de Datos — v2 WordPress
--  Motor      : MySQL 8.0+  (LocalWP)
--  Estrategia : Tablas NATIVAS de WordPress para usuarios,
--               posts (noticias/videojuegos) y comentarios.
--               Solo se crean tablas CUSTOM para lo que WP
--               no cubre: votos, ranking, evento y multimedia.
--
--  TABLAS NATIVAS QUE YA CREA WORDPRESS (NO tocar):
--    wp_users          → clase Usuario
--    wp_usermeta       → metadatos de perfil (alias, rol…)
--    wp_posts          → clase Noticia y Videojuego (CPT)
--    wp_postmeta       → campos extra de Videojuego (nota, anio…)
--    wp_comments       → clase Comentario
--    wp_commentmeta    → metadatos de comentario
--    wp_terms          → clase Etiqueta (taxonomías)
--    wp_term_taxonomy  → tipo de taxonomía (etiqueta, categoría…)
--    wp_term_relationships → relación post ↔ etiqueta (N:M)
-- ============================================================

-- LocalWP ya selecciona la BD automáticamente.
-- Si lo importas desde phpMyAdmin de LocalWP no necesitas
-- el USE; si lo haces desde terminal, descomenta la línea:
-- USE local;

SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================
-- TABLA CUSTOM 1: wp_gh_voto
-- WordPress no tiene sistema de valoraciones numéricas.
-- Suscriptor vota un videojuego (wp_post de tipo 'videojuego').
-- Unicidad: 1 voto por par usuario + videojuego.
-- ============================================================
CREATE TABLE IF NOT EXISTS wp_gh_voto (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_usuario      BIGINT UNSIGNED  NOT NULL,   -- FK → wp_users.ID
    id_videojuego   BIGINT UNSIGNED  NOT NULL,   -- FK → wp_posts.ID (post_type='videojuego')
    puntuacion      TINYINT UNSIGNED NOT NULL,
    fecha           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_voto_usuario_juego (id_usuario, id_videojuego),
    CONSTRAINT chk_voto_puntuacion
        CHECK (puntuacion >= 1 AND puntuacion <= 10),
    CONSTRAINT fk_voto_usuario
        FOREIGN KEY (id_usuario)    REFERENCES wp_users(ID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_voto_videojuego
        FOREIGN KEY (id_videojuego) REFERENCES wp_posts(ID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Votos de comunidad sobre videojuegos. Custom GameHub.';


-- ============================================================
-- TABLA CUSTOM 2: wp_gh_ranking
-- Almacena la nota media calculada de cada videojuego.
-- Se actualiza con un trigger o desde PHP tras cada voto.
-- Equivale al método actualizar() de la clase Ranking.
-- ============================================================
CREATE TABLE IF NOT EXISTS wp_gh_ranking (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_videojuego   BIGINT UNSIGNED  NOT NULL,   -- FK → wp_posts.ID
    nombre          VARCHAR(255)     NOT NULL,
    nota_media      FLOAT            NULL,        -- recalculada desde wp_gh_voto
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_ranking_juego (id_videojuego),
    CONSTRAINT fk_ranking_videojuego
        FOREIGN KEY (id_videojuego) REFERENCES wp_posts(ID)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ranking de videojuegos con nota media de comunidad. Custom GameHub.';


-- ============================================================
-- TABLA CUSTOM 3: wp_gh_evento
-- WordPress tiene plugins de calendario, pero para mantener
-- control total del modelo definido en el diagrama de clases
-- se crea como tabla propia. El id_creador apunta a wp_users.
-- ============================================================
CREATE TABLE IF NOT EXISTS wp_gh_evento (
    id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_creador  BIGINT UNSIGNED  NOT NULL,        -- FK → wp_users.ID
    nombre      VARCHAR(255)     NOT NULL,
    fecha       DATE             NOT NULL,
    descripcion TEXT             NULL,
    lugar       VARCHAR(255)     NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_evento_creador
        FOREIGN KEY (id_creador) REFERENCES wp_users(ID)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Agenda de eventos gaming (ferias, lanzamientos). Custom GameHub.';


-- ============================================================
-- TABLA CUSTOM 4: wp_gh_multimedia
-- Hub de vídeos/streams. WordPress gestiona adjuntos (wp_posts
-- type=attachment) pero no URLs externas con plataforma.
-- id_videojuego es opcional (puede ser contenido general).
-- ============================================================
CREATE TABLE IF NOT EXISTS wp_gh_multimedia (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_videojuego   BIGINT UNSIGNED  NULL,        -- FK opcional → wp_posts.ID
    id_subido_por   BIGINT UNSIGNED  NULL,        -- FK opcional → wp_users.ID
    url             VARCHAR(500)     NOT NULL,
    plataforma      VARCHAR(80)      NOT NULL,    -- 'YouTube','Twitch','Vimeo'…
    titulo          VARCHAR(255)     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_multimedia_videojuego
        FOREIGN KEY (id_videojuego)  REFERENCES wp_posts(ID)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_multimedia_usuario
        FOREIGN KEY (id_subido_por)  REFERENCES wp_users(ID)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='URLs multimedia externas (trailers, streams). Custom GameHub.';


SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- VISTA: v_gh_nota_media
-- Calcula la nota media de comunidad por videojuego.
-- Tomás puede llamarla desde PHP con una query simple.
-- ============================================================
CREATE OR REPLACE VIEW v_gh_nota_media AS
SELECT
    p.ID                                AS id_videojuego,
    p.post_title                        AS titulo,
    COUNT(v.id)                         AS total_votos,
    ROUND(AVG(v.puntuacion), 2)         AS nota_media_comunidad
FROM  wp_posts p
LEFT JOIN wp_gh_voto v ON v.id_videojuego = p.ID
WHERE p.post_type   = 'videojuego'
  AND p.post_status = 'publish'
GROUP BY p.ID, p.post_title;


-- ============================================================
-- DATOS DE PRUEBA — tablas custom únicamente
-- Los usuarios y posts de prueba se crean desde WordPress
-- (o con WP-CLI), NO con INSERT directos en wp_users/wp_posts
-- para respetar los hashes y metadatos que genera WP.
--
-- INSTRUCCIONES PARA TOMÁS:
--   1. Crear en WP-Admin los usuarios de prueba (4 roles).
--   2. Crear 3 posts de tipo 'videojuego' desde WP-Admin.
--   3. Anotar los IDs generados y sustituir los valores
--      de ejemplo de abajo antes de ejecutar los INSERT.
-- ============================================================

-- Sustituye estos IDs por los reales de tu WordPress local:
-- id_usuario_suscriptor = 4
-- id_videojuego_1       = 10
-- id_videojuego_2       = 11
-- id_videojuego_3       = 12
-- id_creador_admin      = 1

-- Votos de prueba
INSERT INTO wp_gh_voto (id_usuario, id_videojuego, puntuacion) VALUES
(4, 10, 10),
(4, 11,  9),
(4, 12,  8);

-- Ranking inicial (se actualizará con los votos)
INSERT INTO wp_gh_ranking (id_videojuego, nombre) VALUES
(10, 'Top Aventura'),
(11, 'Top RPG'),
(12, 'Top Acción/RPG');

-- Eventos de prueba
INSERT INTO wp_gh_evento (id_creador, nombre, fecha, lugar) VALUES
(1, 'Gamescom 2026',       '2026-08-20', 'Colonia, Alemania'),
(1, 'The Game Awards 2026','2026-12-10', 'Los Ángeles, EE.UU.');

-- Multimedia de prueba
INSERT INTO wp_gh_multimedia (id_videojuego, id_subido_por, url, plataforma, titulo) VALUES
(10, 1, 'https://www.youtube.com/watch?v=ejemplo1', 'YouTube', 'Tráiler de prueba 1'),
(12, 1, 'https://www.youtube.com/watch?v=ejemplo2', 'YouTube', 'Tráiler de prueba 2');


-- ============================================================
-- CÓMO IMPORTAR EN LOCALWP
--   1. Abre LocalWP → tu sitio → "Open Site Shell"
--   2. mysql -u root -p'root' local < script_estructura_gamehub_wp.sql
--   O bien: LocalWP → Adminer/phpMyAdmin → Importar este .sql
--
-- CÓMO REGISTRAR EN GRINDSTONE
--   Tarea : WBS-3.1.3 Creación de scripts de Base de Datos
--   Notes : "Hecha por Vicente. En esta tarea hice el script SQL
--            adaptado a WordPress/LocalWP: 4 tablas custom
--            (wp_gh_voto, wp_gh_ranking, wp_gh_evento,
--            wp_gh_multimedia), vista de nota media y seed
--            de datos de prueba."
-- ============================================================
