# Avitar Explainer

A WordPress plugin that adds the `[avitar_explainer]` shortcode. Visitors paste the notes from an Avitar Associates tax card and get a plain-English explanation from Claude (Opus 5.5): a short summary, then a numbered list with each note in bold and its meaning below. Codes the model isn't sure about are marked "⚠ Uncertain". A copy icon copies the formatted explanation for pasting into Word or email.

`Avitar Explainer.md` is the text of the public page that hosts the shortcode, with instructions for finding a tax card on the Avitar website.

## Install

1. Copy the `avitar-explainer` folder into `wp-content/plugins/` (or zip the folder and upload it under **Plugins → Add New → Upload Plugin**), then activate **Avitar Explainer**.
2. Create an API key in the [Anthropic Console](https://console.anthropic.com) under **Settings → API Keys**, and make sure the account has credits.
3. Add the key to `wp-config.php`. It must go *above* the comment `/* That's all, stop editing! Happy publishing. */` near the end of the file, because WordPress loads right after that line:

   ```php
   define( 'ANTHROPIC_API_KEY', 'sk-ant-...' );

   /* That's all, stop editing! Happy publishing. */
   ```

   The key stays on the server; the browser never sees it.
4. Put `[avitar_explainer]` on any page (in the block editor, use a **Shortcode** block). Note the underscore: `[avitar-explainer]` with a hyphen won't work.

## Settings

All optional. Add them to `wp-config.php` in the same place as the key, for example:

```php
define( 'AVITAR_EXPLAINER_DAILY_LIMIT', 100 );
define( 'AVITAR_EXPLAINER_TRUSTED_PROXY', 'cloudflare' );
```

| Constant | Default | Meaning |
|---|---|---|
| `AVITAR_EXPLAINER_DAILY_LIMIT` | `500` | Explanations per day for the whole site |
| `AVITAR_EXPLAINER_IP_LIMIT` | `30` | Explanations per hour from one IP address (kept high because many neighbors share an IP through CGNAT) |
| `AVITAR_EXPLAINER_MAX_CHARS` | `4000` | Longest notes a visitor may paste |
| `AVITAR_EXPLAINER_MODEL` | `claude-opus-5-5` | Claude model |
| `AVITAR_EXPLAINER_TRUSTED_PROXY` | `false` | Only if the site is behind a proxy: `'cloudflare'` (uses `CF-Connecting-IP`) or `'proxy'` (uses the last `X-Forwarded-For` entry, which your proxy adds). Leave `false` otherwise, or visitors can fake their IP to dodge the limits |

Each explanation costs a few cents on Opus 5.5 (a rough estimate of 5–10¢; the Anthropic Console's usage page shows actual spending).

## Notes

- The **Explain** button fetches a fresh security token each time, so page-caching plugins don't break it.
- An explanation usually takes 30–40 seconds. The plugin asks PHP for up to 150 seconds, but some hosts and proxies cut requests off sooner (Cloudflare at 100 seconds, some shared hosts at 30–60), so test on your host.
- Visitors can paste just the notes, the notes with their label, or most of the Avitar page. The plugin keeps only the text between the last lowercase "help" and the "Avitar Associates of New England, Inc." footer, then drops anything up to the first "Notes:" label. Text without these markers is used as-is. The text box accepts up to 5× `AVITAR_EXPLAINER_MAX_CHARS` so a whole-page paste isn't cut off; the limit applies to the notes themselves.
- Pressing Enter in the text box works like clicking **Explain** (Shift+Enter adds a line break).
- Clicking **Explain** (or pressing Enter) scrolls the text box and its label to the top of the window, leaving room for the WordPress admin bar, so the explanation appears just below.
- If the AI service is overloaded, the failed attempt isn't counted against the limits.
- Errors with details (bad API key, API outages) are written to the PHP error log; visitors see a short friendly message. If something fails in the browser, the page says which step failed (security token, waiting for the explanation, or showing it) and logs the error to the browser console.
- The copy icon copies the explanation with its formatting. Copying uses the modern clipboard on HTTPS sites (and `localhost`) and falls back to the browser's own copy elsewhere.

## Development

Run the tests (needs Docker):

    docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tests/run.php

Run the browser-script tests (also Docker):

    docker run --rm -v "$PWD":/app -w /app/tests/js node:22-slim sh -c "npm install --no-audit --no-fund --silent && node run.js"

Local WordPress: put your key in `.wp-env.override.json` as `{ "config": { "ANTHROPIC_API_KEY": "sk-ant-..." } }` (gitignored), then `npx @wordpress/env start`. The site runs at http://localhost:8888 (admin: `admin` / `password`). If the shortcode ever shows as plain text after switching git branches, run `npx @wordpress/env stop && npx @wordpress/env start` so Docker picks up the plugin folder again.
