# CONTEXTO del proyecto: ciclo de clases y reservas

## Estado actual

El proyecto ya tiene implementado el flujo nuevo de reservas de clases por ciclo, sin romper la base legacy de turnos. La funcionalidad quedó organizada en dos capas:

- Backend Laravel en `un_toque_de_luz_API`
- Frontend React en `React.js/un_toque_de_luz`

La migración aditiva del dominio de booking ya fue aplicada en la base configurada y se verificó que la clave foránea de `user_id` coincida con el tipo real de `yoguinis.id` (`INT(11)`). Las pruebas de backend y la compilación del frontend quedaron en verde.

---

## Qué se implementó

### 1) Dominio del negocio

Se creó el modelo de gestión de clases por ciclo:

- `horarios`: horarios base recurrentes
- `ciclos`: ciclos de práctica con fecha de inicio/fin, precio y cantidad de días por semana
- `clases`: instancias concretas generadas por ciclo y horario
- `inscripciones`: inscripción de un yoguini a un ciclo
- `inscripcion_horarios`: selección de horarios fijos de la inscripción
- `reservas`: reservas de clase, tanto regulares como de recuperación
- `recuperaciones`: créditos de recuperación, con vencimiento y estado
- `feriados`: exclusión de días feriados al generar clases
- `configuracion`: configuración general del sistema

### 2) Generación de clases

Se implementó la generación idempotente de clases a partir de un ciclo y una lista de horarios.

Reglas clave:

- no se generan clases en feriados
- no se duplican clases si se vuelve a ejecutar la generación
- se valida que una fecha/horario no pertenezca a otro ciclo
- se acepta un ciclo de un solo día, siempre que la lógica de fechas lo soporte

### 3) Inscripción y cupos

El proceso de inscripción es transaccional y usa lock de fila:

- revisa ciclo activo y vigente
- exige exactamente la cantidad de horarios por semana del ciclo
- valida que cada horario exista y esté activo
- exige que las clases futuras de esos horarios ya estén generadas
- rechaza si no hay cupo suficiente
- crea la inscripción y las reservas regulares para cada clase futura

### 4) Cancelación de reserva y créditos

La lógica de cancelación contempla aviso mínimo para generar un crédito.

Reglas:

- si la reserva se cancela con aviso suficiente, se genera un crédito
- si el aviso es menor, no se genera crédito
- si el crédito pertenece a una recuperación y se cancela la reserva de recuperación con aviso, el crédito original vuelve a estar disponible y no se crea uno nuevo en cadena
- si la clase es cancelada por administración, el mismo comportamiento aplica para reservas de recuperación

### 5) Recuperación entre ciclos

Se habilitó la reserva de recuperación en clases futuras de cualquier ciclo, siempre que:

- la clase esté programada y en el futuro
- la clase esté dentro del mes de vencimiento del crédito
- la clase tenga cupo
- el alumno no tenga ya una reserva para esa clase

### 6) Administración

Se agregaron APIs y pantallas para:

- crear/editar/borrar horarios
- crear/editar/borrar ciclos
- generar clases desde un ciclo
- ver clases, cupos y disponibilidad
- cancelar clases
- completar clases al pasar su horario
- marcar asistencia/falta
- cargar feriados
- bloquear cambios de fechas de un ciclo una vez generadas sus clases

### 7) Seguridad y historial

Se agregaron medidas de integridad para no destruir historial:

- no se elimina un yoguini si tiene inscripciones o reservas asociadas
- si hay historial previo, se devuelve un error 409 claro
- rutas de administración quedan protegidas por middleware de admin
- las tablas de booking no usan cascada al borrar usuarios/inscripciones cuando eso implicaría perder trazabilidad

### 8) Frontend

En React se desarrolló la nueva visualización para:

- inscripción a ciclos
- panel de reservas y créditos del alumno
- panel de administración para ciclos, horarios, feriados y clases
- navegación y estilos responsive para la nueva experiencia

---

## Decisiones de diseño

### A) Mantener la base legacy intacta

Se evitó borrar o reestructurar el modelo de turnos ya existente. El nuevo flujo se implementó como dominio aditivo, compatible con la estructura actual del proyecto.

### B) Resolver el problema del `int` legacy

Durante la primera aplicación de la migración quedaba bloqueada porque `yoguinis.id` es `INT` en MySQL y `foreignId()` genera `BIGINT UNSIGNED`. La corrección fue alinear las FKs a `INT`, que es el tipo real de la base.

### C) Priorizar transacciones y lock

La lógica crítica del negocio (inscripción, reserva, recuperación, cancelación, generación) se ejecuta bajo transacciones para evitar inconsistencias de cupo y créditos.

### D) Alinear zona horaria a Buenos Aires

La aplicación usa `America/Argentina/Buenos_Aires` como zona efectiva, y la lógica de vencimiento y comparación de fechas se calcula con esa zona para evitar errores de horario entre backend y UI.

### E) No inventar pagos ni pasarelas

El precio de ciclo existe en el schema, pero no hay proveedor de pago ni reglas de cobro/refund definidos en este proyecto; por eso no se implementó liquidación ni captura de pagos.

---

## Archivos clave modificados

### Backend

- `un_toque_de_luz_API/database/migrations/2026_10_06_140000_create_cycle_booking_tables.php`
- `un_toque_de_luz_API/app/Services/BookingService.php`
- `un_toque_de_luz_API/app/Services/GenerateCycleClasses.php`
- `un_toque_de_luz_API/app/Http/Controllers/Api/BookingController.php`
- `un_toque_de_luz_API/app/Http/Controllers/Api/BookingAdminController.php`
- `un_toque_de_luz_API/app/Http/Middleware/EnsureAdmin.php`
- `un_toque_de_luz_API/routes/api.php`
- `un_toque_de_luz_API/app/Console/Commands/ExpireCreditsCommand.php`
- `un_toque_de_luz_API/app/Console/Kernel.php`
- `un_toque_de_luz_API/app/Models/Ciclo.php`
- `un_toque_de_luz_API/app/Models/Clase.php`
- `un_toque_de_luz_API/app/Models/Inscripcion.php`
- `un_toque_de_luz_API/app/Models/Reserva.php`
- `un_toque_de_luz_API/app/Models/Recuperacion.php`
- `un_toque_de_luz_API/app/Models/Feriado.php`
- `un_toque_de_luz_API/app/Models/Horario.php`
- `un_toque_de_luz_API/app/Models/Yoguini.php`

### Frontend

- `React.js/un_toque_de_luz/src/pages/BookingStudent.jsx`
- `React.js/un_toque_de_luz/src/pages/MyBookings.jsx`
- `React.js/un_toque_de_luz/src/pages/BookingAdmin.jsx`
- `React.js/un_toque_de_luz/src/components/Navbar.jsx`
- `React.js/un_toque_de_luz/src/components/NavbarAdmin.jsx`
- `React.js/un_toque_de_luz/src/components/NavbarUser.jsx`
- `React.js/un_toque_de_luz/src/Styles/Reservas.css`

### Pruebas

- `un_toque_de_luz_API/tests/Feature/CycleBookingTest.php`

---

## Verificación realizada

Se validó con evidencia real:

- `php artisan test` -> 20 tests pasando
- `npm run build` -> build de Vite correcto
- `php -l` sobre los archivos clave -> sin errores de sintaxis
- `php artisan migrate:status` -> migración aplicada y registrada
- `php artisan route:list --path=booking` -> rutas booking visibles y protegidas por auth/admin

---

## Tarea exacta pendiente para continuar

La tarea pendiente específica y exacta es esta:

1) Si el entorno de despliegue o la base de producción no tiene la configuración de aviso mínimo, cargarla en `configuracion` con la clave `horas_aviso_minimas` y valor `24`.
2) Habilitar el scheduler de Laravel en producción para ejecutar el comando diario:
   `php artisan booking:expire-credits`
   cada minuto o con el mecanismo estándar de cron/task scheduler del servidor.

Esto es lo único que falta para dejar el flujo completamente operativo en entorno real, porque ahora el dominio, la API, la UI y la migración ya quedaron implementados y validados.

> En otras palabras: la aplicación ya está funcional en código; lo que falta no es más negocio, sino dejar persistido ese valor de configuración y asegurar la ejecución periódica del comando de expiración.