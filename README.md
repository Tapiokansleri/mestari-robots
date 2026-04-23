# Mestari Robots

Minimal WordPress plugin for editing `robots.txt` and `llms.txt`. Two textareas under **Settings → Reading**; the robots.txt output overrides anything Yoast / Rank Math / etc. produce.

## Features

- Textareas for both `robots.txt` and `llms.txt` under **Settings → Reading** — no separate settings page.
- Overrides any other `robots.txt` output (hooks `robots_txt` at `PHP_INT_MAX`).
- Serves `/llms.txt` as `text/markdown` so LLMs can discover it.
- Default `robots.txt` auto-detects the active SEO plugin's sitemap URL (Yoast, Rank Math, SEOPress, All in One SEO, Google XML Sitemaps, or WP core).
- Default `llms.txt` is generated from the site title and tagline.
- Respects **Settings → Reading → Discourage search engines from indexing this site** — serves `Disallow: /` for robots.txt and 404s `/llms.txt` when that's checked.
- Self-updates from GitHub releases/tags — updates show up in **Dashboard → Updates** like any WP.org plugin.

## Installation

### From a release zip

1. Download the latest `.zip` from the [Releases page](https://github.com/Tapiokansleri/mestari-robots/releases) (or the `Source code (zip)` from a tag).
2. In WordPress: **Plugins → Add New → Upload Plugin**, pick the zip, activate.

### From source

```sh
cd wp-content/plugins
git clone https://github.com/Tapiokansleri/mestari-robots.git
```

Then activate it in **Plugins**.

## Usage

Go to **Settings → Reading**. Edit the `robots.txt` textarea. Save. The content is served at `/robots.txt` immediately.

Leave the textarea empty to fall back to the auto-generated default.

## Auto-updates

Once installed, the plugin polls GitHub every 6 hours for a newer version. Updates appear under **Dashboard → Updates** and in the **Plugins** list with a standard "update now" link — no extra tooling required.

Version detection order:
1. Latest published GitHub Release.
2. Newest Git tag (fallback — makes `git tag && git push --tags` enough to ship an update).

## Releasing a new version (maintainers)

1. Bump the `Version:` header in `mestari-robots.php` and the `MESTARI_ROBOTS_VERSION` constant to match.
2. Commit.
3. Tag and push:
   ```sh
   git tag v1.1.0
   git push --tags
   ```

Optionally click **Create release from tag** on GitHub to attach a changelog and a dedicated zip asset — the updater will prefer it over the auto-generated zipball.

## License

GPL-2.0-or-later, same as WordPress.
