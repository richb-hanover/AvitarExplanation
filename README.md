# Avitar Explainer

A WordPress plugin that adds the `[avitar_explainer]` shortcode. Visitors paste the notes from an Avitar Associates tax card and get a plain-English, line-by-line explanation from Claude (Opus 5.5). Codes the model isn't sure about are marked "⚠ Uncertain".

## Install

1. Copy the `avitar-explainer` folder into `wp-content/plugins/` and activate **Avitar Explainer**.
2. Add your Anthropic API key to `wp-config.php` (above "That's all, stop editing!"):

   ```php
   define( 'ANTHROPIC_API_KEY', 'sk-ant-...' );
   ```

   The key stays on the server; the browser never sees it.
3. Put `[avitar_explainer]` on any page.

## Settings

All optional, in `wp-config.php`:

| Constant | Default | Meaning |
|---|---|---|
| `AVITAR_EXPLAINER_DAILY_LIMIT` | `500` | Explanations per day for the whole site |
| `AVITAR_EXPLAINER_IP_LIMIT` | `30` | Explanations per hour from one IP address (kept high because many neighbors share an IP through CGNAT) |
| `AVITAR_EXPLAINER_MAX_CHARS` | `4000` | Longest notes a visitor may paste |
| `AVITAR_EXPLAINER_MODEL` | `claude-opus-5-5` | Claude model |
| `AVITAR_EXPLAINER_TRUSTED_PROXY` | `false` | Only if the site is behind a proxy: `'cloudflare'` (uses `CF-Connecting-IP`) or `'proxy'` (uses the last `X-Forwarded-For` entry, which your proxy adds). Leave `false` otherwise, or visitors can fake their IP to dodge the limits |

Each explanation costs roughly 4–7¢ on Opus 5.5.

## Notes

- The **Explain** button fetches a fresh security token each time, so page-caching plugins don't break it.
- An explanation usually takes 10–30 seconds. Hosts that cut off PHP requests after 30 seconds may need a longer limit.
- If the AI service is overloaded, the failed attempt isn't counted against the limits.
- Errors with details (bad API key, API outages) are written to the PHP error log; visitors see a short friendly message.

## Development

Run the tests (needs Docker):

    docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tests/run.php

Local WordPress: put your key in `.wp-env.override.json` as `{ "config": { "ANTHROPIC_API_KEY": "sk-ant-..." } }` (gitignored), then `npx @wordpress/env start`.
