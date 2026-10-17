<?php

declare(strict_types=1);

namespace Imperial\Register;

use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  AuthManager — бизнес-логика регистрации и входа
 * ───────────────────────────────────────────────────────────────
 *  • Кто авторизован (сессии)
 *  • Счётчик попыток входа + блокировка на N секунд (брут-защита)
 *  • Валидация полей, запись в БД, лог в файл
 *  • Все тексты — из config.yml, всё оформление — из Theme
 * ═══════════════════════════════════════════════════════════════
 */
final class AuthManager
{
    public function __construct(private Main $plugin)
    {
    }

    /** @var array<string, true> logged-in lowercase nick => true */
    private array $authenticated = [];

    /** @var array<string, int> lowercase nick => неверных попыток */
    private array $attempts = [];

    /** @var array<string, int> lowercase nick => timestamp до, сек */
    private array $blockedUntil = [];

    /** @var array<string, bool> lowercase nick => есть ли аккаунт в БД (кэш на сессию) */
    private array $registeredCache = [];

    // ═════════════════ СОСТОЯНИЕ СЕССИЙ ═════════════════

    /**
     * Есть ли аккаунт в БД? Ответ кэшируется на сессию —
     * чат/команды до входа не должны долбить MySQL каждым сообщением.
     */
    public function isRegistered(string $nick): bool
    {
        $key = strtolower($nick);
        if (!isset($this->registeredCache[$key])) {
            $this->registeredCache[$key] = $this->plugin->db()->userExists($nick);
        }
        return $this->registeredCache[$key];
    }

    /** Актуализировать кэш после регистрации/удаления. */
    public function markRegistered(string $nick, bool $state): void
    {
        $this->registeredCache[strtolower($nick)] = $state;
    }

    public function isAuthenticated(Player $player): bool
    {
        return isset($this->authenticated[strtolower($player->getName())]);
    }

    public function setAuthenticated(string $nick): void
    {
        $this->authenticated[strtolower($nick)] = true;
        unset($this->attempts[strtolower($nick)]);
    }

    public function forget(string $nick): void
    {
        $key = strtolower($nick);
        unset($this->authenticated[$key], $this->attempts[$key], $this->registeredCache[$key]);
        // blockedUntil СОХРАНЯЕМ — блокировка переживает реконнект
    }

    /** Аккаунт заблокирован за брутфорс? Возвращает остаток в секундах. */
    public function blockedSecondsLeft(string $nick): int
    {
        $until = $this->blockedUntil[strtolower($nick)] ?? 0;
        return max(0, $until - time());
    }

    public function registerBlock(string $nick): void
    {
        $this->blockedUntil[strtolower($nick)] = time() + $this->blockSeconds();
    }

    /**
     * Автовход по IP: если в БД у аккаунта сохранён тот же IP,
     * с которого зашёл игрок — пускаем без пароля (опция конфига).
     *
     * @throws Throwable при сбое БД (вызывающий обязан перехватить)
     */
    public function tryAutoLogin(Player $player): bool
    {
        if (!$this->plugin->getConfig()->getNested('settings.auto-login-by-ip', true)) {
            return false;
        }

        $nick = $player->getName();
        $ip   = $player->getAddress();
        $row  = $this->plugin->db()->findUser($nick);
        $this->markRegistered($nick, $row !== null);

        if ($row === null || !hash_equals((string) $row['ip'], $ip)) {
            return false;
        }

        $this->plugin->db()->touchLogin($nick, $ip);
        $this->setAuthenticated($nick);
        $this->log("АВТОВХОД ПО IP: {$nick} ({$ip})");
        return true;
    }

    // ═════════════════ ВАЛИДАЦИЯ ═════════════════

    /**
     * Валидация данных регистрации.
     * Возвращает [код_ошибки, доп.параметры] либо null, если всё в порядке.
     *
     * @return array{0: string, 1: array<string, string>}|null
     */
    public function validateRegistration(string $pass, string $pass2): ?array
    {
        $min = $this->minPasswordLength();

        return match (true) {
            $pass === '' || $pass2 === '' => ['err-empty', []],
            strlen($pass) < $min          => ['err-short', ['{MIN}' => (string) $min]],
            $pass !== $pass2              => ['err-mismatch', []],
            default                       => null,
        };
    }

    // ═════════════════ ДЕЙСТВИЯ ═════════════════

    /** Длительность блокировки после лимита попыток, сек */
    private function blockSeconds(): int
    {
        return (int) $this->plugin->getConfig()->getNested('settings.block-seconds', 60);
    }

    /** Минимальная длина пароля из конфига */
    public function minPasswordLength(): int
    {
        return (int) $this->plugin->getConfig()->getNested('settings.min-password-length', 6);
    }

    /** Максимум попыток входа из конфига */
    private function maxAttempts(): int
    {
        return (int) $this->plugin->getConfig()->getNested('settings.max-login-attempts', 3);
    }

    /**
     * Регистрация нового аккаунта. true = успех, false = ник уже занят.
     * @throws Throwable при сбое БД (вызывающий обязан обработать)
     */
    public function register(Player $player, string $pass): bool
    {
        $nick = $player->getName();
        $ip   = $player->getAddress();

        try {
            if ($this->plugin->db()->userExists($nick)) {
                return false; // ник мог появиться между показом формы и отправкой
            }
            $this->plugin->db()->registerUser($nick, $pass, $ip);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error("Регистрация {$nick}: {$e->getMessage()}");
            throw $e;
        }

        $this->setAuthenticated($nick);
        $this->markRegistered($nick, true);
        $this->log("РЕГИСТРАЦИЯ: {$nick} ({$ip})");
        return true;
    }

    /**
     * Попытка входа. Возвращает true при успехе.
     * При неверном пароле увеличивает счётчик; после лимита — блокировка.
     */
    public function tryLogin(Player $player, string $pass): bool
    {
        $nick = $player->getName();
        $key  = strtolower($nick);

        try {
            $ok = $this->plugin->db()->verifyPassword($nick, $pass);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error("Логин {$nick}: {$e->getMessage()}");
            throw $e;
        }

        if ($ok) {
            $this->plugin->db()->touchLogin($nick, $player->getAddress());
            $this->setAuthenticated($nick);
            $this->log("ВХОД: {$nick} ({$player->getAddress()})");
            return true;
        }

        $max = $this->maxAttempts();
        $this->attempts[$key] = ($this->attempts[$key] ?? 0) + 1;
        if ($this->attempts[$key] >= $max) {
            $this->registerBlock($nick);
        }
        return false;
    }

    /** Сколько попыток входа ОСТАЛОСЬ. */
    public function attemptsLeft(string $nick): int
    {
        return max(0, $this->maxAttempts() - ($this->attempts[strtolower($nick)] ?? 0));
    }

    /**
     * Смена пароля: проверяет старый, ставит новый.
     */
    public function changePassword(Player $player, string $old, string $new): bool
    {
        $nick = $player->getName();
        try {
            if (!$this->plugin->db()->verifyPassword($nick, $old)) {
                return false;
            }
            $this->plugin->db()->changePassword($nick, $new);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error("Смена пароля {$nick}: {$e->getMessage()}");
            throw $e;
        }
        $this->log("СМЕНА ПАРОЛЯ: {$nick}");
        return true;
    }

    /**
     * Удаление аккаунта админом. Если игрок онлайн — выкидываем.
     */
    public function unregister(string $nick): bool
    {
        try {
            $deleted = $this->plugin->db()->deleteUser($nick);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error("Unregister {$nick}: {$e->getMessage()}");
            throw $e;
        }
        if ($deleted) {
            // Если удаляемый аккаунт онлайн — выгоняем с сервера
            $target = $this->plugin->getServer()->getPlayerExact($nick);
            $target?->kick(Theme::error($this->msg('kick-not-auth')), false);
            $this->forget($nick);
            $this->markRegistered($nick, false);
            $this->log("УДАЛЕНИЕ АККАУНТА: {$nick}");
        }
        return $deleted;
    }

    // ═════════════════ СООБЩЕНИЯ / ЛОГ ═════════════════

    /**
     * Сообщение из config с подстановкой {PLACEHOLDER}.
     */
    public function msg(string $key, array $replacements = []): string
    {
        // ⚠ PM3: Config::get() НЕ понимает точки — только getNested()
        $raw = (string) $this->plugin->getConfig()->getNested("messages.{$key}", $key);
        foreach ($replacements as $token => $value) {
            $raw = str_replace($token, $value, $raw);
        }
        return $raw;
    }

    /**
     * Отправить игроку строку успеха/ошибки + звук.
     * $ok = true → success + SND_ACTION_OK; false → error + SND_BAD_FORMAT.
     */
    public function tell(Player $player, string $message, bool $ok): void
    {
        $player->sendMessage($ok ? Theme::success($message) : Theme::error($message));
        Theme::sound($player, $ok ? Theme::SND_ACTION_OK : Theme::SND_BAD_FORMAT, $ok ? 1.8 : 0.7);
    }

    /**
     * Журнал событий: plugins/LiteRegister/logs/YYYY-MM-DD.log
     */
    public function log(string $line): void
    {
        try {
            $dir = $this->plugin->getDataFolder() . 'logs' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents(
                $dir . date('Y-m-d') . '.log',
                '[' . date('H:i:s') . '] ' . $line . PHP_EOL,
                FILE_APPEND
            );
        } catch (Throwable) {
            // Непишущий диск не должен ронять плагин
            $this->plugin->getLogger()->warning('Не удалось записать в лог-файл: ' . $line);
        }
    }
}
