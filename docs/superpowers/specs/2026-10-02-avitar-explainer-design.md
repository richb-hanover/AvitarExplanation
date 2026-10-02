# Avitar Explainer — Design

## Purpose

A WordPress plugin for a small-town site (~1,000 properties) that lets **public
visitors** paste Avitar Associates tax-card notes — terse assessor shorthand
separated by `*` — and get a plain-English, line-by-line explanation from Claude.
Expected volume: up to ~250 queries on day one, < 500 total over time.

## Constraints

- Single PHP file with inline JS/CSS; shortcode `[avitar_explainer]`.
- Anthropic API key in `wp-config.php` as `ANTHROPIC_API_KEY`; never sent to the browser.
- Server call via `wp_remote_post` from a `register_rest_route` endpoint.
- Protection: WP nonce + IP rate limiting in transients. Visitors are often behind
  the same CGNAT IP, so the per-IP limit is generous and a site-wide daily cap is
  the safety net. Cost is not a primary concern (~4–7¢/query on Opus 5.5).

## Configuration (overridable in wp-config.php)

| Constant | Default |
|---|---|
| `AVITAR_EXPLAINER_MODEL` | `claude-opus-5-5` |
| `AVITAR_EXPLAINER_IP_LIMIT` | 30 per hour per IP |
| `AVITAR_EXPLAINER_DAILY_LIMIT` | 500 per day site-wide |
| `AVITAR_EXPLAINER_MAX_CHARS` | 4000 |
| `AVITAR_EXPLAINER_TRUSTED_PROXY` | false (when true, use `CF-Connecting-IP` / first `X-Forwarded-For`) |

## Flow

1. Shortcode renders textarea, "Explain" button, results area, inline JS/CSS.
2. On load, JS fetches a fresh nonce from `GET /wp-json/avitar-explainer/v1/nonce`
   (no-cache headers) so cached pages never carry stale nonces. REST requests
   without `X-WP-Nonce` run as user 0, so the nonce is consistent for logged-in
   testers too.
3. On submit, JS `POST`s `{notes}` to `/wp-json/avitar-explainer/v1/explain` with
   header `X-Avitar-Nonce`.
4. `permission_callback` verifies the nonce (action `avitar_explainer`). Handler
   validates input (non-empty, ≤ max chars), checks per-IP hourly limit, then
   daily cap, increments both, calls the API.
5. Response `{explanation}` is rendered as escaped text (`textContent`,
   `white-space: pre-wrap`). Button shows "Working…" during the 10–30 s call.

## API call

- `POST https://api.anthropic.com/v1/messages`, headers `x-api-key`,
  `anthropic-version: 2023-06-01`, `anthropic-beta: server-side-fallback-2026-07-01`.
- Body: `model`, `max_tokens: 16000`, `output_config: {effort: "medium"}`,
  `fallbacks: "default"` (server-side refusal fallback), `system`, one user message.
- Timeout 120 s. Check `stop_reason` (`refusal`, `max_tokens`); concatenate `text`
  blocks only (ignore `thinking`).

## System prompt

Expert reader of New Hampshire municipal assessing records (Avitar Associates
cards). Starter glossary (CU, LLA, WFFF, REC LOT, UNB, SF, AC, TOPO, ADJ, W/, HSE,
INC, RMVBL, …). Output: numbered item per `*` segment — original text, then
plain-English meaning; uncertain readings marked `⚠ Uncertain:` with a reason;
short overall summary at end. Plain text, no Markdown.

## Errors

Visitor sees friendly messages (rate limited → 429, too long / empty → 400,
service problem → 502/500). Details (missing key, API status/body) go to
`error_log` only.

## Testing

- `php -l` (via Docker `php` image).
- Stubbed-WP PHP test script: rate limiter, request body build, response parsing.
- End-to-end with `wp-env` and the user's sample card.

## Test data

```
VIEW POT GOOD TOPO* INC FORMER LOT 2 PONDSIDE*8712 SF OUT OF CU POND-SIDE & OB PONDSIDE -LAND OUT OF CU NOT "HSE SITE"=REC LOT ADJ W/100 WFFF*RMVBL DOCK/ WET AROUND OB*1/2006; LLA 1.16 AC TO 401-39*SEPTIC EASEMENT FOR L141 HERE= SAME OWNER*9/14 S INC LOT141=1G MULTI PARCEL*CU5;2/2/1*RD THRU LOT WF SIDE=UNB=RECLOT COND FACTOR
```
