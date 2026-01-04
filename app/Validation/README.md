# Input Validation

Sistema de validación de entrada usando **Respect\Validation**.

## Estructura

```
app/Validation/
├── Validator.php          # Wrapper principal con helpers
├── ValidationException.php # Excepción personalizada
└── README.md             # Este archivo
```

## Uso Básico

### En Controllers (Throwing)

```php
use App\Validation\ValidationException;
use App\Validation\Validator;

class UserController extends BaseController
{
    public function create(Request $request, Response $response): Response
    {
        $data = $this->getBody($request);

        try {
            $this->validate($data, [
                'name' => Validator::string(3, 255),
                'email' => Validator::email(),
                'age' => Validator::integer(true, 18, 120),
            ]);

            // Validación pasó, procesar datos...

        } catch (ValidationException $e) {
            return $this->validationError($response, $e);
        }
    }
}
```

### Validación No-Throwing

```php
$errors = $this->check($data, [
    'name' => Validator::string(3, 255),
    'email' => Validator::email(),
]);

if (!empty($errors)) {
    // Manejar errores manualmente
}
```

## Reglas de Validación Disponibles

### Básicas

```php
// String con longitud
Validator::string(3, 255)           // Min 3, max 255 caracteres
Validator::string(3, 255, false)    // Opcional

// Email
Validator::email()                  // Email requerido
Validator::email(false)            // Email opcional

// Integer
Validator::integer()               // Integer requerido
Validator::integer(true, 1, 100)  // Entre 1 y 100
Validator::integer(false)          // Opcional

// Boolean
Validator::boolean()               // Boolean requerido

// Numeric
Validator::numeric()               // Numérico (string o number)

// URL
Validator::url()                   // URL válida

// Date
Validator::date('Y-m-d')           // Fecha formato específico

// Array
Validator::array()                 // Array requerido
```

### Texto

```php
// Solo letras
Validator::alpha()

// Alfanumérico
Validator::alnum()

// Teléfono (básico)
Validator::phone()
```

### Enumeración

```php
// Valor en lista
Validator::in(['draft', 'published', 'archived'])
```

### Password

```php
// Password fuerte (min 8 chars, uppercase, lowercase, número)
Validator::password()
Validator::password(12)  // Mínimo 12 caracteres
```

### Base de Datos

```php
// Email único (para crear)
Validator::unique('users', 'email')

// Email único excepto ID actual (para actualizar)
Validator::unique('users', 'email', $userId)

// Verificar que existe (foreign key)
Validator::exists('users', 'id')
```

## Validación Compleja

### Encadenar Reglas

```php
use Respect\Validation\Validator as V;

$this->validate($data, [
    'email' => Validator::email()
        ->chain(Validator::unique('users', 'email'))
        ->chain(V::endsWith('@company.com')),
]);
```

### Reglas Personalizadas con Respect\Validation

```php
use Respect\Validation\Validator as V;

$this->validate($data, [
    'username' => V::allOf(
        V::alnum('-_'),
        V::length(3, 20),
        V::startsWith('user_')
    ),
    'tags' => V::arrayType()->each(V::stringType()),
]);
```

### Callback Personalizado

```php
use Respect\Validation\Validator as V;

$this->validate($data, [
    'custom' => V::callback(function($value) {
        return someComplexValidation($value);
    })->setTemplate('Custom validation failed'),
]);
```

## Manejo de Errores

### Estructura de Errores

```json
{
  "error": "Validation failed",
  "message": "Validation failed",
  "errors": {
    "name": [
      "name must have a length between 3 and 255"
    ],
    "email": [
      "email must be valid",
      "This email is already taken"
    ]
  }
}
```

### Métodos de ValidationException

```php
catch (ValidationException $e) {
    $e->getErrors();          // Todos los errores por campo
    $e->getFirstErrors();     // Primer error de cada campo
    $e->hasError('email');    // Check si campo tiene error
    $e->getFieldErrors('email'); // Errores de campo específico
}
```

## Ejemplos Completos

### API Endpoint (JSON)

```php
public function create(Request $request, Response $response): Response
{
    $data = $this->getBody($request);

    try {
        $this->validate($data, [
            'name' => Validator::string(3, 255),
            'email' => Validator::email()
                ->chain(Validator::unique('users', 'email')),
            'password' => Validator::password(8),
            'age' => Validator::integer(true, 18, null),
        ]);

        $user = User::create($data);

        return $this->json($response, [
            'user' => $user
        ], 201);

    } catch (ValidationException $e) {
        return $this->validationError($response, $e);
    }
}
```

### Actualización (Unique con Exclusión)

```php
public function update(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $user = User::findOrFail($id);
    $data = $this->getBody($request);

    try {
        $this->validate($data, [
            'email' => Validator::email()
                ->chain(Validator::unique('users', 'email', $id)),
        ]);

        $user->update($data);

        return $this->json($response, ['user' => $user]);

    } catch (ValidationException $e) {
        return $this->validationError($response, $e);
    }
}
```

### Validación Condicional

```php
$rules = [
    'name' => Validator::string(3, 255),
];

// Solo validar password si está presente
if (isset($data['password'])) {
    $rules['password'] = Validator::password(8);
}

$this->validate($data, $rules);
```

## Personalización de Mensajes

```php
use Respect\Validation\Validator as V;

$rule = V::email()->setTemplate('Por favor ingresa un email válido');
$rule = V::length(3, 20)->setTemplate('{{name}} debe tener entre 3 y 20 caracteres');
```

## Mejores Prácticas

1. **Siempre valida en el servidor** - Nunca confíes solo en validación del cliente
2. **Usa unique() para emails** - Evita duplicados en base de datos
3. **Valida passwords** - Usa `Validator::password()` para contraseñas seguras
4. **Sanitiza después de validar** - La validación no sanitiza automáticamente
5. **Maneja errores apropiadamente** - Retorna 422 para errores de validación en APIs
6. **Usa validación condicional** - Solo valida campos cuando sea necesario

## Ver También

- [Respect\Validation Documentation](https://respect-validation.readthedocs.io/)
- `app/Controllers/UserController.php` - Ejemplos de uso real
- `app/Controllers/BaseController.php` - Métodos helper de validación
