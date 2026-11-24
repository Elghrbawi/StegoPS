<div align="center">

<img src="logo.svg" alt="StegoPS logo" width="90"/>

# StegoPS

**Steganographic Protection System**

Hide encrypted data inside images and audio, protected by layered, user-defined cipher chains, and share the result through expiring, password-protected links.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.9+-3776AB?logo=python&logoColor=white)
![Flask](https://img.shields.io/badge/Flask-API-000000?logo=flask&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-InnoDB-4479A1?logo=mysql&logoColor=white)
![Status](https://img.shields.io/badge/status-academic%20project-00e5c8)

</div>

---

## Overview

StegoPS combines **cryptography** and **steganography** in one web platform. A payload (text or any file) is first encrypted through a configurable **chain of ciphers**, then concealed inside a carrier image or audio file. Anyone looking at the output sees an ordinary picture or sound. Only someone with the right key, method and cipher chain can recover the original.

It is built as a two-tier system:

- **PHP + MySQL web app**: accounts, UI, file management, chains, sharing, admin panel.
- **Python Flask engine**: all encryption and steganography work, reachable only on `127.0.0.1` and protected by a bearer secret.

## Features

- **9 steganography methods** across image and audio carriers
- **Cipher chains**: stack up to 20 algorithms, each with its own key, executed in order on embed and in reverse on extract
- **Save and reuse chains**, with optional public sharing via token
- **Capacity check** before embedding, with automatic fallback to a larger-capacity method when a payload doesn't fit
- **Any payload**: plain text or any file (up to 50 MB). The original filename and type are restored on extraction
- **Integrity protection**: every payload carries a magic header and CRC-32, so a wrong key or method is detected cleanly
- **Secure sharing**: short links (`/s.php?t=TOKEN`) with expiry, download limits and optional password
- **WhatsApp-resistant mode**: the DCT method survives JPEG recompression (MozJPEG, quality 80, 4:2:0)
- **Guest mode** for quick use without an account
- **Admin panel**: users, algorithms (with backup and restore), chains, operations, media, shares and audit logs
- **Hardening basics**: bcrypt with per-user salt, PDO prepared statements, login lockout, `uploads/` locked down, themed error pages (401 to 504)

## Steganography methods

### Image

| Method | Slug | How it works |
|---|---|---|
| LSB Classic | `lsb` | 1 bit per RGB channel, highest capacity |
| XOR Pixel | `xor_pixel` | Key-stream XOR on the LSBs (keyed) |
| Alpha Channel | `alpha` | Data in the transparency plane (PNG only) |
| Image-in-Image | `img_in_img` | 4-bit nibble merge, embeds any file |
| DCT JPEG | `dct` | Mid-frequency DCT coefficients, survives recompression |
| Pixel Vault | `pixel_vault` | Payload stored as extra pixel rows with key-seeded shuffle and XOR |

### Audio

| Method | Slug | How it works |
|---|---|---|
| WAV LSB | `wav_lsb` | Least-significant bit across audio samples |
| Phase Coding | `phase` | Data encoded in the DFT phase of short segments |
| AAHE Adaptive | `aahe` | Psychoacoustic masking, keyed scatter, 2nd-LSB QIM |

## Encryption chain engine

Each step in a chain is a pluggable Python module exposing `encrypt(data, key)` and `decrypt(data, key)`.

| Algorithm | Slug | Category |
|---|---|---|
| Caesar | `caesar` | Classical |
| Vigenère | `vigenere` | Classical |
| AES-256 (CBC) | `aes_256` | Symmetric |
| ChaCha20 | `chacha20` | Symmetric |
| DES (CBC) | `des` | Symmetric (legacy/educational) |
| Triple DES | `des3` | Symmetric |
| NETRUNNER | `netrunner` | Custom, multi-layer byte cipher |

```
Embed:    payload ─▶ step 1 ─▶ step 2 ─▶ … ─▶ step N ─▶ stego method ─▶ carrier
Extract:  carrier ─▶ stego method ─▶ step N ─▶ … ─▶ step 2 ─▶ step 1 ─▶ payload
```

Adding an algorithm is three steps: drop a module in `Server/`, register it in `ALGO_MAP` (`Server/chain_engine.py`), and add a row to `encryption_algorithms`. The admin panel also has a built-in **Test** button per algorithm.

## Architecture

```
┌──────────────┐   HTTP    ┌────────────────────┐  Bearer   ┌──────────────────────┐
│   Browser    │ ────────▶ │  PHP web app       │ ────────▶ │  Flask engine :5001  │
│ (HTML/CSS/JS)│           │  auth · UI · share │   JSON    │  ciphers · stego     │
└──────────────┘           └─────────┬──────────┘           └──────────────────────┘
                                     │ PDO
                               ┌─────▼─────┐
                               │   MySQL   │
                               └───────────┘
```

## Project structure

```
StegoPS/
├── index.php, embed.php, extract.php   # core pages
├── chains.php, files.php, share.php    # chain builder, file manager, sharing
├── s.php                               # short share-link handler
├── login.php, register.php, profile.php, dashboard.php
├── admin/                              # admin panel
├── php/
│   ├── api/                            # AJAX endpoints (process, auth, download, revoke…)
│   ├── config/database.php             # DB + app settings
│   └── includes/                       # auth, chain manager, stego handler, header/footer
├── Server/                             # Python engine
│   ├── server.py                       # Flask API
│   ├── image.py, image_engine2.py      # image steganography
│   ├── audio.py                        # audio steganography
│   ├── chain_engine.py                 # chain executor + algorithm registry
│   └── aes256.py, chacha20.py, …       # cipher modules
├── database/stegops.sql                # schema + seed data
├── frontend/                           # CSS and JS
├── documentations/                     # thesis, schema, architecture, wireframe
├── uploads/                            # runtime storage (PHP execution disabled)
└── setup.php                           # one-time installer
```

## Getting started

### Requirements

- PHP 8+ with PDO MySQL, on Apache (XAMPP/WAMP/LAMP). The `uploads/.htaccess` assumes Apache.
- MySQL or MariaDB
- Python 3.9+
- `ffmpeg` on the server (needed for some audio formats)

### 1. Install the web app

```bash
git clone https://github.com/<your-username>/StegoPS.git
# place it in your web root, e.g. C:\xampp\htdocs\StegoPS  or  /var/www/html/StegoPS
```

Edit `php/config/database.php` if your setup differs from the defaults (`localhost`, `root`, empty password, `http://localhost/StegoPS`).

### 2. Initialize the database

Open `http://localhost/StegoPS/setup.php` once. It creates the upload folders, the database and all tables. Alternatively, import `database/stegops.sql` manually.

> **Delete `setup.php` afterwards.**

### 3. Start the Python engine

```bash
cd Server
python -m venv venv && source venv/bin/activate      # Windows: venv\Scripts\activate
pip install -r requirements.txt
export STEGOPS_SECRET="a-long-random-string"         # Windows: set STEGOPS_SECRET=...
python server.py                                     # listens on 127.0.0.1:5001
```

Check it: `curl http://127.0.0.1:5001/health`

### 4. Use it

Visit `http://localhost/StegoPS/`, register an account (or continue as guest), build a chain, and embed.

## Configuration

| Setting | Where | Default |
|---|---|---|
| DB host, name, user, password | `php/config/database.php` | `localhost` / `stegops_db` / `root` / empty |
| Engine URL | `PYTHON_API_URL` in `php/config/database.php` | `http://127.0.0.1:5001` |
| Engine secret | `PYTHON_API_SECRET` (PHP) and `STEGOPS_SECRET` (Python) | **must match, change it** |
| Max upload size | `MAX_FILE_SIZE` | 200 MB |
| Session lifetime | `SESSION_LIFETIME` | 3600 s |
| Login lockout | `MAX_LOGIN_ATTEMPTS` / `LOCKOUT_TIME` | 5 attempts / 900 s |

## Security notes for deployment

This project is built for learning and demonstration. Before exposing it to the internet:

- [ ] **Change the default admin account.** `database/stegops.sql` seeds an `admin` user. Reset its password immediately.
- [ ] **Replace the shared secret** (`stegops_internal_secret_2025`) on both the PHP and Python sides.
- [ ] **Use a dedicated MySQL user** instead of `root` with an empty password.
- [ ] **Delete `setup.php`** after installation.
- [ ] **Enable HTTPS** and set the session cookie `secure` flag to `true` in `php/includes/auth.php`.
- [ ] Keep the Flask engine bound to `127.0.0.1` (never publicly exposed).
- [ ] Don't rely on the classical ciphers (Caesar, Vigenère), DES or NETRUNNER for real secrecy. Use AES-256 or ChaCha20 as the strong layers.

## Documentation

Detailed design documents live in [`documentations/`](documentations):

- `StegoPS_Thesis.docx`: full project write-up
- `StegoPS_Stego_Engine.docx`: steganography algorithms
- `StegoPS_Encryption_Architecture.docx`: cipher chain design
- `StegoPS_Database_Schema.docx`: schema reference
- `StegoPS_Wireframe.html`: interactive UI wireframe (open in a browser)

## Roadmap

- Video carrier support (the schema already reserves it)
- Additional modern ciphers and authenticated modes
- Automated test suite for the stego engine
- Docker Compose setup for one-command deployment

## Responsible use

StegoPS is intended for privacy, research and education. Use it only on data and media you own or have permission to use, and follow the laws that apply to you.

## License

Add a `LICENSE` file (e.g. MIT) and state it here.
