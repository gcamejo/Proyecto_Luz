# CONTEXTO del proyecto: ciclo de clases y reservas

Actualizado: 2026-10-08

## Estado actual

El flujo nuevo de reservas por ciclo está implementado y convive con el dominio legacy de turnos. Las migraciones aditivas se aplicaron en la base local y se validó el tipo de las claves foráneas contra `yoguinis.id` (`INT(11)`). La última suite completa está en la sección “Total real de tests” al final de este documento; las pruebas concurrentes MySQL se omiten por defecto y requieren habilitación explícita.

La implementación está organizada en:

- Backend Laravel en `un_toque_de_luz_API`
- Frontend React en `React.js/un_toque_de_luz`

El backend separa las operaciones principales en `CicloService`, `InscripcionService`, `ReservaService` y `RecuperacionService`. También cuenta con Form Requests, API Resources y Policies para administración y propiedad de reservas/créditos.

---

## Qué se implementó

### 1) Dominio del negocio

Se creó el modelo de gestión de clases por ciclo:

- `horarios`: horarios base recurrentes
- `ciclos`: ciclos de práctica con fecha de inicio/fin, precio y cantidad de días por semana
- `ciclo_horarios`: horarios seleccionados explícitamente para cada ciclo
- `clases`: instancias concretas generadas por ciclo y horario
- `inscripciones`: inscripción de un yoguini a un ciclo
- `inscripcion_horarios`: selección de horarios fijos de la inscripción
- `reservas`: reservas de clase, tanto regulares como de recuperación
- `recuperaciones`: créditos de recuperación, con vencimiento y estado
- `feriados`: exclusión de días feriados al generar clases
- `configuracion`: configuración general del sistema

### 2) Generación de clases

Se implementó la generación idempotente de clases a partir de los horarios elegidos para cada ciclo. Los ciclos nuevos exigen seleccionar exactamente la cantidad indicada en `clases_por_semana`; la selección se puede editar antes de generar y queda bloqueada después.

La migración `2026_10_08_100000_create_ciclo_horarios_table.php` reconstruye la selección de ciclos existentes a partir de sus clases ya generadas. Los ciclos aún no generados requieren que administración elija sus horarios antes de generar.

Reglas clave:

- no se generan clases en feriados
- no se duplican clases si se vuelve a ejecutar la generación
- solo se consideran horarios asignados al ciclo; horarios activos no seleccionados quedan fuera
- se rechaza una fecha/horario si ya tiene una clase no cancelada de otro ciclo; un espacio ocupado solo por clases canceladas puede reutilizarse en un ciclo nuevo, sin borrar clases ni reservas históricas
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

- el crédito vence el último día del mes de la clase origen (faltada o cancelada), no del mes en que se cancela
- el crédito solo puede usarse para clases programadas y futuras dentro de ese mismo mes, con cupo disponible
- si la reserva se cancela con aviso suficiente, se genera un crédito con vencimiento basado en la fecha de su clase
- si el aviso es menor, no se genera crédito
- si el crédito pertenece a una recuperación y se cancela la reserva de recuperación con aviso, el crédito original vuelve a estar disponible y no se crea uno nuevo en cadena
- si la clase es cancelada por administración, el mismo comportamiento aplica para reservas de recuperación
- administración no puede cancelar una clase después de su fecha/hora de inicio
- el alumno puede darse de baja de una inscripción completa; se cancelan solo las reservas futuras de clases de ese ciclo, se evalúa cada una con la regla de 24 horas y se conserva el historial pasado
- las reservas futuras de otros ciclos no se cancelan al dar de baja la inscripción actual

Ejemplo: cancelar el 30/10 una clase del 5/11 con 24 horas o más de aviso genera un crédito con `vence_en = 2026-11-30`, utilizable solo para clases de noviembre. La baja de un ciclo puede generar créditos de distintos vencimientos y el resumen se agrupa por mes.

Los créditos existentes no se migran ni se modifican. La consulta local previa a este cambio encontró **0 créditos** cuyo mes de `vence_en` difiere del mes de su clase origen.

### 5) Recuperación entre ciclos

Se habilitó la reserva de recuperación en clases futuras de cualquier ciclo, siempre que:

- la clase esté programada y en el futuro
- la clase esté dentro del mes de vencimiento del crédito
- la clase tenga cupo
- el alumno no tenga ya una reserva para esa clase

### 6) Administración

Se agregaron APIs y pantallas para:

- crear, editar, activar/desactivar horarios
- crear y editar ciclos con selección explícita de horarios; la selección queda fija al generar clases
- activar/pausar ciclos y archivarlos después de pausarlos y cancelar todas las clases programadas
- generar clases desde un ciclo
- listar clases con filtros por fecha/ciclo, cupos y disponibilidad
- consultar las reservas y los alumnos de cada clase
- cancelar clases
- completar clases al pasar su horario
- marcar asistencia/falta
- crear, editar y borrar feriados
- bloquear cambios de fechas de un ciclo una vez generadas sus clases

La cancelación admin de clase está disponible por `PATCH /api/booking/admin/classes/{clase}/cancel`; se conserva el POST anterior por compatibilidad. Los endpoints nuevos de administración están protegidos con la Policy de rol admin.

### 7) Seguridad y historial

Se agregaron medidas de integridad para no destruir historial:

- no se elimina un yoguini si tiene inscripciones o reservas asociadas
- si hay historial previo, se devuelve un error 409 claro
- los ciclos se archivan con soft delete solo cuando están pausados y no tienen clases programadas; clases realizadas/canceladas, inscripciones, reservas y créditos quedan visibles en el historial
- las rutas legacy de administración usan middleware de admin; las nuevas rutas de booking usan Policy de rol admin
- las Policies de reserva y recuperación limitan cancelaciones y uso de créditos al propietario
- las tablas de booking no usan cascada al borrar usuarios/inscripciones cuando eso implicaría perder trazabilidad

### 8) Frontend

En React se desarrolló la nueva visualización para:

- inscripción a ciclos
- panel de reservas y créditos del alumno
- acción para darse de baja de un ciclo activo desde Mis reservas, con confirmación y resumen de créditos
- panel de administración para ciclos, horarios, feriados y clases
- vistas de próximas clases e historial separadas usando datos de fecha calculados por el backend
- aviso previo al cancelar sobre si se genera crédito, confirmación de cancelación y detalle de vencimiento
- aviso con el mes y último día de validez del crédito; Mis créditos indica el mes válido y las clases recuperables se filtran a ese mismo mes
- resumen de la baja de ciclo agrupado por mes de vencimiento
- consultas con SWR y una instancia Axios compartida con interceptor que lee el token actual de Redux
- navegación y estilos responsive para la nueva experiencia

El menú admin distingue dos pantallas:

- `Turnos` abre `/adminTurnos`, una vista de solo lectura con clases que tienen alumnos reservados
- `Administrar > Administrar clases` abre `/adminReservas`, con herramientas de gestión

Las rutas legacy de turnos siguen en el proyecto, pero ya no aparecen como opción para crear turnos en el menú admin.

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

### F) Separar el dominio de booking

Las operaciones principales están en servicios por responsabilidad (`CicloService`, `InscripcionService`, `ReservaService`, `RecuperacionService`). La validación HTTP vive en Form Requests y los endpoints principales de clases, ciclos, reservas y créditos usan API Resources. Aún quedan lecturas y algunas operaciones CRUD simples directamente en controladores; se pueden extraer si se continúa el refactor.

---

## Archivos clave modificados

### Backend

- `un_toque_de_luz_API/database/migrations/2026_10_06_140000_create_cycle_booking_tables.php`
- `un_toque_de_luz_API/database/migrations/2026_10_08_100000_create_ciclo_horarios_table.php`
- `un_toque_de_luz_API/database/migrations/2026_10_08_110000_add_soft_deletes_to_ciclos_table.php`
- `un_toque_de_luz_API/app/Services/CicloService.php`
- `un_toque_de_luz_API/app/Services/InscripcionService.php`
- `un_toque_de_luz_API/app/Services/ReservaService.php`
- `un_toque_de_luz_API/app/Services/RecuperacionService.php`
- `un_toque_de_luz_API/app/Services/GenerateCycleClasses.php`
- `un_toque_de_luz_API/app/Http/Requests/Booking/`
- `un_toque_de_luz_API/app/Http/Resources/Booking/`
- `un_toque_de_luz_API/app/Policies/`
- `un_toque_de_luz_API/app/Policies/InscripcionPolicy.php`
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
- `React.js/un_toque_de_luz/src/pages/TurnosClases.jsx`
- `React.js/un_toque_de_luz/src/components/Navbar.jsx`
- `React.js/un_toque_de_luz/src/components/NavbarAdmin.jsx`
- `React.js/un_toque_de_luz/src/components/NavbarUser.jsx`
- `React.js/un_toque_de_luz/src/api/axios.js`
- `React.js/un_toque_de_luz/src/api/fetcher.js`
- `React.js/un_toque_de_luz/src/Styles/Reservas.css`

### Pruebas

- `un_toque_de_luz_API/tests/Feature/CycleBookingTest.php`
- `un_toque_de_luz_API/tests/Feature/BookingCapacityConcurrencyTest.php`
- `un_toque_de_luz_API/tests/Support/booking-enrollment-worker.php`

---

## Verificación realizada

Verificación registrada el 2026-10-08:

- `php artisan test` -> ver “Total real de tests” (suite completa; las pruebas MySQL de concurrencia se omiten por defecto)
- Prueba concurrente explícita contra `un_toque_de_luz` -> pasó: 2 procesos lanzados, 1 reserva confirmada, 1 rechazo y exactamente 1 reserva para el último cupo
- Limpieza concurrente -> conteos antes/después idénticos en `yoguinis` (4), `horarios` (5), `ciclos` (3), `ciclo_horarios` (5), `clases` (16), `inscripciones` (2), `inscripcion_horarios` (4), `reservas` (12) y `recuperaciones` (5); consulta posterior del marcador `CONC_TEST_` -> 0 filas
- Backup previo de `un_toque_de_luz` con `mysqldump --single-transaction` -> `C:\Users\gcamejo\AppData\Local\Temp\Proyecto_Luz_DB_Backups\un_toque_de_luz-20261008-115920.sql` (53.233 bytes)
- Créditos preexistentes afectados por cambio de regla -> 0; no se hizo migración de datos
- `npm run build` -> build de Vite correcto
- diagnósticos del editor -> sin errores en los archivos de booking modificados
- `git diff --check` -> sin errores de whitespace; Windows mostró avisos normales de conversión LF/CRLF
- `php artisan migrate:status` -> migraciones de booking y archivado aplicadas y registradas; no se ejecutó una migración de datos de créditos
- migración ciclo/horario -> aplicada localmente; los 2 pares distintos de clases existentes quedaron respaldados por 2 vínculos en `ciclo_horarios`
- smoke test transaccional MySQL -> dos ciclos temporales de la misma fecha, con horarios distintos, generaron una clase cada uno; el rollback dejó 0 ciclos temporales
- conexión inspeccionada -> `APP_ENV=local`, base `un_toque_de_luz`; no se verificó otro host remoto
- `php artisan route:list --path=booking` -> rutas booking registradas bajo autenticación y autorización admin donde corresponde
- `php artisan schedule:list` -> expiración de créditos programada diariamente a medianoche en la zona horaria de la app
- `php artisan schedule:run` -> ejecutó el scheduler; no había tarea diaria vencida al momento de la comprobación
- Base MySQL local -> `horas_aviso_minimas` persistido con valor `24` mediante `ConfiguracionSeeder`
- tests específicos -> archivado de ciclo con historial preservado después de pausar/cancelar clases, selección y edición de horarios por ciclo, baja completa con créditos por reserva y preservación de clases pasadas, autorización por propietario, segundo ciclo sin colisión de horarios no seleccionados, límite exacto de 24 horas, filtros de clases, reservas por clase y edición segura de feriados

---

## Antes de pasar a producción

La prueba concurrente se ejecutó con éxito en la base local de test-producción. El backup se conserva fuera del repo. Antes del pase:

1. Revisar y eliminar únicamente los datos de prueba que se hayan creado manualmente (usuarios, ciclos, reservas y créditos); la prueba `CONC_TEST_` de esta ejecución no dejó filas.
2. Ejecutar `php artisan db:seed --class=ConfiguracionSeeder` en cada base desplegada y confirmar `horas_aviso_minimas = 24`.
3. Configurar el scheduler del host para invocar `schedule:run` cada minuto; el kernel ya agenda la expiración diaria a medianoche en la zona de la aplicación.
4. Probar login y flujos reales de alumno/admin en el entorno desplegado.

El cron remoto y el flujo manual de login todavía no se verificaron desde este workspace.

## HTTPS en el servidor

### Configuración de la aplicación

Laravel quedó preparado para producción HTTPS, pero `.env.example` conserva ahora defaults de desarrollo local (`APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, `FRONTEND_URL=http://localhost:5173` y cookies no seguras). Al desplegar, configurá `.env` con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.TU-DOMINIO`, cookies seguras y el origen HTTPS real del front. La autenticación actual usa tokens Sanctum en `Authorization: Bearer`, no cookies de sesión compartidas; por eso CORS no habilita credenciales, `SANCTUM_STATEFUL_DOMAINS` queda vacío y se usa `SESSION_SAME_SITE=lax`. Esta elección funciona con dominios distintos o subdominios porque la autenticación no depende de cookies cross-site.

`TRUSTED_PROXIES` debe contener únicamente las IP/CIDR del proxy inmediato que conecta con PHP, separadas por comas. No uses `*`. Si hay Cloudflare delante de nginx, configura TLS Full (strict), valida en nginx las IPs oficiales de Cloudflare con el módulo `real_ip` y confía en Laravel solo en la IP privada de nginx; mantené actualizadas las redes publicadas por Cloudflare. Si PHP recibe la conexión directamente, dejá la variable vacía. No confíes en `X-Forwarded-*` desde clientes arbitrarios.

Laravel fuerza la generación de URLs HTTPS en producción. Las respuestas de producción agregan `Strict-Transport-Security: max-age=300`, `X-Content-Type-Options: nosniff` y `X-Frame-Options: DENY`. Tras validar que todos los subdominios relevantes sirven HTTPS, subí el HSTS primero a `86400` y luego a `31536000` segundos mediante un despliegue. Agregá `includeSubDomains` solo cuando todos los subdominios estén preparados; no solicites preload hasta evaluar sus requisitos y efectos permanentes.

### Certificado y redirección

1. Apuntá los registros DNS de la API y del front a sus respectivos hosts y habilitá el puerto TCP 443 en firewall/hosting.
2. Instalá un certificado válido para cada host (por ejemplo, Let's Encrypt/Certbot o el certificado administrado del hosting). Configurá la clave privada fuera del repositorio y con permisos restringidos.
3. En nginx, redirigí HTTP y serví Laravel desde `public` por HTTPS. Ajustá rutas, socket PHP y nombres de host a tu servidor:

```nginx
server {
	listen 80;
	server_name api.TU-DOMINIO;
	return 301 https://$host$request_uri;
}

server {
	listen 443 ssl;
	server_name api.TU-DOMINIO;
	root /ruta/un_toque_de_luz_API/public;
	index index.php;

	ssl_certificate /etc/letsencrypt/live/api.TU-DOMINIO/fullchain.pem;
	ssl_certificate_key /etc/letsencrypt/live/api.TU-DOMINIO/privkey.pem;

	location / {
		try_files $uri $uri/ /index.php?$query_string;
	}

	location ~ \.php$ {
		include fastcgi_params;
		fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
		fastcgi_param HTTPS on;
		fastcgi_pass unix:/run/php/php-fpm.sock;
	}
}
```

4. En Apache, habilitá `mod_ssl` y `mod_rewrite`, instalá el certificado en el VirtualHost TLS y agregá la redirección al comienzo de `public/.htaccess`, antes de las reglas de Laravel:

```apache
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{HTTPS} !=on
	RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

5. Activá la renovación automática del proveedor. Con Certbot, comprobá que exista su timer/cron y ejecutá `sudo certbot renew --dry-run`; no copies certificados ni claves al repositorio.
6. En el host, completá las variables de `.env.example`, especialmente `APP_KEY`, credenciales de base y `TRUSTED_PROXIES`, y desplegá el archivo de ejemplo del front como `.env.production` con `VITE_API_URL=https://api.TU-DOMINIO/`. Luego ejecutá `php artisan config:clear` y reconstruí el front; nunca publiques `.env` ni `APP_DEBUG=true`.

Probá desde una terminal:

```sh
curl -I http://api.TU-DOMINIO
curl -I https://api.TU-DOMINIO/api/actividades
```

La primera respuesta debe redirigir a HTTPS. En la respuesta HTTPS verificá el código esperado, `Strict-Transport-Security: max-age=300`, `X-Content-Type-Options: nosniff` y `X-Frame-Options: DENY`. Para validar CORS, repetí una petición `OPTIONS` con `Origin: https://TU-DOMINIO` y comprobá que `Access-Control-Allow-Origin` coincida exactamente; un origen ajeno no debe recibir ese encabezado.

### Prueba manual de producción

- Abrí el front por HTTPS, iniciá sesión como alumno y confirmá en Network que login devuelve el token y las llamadas envían `Authorization: Bearer ...` a la API HTTPS.
- Reservá una clase, cancelala y verificá las respuestas, el estado actualizado y el crédito/vencimiento correspondiente.
- Iniciá sesión como administrador y probá el panel, incluyendo una operación de lectura y una acción permitida.
- En DevTools revisá Console y Network: no debe haber mixed content, errores CORS, solicitudes HTTP, certificados inválidos ni fallos de autenticación. Verificá también que las imágenes de actividades carguen desde la API HTTPS.

El certificado, la redirección, el cron de renovación, DNS, firewall, proxies y los flujos reales de login/reserva/cancelación/admin son tareas y verificaciones pendientes del hosting; no se ejecutaron desde este workspace.

### Verificación local HTTPS

- El build de React se ejecutó con `VITE_API_URL=https://api.TU-DOMINIO/`; el bundle contiene el host HTTPS de ejemplo y ninguna referencia a `http://localhost:8000` para recursos propios.
- `VITE_API_URL` es obligatoria al ejecutar el front en producción; el destino local automático queda limitado al modo de desarrollo.
- `php artisan config:clear` -> correcto.
- `php artisan test` -> ver “Total real de tests”. Los omitidos son las pruebas de concurrencia, que requieren `RUN_BOOKING_CONCURRENCY_TESTS=1`.
- La prueba enfocada del middleware confirmó HSTS `max-age=300`, `X-Content-Type-Options: nosniff` y `X-Frame-Options: DENY` en producción.

### Verificación local actual

- `.env` apunta la API a `http://localhost:8000` y permite el origen `http://127.0.0.1:5174`; Vite usó ese puerto porque `5173` ya estaba ocupado. Sanctum stateful queda vacío porque React envía Bearer tokens.
- El preflight CORS para `127.0.0.1:5174` devolvió `204`; `GET /api/public/yoga/cycles` devolvió `200` con ciclos públicos. El frontend también respondió `200`.

## Presentación pública de Yoga y avisos de registro

- La portada incluye el CTA destacado “Quiero comenzar yoga” y `/yoga` muestra información editable desde `React.js/un_toque_de_luz/src/content/yogaPageContent.js` junto con los ciclos públicos.
- `GET /api/public/yoga/cycles` no requiere autenticación, tiene límite propio y solo expone ciclos activos no vencidos con clases futuras programadas, día/hora y disponibilidad; no serializa inscripciones ni datos personales.
- El registro está limitado a cinco solicitudes por minuto. Al terminar la respuesta, se crean notificaciones de base de datos para cada admin y se envía email solo si `ADMIN_NOTIFICATION_EMAIL` está configurado. Los errores se registran sin incluir valores sensibles, y los reintentos del mismo ID no duplican ambos canales.
- El panel admin consulta el contador/listado, permite marcar avisos como leídos y borrar solo los ya leídos; las rutas requieren Sanctum y middleware `admin`. Al detectar un registro nuevo, refresca el directorio Redux de yoguinis.
- El token y usuario autenticados se conservan en `sessionStorage` para mantener la sesión al recargar la misma pestaña y se eliminan al cerrar sesión.
- Las actividades fuera del sistema de ciclos y la página `/comenzar-yoga` ofrecen un contacto secundario por WhatsApp si `VITE_WHATSAPP_NUMBER` está configurada con dígitos solamente. Estas solicitudes se abren fuera de la app y no quedan registradas en el sistema.
- La migración `2026_10_09_120000_create_notifications_table.php` quedó aplicada en la base local `un_toque_de_luz`; no se hicieron cambios de servidor de producción.
- Verificación de esta entrega: ver “Total real de tests”; `npm run build` -> correcto.

## Correo transaccional con Resend

- Método: llamada HTTP directa `POST https://api.resend.com/emails` con Bearer API key; no usa `resend/resend-laravel`, `MAIL_MAILER=resend` ni SwiftMailer para estos avisos. Se usa el cliente HTTP de Laravel 8/Guzzle 7.8.1, incluido en el proyecto. Constraint de PHP del proyecto: `^7.3|^8.0`; PHP local verificado: 7.4.21.
- Variables nuevas: `RESEND_API_KEY` (clave restringida a envío), `RESEND_FROM_EMAIL` (remitente de dominio verificado) y `ADMIN_NOTIFICATION_EMAIL` (destinatario opcional de altas). La clave no se incluye en el repo; completar en el `.env` privado del servidor. `ADMIN_NOTIFICATION_EMAIL` vacío desactiva solo el mail de altas, no la notificación interna.
- En la última inspección, el `.env` local todavía tenía la configuración SMTP anterior y no tenía `RESEND_API_KEY` ni `RESEND_FROM_EMAIL`; el nuevo cliente HTTP no lee `MAIL_PASSWORD`. Ingresar una clave nueva/rotada y el remitente verificado antes de probar un envío real.
- Cada POST tiene timeout de 5 segundos y hasta dos intentos (un reintento) únicamente ante fallas de conexión. Resend recibe `Idempotency-Key` para evitar duplicados durante 24 h. La API responde después del guardado; el envío se ejecuta al terminar la respuesta y sus errores no revierten el alta ni el cambio de contraseña.
- Los logs guardan propósito, estado/tipo de error y clase de excepción; nunca API key, direcciones, cuerpo del correo ni respuesta completa de Resend.
- Registro nuevo: se conserva la notificación de base de datos para los admins. El aviso de cambio de contraseña va al correo anterior, sin incluir la contraseña.

### Configurar y verificar Resend

1. En Resend, crear una API key de producción con permiso solo para enviar emails. Si se expuso una clave anterior, revocarla y usar una nueva.
2. Agregar `untoquedeluz.com.ar` en Domains de Resend. Copiar desde el panel los registros DNS que aparecen para ese dominio, normalmente DKIM y SPF (TXT/CNAME según lo indique Resend), en el DNS autoritativo. No inventar los valores: son únicos de la cuenta.
3. Esperar el estado Verified en Resend. Configurar `RESEND_FROM_EMAIL` con una dirección de ese dominio verificado, por ejemplo `notificaciones@untoquedeluz.com.ar` si está permitido por la configuración.
4. Cargar `RESEND_API_KEY`, `RESEND_FROM_EMAIL` y `ADMIN_NOTIFICATION_EMAIL` en el `.env` privado del servidor. El ejemplo deja `RESEND_API_KEY` y `ADMIN_NOTIFICATION_EMAIL` vacíos y usa un dominio placeholder para el remitente.
5. Después de configurar, ejecutar `php artisan config:clear` si se puede usar Artisan y realizar una prueba controlada con un usuario nuevo. Para actualizar el vendor sin consola en el hosting, generar en local con PHP compatible: `composer install --no-dev --optimize-autoloader`.

### Checklist de producción

- [ ] Revocar cualquier clave Resend expuesta y crear una clave restringida a envío.
- [ ] Verificar el dominio en Resend mediante los registros DNS publicados en su panel; comprobar estado Verified.
- [ ] Usar `RESEND_FROM_EMAIL` del dominio verificado y establecer `ADMIN_NOTIFICATION_EMAIL`.
- [ ] Confirmar PHP compatible con el constraint `^7.3|^8.0`; se recomienda desplegar con una versión probada (PHP 7.4.21 local o PHP 8.x validado).
- [ ] Generar `vendor` para producción con `composer install --no-dev --optimize-autoloader` usando la versión de PHP del hosting; incluir `vendor` al subir si el plan no ofrece Composer/SSH.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false`, permisos de `storage`/`bootstrap/cache`, configuración segura y no subir `.env` al repositorio.
- [ ] Probar email de alta y cambio de contraseña, verificar entrega en Resend Activity y confirmar que un error HTTP no afecte el registro/cambio.

### Verificación local de Resend HTTP

- `php artisan test` -> ver “Total real de tests”; incluye el borrado de avisos leídos.
- `ResendApiMailerTest`, `RegistrationNotificationsTest` y `UserProfileTest` se ejecutan con `Http::fake`; el envío real no se prueba desde la suite.
- Ningún email real se envió durante las pruebas automatizadas; verificar una vez rotada la clave, validado el dominio y cargadas las variables de Resend.
- Frontend: `npm test` -> ver “Total real de tests”; `npm run build` -> correcto después de agregar persistencia de sesión y refresco del directorio admin.

## Módulo de registro de pagos (manual)

Registro manual de pagos de alumnos por mes, con pagos parciales, saldos y saldo a favor, cargado por el admin. NO hay pasarelas de pago ni comprobantes fiscales.

### Modelo

- `cargos`: lo que debe el alumno (periodo mensual, `monto_original`, `descuento` con motivo, `monto_final`, `vence_el`, `estado` pendiente/parcial/pagado/anulado, vínculo opcional a ciclo/inscripción). Anulación lógica con motivo.
- `pagos`: dinero recibido (monto, `fecha_pago`, método efectivo/transferencia/otro, referencia, nota, vigente/anulado, quién lo registró).
- `pago_aplicaciones`: reparte un pago entre cargos (vigente/anulada con motivo). El saldo a favor de un alumno es SIEMPRE calculado: pagos vigentes menos aplicaciones vigentes. Saldos de cargo y de mes se calculan; no se guardan. El `estado` del cargo se recalcula en la misma transacción.
- Migración aditiva `2026_10_14_100000_create_payment_ledger_tables.php` con `DECIMAL(12,2)`; sin borrados físicos ni cascadas. **Pendiente: aplicarla en MySQL con `php artisan migrate` (aún no ejecutada en la base local).**
- Aritmética en centavos enteros (`App\Support\Money`); la API serializa importes como texto decimal (`"1500.00"`). Fechas en `America/Argentina/Buenos_Aires`; `fecha_pago` no puede ser futura. Frontend: formato es-AR `$ 12.500,00` (`src/utils/money.js`).

### Reglas

1. Al inscribirse a un ciclo con precio se crea el cargo del mes dentro de la misma transacción de inscripción (idempotente).
2. “Generar cargos faltantes del ciclo” es idempotente: devuelve `{creados, omitidos, sin_precio}`.
3. Cargos manuales; descuento con motivo obligatorio y sin dejar el cargo por debajo de lo aplicado.
4. Registrar pago con `cargo_id` opcional y `aplicar_excedente`: el excedente va a otros cargos pendientes (más antiguos primero) o queda como saldo a favor. Sin `cargo_id`, todo queda como saldo a favor.
5. El saldo a favor NO se aplica solo: el admin usa “Aplicar saldo a favor” y la pantalla muestra un aviso.
6. Desaplicar y anular pago exigen motivo; anular pago muestra antes los cargos afectados (`cargos_afectados`). Anular cargo solo sin aplicaciones vigentes.
7. Dar de baja una inscripción NO anula el cargo: solo se marca “Inscripción dada de baja, revisar cargo”.
8. Concurrencia: cada operación corre en `DB::transaction`, bloquea primero la fila del alumno (`lockForUpdate` en `yoguinis`) y luego cargos/pagos/aplicaciones.
9. **Devoluciones (refunds): NO implementadas, pendientes.**

### Endpoints (Sanctum; admin bajo `can:manage,App\Models\Cargo`)

- Admin, prefijo `api/payments/admin`: `GET /charges?periodo&estado&ciclo_id&user_id&q`, `GET /summary?periodo`, `GET /students/{id}`, `POST /charges`, `PATCH /charges/{id}/discount`, `PATCH /charges/{id}/void`, `POST /charges/{id}/apply-credit`, `POST /cycles/{ciclo}/charges/generate`, `POST /payments`, `PATCH /payments/{id}/void`, `PATCH /applications/{id}/void`.
- Alumno: `GET /api/payments/me` (solo lectura, solo datos propios).

### Archivos tocados

- Backend nuevo: migración citada, `app/Support/Money.php`, modelos `Cargo`/`Pago`/`PagoAplicacion`, servicios `CargoService`/`PagoService`/`ResumenMensualService` + `Concerns/LocksStudent`, `Policies/PaymentAdminPolicy`, `Rules/MoneyAmount`, `Http/Requests/Payments/*`, `Http/Resources/Payments/*`, controladores `PaymentAdminController`/`PaymentStudentController`.
- Backend modificado: `AuthServiceProvider`, `routes/api.php`, `InscripcionService` (crea el cargo al inscribir).
- Frontend nuevo: `pages/AdminPagos.jsx`, `pages/MisPagos.jsx`, `components/payments/{ActionModal,shared}.jsx`, `Styles/Pagos.css`, `utils/money.js`. Modificado: `pages/App.jsx` (rutas `/adminPagos`, `/misPagos`), `NavbarAdmin.jsx` (Administrar > Pagos), `NavbarUser.jsx` (Mis pagos), `public/robots.txt`.

### Tests agregados

- `tests/Feature/PaymentLedgerTest.php` (22 tests): cargo por inscripción e idempotencia, pagos parciales, saldo a favor, excedente, aplicar/desaplicar, anulaciones, descuentos, centavos, resumen mensual, filtros, autorización y aislamiento del alumno.
- `tests/Feature/PaymentConcurrencyTest.php` + `tests/Support/payment-credit-worker.php`: concurrencia MySQL opt-in (mismas guardas que la de cupos: `RUN_BOOKING_CONCURRENCY_TESTS=1`, `.env` local/mysql, `BOOKING_CONCURRENCY_DB_DATABASE`). Dos workers aplican el mismo saldo a favor: uno aplica y el otro es rechazado. **No se ejecutó contra MySQL** (omitida por defecto; requiere aplicar antes la migración).
- Frontend: `tests/money.test.js` (3 tests).

## Total real de tests

Última corrida completa: Laravel `php artisan test` -> **88 tests** (86 aprobados, 2 omitidos de concurrencia MySQL opt-in, 0 fallidos); frontend `npm test` -> **15 tests** aprobados; `npm run build` -> correcto.

## Migraciones Laravel 12 y despliegue inicial

Actualizado: 2026-10-16. Esta sección es la referencia vigente para `api.untoquedeluz`; las notas históricas anteriores describen entregas previas.

### Esquema y migraciones finales

- Una migración creadora por tabla de la API. Se quitaron las migraciones scaffold de `users`/`sessions`/`password_reset_tokens`, `cache` y `jobs`/`failed_jobs`; esos drivers no se usan en la instalación nueva. En `.env.example` y `.env.production.example`: `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.
- `2019_12_14_000001_create_personal_access_tokens_table.php` es la única creadora de `personal_access_tokens` y usa el esquema Sanctum 4. La migración `2026_10_16_100000_reconcile_personal_access_tokens_for_sanctum_v4.php` agrega `expires_at` con índice solo si falta y deja `name` como `TEXT NOT NULL` en MySQL/MariaDB.
- `actividades` y `yoguinis` se crean sin timestamps, con la nulabilidad, longitudes y collation legacy. `actividades.id` es `INT(11) UNSIGNED`; `yoguinis.id` es `INT(11)` firmado para mantener compatibles sus FKs.
- `2026_10_09_130000_allow_reusing_cancelled_class_slots.php` verifica índices existentes y conserva primero el índice que requiere la FK antes de reemplazar la unicidad anterior.
- `users`, `failed_jobs` y `password_resets` pueden seguir existiendo en una base legacy, pero no se crean en una instalación nueva. Si en el futuro se cambia cache/session/queue a drivers de base de datos, se agrega entonces la migración correspondiente.

### Cuadro de diferencias: MariaDB real frente al fresh

Comparación final `mysqldump --no-data` ejecutada después de aplicar la migración autorizada en `un_toque_de_luz`. Se compararon columnas, tipos, nulabilidad, defaults, índices, FKs y reglas de FK. Las 17 tablas de aplicación coinciden exactamente entre `un_toque_de_luz_scratch` y la base real; ambas incluyen además la tabla técnica `migrations`. La base real conserva las tablas legacy adicionales `users`, `failed_jobs` y `password_resets`.

La diferencia que existía antes de migrar quedó resuelta:

| Tabla | Columna | Real antes | Real después / fresh | Resultado |
|---|---|---|---|---|
| `personal_access_tokens` | `name` | `VARCHAR(255) NOT NULL` | `TEXT NOT NULL` | Aplicado para Sanctum 4. |
| `personal_access_tokens` | `expires_at` | No existía | `TIMESTAMP NULL`, después de `last_used_at`, con índice | Agregado por la migración idempotente. |
| Tablas API restantes | Columnas, tipos, índices y FKs | Coincidían | Coinciden | Sin diferencias. |
| `users`, `failed_jobs`, `password_resets` | Tabla completa | Existen solo en la base legacy | No se crean en fresh | Conservadas sin cambios. |

`actividades.id` se verificó como `INT(11) UNSIGNED` también en el fresh. Los flujos de administración y perfil guardaron valores vacíos como `NULL` en las columnas nullable, sin errores por `NOT NULL` ni truncamiento de longitudes.

### Verificación de esta etapa

- `php artisan migrate:fresh --seed --force` en MySQL `un_toque_de_luz_scratch`: correcto; 17 tablas de aplicación más `migrations`.
- Clone descartable `un_toque_de_luz_reconciled_scratch`, restaurado desde el backup y migrado: correcto. Los conteos de las 20 tablas existentes antes/después fueron iguales. Los 165 tokens de Sanctum mantuvieron el mismo conteo y checksum agregado.
- Comparación no-data final: las 17 tablas de aplicación del fresh y la base real migrada son idénticas; sin diferencias de columnas, tipos, índices ni FKs. Las tablas legacy adicionales están enumeradas arriba.
- Suite MySQL sobre scratch: `php vendor/bin/phpunit --configuration phpunit.mysql.xml` -> 94 tests, 546 assertions, 2 pruebas de concurrencia omitidas por opt-in.
- Suite Laravel SQLite: `php artisan test --compact` -> 93 tests aprobados, 547 assertions, 2 pruebas de concurrencia omitidas por opt-in.
- Frontend: `npm test` -> 15/15 aprobados; `npm run build` -> correcto. Vite mantiene el warning preexistente de `/img/fondo_1.jpg`, que queda sin resolver al build y se busca en runtime.
- Incluye login que persiste y usa un token Sanctum, CRUD de actividades y yoguinis, guardado de campos vacíos, edición de perfil/cambio de contraseña y las consultas usadas por `/adminTurnos` (`booking/admin/cycles` y `booking/admin/classes`). No hubo errores de columnas `NOT NULL` ni tipos en esos flujos.
- `ConfiguracionSeeder` fue verificado como idempotente y fija `horas_aviso_minimas = 24`.
- `php artisan migrate --pretend --force` mostró únicamente `2026_10_16_100000_reconcile_personal_access_tokens_for_sanctum_v4`: agregar `expires_at`, agregar su índice y modificar `name` a `TEXT NOT NULL`. Después de la confirmación explícita se ejecutó `php artisan migrate`; Artisan registró la migración en el lote 10, el historial quedó en 15 registros, y los 165 tokens conservaron el mismo checksum agregado.
- Backup previo, fuera del repositorio: `C:\Users\gcamejo\Backups\un_toque_de_luz\un_toque_de_luz_pre_migrations_20261016_110053.sql`, 66.851 bytes, SHA-256 `127F2CE408360E838EA7B01276846386CFAFAEA9B393E0EEC8E753D9F777CEDB`.

### Seeders y primer despliegue

Seeders que se ejecutan en producción:

- `php artisan db:seed --class=ConfiguracionSeeder --force` -> fija idempotentemente `horas_aviso_minimas = 24`.
- `php artisan db:seed --class=FeriadosArgentinaSeeder --force` -> carga idempotentemente los feriados nacionales argentinos del año actual y el siguiente; calcula Carnaval y Viernes Santo y aplica los traslados nacionales. No agrega días turísticos puente.
- `php artisan db:seed --class=AdminInicialSeeder --force` -> crea/promueve el administrador indicado por `INITIAL_ADMIN_EMAIL` y `INITIAL_ADMIN_PASSWORD`, o solicita email y contraseña con entrada oculta en consola. La contraseña se hashea; al repetirlo no se reemplaza una contraseña existente. No hay credenciales en el código ni en los ejemplos.

`DatabaseSeeder` ejecuta solo `ConfiguracionSeeder` y `FeriadosArgentinaSeeder`; el admin se ejecuta explícitamente para evitar que `migrate:fresh --seed` dependa de credenciales interactivas.

La instalación de producción es una base MySQL/MariaDB **vacía**:

```sh
php artisan key:generate --show
php artisan migrate:fresh --seed --force
php artisan db:seed --class=AdminInicialSeeder --force
```

Generá una clave nueva en el servidor con `key:generate --show` y cargá ese valor solo en el `.env` privado de producción. No copies la `APP_KEY` de desarrollo ni publiques la salida del comando. `migrate:fresh` elimina todas las tablas y datos: es solo para la instalación inicial en la base vacía, nunca para actualizar una instalación con datos.

La base local existente `un_toque_de_luz` ya recibió la migración de Sanctum después del backup y la confirmación explícita; el estado final se comparó con el fresh y no presenta diferencias en las tablas de aplicación. Para otras instalaciones existentes, crear primero un backup, revisar `php artisan migrate --pretend --force` y aplicar solo después de confirmar el destino. No usar `migrate:fresh` sobre una base con datos.