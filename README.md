# Ruta Clara

Sistema PHP/MySQL para administrar choferes que alquilan un remis para trabajar en plataformas: liquidaciones semanales, alquiler del auto, combustible, transferencias, gastos adicionales y usuarios con permisos.

## Funciones

- Landing publica breve.
- Login privado con roles `admin` y `viewer`.
- Panel mensual con resumen de ganancias, alquileres cobrados, pendientes, transferencias y gastos.
- ABM de choferes.
- ABM de liquidaciones semanales basado en la planilla Excel.
- ABM de gastos no considerados: seguro, arreglos, service, luz u otros.
- Usuarios administradores y usuarios de solo lectura.

## Requisitos

- PHP 8.1 o superior.
- MySQL/MariaDB.
- Extension PDO MySQL activa.
- Servidor Apache compatible con `.htaccess` si se sube completo a `public_html`.

## Instalacion en Hostinger

1. Crea una base de datos MySQL desde el panel de Hostinger.
2. En phpMyAdmin, importa `database/schema.sql`.
3. Edita `config/database.php` con host, nombre de base, usuario y password.
4. Sube el proyecto al hosting.
5. Si Hostinger te deja elegir carpeta publica, apunta el dominio a `public/`.
6. Si usas `public_html` directamente, sube todo el proyecto; `index.php` redirige a `public/index.php` y los `.htaccess` bloquean carpetas internas.

Usuario inicial:

- Usuario: `admin`
- Password: `Cambiar123!`

Al ingresar por primera vez, el sistema convierte automaticamente ese password inicial a un hash moderno de PHP. Cambialo desde `Usuarios` apenas entres.

## Publicar en GitHub

`config/database.php` esta en `.gitignore` para no subir credenciales. Sube `config/database.example.php` como referencia.

Comandos sugeridos:

```bash
git init
git add .
git commit -m "Crear sistema Ruta Clara"
git branch -M main
git remote add origin URL_DE_TU_REPO
git push -u origin main
```

## Mapeo desde la Excel

- `Chofer` -> Chofer de la liquidacion.
- `Inicio` / `Fin` -> Periodo semanal.
- `Kms` -> Kilometros.
- `Total de ganancias` -> Ganancia bruta.
- `Ganancias en efectivo` -> Efectivo cobrado.
- `Ganancia neta virtual` -> Se calcula como total menos efectivo.
- `Combustible` -> Costo de combustible.
- `Alquiler del auto` -> Monto de alquiler acordado.
- `No pago` / `Pago` -> Alquiler pendiente o cobrado.
- `Ganancia del chofer` -> Total menos combustible menos alquiler.
- `Ganancia que debo transferir` -> Neto virtual menos alquiler pagado.
- `Concepto de gastos no considerados` / `Valor` -> Gastos.
