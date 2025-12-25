# Windsurf MVC (Slim Framework v4 + Eloquent)

Aplicación base en **PHP 8+** usando:

- Slim Framework v4
- Twig (vistas)
- Eloquent ORM (Illuminate Database)

Arquitectura orientada a MVC (Controllers + Views + Models).

## Requisitos

- PHP 8.0+
- Composer
- (Opcional) MySQL/MariaDB si vas a usar modelos con Eloquent
- Laragon/Apache (recomendado) o servidor embebido de PHP

## Instalación

1) Instala dependencias:

```bash
composer install
```

2) Variables de entorno:

Ya existe un archivo `.env` en la raíz. Ajusta al menos la configuración de base de datos si vas a usarla:

- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## Ejecutar la app

### Opción A: Laragon / Apache (recomendado)

El proyecto incluye `.htaccess` en:

- `/.htaccess` (redirige todo hacia `public/` y bloquea acceso a rutas sensibles)
- `/public/.htaccess` (front-controller hacia `public/index.php`)

Requisitos Apache:

- `mod_rewrite` habilitado
- `AllowOverride All` en el VirtualHost

Luego, abre el dominio que te asigne Laragon (por ejemplo):

- `http://windsurf-project.test/`

### Opción B: Servidor embebido de PHP

Desde la raíz del proyecto:

```bash
php -S localhost:8080 -t public
```

Luego abre:

- `http://localhost:8080/`
- `http://localhost:8080/welcome/TuNombre`

## Rutas disponibles

- `GET /` Home
- `GET /welcome/{name}` Página de bienvenida

## Estructura del proyecto

- `public/index.php` Entrada de la aplicación (Front Controller)
- `config/app.php` Bootstrap de Slim + Twig + Eloquent (retorna `$app`)
- `routes/web.php` Rutas web
- `app/Controllers` Controladores
- `app/Models` Modelos (pendiente de agregar ejemplos)
- `resources/views` Vistas Twig

## Notas de seguridad (base)

- La carpeta `vendor/` y archivos sensibles se bloquean vía `.htaccess` si el DocumentRoot apunta a la raíz.
- Para producción, configura `APP_DEBUG=false`.

## Próximos pasos sugeridos

- Agregar `app/Models` con un ejemplo y migraciones (si quieres, puedo dejarte un comando/estructura simple).
- Agregar middleware de sesión/CSRF.
- Agregar validación de input y manejo centralizado de errores.
