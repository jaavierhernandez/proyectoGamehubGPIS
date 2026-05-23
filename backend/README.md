# GAME-HUB — Backend PHP (WBS-3.1.2) — v2 WordPress
**Asignado a: Tomás**

---

## Archivos entregados

| Archivo | Caso de uso | Descripción |
|---|---|---|
| `config.php` | — | Carga WP + helpers compartidos |
| `registro.php` | CU-01 | Registro de usuario con `wp_insert_user()` |
| `login.php` | CU-02 | Login con `wp_signon()` + control de intentos |
| `publicar_noticia.php` | CU-03 | Publicar noticia con `wp_insert_post()` |
| `comentar.php` | CU-04 | Comentar con `wp_new_comment()` |
| `logout.php` | — | Cerrar sesión con `wp_logout()` |

---

## Dónde colocar los archivos en LocalWP

Copiar la carpeta `backend_gamehub/` dentro del tema activo de WordPress:

```
wp-content/
└── themes/
    └── gamehub/          ← tema del proyecto
        └── backend_gamehub/
            ├── config.php
            ├── registro.php
            ├── login.php
            ├── publicar_noticia.php
            ├── comentar.php
            └── logout.php
```

> La ruta en `config.php` sube 4 niveles (`../../../../wp-load.php`) para
> llegar a la raíz de WordPress. Si cambias la ubicación de la carpeta,
> ajusta esa ruta.

---

## Mapeo de roles del proyecto → WordPress

| Proyecto | WordPress | Puede publicar noticias |
|---|---|---|
| Administrador | administrator | ✅ |
| Redactor | editor | ✅ |
| Colaborador | author | ❌ |
| Suscriptor | subscriber | ❌ |

---

## Cómo llamar a cada endpoint desde el frontend (fetch JS)

### CU-01 — Registro
```javascript
fetch('/wp-content/themes/gamehub/backend_gamehub/registro.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    nombre:   'Ana García',
    email:    'ana@ejemplo.com',
    password: 'contraseña123',
    alias:    'ana_g'
  })
}).then(r => r.json()).then(data => {
  if (data.ok) window.location.href = 'login.php';
  else console.error(data.errores);
});
```

### CU-02 — Login
```javascript
fetch('/wp-content/themes/gamehub/backend_gamehub/login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ login: 'ana_g', password: 'contraseña123' })
}).then(r => r.json()).then(data => {
  if (data.ok) window.location.href = data.redirigir;
});
```

### CU-03 — Publicar noticia (solo Redactor/Administrador)
```javascript
fetch('/wp-content/themes/gamehub/backend_gamehub/publicar_noticia.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    titulo:    'Nuevo juego anunciado',
    contenido: 'Texto de la noticia...',
    estado:    'publicada',           // 'borrador' | 'publicada' | 'archivada'
    etiquetas: ['RPG', 'Acción'],    // nombres de etiqueta (WP las crea si no existen)
    imagen_url: 'https://...'        // opcional
  })
}).then(r => r.json()).then(data => console.log(data));
```

### CU-04 — Comentar
```javascript
fetch('/wp-content/themes/gamehub/backend_gamehub/comentar.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    id_noticia: 5,    // ID del post en WordPress
    texto: 'Me parece muy interesante esta noticia.'
  })
}).then(r => r.json()).then(data => console.log(data));
```

---

## Seguridad implementada (RNF03)

- ✅ Contraseñas: hash gestionado por WordPress internamente
- ✅ Consultas: API de WordPress usa prepared statements
- ✅ Validación doble en todos los endpoints
- ✅ Control de roles: `current_user_can()` y `gh_requerir_rol()`
- ✅ Bloqueo por intentos: máx. 5 intentos, usando Transients de WP
- ✅ XSS: `sanitize_text_field()`, `wp_kses_post()`, `esc_url_raw()`
- ✅ Cookies: `wp_set_auth_cookie()` con HttpOnly gestionado por WP
