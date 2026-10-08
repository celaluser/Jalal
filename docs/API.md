# REST API and webhooks

Available on plans with the **REST API & webhooks** feature. Create tokens and webhooks in the panel under *Settings → API & webhooks*.
All money values are integers in minor units (cents). All requests and responses are JSON over HTTPS.

## Authentication

```
Authorization: Bearer qrm_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

A token belongs to one restaurant and carries abilities: `menu:read`, `menu:write`, `orders:read`, `orders:write`.
Tokens are shown once, stored hashed, can expire, and can be revoked at any time.
Limits: 120 requests per minute per token (and 300 per minute per IP). Exceeding it returns `429`.
Errors: `401` bad/expired token, `403` missing ability or plan without API, `404` not found (also for other restaurants' data), `422` validation.

## Endpoints (`/api/v1`)

| Method | Path | Ability | Notes |
| --- | --- | --- | --- |
| GET | `/me` | any | Restaurant and token abilities |
| GET | `/menu` | `menu:read` | `categories[]`, `products[]` (names per language) |
| PATCH | `/products/{id}` | `menu:write` | Any of `price`, `is_available`, `is_active`, `stock_qty` |
| GET | `/orders` | `orders:read` | Filters `status`, `since` (date), `per_page` (1-100); paginated |
| GET | `/orders/{id}` | `orders:read` | Includes items and customer |
| POST | `/orders/{id}/status` | `orders:write` | Body `status` (`accepted`, `preparing`, `ready`, `completed`, `cancelled`), optional `reason`. Same rules as the panel; invalid moves return `422` with `code` |

Example:

```
curl -H "Authorization: Bearer $TOKEN" "https://example.com/api/v1/orders?status=new"
curl -X POST -H "Authorization: Bearer $TOKEN" -d status=accepted https://example.com/api/v1/orders/42/status
```

## Webhooks

Events: `order.created`, `order.status_changed` (adds `previous_status`), `order.paid`, plus `ping` for the Send test button.
Body:

```json
{ "id": "uuid", "event": "order.created", "created_at": "2026-03-04T10:00:00+00:00", "data": { "order": { "id": 42, "number": 1042, "status": "new", "total_cents": 2000, "items": [] } } }
```

Headers: `X-Webhook-Id`, `X-Webhook-Event`, `X-Webhook-Signature: t=<unix time>,v1=<hex>` where `v1 = HMAC_SHA256(secret, "<t>.<raw body>")`.
Verify the signature with the secret shown when you created the webhook, and reject messages whose `t` is older than five minutes.

```php
[$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']));
$ok = hash_equals(hash_hmac('sha256', $t.'.'.file_get_contents('php://input'), $secret), $v1) && abs(time() - (int) $t) < 300;
```

Delivery: respond with any `2xx` within 8 seconds. Otherwise it is retried (5 tries, growing pauses) and logged in the panel. After 15 failed deliveries in a row the webhook is switched off.
Only public HTTPS addresses (port 443 or 8443) are accepted; redirects are not followed. Needs the queue worker (cron `queue:work` on shared hosting).

## Zapier, Make, n8n

Use *Webhooks* as the trigger (paste a "catch hook" URL from the tool) and the API with a Bearer token for actions. There is no certified Zapier/Make app.

## Not available

Creating orders through the API, customers, reservations and payments endpoints, and reservation webhooks.
