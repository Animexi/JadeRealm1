# 🏯 LiteRegister — Небесная Империя

**Регистрация и авторизация для Minecraft Bedrock (LiteCore v1.0.9 / API 3.0.0 / PHP 8.0)**

---

## 📁 Структура

```
LiteRegister/
├── plugin.yml              ← манифест плагина
├── config.yml              ← конфиг (хост БД, настройки, сообщения)
├── resources/
│   └── config.yml          ← конфиг по умолчанию (авто-экспорт)
├── src/
│   └── Imperial/
│       └── Register/
│           ├── Main.php                ← точка входа, инициализация
│           ├── Database.php            ← PDO-обёртка MySQL (синглтон)
│           ├── AuthManager.php         ← бизнес-логика: сессии, валидация, логирование
│           ├── FormManager.php         ← все UI-формы (регистрация, вход, смена пароля)
│           ├── Theme.php               ← ЕДИНЫЙ стиль: цвета, звуки, рамки
│           ├── PlayerListener.php      ← события игрока: блокировки до авторизации
│           └── Command/
│               ├── ChangePasswordCommand.php
│               ├── UnregisterCommand.php
│               └── ReloadCommand.php
└── README.md
```

---

## 🚀 Установка

1. **Собери .phar** (если используется phar):
   ```bash
   # Через poggit/phar.io — или вручную:
   pharData = new PharData('LiteRegister.phar');
   pharData->buildFromDirectory(__DIR__ . '/src');
   pharData->buildFromDirectory(__DIR__, '/plugin.yml|/config.yml|/resources/');
   ```

   **Или проще** — для LiteCore можно положить `src/` прямо в папку плагина без phar:
   - Скопируй папку `LiteRegister/` целиком в `plugins/LiteRegister/`
   - LiteCore загружает директорию как плагин (содержит plugin.yml)

2. **Проверь БД** (MySQL):
   ```sql
   -- Таблицы создаются АВТОМАТИЧЕСКИ, но базу нужно создать вручную:
   CREATE DATABASE IF NOT EXISTS `gs148051`
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   
   -- Дай права пользователю:
   GRANT ALL PRIVILEGES ON `gs148051`.* TO 'gs148051'@'localhost' IDENTIFIED BY 'mrK7CirK3B4q';
   FLUSH PRIVILEGES;
   ```

3. **Перезагрузи сервер** или `/pl reload LiteRegister`

4. **Проверь логи** — в консоли должно быть:
   ```
   [LiteRegister] ✦ Небесная Империя: пробуждение LiteRegister…
   [LiteRegister] ✓ База данных готова (префикс: lc_)
   [LiteRegister] ✓ LiteRegister v1.0.0 активирован
   ```

---

## ⚙️ Настройка

Редактируй `plugins/LiteRegister/config.yml`:

| Параметр | Описание |
|----------|----------|
| `database.host` | Хост MySQL (по умолчанию `localhost`) |
| `database.port` | Порт (по умолчанию `3306`) |
| `database.user` | Логин БД |
| `database.password` | Пароль БД |
| `database.name` | Имя БД |
| `settings.min-password-length` | Мин. длина пароля (6) |
| `settings.max-login-attempts` | Макс. попыток входа (3) |
| `settings.block-seconds` | Длительность блокировки (60 сек) |
| `settings.form-delay-ticks` | Задержка формы после входа (20 тиков = 1 сек) |
| `settings.allowed-commands` | Команды, разрешённые до авторизации |

Перезагрузка конфига: `/lcreload`

---

## 🎮 Команды и права

| Команда | Описание | Право |
|---------|----------|-------|
| `/register` | Форма регистрации (автоматическая) | — |
| `/login` | Форма входа (автоматическая) | — |
| `/changepassword [old] [new]` | Смена пароля | `lc.register.changepassword` |
| `/unregister <nick>` | Удаление аккаунта | `lc.register.admin` |
| `/lcreload` | Перезагрузка конфига | `lc.register.admin` |

По умолчанию `/changepassword` открывает красивую форму, `/changepassword old new` — мгновенную смену.

---

## 🎨 Тематика «Небесная Империя»

Все цвета, звуки, рамки и префиксы хранятся в **`Theme.php`** — единый источник стиля для ВСЕХ будущих плагинов.

### Палитра
| Цвет | Код | Значение |
|------|-----|----------|
| Золото | `§6` / `§e` | Заголовки, акценты |
| Нефрит | `§a` / `§2` | Успех |
| Небо | `§b` / `§3` | Информация |
| Пурпур | `§d` / `§5` | Важное |
| Тревога | `§c` / `§4` | Ошибки |
| Текст | `§f` | Основной |
| Приглушённый | `§7` | Подсказки |
| Рамка | `§8` | Разделители |

### Звуки
| Событие | Звук | Pitch |
|---------|------|-------|
| Открытие формы | `random.orb` | 1.2 |
| Регистрация | `random.levelup` | 1.0 |
| Вход | `random.orb` | 1.5 |
| Неверный пароль | `mob.villager.no` | 0.8 |
| Пустое поле | `note.bass` | 0.5 |

---

## 📊 База данных

Автоматически создаваемые таблицы (префикс `lc_`):

**`lc_users`** — данные игроков:
- `id`, `username`, `password_hash`, `ip`, `reg_date`, `last_login`, `karma`, `rank`

**`lc_meta`** — служебная таблица (версия схемы, настройки)

---

## 📝 Логи

Журнал событий пишется в:
```
plugins/LiteRegister/logs/YYYY-MM-DD.log
```
Пример:
```
[14:32:01] РЕГИСТРАЦИЯ: DragonLord (192.168.1.100)
[14:35:22] ВХОД: DragonLord (192.168.1.100)
[15:01:00] СМЕНА ПАРОЛЯ: DragonLord
```

---

## 🔮 Дальнейшее развитие

`Theme.php` — ядро стиля для всех будущих плагинов Империи. Следующие плагины могут:
- Подключать `Imperial\Register\Theme` и использовать константы
- Расширять `AuthManager` для кастомных проверок
- Использовать `Database` как общий слой БД

---

## ⚠️ Совместимость

- **Ядро:** LiteCore v1.0.9
- **API:** PocketMine-MP 3.0.0 (не 4.x!)
- **PHP:** 8.0 (строго! НЕ 8.1+)
- **БД:** MySQL 5.7+ / MariaDB 10.3+
