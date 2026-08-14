# Code review: credit bonus

Наведений фрагмент я б не прийняв у production-гілку для betting/wallet домену.

## Backend

1. **[Blocker] Немає автентифікації та авторизації.** `GET /players/{player}/credit-bonus` дозволяє будь-кому вказати чужий `player` ID і змінити його баланс. Гравець має визначатися з auth token, а endpoint повинен перевіряти permission/policy; для клієнтського сценарію не слід приймати `player.id` з UI.

2. **[Blocker] Неправильний HTTP method.** GET не повинен змінювати стан і баланс. Потрібен POST/PATCH endpoint із коректним rate limit та audit trail.

3. **[Blocker] Немає транзакції та захисту від race condition.** Два паралельні запити можуть прочитати один старий balance, додати суму й перетерти результат один одного; повторний запит може двічі нарахувати бонус. Потрібні DB transaction, блокування рядка або атомарний SQL update, а для повторюваних команд — idempotency key/унікальний business key.

4. **[Blocker] Немає перевірки суми.** `amount` повністю контролюється клієнтом: можна передати від’ємне число, нуль, дуже велике значення або нечислове значення. Це дозволяє списувати кошти, псувати баланс або викликати помилки типів. Сума повинна приходити з trusted bonus/promo record, а не з request.

5. **[High] Небезпечна грошова арифметика.** Для wallet не можна покладатися на неявне приведення PHP values і формат зберігання `balance`. Потрібен integer minor units або DECIMAL із чіткими правилами округлення; API має повертати стабільний money формат.

6. **[High] Немає доменної моделі операції.** Зміна тільки `players.balance` не залишає claim/ledger/audit запису: неможливо довести, чому, коли й ким зараховано бонус, чи скасувати його рівно один раз. Потрібна таблиця bonus credit/ledger з idempotency key, source, amount, status, timestamps і зв’язком із оператором/промокодом.

7. **[High] Немає перевірки лімітів і балансу.** Не задано upper bound, currency, account status, ліміти бонусної кампанії чи правила negative balance. Перед мутацією треба перевірити доменні інваріанти і мати DB-level/transaction-level захист.

8. **[Medium] Немає валідації та зрозумілих помилок.** `$request->amount` використовується без FormRequest/validator; усі помилки будуть неструктурованими 500/SQL/type errors. Потрібні 422 для input errors, 401/403 для auth, 404 для resource і 409 для duplicate/conflict.

9. **[Medium] Немає rate limiting та audit/security controls.** Грошову операцію можна викликати без обмеження частоти. Потрібні rate limit, structured audit log, correlation/idempotency ID і, якщо це адмінська операція, окрема роль та підтвердження.

## Frontend

10. **[High] Клієнт підсилює IDOR і довіряє локальному player ID.** `this.player.id` формується на клієнті й передається в URL, хоча backend має брати суб’єкта з токена.

11. **[High] Клієнт відправляє мутацію як query params GET.** Це узгоджується з небезпечною backend-семантикою: URL може потрапити в browser history, proxy logs або analytics. Слід використовувати POST body і передавати auth token через axios interceptor.

12. **[Medium] Немає loading/error/rollback станів.** `this.player.balance` змінюється лише в success path; немає повідомлення користувачу про 401/403/409/422, блокування повторного натискання чи повторної синхронізації після конфлікту.

## Рекомендований напрямок

Замінив би endpoint на `POST /api/promo/claim`, де promo code/bonus amount береться з серверної таблиці, гравець — з Sanctum token, а операція пише immutable claim у транзакції з row lock та унікальним обмеженням. Для скасування — окремий `PATCH /api/promo/{claim}/revoke`, який перевіряє власника, статус і достатність коштів; обидві дії мають повертати стабільні JSON-помилки й бути покриті concurrency/idempotency тестами.
