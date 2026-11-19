# Ngunda Wireless — Build Status

Updated after Phase 4 and the Phase 5 token engine. See `PHASE1-ARCHITECTURE-PLAN.md` for the plan.

| Phase | State | Evidence |
|---|---|---|
| 1 Architecture | Done | `docs/PHASE1-ARCHITECTURE-PLAN.md` |
| 2 Ubuntu assessment | **Blocked: needs the server** | `scripts/phase2/assess-ubuntu.sh` written, not yet run |
| 3 Network infrastructure | **Not started: needs Phase 2 and your approval** | n/a |
| 4 Laravel app | Done (tested on SQLite) | 18 tests |
| 5 Token authentication | **Engine and admin UI done.** Captive-portal page and openNDS FAS hook not started (needs Phase 3) | 29 tests |
| 6–10 | Not started | n/a |

## Run the Laravel tests (Windows, from `laravel/`)

```
php vendor\phpunit\phpunit\phpunit
```
Currently 47 tests, 840 assertions, all passing on in-memory SQLite.
Not covered here: true parallel redemption (needs MySQL on the server). See "Known gaps".

## First-time setup (any OS)

```
cd laravel
cp .env.example .env && php artisan key:generate
# set DB_* for MySQL, NWS_TOKEN_PEPPER, NWS_ADMIN_EMAIL, NWS_ADMIN_PASSWORD in .env
php artisan migrate --seed
```
No default admin password exists. The seeder creates the Super Administrator only from `NWS_ADMIN_EMAIL` / `NWS_ADMIN_PASSWORD`.

## Key design points implemented

- All 20 required tables + `token_batches`, `permission_role`. Money is `decimal(12,2)`. FK `RESTRICT` on financial/audit data.
- Tokens: HMAC-SHA256 hashed (peppered), 32-symbol alphabet, `random_int`. Encrypted print copy is deleted on first use or after 30 days.
- Redemption: one DB transaction, `SELECT … FOR UPDATE` on the token, unique index on active session per device.
- Firewall grant happens inside the transaction. If the gateway fails, activation rolls back (token is not burned).
- Gateway default is `UnavailableGateway`, which **fails closed** until the Python service (Phase 6) is bound.
- Login is rate-limited and audited. Audit log is append-only. Secrets are redacted or encrypted.

## Known gaps / honest caveats

1. **Nothing here has touched a real network.** No hostapd, dnsmasq, nftables, openNDS, tc or Python service exists yet.
2. Concurrency is tested logically (sequential) on SQLite. A real parallel test on MySQL must run on the server (Phase 9).
3. Settings pages store network values only. Applying them (validate, back up, apply, roll back) belongs to Phase 6.
4. The openNDS FAS parameter format and security level must be confirmed against the installed openNDS version in Phase 3.
5. Network Administrator can also edit business/portal fields on the Settings page (minor; to split later).
6. Windows sandbox quirk: `artisan test` fails inside the sandbox because `vendor/autoload.php` shows as unreadable to `is_readable()`. Tests were run outside the sandbox with the approval flow.
