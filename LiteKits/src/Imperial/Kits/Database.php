<?php

declare(strict_types=1);

namespace Imperial\Kits;

use PDO;
use RuntimeException;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Database — PDO-обёртка MySQL (singleton на плагин)
 * ───────────────────────────────────────────────────────────────
 *  • Только prepared statements (защита от инъекций)
 *  • Своя таблица lc_kits_cooldowns — таблицы LiteRegister
 *    (lc_users / lc_meta) и LiteRanks (lc_player_ranks) НЕ трогаем
 *  • Кулдаун — 5 часов на КАЖДЫЙ кит отдельно (пара user+kit)
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
     * @throws \PDOException при недоступности MySQL
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
        return self::$instance ?? throw new RuntimeException('Database не инициализирован');
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
     * Автосоздание таблицы кулдаунов при первом запуске.
     */
    public function createTables(): void
    {
        $t = $this->prefix('kits_cooldowns');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$t}` (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            username  VARCHAR(16) NOT NULL,
            kit_id    INT NOT NULL,
            last_used DATETIME NOT NULL,
            UNIQUE KEY uniq_user_kit (username, kit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ═════════════════ ЗАПРОСЫ КУЛДАУНОВ ═════════════════

    /**
     * Когда игрок последний раз брал кит (UNIX timestamp) или null.
     */
    public function getLastUsed(string $username, int $kitId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT UNIX_TIMESTAMP(last_used) AS ts
               FROM `{$this->prefix('kits_cooldowns')}`
              WHERE username = :u AND kit_id = :k
              LIMIT 1"
        );
        $stmt->execute([':u' => $username, ':k' => $kitId]);
        $row = $stmt->fetch();

        if ($row === false || $row['ts'] === null) {
            return null;
        }
        return (int) $row['ts'];
    }

    /**
     * Все кулдауны игрока одним запросом: [kit_id => UNIX timestamp].
     *
     * @return array<int, int>
     */
    public function getAllLastUsed(string $username): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT kit_id, UNIX_TIMESTAMP(last_used) AS ts
               FROM `{$this->prefix('kits_cooldowns')}`
              WHERE username = :u"
        );
        $stmt->execute([':u' => $username]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['kit_id']] = (int) $row['ts'];
        }
        return $out;
    }

    /**
     * Отметить получение кита (обновить/вставить last_used = NOW()).
     */
    public function touch(string $username, int $kitId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->prefix('kits_cooldowns')}` (username, kit_id, last_used)
             VALUES (:u, :k, NOW())
             ON DUPLICATE KEY UPDATE last_used = NOW()"
        );
        $stmt->execute([':u' => $username, ':k' => $kitId]);
    }

    /**
     * Сбросить кулдаун одного кита (для админ-команд будущего).
     */
    public function resetCooldown(string $username, int $kitId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `{$this->prefix('kits_cooldowns')}` WHERE username = :u AND kit_id = :k"
        );
        $stmt->execute([':u' => $username, ':k' => $kitId]);
        return $stmt->rowCount() > 0;
    }
}
