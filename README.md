# PromoWallet

Невеликий Laravel + Vue REST API для роботи з бонусними промокодами. Проєкт реалізує обидва тікети тестового завдання: нарахування бонусу, історію застосувань і безпечне скасування нарахування.

## Локальний запуск

Потрібні PHP 8.2+, Composer, Node.js 20+ і SQLite.

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Force database/database.sqlite
composer install
php artisan key:generate
php artisan migrate --seed
npm install
```

Запустіть два процеси в різних терміналах:

```powershell
php artisan serve
npm run dev
```

Відкрийте [http://localhost:8000](http://localhost:8000). Демо-користувач:

```text
Email:    demo@example.com
Password: password
```

Seed-коди: `WELCOME10` (₴10.00), `SPORTS25` (₴25.00), `EXPIRED1` (прострочений).

## API

Після `POST /api/auth/login` токен передається в `Authorization: Bearer <token>`.

| Method | Endpoint | Призначення |
| --- | --- | --- |
| POST | `/api/auth/login` | Отримати Sanctum-токен |
| GET | `/api/me` | Поточний користувач і баланс |
| POST | `/api/promo/claim` | Застосувати промокод; body: `{ "code": "WELCOME10" }` |
| GET | `/api/promo/history?status=applied&page=1` | Історія з фільтром і пагінацією |
| PATCH | `/api/promo/{claimId}/revoke` | Скасувати власне успішне нарахування |

Для помилок доменної логіки API повертає HTTP-код, `message` і стабільний `error.code`, наприклад `promo_not_found`, `promo_expired`, `promo_already_used`, `claim_not_reversible` або `insufficient_balance`. Помилки формату коду повертають 422 з Laravel validation errors.

## Ключові рішення безпеки

- Гравець береться з Sanctum-токена; `user_id` не приймається з тіла запиту.
- Баланс і сума зберігаються в мінорних одиницях (`BIGINT`), тому арифметика не залежить від float.
- Claim/revoke виконуються в транзакції. Рядок користувача блокується `lockForUpdate()`, тому паралельні зміни балансу для одного гравця серіалізуються.
- Повторне використання перевіряється за зв’язкою гравець + промокод. Скасований claim також вважається вже використаним.
- Revoke додатково перевіряє власника, статус і достатність балансу; повторний виклик не списує кошти вдруге.
- Відхилені спроби записуються в історію із причиною, але не змінюють баланс.

## Перевірки

```powershell
php artisan test
# Якщо Windows-шлях містить кирилицю і artisan test не може передати шлях PHPUnit:
vendor/bin/phpunit --configuration phpunit.xml
npx vite build
```

Для цього проєкту перевірено `vendor/bin/phpunit --configuration phpunit.xml` — 7 тестів, 42 assertions — і production build Vite.

## Історія комітів

Інкременти розділені за етапами завдання:

1. `Initialize Laravel and Vue application` — базовий каркас.
2. `Implement promo claim and history` — Тікет 1: API, history, auth, Vue claim/history, seed і feature-тести.
3. `Limit promo API to claim and history` — вирівнювання маршрутизації з межею Тікета 1.
4. `Add safe promo revocation` — Тікет 2: revoke, UI підтвердження й регресійні тести.

Додаткові матеріали для здачі:

- [`docs/AI_PROMPTS.md`](docs/AI_PROMPTS.md) — лог промптів та ітерацій.
- [`docs/CODE_REVIEW.md`](docs/CODE_REVIEW.md) — письмове рев’ю фрагмента з Частини 2.
- [`docs/DEMO_SCRIPT.md`](docs/DEMO_SCRIPT.md) — короткий сценарій відео або скріншотів.
- [`docs/screenshots/`](docs/screenshots/) — послідовність скріншотів запуску й обох фіч.
