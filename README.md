# Ruta Clara

Ruta Clara es una web app responsive para controlar la operacion diaria de choferes que trabajan con autos tipo Uber, Cabify o remis. El foco del MVP es cargar datos muy rapido desde celular y consultar ganancias, descuentos, neto y gastos generales sin usar Excel.

## Stack recomendado

- PHP 8.1+ con PDO.
- MySQL o MariaDB.
- HTML, CSS y JavaScript sin framework para que funcione bien en hosting compartido.
- Apache con carpeta publica `public/`.

Esta base esta pensada para Hostinger o un hosting PHP comun. Mas adelante se puede migrar a Laravel, API REST o app mobile sin cambiar el modelo central de datos.

## Estructura de base de datos

Tablas principales:

- `users`: usuarios con rol `admin` o `driver`, estado, ultimo acceso, espacio administrativo y vinculo opcional a `drivers`.
- `remember_tokens`: sesiones persistentes para el check "Recordarme".
- `password_resets`: tokens temporales para recuperar contrasena.
- `drivers`: choferes con nombre, telefono, DNI, estado y observaciones.
- `cars`: autos con marca, modelo, patente, año, estado y vinculacion al chofer asignada desde Choferes.
- `daily_entries`: cargas por dia o semana con chofer, auto, ganancias totales, devoluciones/gastos app, efectivo, alquiler a facturar, estado de facturacion, combustible informativo, kilometros y neto.
- `general_expenses`: gastos generales de flota/empresa sin relacion con chofer, con fecha, importe y observacion. Se descuentan de los alquileres para calcular el neto real de la flota.
- `weekly_money_notes`: observacion semanal administrativa para registrar que se hizo con la plata de la flota.

El archivo `database/schema.sql` recrea la base desde cero e incluye datos demo. Si ya tenes la base importada y solo queres agregar recordarme/recuperacion de contrasena, ejecuta `database/auth_tokens_migration.sql`. Para adaptar una base existente al nuevo modelo de carga de choferes y gastos generales, ejecuta `database/daily_entries_income_migration.sql`. Para separar la cuenta `personal` de los datos demo de `admin` / `chofer`, ejecuta `database/personal_workspace_migration.sql`. Para agregar el control semanal de que se hizo con la plata, ejecuta `database/weekly_money_notes_migration.sql`.

Usuarios iniciales:

- Usuario Personal: `personal` / `Cambiar123!`
- Administrador: `admin` / `Cambiar123!`
- Chofer: `chofer` / `Cambiar123!`

`personal` queda en su propio espacio administrativo. Los datos demo de `admin` y `chofer` pertenecen al espacio demo y no aparecen en el panel, choferes, autos, cargas, reportes ni usuarios de `personal`.

## Pantallas principales

- Panel: control operativo por dia, semana o mes con cargas, alquileres a facturar, estado de facturacion, gastos de flota/empresa, neto real de flota y observacion semanal del dinero.
- Carga: formulario mobile-first para crear una carga diaria o semanal con ganancias totales, devoluciones/gastos app, efectivo, alquiler a facturar, combustible, kilometros y observaciones.
- Choferes: alta, edicion, activacion/desactivacion, asignacion de auto, historial resumido, resumen de alquileres a facturar por semana o mes y acceso a cargas filtradas por chofer.
- Autos: alta, edicion, activacion/desactivacion y rendimiento acumulado.
- Reportes: totales, promedio diario, mejores dias, peores dias, comparacion semanal y comparacion mensual.
- Usuarios: administracion de accesos, roles y vinculaciones visible solo para `personal`.
- Login: check "Recordarme" y flujo "Olvide mi contrasena" con enlace temporal.

## Flujo de usuario

Administrador:

1. Ingresa al sistema.
2. Crea choferes.
3. Crea autos y luego los asigna desde la seccion Choferes.
4. Crea usuarios tipo chofer y los vincula al chofer correspondiente.
5. Revisa panel, cargas y reportes de toda la operacion.

Chofer:

1. Ingresa con su usuario.
2. Carga su dia desde el celular.
3. Consulta sus propios registros, autos asignados y reportes.

## UX/UI

- Mobile first para que la carga diaria sea rapida.
- Sidebar en escritorio y navegacion inferior en celular.
- Fondo claro, tarjetas sobrias, bordes suaves y alto contraste.
- Azul oscuro como identidad, verde para ingresos/netos positivos y naranja/rojo para gastos o alertas.
- Formularios cortos, botones grandes y calculo automatico visible antes de guardar.

## MVP inicial incluido

- Login con roles `admin` y `driver`.
- Login con "Recordarme" mediante cookie HttpOnly y token guardado en base.
- Recuperacion de contrasena mediante token temporal. En el MVP el enlace se muestra en pantalla; en produccion debe enviarse por email o WhatsApp.
- Panel operativo con filtros por dia, semana, mes, chofer y auto.
- Carga diaria/semanal con calculo automatico de descuentos, neto, semana, mes y año.
- Calculo de neto de flota/empresa: alquileres del periodo menos gastos generales de flota.
- CRUD de choferes con asignacion de auto.
- CRUD de autos sin asignacion directa a chofer.
- Reportes por periodo, chofer y auto.
- Restriccion server-side para que el chofer solo vea y cargue sus propios datos.

## Funcionalidades futuras preparadas

- Exportar a Excel.
- Descargar PDF.
- Enviar resumen por WhatsApp.
- Adjuntar comprobantes.
- Control de mantenimiento del vehiculo.
- Alertas de seguro, VTV/RTO y licencia.
- Control de pagos al dueño del auto.
- Control de comisiones.

Tablas futuras sugeridas:

- `entry_attachments` para comprobantes.
- `vehicle_maintenance` para servicios y reparaciones.
- `vehicle_alerts` para vencimientos.
- `owner_payments` para pagos al dueño.
- `commissions` para reglas de comision.

## Instalacion

1. Crea una base MySQL/MariaDB.
2. Importa `database/schema.sql`.
3. Copia `config/database.example.php` como `config/database.php`.
4. Completa host, base, usuario y password.
5. Apunta el dominio o subdominio a `public/`.

Si subis todo a `public_html`, el `index.php` raiz redirige a `public/index.php` y los `.htaccess` protegen carpetas internas.

## Estructura del proyecto

```text
app/
  Database.php
  bootstrap.php
  helpers.php
  views/
config/
  database.example.php
database/
  schema.sql
public/
  assets/
  cars.php
  daily_entries.php
  daily_entry_form.php
  dashboard.php
  drivers.php
  login.php
  reports.php
  users.php
```
