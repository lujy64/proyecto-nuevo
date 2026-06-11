<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configPath = __DIR__ . '/../config/database.php';

        if (!is_file($configPath)) {
            throw new RuntimeException('Falta config/database.php. Copia config/database.example.php y completa los datos de Hostinger.');
        }

        $config = require $configPath;
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');

        if ($database === '' || $username === '' || $database === 'TU_BASE_DE_DATOS' || $username === 'TU_USUARIO') {
            throw new RuntimeException('Completa config/database.php con el nombre de la base, usuario y password de Hostinger.');
        }

        $host = (string) ($config['host'] ?? 'localhost');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $password = (string) ($config['password'] ?? '');
        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo conectar a MySQL. Revisa los datos de config/database.php.');
        }

        return self::$connection;
    }
}
