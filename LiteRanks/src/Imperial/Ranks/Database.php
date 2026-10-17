<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use PDO;
use RuntimeException;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Database — PDO-обёртка MySQL (singleton на плагин)
 * ───────────────────────────────────────────────────────────────
 *  • Только prepared statements (защита от инъекций)
 *  • Своя таблица lc_player_ranks — таблицы LiteRegister
 *    (lc_users / lc_meta) НЕ трогаем
 *  • Ранги живут в config.yml, в БД — лишь привязка игрока
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
     * Автосоздание таблицы при первом запуске.
     */
    public function createTables(): void
    {
        $t = $this->prefix('player_ranks');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$t}` (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            username        VARCHAR(16) NOT NULL,
            rank_id         VARCHAR(32) NOT NULL DEFAULT 'strannik',
            display_rank_id VARCHAR(32) NULL,
            granted_by      VARCHAR(16) NULL,
            granted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at      DATETIME NULL,
            UNIQUE KEY uniq_player (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ═════════════════ ЗАПРОСЫ ═════════════════

    /**
     * Строка ранга игрока (или null — значит «по умолчанию»).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->prefix('player_ranks')}` WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Выдать/сменить ранг. Нет строки — создастся сама.
     */
    public function upsertRank(string $username, string $rankId, ?string $grantedBy, ?string $expiresAt): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->prefix('player_ranks')}`
                (username, rank_id, granted_by, granted_at, expires_at)
             VALUES (:u, :r, :b, NOW(), :x)
             ON DUPLICATE KEY UPDATE
                rank_id    = VALUES(rank_id),
                granted_by = VALUES(granted_by),
                granted_at = NOW(),
                expires_at = VALUES(expires_at)"
        );
        $stmt->execute([':u' => $username, ':r' => $rankId, ':b' => $grantedBy, ':x' => $expiresAt]);
    }

    /**
     * Выбранная печать (префикс). NULL = «как настоящий ранг».
     */
    public function upsertDisplay(string $username, ?string $displayId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->prefix('player_ranks')}` (username, display_rank_id)
             VALUES (:u, :d)
             ON DUPLICATE KEY UPDATE display_rank_id = VALUES(display_rank_id)"
        );
        $stmt->execute([':u' => $username, ':d' => $displayId]);
    }

    /**
     * Истёк временный ранг — молча возвращаем к рангу по умолчанию.
     */
    public function expireReset(string $username, string $defaultRankId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->prefix('player_ranks')}`
                SET rank_id = :r, display_rank_id = NULL, expires_at = NULL
              WHERE username = :u"
        );
        $stmt->execute([':r' => $defaultRankId, ':u' => $username]);
    }

    /**
     * Снять ранг совсем (удалить строку) → игрок снова «по умолчанию».
     */
    public function delete(string $username): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->prefix('player_ranks')}` WHERE username = :u");
        $stmt->execute([':u' => $username]);
        return $stmt->rowCount() > 0;
    }
}
