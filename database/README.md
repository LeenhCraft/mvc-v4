# Database Migrations

Este directorio contiene las migraciones de base de datos del proyecto.

## Estructura

```
database/
├── migrations/         # Archivos de migración
├── seeders/           # Seeders para poblar datos (futuro)
├── MigrationRunner.php # Sistema de migraciones
└── README.md          # Este archivo
```

## Comandos de Migración

### Ejecutar migraciones pendientes
```bash
php migrate
# o
php migrate up
```

### Ver estado de migraciones
```bash
php migrate status
```

### Revertir última migración (batch)
```bash
php migrate rollback
# o
php migrate down
```

### Revertir TODAS las migraciones
```bash
php migrate reset
```

### Reset + Migrate (fresh start)
```bash
php migrate fresh
```

## Crear una Nueva Migración

Las migraciones siguen el formato: `YYYY_MM_DD_HHMMSS_description.php`

Ejemplo: `2024_01_01_000001_create_users_table.php`

### Plantilla de Migración

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return function (string $direction) {
    $schema = Capsule::schema();

    if ($direction === 'up') {
        $schema->create('table_name', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->timestamps();
        });
    }

    if ($direction === 'down') {
        $schema->dropIfExists('table_name');
    }
};
```

## Mejores Prácticas

1. **Nombrado**: Usa nombres descriptivos para las migraciones
2. **Orden**: Usa timestamps en el nombre para mantener el orden
3. **Reversibilidad**: Siempre implementa `up` y `down`
4. **Atomicidad**: Una migración = una tarea específica
5. **No modificar**: Nunca modifiques migraciones ya ejecutadas

## Tipos de Columnas Comunes

```php
$table->bigIncrements('id');              // Auto-increment primary key
$table->string('name', 255);              // VARCHAR(255)
$table->text('description');              // TEXT
$table->integer('count');                 // INTEGER
$table->boolean('is_active');             // BOOLEAN
$table->decimal('price', 8, 2);          // DECIMAL(8,2)
$table->timestamp('created_at');          // TIMESTAMP
$table->timestamps();                     // created_at + updated_at
$table->softDeletes();                    // deleted_at (soft delete)
```

## Foreign Keys

```php
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade');  // o 'set null', 'restrict'
```

## Indexes

```php
$table->index('email');                   // Index simple
$table->unique('email');                  // Unique constraint
$table->index(['user_id', 'created_at']); // Composite index
```
