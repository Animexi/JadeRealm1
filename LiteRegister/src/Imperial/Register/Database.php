<?php

declare(strict_types=1);

namespace Imperial\Register;

use PDO;
use PDOException;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Database — PDO-обёртка MySQL (singleton на плагин)
 * ───────────────────────────────────────────────────────────────
 *  • Только prepared statements (защита от инъекций)
 *  • Таблицы создаются автоматически (CREATE TABLE IF NOT EXISTS)
 *  • Версия схемы хранится в {prefix}meta
 * ═══════════════════════════════════════════════════════════════
 */
final class Database
{
    /** Текущая версия схемы БД плагина */
    public const SCHEMA_VERSION = '1';

    private static ?self $instance = null;

    private PDO $pdo;
    private string $prefix;

    private function __construct(PDO $pdo, string $prefix)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    /**
     * Инициализация соединения (вызывается из Main::onEnable()).
     *
     * @throws PDOException при недоступности MySQL
     */
    public static function init(string $host, int $port, string $user, string $password, string $dbName, string $prefix): self
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        self::$instance = new self($pdo, $prefix);
        return self::$instance;
    }

    /** Готовый синглтон или исключение, если БД не поднята. */
    public static function get(): self
    {
        return self::$instance ?? throw new \RuntimeException('Database не инициализирован');
    }

    public static function close(): void
    {
        self::$instance = null;
    }

    public function prefix(string $table): string
    {
        return $this->prefix . $table;
    }

    /**
     * Автосоздание таблиц при первом запуске.
     */
    public function createTables(): void
    {
        $users = $this->prefix('users');
        $meta  = $this->prefix('meta');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$users}` (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(16) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            ip            VARCHAR(45) NOT NULL,
            reg_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login    DATETIME NULL,
            karma         INT NOT NULL DEFAULT 0,
            `rank`        VARCHAR(32) NOT NULL DEFAULT 'player'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$meta}` (
            `key`   VARCHAR(64) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Фиксируем версию схемы (INSERT ... ON DUPLICATE KEY UPDATE)
        $stmt = $this->pdo->prepare("INSERT INTO `{$meta}` (`key`, `value`) VALUES ('schema_version', :v)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([':v' => self::SCHEMA_VERSION]);
    }

    // ═════════════════ ЗАПРОСЫ ПОЛЬЗОВАТЕЛЯ ═════════════════

    /**
     * Найти пользователя по нику.
     *
     * @return array<string, mixed>|null
     */
    public function findUser(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->prefix('users')}` WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Есть ли такой ник в БД? */
    public function userExists(string $username): bool
    {
        return $this->findUser($username) !== null;
    }

    /**
     * Зарегистрировать пользователя.
     * password_hash() вызывается ВНУТРИ — хеш не утечёт в логи.
     */
    public function registerUser(string $username, string $plainPassword, string $ip): bool
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->prefix('users')}` (username, password_hash, ip, last_login)
             VALUES (:u, :p, :i, NOW())"
        );
        return $stmt->execute([
            ':u' => $username,
            ':p' => $hash,
            ':i' => $ip,
        ]);
    }

    /** Проверка пароля через password_verify(). */
    public function verifyPassword(string $username, string $plainPassword): bool
    {
        $row = $this->findUser($username);
        return $row !== null && password_verify($plainPassword, (string) $row['password_hash']);
    }

    /** Обновить last_login и последний IP. */
    public function touchLogin(string $username, string $ip): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->prefix('users')}` SET last_login = NOW(), ip = :i WHERE username = :u"
        );
        $stmt->execute([':i' => $ip, ':u' => $username]);
    }

    /** Смена пароля (возвращает false, если ник не найден). */
    public function changePassword(string $username, string $newPlainPassword): bool
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->prefix('users')}` SET password_hash = :p WHERE username = :u"
        );
        $stmt->execute([':p' => $hash, ':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    /** Удаление аккаунта (для /unregister). */
    public function deleteUser(string $username): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->prefix('users')}` WHERE username = :u");
        $stmt->execute([':u' => $username]);
        return $stmt->rowCount() > 0;
    }
}
