# Guía vigente de producción — Un Toque de Luz

Actualizada: 2026-10-23. API Laravel 12 / PHP 8.3; frontend React/Vite. Esta es la única guía operativa vigente. Las notas anteriores se consideran históricas y no deben usarse para desplegar.

## Entorno y configuración

- Dominio del frontend: `untoquedeluz.com.ar`.
- API: `api.untoquedeluz.com.ar`, con document root apuntando a `api.untoquedeluz/public`.
- PHP del hosting: 8.3. La base local real ya tiene aplicada la migración Sanctum; no ejecutar `migrate:fresh` sobre bases con información.
- Zona horaria: `America/Argentina/Buenos_Aires` en ambos ejemplos y en PHPUnit. Las reglas de 24 horas, cambio de mes, último día del mes, medianoche local y resumen de pagos tienen tests en esa zona.
- Producción: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=daily`, `LOG_DAYS=7`, `LOG_LEVEL=warning`.
- Cache/sesión/cola: `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.
- Sanctum: emisión y expiración global a 30 días (`SANCTUM_TOKEN_TTL_MINUTES=43200`, `SANCTUM_EXPIRATION_MINUTES=43200`). Los tokens legacy con más de 30 días requieren nuevo login tras habilitar el límite global.
- Resend: `RESEND_VERIFY_SSL=true` en producción; el código solo permite omitir verificación TLS cuando `APP_ENV=local`. Configurar API key/remitente únicamente en el `.env` privado.
- Protección: límites probados para login, registro, cambio de contraseña, actividades públicas y ciclos públicos; cualquier exceso responde 429.

## Release sin SSH

El generador está en [deploy/build-release.ps1](deploy/build-release.ps1). **No se ejecutó**: primero revisar ese archivo y completar las decisiones legales listadas abajo. Requiere PHP 8.3, Composer, Node/npm y `React.js/un_toque_de_luz/.env.production` local. Verifica que `VITE_API_URL` use HTTPS y que `VITE_WHATSAPP_NUMBER` sea numérico, sin mostrar sus valores. El gate legal compara frontend, config Laravel staged y `.env.production.example` versionado de API; no lee el `.env.production` local para ese chequeo. El `.env` privado de API tampoco entra al ZIP ni se inspecciona; al configurarlo en hosting, mantener `LEGAL_POLICY_VERSION` y `LEGAL_POLICY_UPDATED_AT` alineados con el ejemplo versionado. Ante fallo elimina solo el directorio de release parcial creado por esa ejecución y restaura las variables de entorno temporales del checker. Produce dos ZIP bajo `release/`, ignora env/tests/node_modules/logs/dumps y vuelve a inspeccionar los ZIP para rechazar archivos privados.

Secuencia sugerida después de revisar el script:

1. Generar una `APP_KEY` nueva con `php artisan key:generate --show` en el entorno local privado PHP 8.3. Pegarla directamente en el `.env` privado del hosting; no copiar la clave de desarrollo ni guardar el valor en el repo.
2. Crear el `.env` de producción desde `api.untoquedeluz/.env.production.example` en el administrador de archivos del hosting. Completar credenciales, `APP_KEY`, `TRUSTED_PROXIES` solo si corresponde, y variables privadas de Resend. No subir ningún `.env`.
3. Crear una base vacía con phpMyAdmin. Importar el volcado verificado descrito abajo.
4. Subir el ZIP de API fuera de la carpeta pública y extraerlo. Configurar el subdominio API con document root en la carpeta `public` de ese release.
5. Subir el ZIP de frontend y extraer su contenido en el document root de `untoquedeluz.com.ar`.
6. Activar SSL/AutoSSL desde el panel para el dominio y subdominio. Habilitar “Force HTTPS” en el panel; si no existe, aplicar la regla HTTPS del ejemplo `.htaccess` abajo.
7. En el selector PHP del panel, elegir PHP 8.3 y habilitar `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `bcmath` y `gd` si se procesan imágenes. No dejar un `phpinfo()` público.
8. Configurar permisos de escritura del usuario PHP sobre `storage`, `bootstrap/cache` y `public/storage/img`; no usar permisos globales 777.
9. Configurar DNS del dominio del front y un registro A/AAAA para `api.untoquedeluz.com.ar` hacia el hosting. Esperar propagación y verificar ambos hosts por HTTPS.
10. En Resend, verificar `untoquedeluz.com.ar`, copiar del panel sus registros DNS (DKIM/SPF), esperar el estado Verified y cargar el remitente/API key solo en el `.env` privado.
11. En el panel, crear un cron diario al horario indicado abajo. No hace falta un proceso residente ni SSH.
12. Completar la checklist manual al final de este documento antes de retirar el release anterior.

## Privacidad, términos y consentimiento

La fuente única del borrador está en `React.js/un_toque_de_luz/src/content/legalContent.js`: `borrador-2026-10-23-v1`, actualizado el `2026-10-23`. Las páginas públicas y el gate usan esa versión. No publicar como texto definitivo hasta resolver los marcadores y aprobar el contenido legalmente.

| Datos observados | Uso/riesgo relevante |
|---|---|
| Cuenta: nombre, apellido, domicilio, número, teléfono, fecha de nacimiento, email y hash de contraseña | La API actual autentica con `yoguinis`; la tabla legacy `users` conserva campos 2FA. No hay edad mínima aplicada. |
| Inscripciones, horarios, reservas, asistencia/falta, cancelaciones y créditos | Historial operativo; vencimientos y cancelaciones se conservan. |
| Cargos, descuentos, pagos y aplicaciones | `nota`, referencias y motivos de descuento/anulación son texto libre y podrían contener datos personales o de salud escritos accidentalmente. |
| Notificaciones y tokens Sanctum | Notificaciones de registro incluyen nombre/email/fecha; tokens se guardan como hash. Resend envía avisos transaccionales configurados. |
| Consentimiento y residuos legacy | `consentimientos` registra usuario, tipo, versión y fecha/hora, sin IP. `password_resets` y `failed_jobs` legacy contienen email/token y payload/excepción, respectivamente. |
| Navegador e integraciones | Bearer token y datos de interfaz en `sessionStorage`; puede emitirse `XSRF-TOKEN` técnico. WhatsApp se abre por enlace. No se detectó analítica ni contenido remoto embebido. |

No hay campos estructurados para salud, lesiones o aptitud física, pero el texto libre puede recibirlos. No hay flujo de recuperación de contraseña implementado en la API actual. La conservación no tiene purga automática general; los plazos legales y logs del hosting requieren confirmar con asesoría/proveedor. El borrado físico de una cuenta se rechaza cuando conserva reservas, inscripciones o consentimientos. Pagos/cargos se anulan y conservan; no se borran como historial.

El registro requiere aceptar ambos documentos. Una versión nueva vuelve a bloquear las funciones autenticadas hasta aceptar los documentos pendientes; aceptación y timestamp quedan asociados por tipo y versión. Perfil ofrece exportación JSON de los datos de esa cuenta (perfil, inscripciones, reservas, créditos, consentimientos, cargos, pagos y aplicaciones), sin contraseña ni token. La solicitud de supresión crea una notificación interna para administración y **no borra datos automáticamente**. Administración también puede consultar el historial de consentimiento y recibir esa solicitud.

Decisiones que bloquean la publicación definitiva y el release:

- Identidad legal, CUIT/DNI, domicilio y email de privacidad.
- Si se admiten menores y si se recolecta información de salud en campos libres.
- Plazo legal de conservación, jurisdicción y revisión de la limitación de responsabilidad/aptitud.
- Política de cancelación del estudio: el comportamiento actual expira créditos al final del mes; no reprograma automáticamente ni mantiene crédito sin vencimiento. Definir la política antes de afirmar otra cosa.
- Confirmar contrato, subprocesadores y región vigente de Resend/WhatsApp y los plazos de respuesta legal con asesoría.

`npm run check:legal` actualmente falla solo por diez marcadores deliberados; no es un fallo técnico ni se deben reemplazar por datos inventados. El release debe permanecer bloqueado hasta completar la revisión.

El build del API se hace con `composer install --no-dev --optimize-autoloader`; el `composer.json` fija `config.platform.php=8.3`. En este hosting no usar `config:cache` ni `route:cache`: no hay consola para regenerarlas al cambiar el `.env`, y una cache vieja puede retener configuración anterior. La aplicación funciona con la carga normal de configuración.

## Carpetas públicas y `.htaccess`

```text
/home/USUARIO/apps/untoquedeluz-api/          API completa, vendor y .env privado
/home/USUARIO/apps/untoquedeluz-api/public/   document root de api.untoquedeluz.com.ar
/home/USUARIO/domains/untoquedeluz.com.ar/public_html/  contenido del ZIP frontend
```

El archivo `React.js/un_toque_de_luz/public/.htaccess` usa este fallback SPA; el build Vite lo copia a `dist/.htaccess`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ /index.html [L]
</IfModule>
```

Para un servidor Apache que no fuerza HTTPS desde el panel, anteponer en ambos document roots (web y API), antes del fallback/router:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

Usar esta regla solo si Apache ve directamente la conexión TLS. Si el proveedor termina TLS en un proxy, preferir el redirect HTTPS del panel para evitar loops.

La API conserva `api.untoquedeluz/public/.htaccess` para enviar rutas dinámicas a `index.php`. No copiar el `.htaccess` de Laravel al document root del frontend.

## Base vacía y volcado inicial

El esquema usa `utf8mb4_general_ci` para tablas legacy y `utf8mb4_unicode_ci` para el resto; no depende de collations recientes exclusivas de MySQL 8. El volcado se probó importándolo en una segunda base MariaDB 10.4.20 nueva: 17 tablas de aplicación, schema idéntico y sin errores. Las collations usadas (`utf8mb4_general_ci`, `utf8mb4_unicode_ci`) existen en MySQL y en versiones antiguas de MariaDB. No se probó en un servidor Oracle MySQL separado.

Procedimiento repetible, siempre con dos bases descartables y nombres únicos; nunca apuntar estas variables a `un_toque_de_luz`:

1. Crear `untoquedeluz_seed_export_tmp` vacía y asignarla a `DB_DATABASE` solo en el proceso local.
2. Ejecutar `php artisan migrate:fresh --seed --force`. Esto crea schema, configuración de aviso y feriados, no usuarios de prueba.
3. Para probar el primer administrador, ejecutar `php artisan db:seed --class=AdminInicialSeeder --force` en consola y usar credenciales efímeras. El prompt oculta la contraseña. Eliminar después esa cuenta de test del scratch antes de exportar.
4. Desde ese scratch limpio, crear el dump fuera del repo y fuera de cualquier document root:

```sh
mysqldump --single-transaction --quick --no-tablespaces NOMBRE_DB > /RUTA_PRIVADA/untoquedeluz-seed.sql
```

5. Crear `untoquedeluz_seed_import_tmp` nueva, importarla con phpMyAdmin y comparar estructura/contadores. El import de prueba finalizó sin errores; los scratch y dumps temporales de esta sesión fueron eliminados.
6. Eliminar ambos scratch. Para producción, importar solo el dump sin cuenta admin de prueba; luego correr `AdminInicialSeeder` desde la consola del hosting si está disponible. Sin consola, crear/promover la cuenta con un procedimiento privado aprobado por el proveedor; nunca incrustar password o hash en el release ni en el SQL publicado.

No guardar el dump dentro del repo. El `.gitignore` raíz excluye `*.sql`, extensiones de dump/backup y carpetas de respaldos.

### Producción existente con SSH (alternativa)

Para una actualización de código sobre una instalación ya poblada, primero backup, luego `php artisan migrate --force` y verificación. **Nunca** usar `migrate:fresh` sobre esa base. Esta alternativa solo aplica si el plan superior habilita SSH/CLI.

## Cron y respaldos

`booking:expire-credits` figura en Artisan, calcula “hoy” con `America/Argentina/Buenos_Aires` y es idempotente. El panel permite cada 5 minutos; programarlo una vez por día a las 00:05 Buenos Aires, o 03:05 si el cron usa UTC. El cron ejecuta directamente el comando; no configurar el scheduler de Laravel:

```cron
5 0 * * * /RUTA/PHP-8.3 /home/USUARIO/apps/untoquedeluz-api/artisan booking:expire-credits
```

Backups:

1. Habilitar además el backup automático que ofrece el hosting (archivos + base).
2. Programar un dump diario fuera del document root, por ejemplo `/home/USUARIO/private-backups/`, con `mysqldump --single-transaction --quick --no-tablespaces` y compresión gzip.
3. Mantener una rotación mínima de 14 diarios y 3 mensuales; proteger credenciales con un option file de MySQL fuera de la carpeta pública y permisos privados del panel. No poner passwords en la línea de cron.
4. Restauración: crear una base nueva en phpMyAdmin, importar el último dump verificado, comprobar tablas/contadores, apuntar el release de API a esa base desde el `.env` privado y probar lectura/login antes de retirar la base dañada. Conservar el backup original hasta verificar el servicio.

Restauración segura: primero importar a una base nueva, verificar que las tablas y filas estén presentes y conservar la base actual. Cambiar el `.env` privado para apuntar a la restaurada solo durante una ventana controlada. Si hay escrituras posteriores al backup, conciliarlas antes de volver a abrir el sitio; nunca sobrescribir la única copia buena.

## Checklist manual post-despliegue

- [ ] HTTPS funciona en el dominio y subdominio; HTTP redirige a HTTPS y el certificado es válido.
- [ ] El frontend carga y navega por rutas SPA al recargar; `ActiYoga.jpg` y las imágenes subidas responden desde `/storage/img/`.
- [ ] Registrar un usuario desde un ciclo concreto; comprobar que “Volver” vuelve a Yoga y que login lleva al ciclo preseleccionado.
- [ ] Login/logout y perfil; cambiar contraseña y confirmar correo de aviso sin exponer la clave.
- [ ] Aceptar Privacidad/Términos, comprobar el bloqueo antes de aceptar y verificar historial/timestamp de cada versión.
- [ ] Descargar el JSON de perfil y confirmar que solo contiene los datos de la cuenta autenticada, sin credenciales.
- [ ] Solicitar supresión y confirmar aviso interno a administración; verificar que la solicitud no borra la cuenta automáticamente.
- [ ] Reservar una clase; cancelar con 24 horas de margen y verificar crédito/mes de vencimiento; repetir con menos de 24 horas y comprobar que no se crea crédito.
- [ ] Usar un crédito de recuperación y confirmar que solo permite clases futuras del mes válido.
- [ ] Como admin, registrar un pago parcial y comprobar saldo pendiente; registrar excedente y comprobar saldo a favor; aplicar ese saldo a otro cargo.
- [ ] Confirmar aviso interno al administrador al registrar usuario y entrega de correo vía Resend; revisar que SSL permanece verificado y que las credenciales no aparecen en logs.
- [ ] Probar CORS desde el origen HTTPS del frontend y confirmar que un origen no permitido no recibe `Access-Control-Allow-Origin`.
- [ ] Verificar en el panel de cron la próxima ejecución y comprobar el log/resultado de `booking:expire-credits`.

## Reversión

Conservar el ZIP del release anterior y el backup previo de base hasta completar la checklist. Si falla solo el código, restaurar el document root al release anterior. Si hay corrupción de datos, pausar escrituras, restaurar el backup a una base nueva, validar y cambiar el `.env` privado a esa base. No borrar el release ni el backup anterior durante la ventana de verificación.

## Total real de tests

| Suite/verificación | Resultado verificado en esta sesión |
|---|---:|
| Laravel SQLite (`php artisan test --compact`) | 109 aprobados, 706 assertions, 2 omitidos |
| Frontend (`npm test`) | 22 aprobados, 0 fallidos |
| Frontend (`npm run build`) | Correcto |
| Gate (`npm run check:legal`) | 10 marcadores pendientes; sin discrepancia de versión/fecha |
| `deploy/build-release.ps1` | Sintaxis PowerShell válida; no ejecutado, pendiente de revisión del usuario |
| MariaDB 10.4 scratch | La migración de consentimiento se probó antes: `user_id INT(11)`, FK sin cascade, índice único `(user_id,tipo,version)`, sin IP; scratch eliminado. No se volvió a ejecutar la suite completa en MySQL en esta sesión. |

No se leyó ni modificó la base real ni un `.env` privado; PHPUnit usa SQLite `:memory:`. El gate leyó el `.env.production.example` versionado, no el `.env.production` local. Composer/npm audit y la validación Linux de la tabla histórica siguiente no se repitieron en esta sesión.

### Auditorías históricas (no repetidas)

| Verificación anterior | Resultado registrado |
|---|---:|
| Composer audit | 0 advisories |
| npm audit | 26 advisories: 2 críticas, 14 altas, 7 moderadas, 3 bajas |
| PSR-4 y casing de imports/assets | Correcto en Windows; no probado en kernel Linux |

## Auditoría y límite Linux

La inspección de Git no encontró SQL/dumps versionados: los únicos archivos `.env*` seguidos son ejemplos. Un escaneo de patrones comunes de llaves/tokens/passwords sobre todos los blobs Git encontró 0 matches; no sustituye rotar cualquier credencial expuesta anteriormente.

`npm audit` mostró advisories en `@babel/core`, `@babel/helpers`, `@babel/runtime`, `@remix-run/router`, `axios`, `body-parser`, `browserslist`, `compression`, `esbuild`, `express`, `express-session`, `follow-redirects`, `form-data`, `lodash`, `morgan`, `nanoid`, `on-headers`, `path-to-regexp`, `postcss`, `proxy-addr`, `qs`, `react-router`, `react-router-dom`, `rollup`, `source-map-js` y `vite`; fixes disponibles, pero no se aplicaron automáticamente. Planificar upgrades compatibles en otro cambio y no elevar major sin revisar.

WSL Ubuntu 24.04 existe, pero carece de runtimes PHP/Composer/Node Linux; el intento de instalar con `sudo -n` se detuvo sin contraseña. Docker Engine tampoco está activo. Para completar la corrida Linux, instalar PHP 8.3 con extensiones `pdo_mysql`, `sqlite3`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `gd`, Composer y Node 18+, luego ejecutar `composer install` + `php artisan test` en el API y `npm ci`, `npm test`, `npm run build` en el frontend.

## Histórico

Las implementaciones anteriores de reservas por ciclo, créditos, pagos manuales, notificaciones, perfiles y avisos de Resend siguen en el código. Las notas operativas anteriores fueron reemplazadas por esta guía; usar solo las rutas, cron, dominios y resultados documentados aquí.