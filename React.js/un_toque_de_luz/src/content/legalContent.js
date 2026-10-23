export const POLICY_VERSION = 'borrador-2026-10-23-v1'
export const POLICY_UPDATED_AT = '2026-10-23'
export const POLICY_DRAFT = true

export const legalContent = {
  privacidad: {
    title: 'Política de Privacidad',
    introduction: 'Este es un borrador para revisión legal. Describe el funcionamiento observado del sistema; completá los datos marcados antes de publicarlo como versión definitiva.',
    sections: [
      {
        title: 'Responsable',
        paragraphs: [
          '[COMPLETAR NOMBRE O RAZÓN SOCIAL]',
          '[COMPLETAR CUIT/DNI]',
          '[COMPLETAR DOMICILIO]',
          '[COMPLETAR EMAIL DE CONTACTO PRIVACIDAD]',
        ],
      },
      {
        title: 'Datos que trata el sistema',
        paragraphs: [
          'Para crear una cuenta se solicitan nombre, apellido, domicilio, número, teléfono, fecha de nacimiento, email y contraseña. La contraseña se almacena como hash y no se devuelve en las respuestas normales de la API.',
          'Para gestionar clases se asocian a la cuenta las inscripciones, horarios elegidos, reservas, asistencia/falta, cancelaciones y créditos de recuperación con su fecha de vencimiento.',
          'Para administrar cargos y pagos se registran períodos, importes, descuentos, estado, medio de pago, referencia, quién registró la operación y, cuando corresponde, notas y motivos de anulación. Esos campos de texto libre podrían recibir información personal o sensible si alguien la escribe allí.',
          'También se guardan notificaciones de registro con nombre, email y fecha; los tokens de acceso se guardan como hash junto con su titular, permisos y fechas de uso/vencimiento. La tabla legacy `users` contiene además campos de autenticación de dos factores; la API actual autentica con `yoguinis`.',
          'El sistema registra cada aceptación de Privacidad y Términos con la cuenta, el tipo de documento, su versión y la fecha/hora. No guarda la dirección IP en ese registro.',
          'La base legacy conserva `password_resets` (email, token y fecha) y `failed_jobs` (payload/excepción de un trabajo fallido); la API actual no tiene un flujo de restablecimiento de contraseña implementado y usa cola síncrona para los nuevos procesos.',
          'No hay campos estructurados para salud, lesiones ni antecedentes médicos. Los campos libres `nota`, `motivo_descuento`, `motivo_anulacion` y el contenido de notificaciones podrían incluir accidentalmente observaciones de salud: [DEFINIR SI SE RECOLECTAN OBSERVACIONES DE SALUD].',
        ],
      },
      {
        title: 'Finalidades',
        paragraphs: [
          'Los datos se usan para crear y administrar cuentas, mostrar actividades, organizar ciclos y horarios, gestionar inscripciones, reservas, asistencia y créditos, registrar cargos/pagos manuales y enviar avisos de alta o cambio de contraseña.',
          'El acceso administrativo permite consultar datos de alumnos y gestionar clases y pagos según los permisos del sistema.',
        ],
      },
      {
        title: 'Proveedores y enlaces externos',
        paragraphs: [
          'Resend recibe los emails transaccionales configurados: al registrar una cuenta, el mensaje al administrador puede incluir nombre, email y fecha; al cambiar contraseña, el aviso al correo anterior incluye nombre y fecha, nunca la contraseña. El DPA público de Resend informa que sus operaciones primarias de procesamiento tienen lugar en Estados Unidos; verificá contrato, cuenta y subprocesadores vigentes antes de publicar.',
          'El sitio puede abrir enlaces `wa.me` al hacer clic en WhatsApp. El enlace incluye el número de destino y el texto de consulta; la persona continúa en WhatsApp y decide si envía el mensaje. El código no permite determinar el país/región en que WhatsApp/Meta procesa esa conversación.',
          'No se encontraron mapas, videos, redes sociales embebidas, fuentes remotas ni servicios de analítica cargados por el frontend. Los paquetes de frontend se descargan durante el build, no desde el navegador del alumno.',
        ],
      },
      {
        title: 'Almacenamiento en el navegador',
        paragraphs: [
          'Para mantener la sesión durante la pestaña actual, React guarda en `sessionStorage` la clave `un-toque-de-luz.auth-session`, con el token Bearer de Sanctum y los datos de usuario necesarios para la interfaz. No guarda la contraseña. La clave se borra al cerrar sesión o si el contenido no es válido; `sessionStorage` se limita a la sesión/pestaña del navegador.',
          'La autorización de la API se realiza con el token Bearer guardado en `sessionStorage`, no mediante una cookie de sesión. El login actual no requiere la cookie CSRF de Sanctum. El comportamiento del navegador/proveedor debe verificarse al desplegar en subdominios HTTPS.',
        ],
      },
      {
        title: 'Conservación y eliminación',
        paragraphs: [
          'Las cuentas con inscripciones, reservas o registros de consentimiento legal no pueden borrarse físicamente desde administración; el sistema rechaza la operación y conserva el historial. Los cargos, pagos y aplicaciones se anulan/cambian de estado en vez de borrarse físicamente. Las clases se archivan con borrado lógico y los créditos vencidos pasan a estado “vencida”, sin eliminar su fila.',
          'Las notificaciones pueden borrarse manualmente después de leídas. Al eliminar una actividad también se borra su imagen subida correspondiente; reemplazar una imagen no elimina automáticamente la anterior. No hay una purga automática de tokens vencidos, notificaciones, pagos o historial contable implementada por la aplicación.',
          'El plazo de conservación legal debe completarse con asesoramiento: [COMPLETAR PLAZO EN AÑOS, EJ: 10 AÑOS]. La configuración de producción de logs diarios prevé 7 días; la retención de logs de acceso del hosting depende del proveedor y no se verificó desde este proyecto.',
        ],
      },
      {
        title: 'Derechos de acceso, rectificación, actualización y supresión',
        paragraphs: [
          'La persona titular puede solicitar acceso a sus datos; la respuesta debe darse dentro de 10 días corridos. Puede pedir rectificación o actualización de datos inexactos y supresión cuando corresponda; la respuesta debe darse dentro de 5 días hábiles. El borrador no implementa un trámite automático: el marcador de contacto de privacidad indica dónde presentar la solicitud.',
        ],
      },
      {
        title: 'Autoridad de control',
        paragraphs: [
          '“La AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA, en su carácter de Órgano de Control de la Ley N° 25.326, tiene la atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento de las normas vigentes en materia de protección de datos personales.”',
        ],
        link: { href: 'https://www.argentina.gob.ar/aaip', label: 'www.argentina.gob.ar/aaip' },
      },
      {
        title: 'Personas menores de edad',
        paragraphs: [
          'El registro solicita fecha de nacimiento, pero el sistema no aplica una edad mínima ni un flujo de autorización de responsables adultos. La política del estudio debe definirse antes de publicar: [DEFINIR SI SE ADMITEN MENORES EN EL ESTUDIO].',
        ],
      },
    ],
  },
  terminos: {
    title: 'Términos y Condiciones',
    introduction: 'Este es un borrador para revisión legal. Resume lo que permite actualmente la aplicación y no reemplaza la definición comercial ni el asesoramiento profesional.',
    sections: [
      {
        title: 'Ciclos, horarios e inscripción',
        paragraphs: [
          'Las clases se organizan en ciclos con fechas, horarios semanales, cupos y precio publicados. Para inscribirse, la persona crea una cuenta, elige la cantidad de horarios indicada para el ciclo y confirma la inscripción. La reserva se genera para las clases futuras disponibles de esos horarios.',
          'El ciclo puede pausarse o archivarse por administración. El sistema conserva el historial de clases, reservas e inscripciones.',
        ],
      },
      {
        title: 'Aceptación de documentos',
        paragraphs: [
          'Para registrar una cuenta se requiere marcar una casilla de aceptación. Cuando se publica una nueva versión, la aplicación solicita aceptar los documentos pendientes antes de habilitar las funciones autenticadas. La versión y la fecha/hora se guardan por tipo de documento.',
        ],
      },
      {
        title: 'Cancelaciones por parte del alumno',
        paragraphs: [
          'La cancelación de una reserva genera un crédito de recuperación cuando se realiza con al menos {{horas_aviso_minimas}} horas de anticipación al inicio de la clase. El valor se obtiene de la configuración vigente del sistema.',
          'El crédito vence el último día del mes en que se dictaba la clase cancelada. Puede usarse en una clase futura del mismo mes, sujeto a cupo, y queda asociado a la cuenta que lo generó. La aplicación no permite transferirlo a otra cuenta ni canjearlo por dinero.',
          'Con menos del aviso mínimo no se genera crédito. Si se cancela una reserva de recuperación con aviso, el crédito original puede volver a quedar disponible sin crear una cadena de créditos.',
        ],
      },
      {
        title: 'Cancelaciones por parte del estudio',
        paragraphs: [
          'Cuando administración cancela una clase futura, el sistema marca la clase como cancelada y genera o devuelve créditos a las reservas afectadas. El comportamiento implementado hace vencer los créditos generados al final del mes de la clase; no hay reprogramación automática ni crédito sin vencimiento. La política solicitada de reprogramación o acreditación sin vencimiento dentro del mes no coincide completamente con ese flujo: [DEFINIR POLÍTICA PARA CANCELACIONES DEL ESTUDIO].',
        ],
      },
      {
        title: 'Pagos y saldos',
        paragraphs: [
          'La aplicación registra pagos manualmente desde administración; no integra una pasarela de pago ni cobra automáticamente. Los pagos pueden aplicarse a cargos, registrar pagos parciales o generar saldo a favor, que se calcula como dinero recibido menos aplicaciones vigentes. Administración puede aplicar ese saldo a cargos pendientes.',
          'Los cargos mantienen estados pendiente, parcial, pagado o anulado. El sistema registra vencimientos cuando se informan, pero no calcula intereses, recargos automáticos ni acciones de cobranza por mora.',
        ],
      },
      {
        title: 'Aptitud para la práctica y responsabilidad',
        paragraphs: [
          'El sistema no solicita ni registra una declaración de aptitud física, lesiones o antecedentes de salud. La declaración y sus efectos jurídicos requieren definición y revisión: [COMPLETAR / REVISAR LEGALMENTE LIMITACIÓN DE RESPONSABILIDAD].',
        ],
      },
      {
        title: 'Ley aplicable y jurisdicción',
        paragraphs: [
          'La ley aplicable y jurisdicción deben completarse antes de aceptar estos términos: Tribunales Ordinarios en lo Comercial de [COMPLETAR JURISDICCIÓN / CIUDAD].',
        ],
      },
    ],
  },
}