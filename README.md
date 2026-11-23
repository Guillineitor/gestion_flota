# API de equipos, combustible y mantenciones

API REST en Laravel para que una empresa registre sus equipos de una flota (camiones,
excavadoras y generadores), las cargas de combustible y las mantenciones de
cada uno. Incluye reporte por rango de fechas y detección de equipos que
necesitan mantención preventiva.

Además trae una **interfaz web** (en React) para iniciar sesión, administrar los
equipos (crear, consultar, editar y eliminar) y generar el reporte por equipo.

**Versiones con las que se desarrolló:** PHP 8.5, Laravel 13, MySQL 8.4,
Node.js 22, Vite 8 y React 19.3.1.

## Cómo levantarlo

Requisitos: PHP 8.3+ (con las extensiones `pdo_mysql`, `pdo_sqlite`,
`mbstring`, `xml`, `curl`, `zip` y `bcmath`), Composer 2, MySQL 8, Node.js 20.19
o superior y `pnpm` (`npm install -g pnpm`).

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
```

Se crea una base de datos vacía en MySQL y se configura la conexión en `.env`:

```sql
CREATE DATABASE gestion_flota CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```env
APP_TIMEZONE=America/Santiago

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_flota
DB_USERNAME=root
DB_PASSWORD=tu_clave
```

Se crean las tablas y se cargan los datos de ejemplo:

```bash
php artisan migrate --seed
```

Se levantan el backend y el frontend, cada uno en su propia terminal y con
ambas abiertas a la vez:

```bash
# Terminal 1: backend
php artisan serve

# Terminal 2: frontend
pnpm run dev
```

- La API queda en `http://127.0.0.1:8000/api`.
- La interfaz web queda en `http://127.0.0.1:8000` (redirige a `/login`).
  Siempre se abre el puerto 8000: el 5173 es solo el servidor de assets de Vite.

**Usuario de ejemplo:** `admin@construckit.test` / `password`

## Interfaz web

Aplicación de una sola página (React + React Router) servida por Laravel. Todas
las URLs devuelven la misma vista y React decide qué pantalla mostrar.

| URL | Pantalla |
|---|---|
| `/login` | Inicio de sesión |
| `/equipos` | Lista de equipos (paginada) con botones Editar y Eliminar |
| `/equipos/nuevo` | Formulario para crear un equipo |
| `/equipos/{id}/editar` | Formulario para editar un equipo |
| `/reportes` | Reporte por equipo y rango de fechas |

- Sin sesión, cualquier URL redirige a `/login` y, al ingresar, vuelve a la
  página que se quería ver.
- Los formularios validan en el navegador (campos obligatorios, largos máximos,
  tipo y estado válidos, horómetro y sus reglas) y muestran
  bajo cada campo los errores que devuelve la API (error 422).
- Eliminar pide confirmación. Si el equipo tiene historial, se muestra el error
  409 de la API y el equipo no desaparece de la lista.

## Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria (ver `phpunit.xml`), así que no tocan la base
de MySQL. Cubren la regla del horómetro, el reporte y las mantenciones
preventivas.

## Autenticación

La API usa tokens de Laravel Sanctum. Se obtiene un token con el login y se
envía en el header `Authorization: Bearer <token>`. Se recomienda enviar
también `Accept: application/json`.

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@construckit.test","password":"password"}'
```

## Endpoints

Todos exigen token, salvo `login`.

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/login` | Devuelve un token (máx. 5 intentos por minuto) |
| POST | `/api/logout` | Revoca el token usado en la petición |
| GET | `/api/equipos` | Lista equipos, de 15 en 15 |
| POST | `/api/equipos` | Crea un equipo |
| GET | `/api/equipos/{id}` | Muestra un equipo |
| PUT/PATCH | `/api/equipos/{id}` | Edita un equipo (basta con enviar los campos que cambian) |
| DELETE | `/api/equipos/{id}` | Elimina un equipo sin historial |
| GET | `/api/equipos/{id}/cargas-combustible` | Lista las cargas, de 20 en 20 |
| POST | `/api/equipos/{id}/cargas-combustible` | Registra una carga |
| GET | `/api/equipos/{id}/mantenciones` | Lista las mantenciones, de 20 en 20 |
| POST | `/api/equipos/{id}/mantenciones` | Registra una mantención |
| GET | `/api/equipos/{id}/reporte?desde=Y-m-d&hasta=Y-m-d` | Reporte del equipo en el rango |
| GET | `/api/equipos/mantencion-preventiva` | Equipos que necesitan mantención preventiva |

### Ejemplos

Equipo:
```json
{ "codigo": "CAM-001", "tipo": "camion", "patente": "ABCD12",
  "marca": "Volvo", "modelo": "FH16" }
```
`tipo`: `camion`, `excavadora` o `generador`.
`estado`: `operativo` (por defecto), `en_mantencion` o `fuera_de_servicio`.

Carga de combustible:
```json
{ "fecha_hora": "2026-03-10 08:00:00", "litros": 80,
  "monto_clp": 90000, "horometro": 140 }
```

Mantención:
```json
{ "tipo": "preventiva", "fecha": "2026-03-12", "horometro": 150,
  "costo_clp": 250000, "descripcion": "Cambio de aceite y filtros" }
```
`tipo`: `preventiva` o `correctiva`.

### Respuesta del reporte

```json
{
  "equipo": { "id": 4, "codigo": "EXCAVADORA-1" },
  "periodo": { "desde": "2026-09-01", "hasta": "2026-10-31" },
  "cargas_consideradas": 3,
  "litros_totales": 215,
  "gasto_combustible_clp": 323360,
  "gasto_mantenciones_clp": 90000,
  "horas_de_uso": 160,
  "rendimiento_l_por_hora": 1
}
```

### Errores

| Código | Cuándo |
|---|---|
| 401 | Sin token, o token inválido (siempre en JSON) |
| 404 | El equipo no existe |
| 409 | Se intenta eliminar un equipo con cargas o mantenciones |
| 422 | Datos inválidos; el detalle viene en `errors` |
| 429 | Demasiados intentos de login |

## Probar con Postman

La colección está en `gestion_flota.postman_collection.json`.

1. En Postman: **Import** y se elige ese archivo.
2. Con el backend levantado (`php artisan serve`), se ejecuta primero **Login**:
   guarda el token automáticamente y el resto de las peticiones lo usan.
3. Se ejecutan las carpetas en orden (1 a 7) o todas juntas con **Run collection**.
   Cada petición trae tests que verifican el código de respuesta y los valores
   principales.

La URL base está en la variable de colección `baseUrl`
(`http://127.0.0.1:8000/api`). La carpeta 2 crea un equipo de prueba nuevo en
cada ejecución, así que los resultados no dependen de los datos del seeder. La
carpeta 6 reúne los casos de error (401, 404, 409 y 422).

## Decisiones tomadas

El enunciado dejaba algunos puntos obligatorios, así como abiertos a criterio propio. Estas fueron las
decisiones tomadas.

### Horómetro

- Una carga o mantención con horómetro **mayor** al actual se registra y
  actualiza el horómetro del equipo.
- Un horómetro **igual** al actual se acepta y no cambia nada: es normal
  cargar combustible sin haber usado el equipo entre medio.
- Un horómetro **menor** se rechaza con 422 y un mensaje que indica el valor
  ingresado, el último registrado y el código del equipo. No se guarda nada.
- La regla es la misma para cargas y mantenciones (`HorometroService`). Se
  ejecuta dentro de una transacción y con la fila del equipo bloqueada
  (`lockForUpdate`), para que dos peticiones simultáneas no se pisen.
- El "último horómetro registrado" es el `horometro_actual` del equipo. No se
  comparan las fechas de los registros, así que no se soportan cargas
  retroactivas con un horómetro menor al actual.
- El horómetro admite un decimal, igual que la columna de la base de datos.
- Al editar un equipo no se puede bajar su `horometro_actual`, porque sería
  una forma de saltarse la regla anterior.

### Reporte

- El rango incluye el día `desde` y el día `hasta` completos, en la zona
  horaria de la aplicación (`America/Santiago`).
- Las cargas se filtran por `fecha_hora` y las mantenciones por `fecha`.
- Rendimiento (litros por hora), método de estanque a estanque: cada carga
  repone lo consumido desde la anterior, así que los litros consumidos son la
  suma de las cargas del rango, excepto la primera, y las horas de uso son
  el horómetro de la última carga menos el de la primera.
  `rendimiento = litros consumidos / horas de uso`.
- Es `null` si hay menos de 2 cargas en el rango o si las horas de uso son 0
  (no hay datos suficientes). Dividir litros totales por horas sobreestimaría
  el consumo, porque incluiría el combustible de la primera carga.

### Mantención preventiva

- Un equipo necesita mantención si lleva más de 250 horas (estrictamente
  mayor: con exactamente 250 todavía no entra) desde su última preventiva.
- La última preventiva es la de mayor horómetro. Si el equipo nunca tuvo una,
  se cuenta desde 0.
- Las mantenciones correctivas no reinician el conteo.
- Registrar una mantención no cambia el estado del equipo; eso se hace
  editándolo.

### Equipos

- Un equipo con cargas o mantenciones no se puede eliminar (409), para no
  perder su historial. La base de datos también lo impide.
- La patente es opcional. El código interno es único.
- Si no se indica, el estado es `operativo` y el horómetro `0`.

### Fechas y zona horaria

La aplicación trabaja en `America/Santiago`. Las fechas de las respuestas JSON
salen en formato ISO 8601 en UTC (terminan en `Z`), que es el comportamiento
estándar de Laravel y lo interpretan sin ambigüedad todos los clientes.

### Autenticación

- Sanctum con tokens. No hay endpoint de registro: el usuario de ejemplo lo
  crea el seeder.
- El login está limitado a 5 intentos por minuto. Si el email no existe o la
  clave es incorrecta, responde lo mismo, para no revelar qué emails existen.
- Las rutas de `/api/*` sin autenticar responden 401 en JSON, incluso si el
  cliente no envía `Accept: application/json`.

### Interfaz web

- La interfaz consume la misma API REST con el token de Sanctum, igual que
  cualquier otro cliente. Laravel solo sirve la vista `reporte.blade.php` con el
  contenedor de React.
- `routes/web.php` tiene una ruta comodín que devuelve esa vista para cualquier
  URL que no empiece con `/api`, de modo que se puede recargar o compartir un
  enlace como `/equipos/5/editar`. Las pantallas las define React Router.
- El token se guarda en `localStorage` para que la sesión sobreviva a una
  recarga. Es una solución simple y suficiente para esta prueba; en producción
  se preferiría una cookie `HttpOnly` (Sanctum en modo SPA), porque
  `localStorage` es legible por cualquier script de la página si existiera una
  vulnerabilidad XSS. Si la API responde 401, la interfaz borra el token y vuelve
  al login.
- Los formularios validan antes de enviar para dar respuesta inmediata, pero la
  fuente de verdad es la API: sus errores 422 se muestran bajo cada campo.
- El equipo y las fechas del reporte no se guardan en la URL: `/reportes`
  identifica la pantalla, no la consulta.

## Datos de ejemplo (seeder)

`php artisan migrate:fresh --seed` deja el usuario de ejemplo y 6 equipos con
cargas y mantenciones fijas (sin valores aleatorios), pensados para cubrir cada
caso. El combustible se valoriza a $1.504 por litro.

| Equipo | Estado | Horómetro | Última preventiva | Horas desde ella | ¿Necesita preventiva? |
|---|---|---|---|---|---|
| CAMION-1 | operativo | 300 | 100 | 200 | No |
| CAMION-2 | operativo | 400 | 100 | 300 | Sí |
| CAMION-3 | fuera de servicio | 350 | 100 | 250 | No (justo en el límite) |
| EXCAVADORA-1 | operativo | 280 | nunca | 280 | Sí (cuenta desde cero) |
| EXCAVADORA-2 | en mantención | 630 | 500 | 130 | No |
| GENERADOR-1 | operativo | 120 | nunca | 120 | No |

## Estructura

- `app/Models`: `Equipo`, `CargaCombustible`, `Mantencion`.
- `app/Enums`: `TipoEquipo`, `EstadoEquipo`, `TipoMantencion`.
- `app/Services/HorometroService.php`: la regla del horómetro.
- `app/Http/Controllers`: un controlador por recurso, más el reporte y las
  preventivas (de una sola acción).
- `app/Http/Requests`: validaciones de equipos, cargas y mantenciones.
- `routes/api.php`: la API. `routes/web.php`: la ruta comodín de la interfaz.
- `resources/views/reporte.blade.php`: vista base que carga la aplicación React.
- `resources/js/reporte.jsx`: la interfaz (login, equipos, formularios, reporte).
- `resources/css/reporte.css`: estilos, con modo claro y oscuro automático.
- `tests/Feature`: tests del horómetro, del reporte y de las preventivas.

## Lo que quedó fuera

- **Docker Compose:** no se incluyó para no entregar una configuración que no
  se pueda explicar con seguridad.
- **Estructura del frontend:** por simplicidad, toda la interfaz está en un solo
  archivo, en una aplicación más grande se dividiría en componentes por pantalla (y más funcionalidades)

## Preguntas cortas de la Parte 2

### 1. Despliegue en Google Cloud

No soy un experto en Google Cloud, pues en el pasado he interactuado de forma indirecta. Sin embargo, intería priorizar servicios administrados que me eviten configurar servidores a mano, guardar las claves de la forma más rigurosa posible (nunca en el repositorio de forma directa), y establecer un ritmo de respaldos (sea diarios, cada X días o semanal) de forma automáticos en la base. Más detalles de como hacer cada una de estás va un poco más allá de mis conocimientos, pero siempre aprendo a manejar rápidamente cual opción y tecnología.

### 2. Incidente

Primero consultaría en que dispositivos móviles les afecta el problema, que navegadores usan dichos móviles, revisar, ver qué cambió recientemente y probar la API (para descartar fallos en la conexión a internet o del backend). Si el problema es del backend, reviso si el servicio está levantado, revisar los logs, verificar la base de datos y sus conexiones y descarte alguna falla por los lenguajes o herramientas que forman el backend. Sino es un tema de navegadores específicos, investigaría el porque de ello antes de hacer cambios al código. Si coincide el problema con un cambio reciente en la página web, comparo los cambios entre commits y se depuro para arreglar/comprobar el problema. En paralelo, aviso de inmediato a los superiores de la empresa del origen del problema y su posible solución.

### 3. Rendimiento

Antes de tocar código, mido dónde se va el tiempo (ya sea si es un tema de PHP y de la página web, o de la base de datos y sus consultas). Si fuera un tema de la base de datos, revisaría que tipo de consultas se hacen, ver si se podría añadir nuevos filtros para reducir el tamaño de ciertas consultas (como por ejemplo el rango máximo de tiempo de datos en lo que abarca la consulta, en vez de los 2 años completos).

### 4. Soporte TI

Primero, preguntaría la fecha de entrega del proyecto, quién es la persona está sin acceso a sy correo y si el notebook tiene información crítica sin respaldo. Si el notebook no tiene información crítica y no hay apuros con la entrega del proyecto, entrego rápidamente un notebook de reemplazo a su persona, luego resolvería el problema del correo, para finalmente usar el resto del día para trabajar en el proyecto con el resto del equipo. Si la entrega corre riesgo, priorizo el proyecto de la mejor manera que sea posible con el equipo, y si el notebook sin encender es muy importante, llamaría al servicio técnico ese mismo día para intentar repararlo antes de tomar decisiones más fuertes. A las personas involucradas con el notebook y correo, intentaría darles un tiempo estimado de cuando se restaura la normalidad en esos casos y avisar temprano al resto del equipo si la entrega del desarrollo corre riesgo debido a su proximidad.
