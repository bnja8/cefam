# Farmacia Asistencial CESFAM

Aplicación Laravel para apoyar el inventario y la dispensación de una farmacia asistencial. Laravel gestiona la lógica, los modelos y las rutas web; las páginas se renderizan con Blade y usan los recursos JavaScript y CSS de `resources`. Las migraciones Laravel crean y actualizan el esquema directamente, sin depender de archivos `.sql`; `database/schema/nuevo_schema.sql` se conserva como referencia e importación manual opcional. Los RUT de pacientes, profesionales de salud y usuarios funcionan como claves primarias; sus relaciones usan las columnas `paciente_rut`, `profesional_rut` y `usuario_rut`.

Laravel es el único componente que se conecta a MariaDB/MySQL. La interfaz web se sirve desde la misma aplicación; la API JSON versionada se mantiene disponible para integraciones y clientes que la necesiten.

Los cambios incrementales del esquema se registran en migraciones Laravel. Los archivos SQL de `database/schema` son snapshots de referencia y no son necesarios para instalar o migrar la base de datos.

## Requisitos

- PHP 8.5.12 o superior con extensiones PDO del motor elegido.
- Laravel 13.
- Composer.
- MySQL 8.0 o superior; SQLite se utiliza exclusivamente para las pruebas automatizadas.
- Node.js y npm para compilar los recursos de Vite.

## Instalación

1. Instala las dependencias:

   ```bash
   composer install
   npm install
   ```

2. Copia `.env.example` como `.env`, configura las credenciales de MySQL y crea la base `cefam`.

3. Genera la clave y define `ADMINISTRATOR_INITIAL_PASSWORD` fuera del código, con un secreto único de al menos 16 caracteres. Se necesita solo si el seeder debe crear la primera cuenta administradora. Después aplica el esquema, carga los datos de demostración y compila los recursos:

   ```bash
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   php artisan serve
   ```

   Para ejecutar las pruebas, se usa SQLite en memoria; la base operativa configurada en `.env.example` es MySQL (`DB_CONNECTION=mysql`). `php artisan migrate --force` crea el esquema desde cero con las migraciones y también reconoce un esquema completo que ya se haya importado. `php artisan migrate --seed` además ejecuta `DatabaseSeeder`, que carga roles, categorías, ubicaciones, el catálogo farmacéutico de referencia y la cuenta administradora inicial. Las sesiones se guardan en archivos (`SESSION_DRIVER=file`), así no dependen de una tabla `sessions` ajena al esquema de farmacia.

4. Inicia Laravel con `php artisan serve` y abre la URL local que indica el comando. Para recargar los recursos CSS y JavaScript durante el desarrollo, ejecuta `npm run dev` en otra terminal; `npm run build` genera los recursos para producción.

Para agregar más registros de ejemplo en pacientes, autorizaciones, inventario, programas, solicitudes, remesas y movimientos, ejecuta:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Este seeder es adicional al seeder base, es repetible y no elimina ni reinicia los datos operativos. Incluye registros activos e inactivos, lotes próximos a vencer y vencidos para probar búsquedas, filtros y reportes.

## Cuenta administradora

No existe una contraseña de demostración fija. El seeder falla de forma explícita si debe crear la cuenta administradora y `ADMINISTRATOR_INITIAL_PASSWORD` no contiene un secreto de al menos 16 caracteres. Inyéctalo desde el gestor de secretos o como variable temporal de proceso, ejecuta el seeder y retíralo después. No lo guardes en el repositorio ni en imágenes de despliegue. Si ya existe la cuenta anterior, el seeder conserva su hash de contraseña al migrarla.

Si el esquema ya fue importado y solo necesitas crear o completar la cuenta administradora, ejecuta:

```bash
php artisan db:seed --class=AdministratorSeeder
```

Este seeder migra la cuenta anterior `11.111.111-1` al nuevo RUT, actualiza sus referencias, asigna el rol de Administrador y conserva su contraseña. Si ninguna cuenta administradora existe, requiere `ADMINISTRATOR_INITIAL_PASSWORD`.

## Funcionalidad y reglas del sistema

Las pantallas activas se renderizan desde las plantillas Blade en `resources/views`. Los controladores y servicios Laravel comparten la lógica de negocio entre las rutas web y los endpoints JSON autenticados bajo `/api/v1`.

- JavaScript organizado en módulos por responsabilidad bajo `resources/js/modules`; `resources/js/app.js` conserva únicamente las importaciones y la inicialización.
- Inicio de sesión, cuentas con contraseña hasheada y roles CESFAM.
- Panel con stock disponible por categoría, pacientes y recetas atendidas, productos sin existencias, productos más dispensados y alertas operativas.
- Navegación por módulos con hubs responsivos de tarjetas de acceso y retorno al hub desde las vistas operativas; las rutas de alta y traspaso abren directamente sus formularios. Las operaciones muestran notificaciones globales de éxito o error y conservan el mensaje contextual en la vista.
- Búsqueda autenticada de pacientes, productos, lotes y códigos de barra desde la barra superior.
- Registro y búsqueda de pacientes.
- Consulta de personal de salud con búsqueda y filtro por tipo profesional; Administrador y Químico Farmacéutico pueden registrar profesionales, mientras Técnico Farmacéutico solo puede consultarlos.
- Inventario resumido por ID de producto (nunca agrupado solo por nombre), con stock disponible total y desglose de bodega/vitrina. Al abrir el producto se muestra una fila por lote y ubicación, con fecha de vencimiento y recepción, estado, alerta de mínimo y traspaso FEFO; la acción «Ver lotes» carga el detalle y lo lleva a la vista. El stock registrado de lotes vencidos o inactivos se conserva para trazabilidad, pero no se cuenta como disponible ni puede dispensarse.
- API activa: endpoints autenticados JSON de sesión, panel y los módulos de inventario, remesas, pacientes, personal, terceros, programas y afiliaciones, usuarios, recetas, dispensaciones, carros, libro controlado, órdenes de entrega, alertas, reportes, búsqueda y notificaciones.
- Arquitectura de ejecución: Laravel autentica las sesiones, ejecuta las reglas del dominio y renderiza las vistas Blade; los recursos estáticos se compilan con Vite. La API permanece como interfaz complementaria, no como requisito para servir la aplicación web.
- Recepciones posteriores del mismo producto, lote y ubicación se suman al stock existente, validando que la fecha de vencimiento del lote coincida, conservando el stock mínimo configurado y registrando cada recepción por separado para trazabilidad.
- Ajustes de existencias por solicitud: Administrador, Técnico Farmacéutico y QF pueden solicitar; solo un QF distinto del solicitante autoriza o rechaza. El stock cambia únicamente al aprobarse, y la solicitud queda vinculada al movimiento y a la auditoría. El esquema incremental está en `database/schema/database_1.11.sql`.
- La estructura conserva `productos` como catálogo, `lotes` como partidas únicas por `(producto_id, numero_lote)` e `inventario` como stock único por `(lote_id, ubicacion_id)`. Las fechas de vencimiento distintas corresponden a lotes distintos, no a productos distintos; el número de lote repetido con vencimiento contradictorio se rechaza. Las recepciones nuevas guardan la fecha de primera recepción del lote y su estado activo/inactivo; la migración recupera fechas antiguas desde remesas o movimientos cuando existe esa trazabilidad y deja el dato vacío cuando no puede determinarse.
- Dispensación con receta vigente obligatoria, validación de paciente y producto prescrito, control de cuotas y asignación FEFO de lotes vigentes.
- La asignación FEFO está centralizada y bloquea existencias dentro de la transacción; si el stock no alcanza, se revierten juntos el descuento, la entrega, las cuotas, los movimientos y la auditoría. Un lote sigue vigente durante su fecha de vencimiento.
- La receta limita las cantidades acumuladas por producto entre todas sus entregas; pasa a `UTILIZADA` solo cuando se completan todas sus líneas. Las preparaciones unidosis requieren paciente y tratamiento activos y vigentes en la fecha indicada; la confirmación QF es una transición atómica de `PREPARANDO` a `CONFIRMADA`.
- Preparación unidosis con búsqueda de medicamentos disponibles en el carro, horario `HH:mm` validado también en servidor y sin columna de dosis; programas de salud editables y activables/desactivables.
- Los formularios de alta de personas buscan por RUT y completan datos existentes; los duplicados del mismo módulo se bloquean en pantalla y se validan también en servidor. La impresión de etiquetas abre el diálogo para guardar como PDF sin navegar a la ruta de la etiqueta.
- Consulta autenticada de código de barras para identificar el producto y mostrar el lote vigente de vitrina más próximo a vencer; el descuento y la asignación final FEFO se validan al confirmar la dispensación.
- Orden de entrega imprimible con lotes dispensados, preparada para imprimir o guardar como PDF desde el navegador.
- Registro y consulta de recetas con detalle de medicamentos, profesional emisor, vigencia y tipo; al registrar, se puede indicar opcionalmente un tratamiento para el mismo paciente y guardar ambas operaciones en una única transacción.
- Programas y afiliaciones de pacientes activos con cuotas de productos; no se permiten afiliaciones activas con períodos superpuestos para el mismo programa. La vigencia de una cuota debe coincidir temporalmente con la afiliación.
- Solicitudes de reposición automáticas cuando el stock vigente alcanza el mínimo, por el déficit para recuperarlo y sin duplicar solicitudes pendientes o solicitadas; también se permiten solicitudes manuales. Recepción de remesas con ingreso de lotes a bodega: una remesa asociada solo puede cerrar una solicitud abierta y sus cantidades totales por producto deben coincidir con lo solicitado; los lotes distintos del mismo producto se conservan por separado.
- Carro de Paro con salidas trazables; Carro Unidosis con preparación FEFO, rótulos imprimibles y confirmación QF.
- Reportes filtrables con descargas CSV independientes de entregas y movimientos, además de exportaciones de existencias actuales y lotes próximos a vencer; impresión del navegador a PDF.
- Respaldo MySQL automático diario a las 02:00, en almacenamiento privado, con retención configurable (30 días por defecto).
- Libro de controlados exclusivo QF, con confirmación de identidad, validación de contraseña y descuento de existencias.
- Atención de alertas de stock y vencimiento.
- Alta y activación/desactivación de usuarios para Administrador.
- Edición de usuarios, pacientes, productos y personas autorizadas reservada al Administrador; los registros se desactivan en lugar de eliminarse para conservar el historial clínico y de inventario.
- Cantidades y existencias se muestran sin decimales innecesarios (por ejemplo, `1.200` en vez de `1.200,00`), preservando fracciones cuando existan.
- Las cantidades ingresadas respetan la precisión de dos decimales definida por `NUMERIC(12,2)` en el esquema, evitando redondeos silenciosos al guardar stock, recetas, cuotas y dispensaciones.
- Registro de nombres y apellidos con los campos separados definidos en `db/farmacia_asistencial_actualizada.sql`; el nombre completo se compone para mostrarlo en las vistas. El código visible del catálogo se persiste en `productos.numero_serie`.
- RUT chileno validado con Módulo 11 en cliente y servidor, normalizado a dígitos y DV mayúscula al guardarlo y antes de autenticar. Una migración canoniza registros anteriores y conserva las restricciones únicas. Los registros históricos se normalizan sin alterar su dígito verificador; si este no es válido, debe corregirse con el RUT confirmado antes de que la persona pueda iniciar sesión o volver a registrarse.
- Lectura de códigos de barras con lectores USB tipo teclado HID, procesando automáticamente la secuencia rápida y el Enter final, además de permitir escaneo en el campo enfocado.
- Página 404 propia para cualquier ruta inexistente.

El seeder incorpora registros demostrativos solo cuando faltan; volver a ejecutarlo no restablece existencias, perfiles, credenciales ni datos operativos existentes.

Administración global y reportes están restringidos a Administrador y Químico Farmacéutico. El libro digital y la confirmación de unidosis son exclusivos del Químico Farmacéutico. El registro de controlados confirma identidad con contraseña; no implementa una firma electrónica avanzada certificada, por lo que debe validarse con el procedimiento regulatorio local antes de usarlo como libro oficial.

El respaldo requiere el cliente MySQL (`mysqldump`); se puede configurar su ruta mediante `MYSQLDUMP_BINARY`. Para producción, configura el Programador de tareas de Windows o cron para ejecutar `php artisan schedule:run` cada minuto. Los archivos quedan en `storage/app/private/backups`, fuera de la carpeta pública. La restauración y su prueba deben ejecutarse en una base aislada antes de recuperar un respaldo en producción; la aplicación no reemplaza automáticamente la base activa. Las remesas se registran desde los documentos recibidos de CENABAST; la conexión automática con un servicio externo requiere especificación técnica y credenciales de integración que no forman parte del informe. El registro de controlados confirma identidad, conserva trazabilidad y genera alertas de descuadre por conciliación física; no incorpora firma electrónica avanzada certificada, que debe validarse con el procedimiento regulatorio local antes de usarlo como libro oficial.

## Organización Laravel

- `bootstrap/app.php`: registra las rutas web en `routes/web.php` y `routes/api.php` junto con los archivos JSON por módulo bajo `routes/api/`. Laravel agrega el prefijo `/api`; cada archivo conserva el prefijo `/v1` y sus middleware de sesión/autenticación.
- `routes/web.php`: rutas de navegación, formularios y operaciones HTML de la aplicación.
- `routes/api.php`: endpoints JSON versionados para sesión y panel; `routes/api/` contiene endpoints por módulo.
- `routes/console.php`: programación de respaldos y tareas de consola.
- `app/Http/Controllers/`: coordinación HTTP por módulo.
- `app/Http/Requests/`: validación de entradas separada de los controladores.
- `app/Models/`: modelos Eloquent para las entidades del dominio.
- `app/Services/`: reglas transaccionales, FEFO, búsquedas, auditoría, alertas y respaldos.
- `app/Support/PersonName.php`: composición y búsqueda de nombres usando las columnas normalizadas de usuarios, pacientes, profesionales y terceros.
- `resources/views/`: plantillas Blade para las páginas y componentes reutilizables.
- `resources/css` y `resources/js`: estilos y comportamiento del frontend compilados con Vite.

La aplicación web y su API se ejecutan desde el mismo proyecto Laravel; no hace falta mantener un proyecto Vue separado para operar el sistema.

## Despliegue

Despliega Laravel detrás de Apache o Nginx apuntando el document root a `public/`; no expongas directamente la base de datos. En producción establece `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` y `TRUSTED_PROXIES` con las direcciones privadas exactas de los proxies. La aplicación rechaza iniciar en producción si faltan estos controles. Compila los recursos con `npm run build` antes del despliegue.

## Pruebas

```bash
php artisan test
```
