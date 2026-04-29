# durcoin
# 🎵 SoloHost

> **Single-file PHP media hosting with blockchain-gated subscriptions.**  
> Zero database. Zero dependencies. Zero build step. One PHP file.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://www.php.net/)
[![Single File](https://img.shields.io/badge/architecture-single--file-00c853.svg)](#)
[![No Database](https://img.shields.io/badge/database-none-red.svg)](#)
[![Waves](https://img.shields.io/badge/blockchain-Waves-0055ff.svg)](https://waves.tech/)
[![DURCOIN](https://img.shields.io/badge/token-DURCOIN-facc15.svg)](https://wx.network/)

**Drop one file in any folder. Instantly get a media site with Radio mode, Cinema mode, crypto subscriptions, and 10 languages. That's it.**

👉 **Live demo:** [djdurcoin.ru](https://djdurcoin.ru)

---

## ✨ What it does

- 📁 **Drop-in deployment** — single `index.php` file, no `composer install`, no `npm`, no database, no cron
- 🎵 **Smart media player** — Radio mode (continuous audio shuffle) and Cinema mode (video playlist)
- 🔀 **Shuffle & repeat** — server-side persistent order, client-side repeat
- 🔗 **Shareable playlists** — URLs that auto-start playback at any track
- 🔒 **Crypto-gated access** — Waves blockchain subscriptions via DURCOIN token
- 🦊 **Keeper Wallet integration** — one-click sign-in + on-chain payment
- 🌍 **10 languages** — English, Русский, 中文, Español, Français, Deutsch, 日本語, 한국어, Português, العربية (RTL supported)
- 🎨 **Dark + minimalist light theme** — monochrome grayscale for light mode
- 📱 **Mobile-first** — compact row layout on phones, grid on desktop
- ⚡ **Scales to 500K+ files** — JSON index cache (1h TTL), paginated API, infinite scroll
- 🎧 **HTML5 streaming** — byte-range requests, MediaSession API, lock-screen controls
- 🔐 **Ed25519 verification** — proper Curve25519→Ed25519 conversion for Waves signatures

---

## 🚀 Quick Start (60 seconds)

### 1. Download

```bash
curl -O https://raw.githubusercontent.com/YOUR_USER/solohost/main/index.php
```

### 2. Configure

Open `index.php`, edit the top section:

```php
$SITE_TITLE              = 'My Media Hub';
$SITE_DESCRIPTION        = 'My songs, paid via DURCOIN';
$WAVES_SYSTEM_ADDRESS    = 'YOUR_WAVES_ADDRESS_HERE';
$WAVES_MIN_AMOUNT        = 10.00;   // DURCOIN per subscription
$WAVES_SUBSCRIPTION_DAYS = 1;       // access duration
```

### 3. Drop your files

```
my-site/
├── index.php       ← the only file you need
├── song1.mp3
├── video.mp4
├── photo.jpg
└── document.pdf
```

### 4. Upload to any PHP host

```bash
# Any cheap shared hosting works. Or locally:
php -S localhost:8000
```

**Done.** Visit `https://your-domain.com/` — your media site is live. Earnings go directly to your Waves wallet.

---

## 📋 Requirements

- **PHP 7.4+** with:
  - `ext-sodium` — Ed25519 verification
  - `ext-gmp` — Curve25519 math
  - `ext-curl` *(optional — falls back to `file_get_contents`)*
- **Write permission** for `_index.cache.json`
- **Any web server** — Apache, nginx, Caddy, built-in PHP server

That's the entire dependency tree.

---

## 🎯 Philosophy: Token-Powered, Fork-Friendly

This project is a **gift to the DURCOIN + Waves ecosystem**. It exists to make it trivial for anyone to monetize their media collection using DURCOIN as the payment layer.

### What we want you to do ✅

- **Host your own media** — drop `index.php` in any folder with your MP3s/MP4s
- **Deploy on your domain** — one file, deploy anywhere
- **Customize look & feel** — change colors, titles, default language
- **Keep the DURCOIN payment layer** — all subscription $$$ go to **your** wallet, we don't touch it
- **Scale your content business** — no Stripe, no PayPal, no chargebacks

### What we *kindly ask* you not to do ⚠️

**Please don't fork this specifically to swap DURCOIN for another token.**

It's legal under MIT — we can't stop you — but it defeats the purpose. The obfuscated `_core_*()` functions in the code are a gentle signal: *"the author intends this for DURCOIN."*

If you genuinely need a different blockchain or token, please:
- ✍️ Rewrite your own implementation from scratch
- 📖 Use this codebase as a **design reference**, not a template
- 🤖 Use the AI prompt below to bootstrap your own version faster

### 🤖 Fork-With-AI Prompt

Need a different blockchain entirely? Feed this to Claude / GPT-4 along with `index.php`:

> *"Here is a reference single-file PHP media hosting implementation with Waves blockchain + DURCOIN token subscriptions. I want to build a similar system for **[YOUR_CHAIN]** with **[YOUR_TOKEN]**. Preserve these features: file indexing with JSON cache, Radio/Cinema playback modes, shareable playlist URLs, dark/light themes, i18n with 10 languages, pagination with infinite scroll, HTTP byte-range streaming. Replace the blockchain layer entirely — don't adapt the Waves code, rewrite it. Keep everything in a single PHP file with zero dependencies."*

This produces cleaner results than patching our code **and** keeps the DURCOIN ecosystem intact. Win-win.

### Why this model?

We believe **open code + token economics > paywalled code**.

- You get a **free, working tool** — no subscription to us, no backend lock-in
- We get **token utility growth** — every new site increases DURCOIN demand
- The **entire ecosystem** benefits from the network effects

This is not altruism. This is **aligned incentives**.

---

## 🔍 Full Transparency

The code contains **one intentionally obfuscated section** — three functions that hex-encode:
- The DURCOIN token asset ID on Waves blockchain
- Token decimals
- Waves public node URL

**What it is:** `pack('H*', 'hexstring')` encoding. One line to decode.  
**What it's NOT:** encryption, DRM, or license enforcement.

**Why obfuscated?** It's a **friction signal**, not a lock. It makes "use as-is with DURCOIN" the path of least resistance while letting anyone who reads the code understand what they're using.

**How to decode:**

```bash
php -r "echo pack('H*', '4631486F414C79...') . PHP_EOL;"
```

**How to replace with your own values** (if you're writing a fork for your token):

```php
function _core_asset_id()       { return 'YOUR_ASSET_ID'; }
function _core_asset_decimals() { return 8; }
function _core_node()           { return 'https://your-node.example.com'; }
```

That's it. MIT allows this. We just ask you consider writing your own implementation instead — see the Philosophy section above.

---

## 🎨 Screenshots

| Desktop Dark | Mobile Light |
|---|---|
| Media grid with subscription gating | Minimalist monochrome row layout |

*(Add your screenshots to `docs/screenshots/` and link them here.)*

---

## 💡 How It Works

### File handling

Every file in the same folder as `index.php` is served. Categories auto-detected by extension:

| Category | Extensions |
|---|---|
| 🎵 Audio | mp3, wav, ogg, flac, m4a, aac, opus, wma |
| 🎬 Video | mp4, webm, mov, mkv, avi, m4v, 3gp, flv |
| 🖼 Images | jpg, jpeg, png, gif, webp, svg, bmp, avif, ico, tiff |
| 📄 Documents | pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf, odt, md, csv |
| 📦 Archives | zip, rar, 7z, tar, gz, bz2, xz |

### Subscription model

| Action | Free? | Subscriber-only? |
|---|---|---|
| 🎵 Radio mode (audio continuous play) | ✅ | — |
| 🎬 Cinema mode (video playlist) | ✅ | — |
| 🔗 Share playlist URLs | ✅ | — |
| 🖼 Preview images on cards | — | 🔒 |
| 📄 Open/download individual files | — | 🔒 |

This creates organic **"try before you buy"** funnel. Visitors enjoy Radio/Cinema free, then pay to unlock direct file access.

### Index cache

On first request, the script scans the folder and caches file metadata into `_index.cache.json`. Subsequent requests read this JSON instead of `opendir()`-ing thousands of files. Cache rebuilds every 1 hour or on-demand via "Shuffle again" button.

This is what lets **one PHP file** handle hundreds of thousands of media files without slowing down.

---

## 🔧 Configuration Reference

| Setting | Default | Description |
|---|---|---|
| `$SITE_TITLE` | *(your title)* | Page title and header |
| `$SITE_DESCRIPTION` | *(your subtitle)* | Header subtitle |
| `$DEFAULT_THEME` | `'dark'` | `'dark'` or `'light'` |
| `$DEFAULT_LANG` | `'en'` | Default language code |
| `$WAVES_SYSTEM_ADDRESS` | — | **Your** Waves wallet for payments |
| `$WAVES_MIN_AMOUNT` | `10.00` | Minimum subscription payment (in DURCOIN) |
| `$WAVES_SUBSCRIPTION_DAYS` | `1` | Subscription duration |
| `$INDEX_CACHE_TTL` | `3600` | Cache TTL in seconds |
| `$PAGE_SIZE` | `60` | Cards per infinite-scroll page |
| `$MAX_SHARE_LIST_SIZE` | `300` | Max files in share URL |

---

## 🌐 API Endpoints

All AJAX via `?action=<name>`:

| Endpoint | Method | Purpose |
|---|---|---|
| `?action=list&tab=all&search=&page=1` | GET | Paginated file list |
| `?action=playlist&cat=audio` | GET | Full playlist for Radio/Cinema |
| `?action=names&tab=audio` | GET | Filtered names for sharing |
| `?action=reshuffle` | GET | Re-randomize index |
| `?action=nonce` | GET | Get session auth nonce |
| `?action=keeperAuth` | POST | Verify Keeper signature |
| `?action=checkPayment` | GET | Poll blockchain for payment |
| `?stream=file.mp3` | GET | Audio/video stream (public) |
| `?open=file.jpg` | GET | Direct file (subscribers) |
| `?download=file.pdf` | GET | Force download (subscribers) |

---

## 🔗 Shareable URLs

Generate auto-playing playlist links:

```
https://your.site/#play=audio&list=song1.mp3|song2.mp3&from=song1.mp3
https://your.site/#play=video&search=tutorial
```

URL fragments (`#`) keep params client-side — **no server logs of what people share.**

---

## 🎯 Use Cases

- 🎸 **Musicians** — sell access to demos, unreleased tracks, stems
- 🎬 **Video creators** — monetize archive content without YouTube's cut
- 🎤 **Podcasters** — subscriber-only episodes with no Patreon fees
- 📸 **Photographers** — high-res image packs with instant access
- 📚 **Researchers** — paywalled PDF collections
- 🎨 **Anyone** with a folder of files wanting to sell access simply

---

## 🔐 Security

- ✅ All file access via `resolveFile()` — can't escape directory, can't access dotfiles
- ✅ Sessions use `session_regenerate_id(true)` on auth success
- ✅ Ed25519 signatures verified server-side via `sodium_crypto_sign_verify_detached`
- ✅ Waves addresses validated by regex + on-chain public-key lookup
- ✅ Minimal external resources (only QR code generator)

**Not included** (by design — single-file constraint):
- ❌ Rate limiting → use nginx `limit_req` or Cloudflare
- ❌ HTTPS → use reverse proxy or Cloudflare Tunnel
- ❌ File upload → SFTP/rsync your files manually

---

## 📖 Why Waves Blockchain?

| Feature | Waves | Traditional (Stripe/PayPal) |
|---|---|---|
| Transaction fee | ~$0.01 | 2.9% + $0.30 |
| Chargeback risk | None | High |
| Geographic restrictions | None | Many countries excluded |
| Settlement time | ~1 minute | 2-7 days |
| Micropayments | ✅ Viable | ❌ Fees eat profits |
| Custom tokens | ✅ Any asset | ❌ USD only |

---

## 🤝 Contributing

This is a **deliberately minimal** project. The whole philosophy is "one file, zero dependencies, zero tooling."

**Welcome PRs:**
- ✅ Features that don't add dependencies
- ✅ i18n translations / language improvements
- ✅ Security fixes
- ✅ Performance optimizations
- ✅ Bug fixes

**Not welcome:**
- ❌ Framework refactors (Laravel / Symfony / etc.)
- ❌ Build steps (webpack / TypeScript / SASS)
- ❌ Database integrations (SQLite / MySQL / PostgreSQL)
- ❌ Forks that strip DURCOIN — please rewrite from scratch instead (see Philosophy)

---

## 📜 License

**MIT License** — see [LICENSE](LICENSE) file.

The license imposes no restrictions on forking with different tokens. The request in "Philosophy" section is a **social norm**, not a legal requirement. We trust the community to respect the ecosystem that gave them the tool.

---

## 🙏 Built On

- [Waves Blockchain](https://waves.tech/) — payment settlement layer
- [Waves Keeper](https://keeper-wallet.app/) — browser wallet extension  
- Native browser APIs — MediaSession, IntersectionObserver, Clipboard
- QR codes by [goqr.me](https://goqr.me/api/)

---

## 💬 Support

- ⭐ **Star this repo** — helps visibility
- 🐦 **Share on social** — tag `#DURCOIN` and `#Waves`
- 💰 **Hold some DURCOIN** — be part of the ecosystem
- 🏠 **Deploy a site** — use it, send me the URL, I'll feature it

**Author's reference deployment:** [djdurcoin.ru](https://djdurcoin.ru)  
**Author's wallet:** `3P95dfoJHC6dP6GaeCYGEMRYg7o4UAXE1w6`

---

*Built because I got tired of Plex, Jellyfin, and "just use S3+CloudFront." Sometimes you just need one file that works.*
