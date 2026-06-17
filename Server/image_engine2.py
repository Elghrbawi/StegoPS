"""
╔══════════════════════════════════════════════════════════════════════════════╗
║              StegoPS — Image Steganography Engine 2                         ║
║                    stego/image_engine2.py                                   ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  METHOD: Height-Expansion Concealment with Key-Seeded Pixel Scrambling      ║
║  FORMATS: PNG (recommended), JPEG                                            ║
║  SECURITY: XOR cipher + Fisher-Yates shuffle (seeded by SHA-256 key hash)   ║
╚══════════════════════════════════════════════════════════════════════════════╝

HOW IT WORKS — TECHNICAL OVERVIEW
══════════════════════════════════

  EMBED PIPELINE:
  ┌─────────────┐    ┌──────────────────┐    ┌─────────────────┐
  │   Payload   │───▶│ Render as Image  │───▶│ XOR-Encrypt     │
  │ (encrypted) │    │ (noise pixels)   │    │ pixels w/ key   │
  └─────────────┘    └──────────────────┘    └────────┬────────┘
                                                       │
  ┌─────────────┐    ┌──────────────────┐    ┌────────▼────────┐
  │ Output File │◀───│ Patch height     │◀───│ Pixel-Shuffle   │
  │  (stego)    │    │ back to original │    │ (key-seeded)    │
  └─────────────┘    └──────────────────┘    └─────────────────┘

  EXTRACT PIPELINE:
  ┌─────────────┐    ┌──────────────────┐    ┌─────────────────┐
  │ Stego Image │───▶│ Read true height │───▶│ Extract hidden  │
  │             │    │ vs stored height │    │ pixel rows      │
  └─────────────┘    └──────────────────┘    └────────┬────────┘
                                                       │
  ┌─────────────┐    ┌──────────────────┐    ┌────────▼────────┐
  │  Recovered  │◀───│  Chain-decrypt   │◀───│ Pixel-Unshuffle │
  │   Payload   │    │  (via executor)  │    │ (key-seeded)    │
  └─────────────┘    └──────────────────┘    └─────────────────┘


HEIGHT MANIPULATION TECHNIQUE
══════════════════════════════
  PNG stores image height as a 4-byte big-endian integer at bytes 20–23
  of the IHDR chunk. JPEG stores height in the SOF0/SOF2 marker.

  This engine:
    1. Opens the carrier image normally via Pillow
    2. Renders the (already chain-encrypted) payload bytes as pixel rows
    3. XOR-encrypts those pixel rows with the stego key hash
    4. Applies Fisher-Yates shuffle to scramble all pixel positions
    5. Writes a sentinel row between original and hidden zone
    6. Concatenates: [original pixels] + [sentinel row] + [hidden pixels]
    7. Saves the combined image (true height = original + 1 + hidden)
    8. PATCHES the height bytes in the file back to original height
       → Most viewers read the header height and stop rendering there
       → The hidden zone is completely invisible to standard image viewers

  To extract: inflate height header → read actual pixel data →
              locate sentinel row → unshuffle → XOR-decrypt → recover bytes


PIXEL SCRAMBLING & ENCRYPTION
══════════════════════════════
  Key Processing:
    key_hash = SHA-256(stego_key.encode())     → 32-byte digest
    seed     = int(key_hash[:32], 16)          → 256-bit integer seed
    xor_key  = key_hash bytes (cycled)         → used for XOR encryption

  Scrambling:
    - All pixels of the hidden zone are flattened into a 1D byte array
    - Fisher-Yates shuffle applied using the seeded random generator
    - Without the correct key, pixels appear as random colored noise
    - The shuffle is perfectly reversible with the same key + seed

  Encryption:
    - After shuffling, each pixel channel byte is XORed with cycling
      bytes from the key_hash
    - Adds a second layer: even if pixel positions were guessed,
      the color values are still scrambled


HIDDEN ZONE LAYOUT
══════════════════
  ┌────────────────────────────────────────────┐
  │         Original carrier pixels            │  ← declared height (header)
  ├────────────────────────────────────────────┤
  │  Sentinel row: MAGIC(8) + hidden_h(4) + …  │  ← 1 row
  ├────────────────────────────────────────────┤
  │  Encrypted + shuffled payload pixel rows   │  ← hidden zone
  └────────────────────────────────────────────┘
       ↑ all of this is invisible to image viewers


SECURITY NOTES
══════════════
  ✔ Hidden zone is outside declared image dimensions
  ✔ Pixels appear as random noise without the correct stego key
  ✔ Two-layer protection: pixel shuffle + XOR encryption
  ✔ Key is never stored in the file
  ✔ Works on top of StegoPS chain encryption (payload already encrypted)
  ✘ File size is larger than original (unavoidable with this method)
  ✘ Forensic tools that read raw pixel data past declared height can detect
    that extra data exists (though they cannot read it without the key)
  → For maximum stealth: always use PNG (lossless)
    JPEG lossy compression may corrupt hidden pixel rows


USAGE IN STEGOPS
════════════════
  from stego.image_engine2 import embed_image2, extract_image2

  # Embed (payload is already chain-encrypted bytes)
  stego_bytes = embed_image2(carrier_bytes, encrypted_payload, stego_key)

  # Extract (returns raw bytes — pass to chain executor to decrypt)
  encrypted_payload = extract_image2(stego_bytes, stego_key)

STANDALONE USAGE
════════════════
  python stego/image_engine2.py encode photo.png "my secret text" mykey output.png
  python stego/image_engine2.py decode output.png mykey revealed.png
"""

import hashlib
import io
import math
import os
import random
import struct
import zlib
from io import BytesIO

from PIL import Image, ImageDraw, ImageFont


# ══════════════════════════════════════════════════════════════════════════════
#  CONSTANTS
# ══════════════════════════════════════════════════════════════════════════════

# Sentinel magic — marks the boundary row between carrier and hidden zone
SENTINEL_MAGIC = b"\xDE\xAD\xBE\xEF\xCA\xFE\xBA\xBE"

# Bytes per pixel row component stored in sentinel (magic + uint32 height)
SENTINEL_PAYLOAD_BYTES = len(SENTINEL_MAGIC) + 4   # 12 bytes


# ══════════════════════════════════════════════════════════════════════════════
#  KEY DERIVATION
# ══════════════════════════════════════════════════════════════════════════════

def _derive_key_material(stego_key: str) -> tuple:
    """
    Derive a numeric seed and XOR key bytes from the stego key string.

    Uses SHA-256 so any string (short or long) produces uniform 256-bit material.

    Returns:
        (seed: int, xor_bytes: bytes)
          seed      — integer used to seed Fisher-Yates shuffle RNG
          xor_bytes — 32 raw bytes cycled for XOR pixel encryption
    """
    digest    = hashlib.sha256(stego_key.encode("utf-8")).hexdigest()
    seed      = int(digest, 16)                 # full 256-bit integer seed
    xor_bytes = bytes.fromhex(digest)           # 32 bytes for XOR
    return seed, xor_bytes


# ══════════════════════════════════════════════════════════════════════════════
#  PIXEL SCRAMBLING — Fisher-Yates shuffle / unshuffle
# ══════════════════════════════════════════════════════════════════════════════

def _xor_pixels(pixel_array: list, xor_bytes: bytes) -> list:
    """XOR every byte in pixel_array with cycling xor_bytes."""
    kl = len(xor_bytes)
    return [pixel_array[i] ^ xor_bytes[i % kl] for i in range(len(pixel_array))]


def _shuffle_pixels(pixel_array: list, seed: int) -> list:
    """
    Fisher-Yates shuffle of pixel_array using seeded RNG.
    Deterministic — same seed always produces same shuffle order.
    """
    arr = pixel_array[:]
    rng = random.Random(seed % (2 ** 32))   # Random() takes 32-bit seed
    for i in range(len(arr) - 1, 0, -1):
        j = rng.randint(0, i)
        arr[i], arr[j] = arr[j], arr[i]
    return arr


def _unshuffle_pixels(pixel_array: list, seed: int) -> list:
    """
    Reverse the Fisher-Yates shuffle by replaying the swap sequence
    in reverse order.
    """
    arr = pixel_array[:]
    rng = random.Random(seed % (2 ** 32))
    swaps = []
    for i in range(len(arr) - 1, 0, -1):
        j = rng.randint(0, i)
        swaps.append((i, j))
    for i, j in reversed(swaps):
        arr[i], arr[j] = arr[j], arr[i]
    return arr


# ══════════════════════════════════════════════════════════════════════════════
#  HIDDEN IMAGE ENCRYPTION / DECRYPTION
# ══════════════════════════════════════════════════════════════════════════════

def _encrypt_hidden_image(img: Image.Image, seed: int, xor_bytes: bytes) -> Image.Image:
    """
    Encrypt a PIL image:
      Step 1 — Flatten all pixel channels to a 1D byte list
      Step 2 — XOR every byte with cycling xor_bytes
      Step 3 — Fisher-Yates shuffle the entire byte array

    Result looks like random colored noise without the correct key.
    """
    width, height = img.size
    pixels = list(img.tobytes())           # flat: R G B R G B … (width*height*3 bytes)
    pixels = _xor_pixels(pixels, xor_bytes)
    pixels = _shuffle_pixels(pixels, seed)
    return Image.frombytes("RGB", (width, height), bytes(pixels))


def _decrypt_hidden_image(img: Image.Image, seed: int, xor_bytes: bytes) -> Image.Image:
    """
    Decrypt a PIL image — exact reverse of _encrypt_hidden_image:
      Step 1 — Unshuffle pixel bytes
      Step 2 — XOR to undo encryption
    """
    width, height = img.size
    pixels = list(img.tobytes())
    pixels = _unshuffle_pixels(pixels, seed)
    pixels = _xor_pixels(pixels, xor_bytes)
    return Image.frombytes("RGB", (width, height), bytes(pixels))


# ══════════════════════════════════════════════════════════════════════════════
#  PAYLOAD → PIXEL ROWS CONVERSION
# ══════════════════════════════════════════════════════════════════════════════

def _payload_to_image(payload: bytes, width: int) -> Image.Image:
    """
    Pack raw payload bytes into a PIL image of the given width.
    Each pixel stores 3 bytes (R, G, B).
    The first 4 bytes of the pixel data store the payload length (uint32 big-endian)
    so extraction knows exactly how many bytes to recover.

    Hidden zone layout (pixel bytes):
      [0:4]   — uint32 big-endian payload length
      [4:4+N] — raw payload bytes
      [rest]  — zero padding to fill last pixel row
    """
    length_header = struct.pack(">I", len(payload))
    full_data     = length_header + payload

    # How many pixel rows do we need?
    bytes_per_row = width * 3
    n_rows        = math.ceil(len(full_data) / bytes_per_row)
    padded        = full_data + b"\x00" * (n_rows * bytes_per_row - len(full_data))

    return Image.frombytes("RGB", (width, n_rows), padded)


def _image_to_payload(img: Image.Image) -> bytes:
    """
    Recover raw payload bytes from pixel rows produced by _payload_to_image.
    Reads length header first, then slices exact payload bytes.
    """
    raw         = img.tobytes()
    payload_len = struct.unpack(">I", raw[:4])[0]
    return raw[4: 4 + payload_len]


# ══════════════════════════════════════════════════════════════════════════════
#  TEXT → IMAGE RENDERER  (for standalone / human-readable mode)
# ══════════════════════════════════════════════════════════════════════════════

def _render_text_image(text: str, width: int) -> Image.Image:
    """
    Render a text string into a black-background RGB image of given width.
    White monospace text, auto word-wrapped.
    Used by the standalone encode() function when the payload is human text.
    """
    font_size = max(14, width // 60)

    font = None
    for font_name in [
        "DejaVuSansMono.ttf", "Courier New.ttf",
        "cour.ttf", "LiberationMono-Regular.ttf",
    ]:
        try:
            font = ImageFont.truetype(font_name, font_size)
            break
        except (IOError, OSError):
            continue
    if font is None:
        font = ImageFont.load_default()

    padding  = 10
    dummy    = Image.new("RGB", (width, 100))
    draw     = ImageDraw.Draw(dummy)

    # Word-wrap
    wrapped_lines = []
    for raw_line in text.split("\n"):
        words = raw_line.split()
        if not words:
            wrapped_lines.append("")
            continue
        current = ""
        for word in words:
            test = (current + " " + word).strip()
            bbox = draw.textbbox((0, 0), test, font=font)
            if bbox[2] - bbox[0] <= width - 2 * padding:
                current = test
            else:
                if current:
                    wrapped_lines.append(current)
                current = word
        if current:
            wrapped_lines.append(current)

    line_height  = font_size + 4
    total_height = padding * 2 + len(wrapped_lines) * line_height

    img  = Image.new("RGB", (width, total_height), color=(0, 0, 0))
    draw = ImageDraw.Draw(img)
    y    = padding
    for line in wrapped_lines:
        draw.text((padding, y), line, fill=(255, 255, 255), font=font)
        y += line_height

    return img


# ══════════════════════════════════════════════════════════════════════════════
#  SENTINEL ROW
# ══════════════════════════════════════════════════════════════════════════════

def _encode_sentinel_row(hidden_height: int, width: int) -> Image.Image:
    """
    Build a 1-pixel-tall image that marks the boundary between the
    carrier and the hidden zone.

    Row byte layout:
      [0:8]   SENTINEL_MAGIC  — 8 bytes
      [8:12]  hidden_height   — uint32 big-endian (number of hidden pixel rows)
      [12:]   zero-filled

    This sentinel is NOT encrypted — it only tells the decoder where the
    hidden zone is. Without the key, the hidden zone is still unreadable.
    """
    row_bytes = bytearray(width * 3)
    marker    = SENTINEL_MAGIC + struct.pack(">I", hidden_height)
    for i, b in enumerate(marker):
        if i < len(row_bytes):
            row_bytes[i] = b
    return Image.frombytes("RGB", (width, 1), bytes(row_bytes))


def _decode_sentinel_row(img: Image.Image, row_y: int):
    """
    Read sentinel row at vertical position row_y.
    Returns hidden_height (int) if valid sentinel found, else None.
    """
    row_bytes = img.crop((0, row_y, img.width, row_y + 1)).tobytes()
    if bytes(row_bytes[:8]) == SENTINEL_MAGIC:
        return struct.unpack(">I", row_bytes[8:12])[0]
    return None


# ══════════════════════════════════════════════════════════════════════════════
#  PNG HEIGHT PATCHING
# ══════════════════════════════════════════════════════════════════════════════

def _patch_png_height(data: bytes, new_height: int) -> bytes:
    """
    Patch the IHDR height field in raw PNG bytes and recompute CRC.

    PNG structure:
      8-byte signature
      IHDR chunk: 4(length) + 4("IHDR") + 13(data) + 4(CRC)
        IHDR data: 4(width) + 4(height) + 1(bit depth) + 1(color type) + 3(other)
      Height is at offset 20–23 (file-level).
    """
    ihdr_data_start = 16           # after signature(8) + chunk_length(4) + "IHDR"(4)
    ihdr_data_end   = ihdr_data_start + 13

    ihdr_data = bytearray(data[ihdr_data_start:ihdr_data_end])
    struct.pack_into(">I", ihdr_data, 4, new_height)   # height at offset 4 within IHDR data

    crc       = zlib.crc32(b"IHDR" + bytes(ihdr_data)) & 0xFFFFFFFF
    crc_bytes = struct.pack(">I", crc)

    return (
        data[:ihdr_data_start]
        + bytes(ihdr_data)
        + crc_bytes
        + data[ihdr_data_end + 4:]     # skip original 4-byte CRC
    )


def _read_png_declared_height(data: bytes) -> int:
    """Read the height value declared in the PNG IHDR header."""
    return struct.unpack(">I", data[20:24])[0]


# ══════════════════════════════════════════════════════════════════════════════
#  JPEG HEIGHT PATCHING
# ══════════════════════════════════════════════════════════════════════════════

def _find_jpeg_sof(data: bytes):
    """
    Locate the SOF0 (0xFFC0) or SOF2 (0xFFC2) marker in JPEG data.
    Returns (marker_pos, height_pos).
    height_pos is the byte offset where the 2-byte height value starts.
    """
    i = 0
    while i < len(data) - 1:
        if data[i] != 0xFF:
            i += 1
            continue
        marker = data[i + 1]
        if marker in (0xC0, 0xC2):
            # SOF layout: FF Cx | 2-byte length | 1-byte precision | 2-byte height | 2-byte width
            return i, i + 5
        elif marker in (0xD8, 0xD9, 0x01) or (0xD0 <= marker <= 0xD7):
            i += 2
        else:
            if i + 3 < len(data):
                length = struct.unpack(">H", data[i + 2:i + 4])[0]
                i += 2 + length
            else:
                break
    return None, None


def _patch_jpeg_height(data: bytes, new_height: int) -> bytes:
    """Patch height field in the JPEG SOF marker."""
    _, height_pos = _find_jpeg_sof(data)
    if height_pos is None:
        raise ValueError("Could not locate SOF marker in JPEG data.")
    patched = bytearray(data)
    struct.pack_into(">H", patched, height_pos, new_height)
    return bytes(patched)


def _read_jpeg_declared_height(data: bytes) -> int:
    """Read declared height from the JPEG SOF marker."""
    _, height_pos = _find_jpeg_sof(data)
    if height_pos is None:
        raise ValueError("Could not locate SOF marker in JPEG data.")
    return struct.unpack(">H", data[height_pos:height_pos + 2])[0]


# ══════════════════════════════════════════════════════════════════════════════
#  PUBLIC API — StegoPS integration
# ══════════════════════════════════════════════════════════════════════════════

def embed_image2(carrier_bytes: bytes, payload: bytes, stego_key: str) -> bytes:
    """
    Embed arbitrary payload bytes into a carrier image using Height-Expansion.

    The payload should already be chain-encrypted by the StegoPS chain executor
    before being passed here. This function handles only the steganographic layer.

    Steps:
      1. Pack payload bytes into pixel rows (_payload_to_image)
      2. XOR-encrypt + Fisher-Yates shuffle pixel data (key-seeded)
      3. Append sentinel row + encrypted pixel rows below the carrier
      4. Save combined image as PNG
      5. Patch PNG height header back to original carrier height

    Args:
        carrier_bytes : Raw bytes of any PIL-readable image (PNG, JPG, BMP…)
        payload       : Bytes to hide (already encrypted by chain executor)
        stego_key     : String key — seeds both shuffle RNG and XOR stream

    Returns:
        PNG bytes of the stego image (same apparent dimensions as carrier)

    Raises:
        ValueError : if carrier_bytes is not a valid image
    """
    carrier    = Image.open(BytesIO(carrier_bytes)).convert("RGB")
    orig_w, orig_h = carrier.size

    seed, xor_bytes = _derive_key_material(stego_key)

    # Pack payload into pixel rows, encrypt pixel data
    hidden_img      = _payload_to_image(payload, orig_w)
    encrypted_img   = _encrypt_hidden_image(hidden_img, seed, xor_bytes)
    hidden_h        = encrypted_img.height

    # Build sentinel row
    sentinel_row    = _encode_sentinel_row(hidden_h, orig_w)

    # Stack: carrier | sentinel | encrypted hidden zone
    total_h = orig_h + 1 + hidden_h
    combined = Image.new("RGB", (orig_w, total_h))
    combined.paste(carrier,       (0, 0))
    combined.paste(sentinel_row,  (0, orig_h))
    combined.paste(encrypted_img, (0, orig_h + 1))

    # Save as PNG, then patch height header back to orig_h
    buf = BytesIO()
    combined.save(buf, format="PNG", optimize=False)
    raw     = buf.getvalue()
    patched = _patch_png_height(raw, orig_h)

    return patched


def extract_image2(stego_bytes: bytes, stego_key: str) -> bytes:
    """
    Extract payload bytes from a stego image created by embed_image2().

    Steps:
      1. Read the declared height from the PNG header
      2. Inflate the height header to force Pillow to decode all pixel rows
      3. Locate the sentinel row at declared_height
      4. Crop the encrypted hidden zone (rows after sentinel)
      5. Unshuffle + XOR-decrypt pixel data
      6. Recover raw payload bytes from pixel rows

    Args:
        stego_bytes : Raw bytes of the stego image (PNG or JPEG)
        stego_key   : Same string key used during embed_image2()

    Returns:
        Raw payload bytes (pass to chain executor to decrypt)

    Raises:
        ValueError : if no hidden data found, sentinel missing, or key is wrong
    """
    # Determine format from magic bytes
    if stego_bytes[:8] == b"\x89PNG\r\n\x1a\n":
        declared_h = _read_png_declared_height(stego_bytes)
        inflated   = _patch_png_height(stego_bytes, 99999)
    elif stego_bytes[:2] == b"\xff\xd8":
        declared_h = _read_jpeg_declared_height(stego_bytes)
        inflated   = _patch_jpeg_height(stego_bytes, 9999)
    else:
        raise ValueError("Unsupported image format. Use PNG or JPEG.")

    # Force Pillow to decode all rows including the hidden zone
    full_img = Image.open(BytesIO(inflated))
    full_img.load()
    full_img = full_img.convert("RGB")
    actual_h = full_img.height

    if actual_h <= declared_h:
        raise ValueError(
            "No hidden data detected. The image height matches its header — "
            "either this image was not embedded with StegoPS engine 2, or it "
            "was re-saved by an image editor that stripped the extra pixel rows."
        )

    # Read sentinel row
    hidden_h = _decode_sentinel_row(full_img, declared_h)
    if hidden_h is None:
        raise ValueError(
            "Sentinel marker not found at expected position. "
            "Wrong stego key, corrupted file, or not embedded with this engine."
        )

    # Crop encrypted hidden zone
    hidden_start = declared_h + 1
    hidden_end   = hidden_start + hidden_h
    if hidden_end > actual_h:
        raise ValueError("Hidden zone extends beyond file bounds — data is corrupted.")

    encrypted_zone = full_img.crop((0, hidden_start, full_img.width, hidden_end))

    # Decrypt pixel data
    seed, xor_bytes = _derive_key_material(stego_key)
    decrypted_img   = _decrypt_hidden_image(encrypted_zone, seed, xor_bytes)

    return _image_to_payload(decrypted_img)


def get_capacity2(carrier_bytes: bytes) -> dict:
    """
    Return capacity information for height-expansion technique.
    Capacity is theoretically unlimited (payload grows the file vertically),
    but this returns carrier dimensions for reference.

    Returns:
        dict with width, height, technique, and notes
    """
    img = Image.open(BytesIO(carrier_bytes))
    w, h = img.size
    return {
        "technique"  : "height_expansion",
        "width"      : w,
        "height"     : h,
        "capacity"   : "unlimited (file grows vertically with payload size)",
        "note"       : "Use PNG output — JPEG lossy compression may corrupt hidden rows",
    }


# ══════════════════════════════════════════════════════════════════════════════
#  STANDALONE PUBLIC API  (mirrors original PixelVault encode/decode interface)
# ══════════════════════════════════════════════════════════════════════════════

def encode(input_image_path: str, secret_text: str, key: str,
           output_path: str) -> dict:
    """
    Standalone encode: hide secret_text inside input_image_path.
    Saves stego image to output_path.

    Args:
        input_image_path : Path to carrier image (PNG or JPEG)
        secret_text      : Human-readable text to hide
        key              : Stego key string
        output_path      : Output path — use .png for best results

    Returns:
        dict with operation metadata

    Raises:
        ValueError       : if text is empty or format unsupported
        FileNotFoundError: if input image not found
    """
    if not secret_text.strip():
        raise ValueError("Secret text cannot be empty.")

    ext = os.path.splitext(output_path)[1].lower()
    if ext not in (".png", ".jpg", ".jpeg"):
        raise ValueError(f"Unsupported output format: '{ext}'. Use .png or .jpg")

    with open(input_image_path, "rb") as f:
        carrier_bytes = f.read()

    carrier    = Image.open(BytesIO(carrier_bytes)).convert("RGB")
    orig_w, orig_h = carrier.size

    seed, xor_bytes = _derive_key_material(key)

    # Render text to image, then treat rendered image bytes as payload
    text_img   = _render_text_image(secret_text, orig_w)
    # Use raw pixel bytes of rendered text as payload (preserves text as image on decode)
    payload    = text_img.tobytes()
    # Store dimensions so we can reconstruct on decode
    dim_header = struct.pack(">II", text_img.width, text_img.height)
    full_payload = dim_header + payload

    hidden_img      = _payload_to_image(full_payload, orig_w)
    encrypted_img   = _encrypt_hidden_image(hidden_img, seed, xor_bytes)
    hidden_h        = encrypted_img.height

    sentinel_row = _encode_sentinel_row(hidden_h, orig_w)
    total_h      = orig_h + 1 + hidden_h
    combined     = Image.new("RGB", (orig_w, total_h))
    combined.paste(carrier,       (0, 0))
    combined.paste(sentinel_row,  (0, orig_h))
    combined.paste(encrypted_img, (0, orig_h + 1))

    buf = BytesIO()
    if ext == ".png":
        combined.save(buf, format="PNG", optimize=False)
        raw     = buf.getvalue()
        patched = _patch_png_height(raw, orig_h)
    else:
        combined.save(buf, format="JPEG", quality=100, subsampling=0)
        raw     = buf.getvalue()
        patched = _patch_jpeg_height(raw, orig_h)

    with open(output_path, "wb") as f:
        f.write(patched)

    return {
        "status"                   : "success",
        "carrier_dimensions"       : f"{orig_w}x{orig_h}",
        "hidden_zone_height_rows"  : hidden_h,
        "hidden_zone_pixels"       : orig_w * hidden_h,
        "total_file_height"        : total_h,
        "declared_height_in_header": orig_h,
        "output_path"              : output_path,
        "format"                   : ext.lstrip(".").upper(),
    }


def decode(stego_image_path: str, key: str, output_path: str) -> dict:
    """
    Standalone decode: extract hidden text image from stego image.
    Saves the revealed text as an image to output_path.

    Args:
        stego_image_path : Path to stego image produced by encode()
        key              : Same key used during encode()
        output_path      : Path to save the revealed text image

    Returns:
        dict with operation metadata

    Raises:
        ValueError       : if no hidden data found or sentinel missing
        FileNotFoundError: if stego image not found
    """
    with open(stego_image_path, "rb") as f:
        raw = f.read()

    ext = os.path.splitext(stego_image_path)[1].lower()

    if ext == ".png" or raw[:8] == b"\x89PNG\r\n\x1a\n":
        declared_h = _read_png_declared_height(raw)
        inflated   = _patch_png_height(raw, 99999)
    elif ext in (".jpg", ".jpeg") or raw[:2] == b"\xff\xd8":
        declared_h = _read_jpeg_declared_height(raw)
        inflated   = _patch_jpeg_height(raw, 9999)
    else:
        raise ValueError(f"Unsupported format: '{ext}'")

    full_img = Image.open(BytesIO(inflated))
    full_img.load()
    full_img = full_img.convert("RGB")
    actual_h = full_img.height

    if actual_h <= declared_h:
        raise ValueError(
            "No hidden data detected. Either this image has no hidden content, "
            "or it was re-saved by an editor that stripped the extra pixel rows."
        )

    hidden_h = _decode_sentinel_row(full_img, declared_h)
    if hidden_h is None:
        raise ValueError(
            "Sentinel marker not found. The file may be corrupted or was not "
            "encoded with StegoPS image_engine2."
        )

    hidden_start = declared_h + 1
    hidden_end   = hidden_start + hidden_h
    if hidden_end > actual_h:
        raise ValueError("Hidden zone is beyond file bounds — data corrupted.")

    encrypted_zone  = full_img.crop((0, hidden_start, full_img.width, hidden_end))
    seed, xor_bytes = _derive_key_material(key)
    decrypted_img   = _decrypt_hidden_image(encrypted_zone, seed, xor_bytes)

    # Recover raw payload and reconstruct text image
    recovered_bytes = _image_to_payload(decrypted_img)
    txt_w, txt_h    = struct.unpack(">II", recovered_bytes[:8])
    pixel_bytes     = recovered_bytes[8: 8 + txt_w * txt_h * 3]
    text_img        = Image.frombytes("RGB", (txt_w, txt_h), pixel_bytes)
    text_img.save(output_path)

    return {
        "status"                : "success",
        "declared_header_height": declared_h,
        "actual_pixel_height"   : declared_h + 1 + hidden_h,
        "hidden_zone_height"    : hidden_h,
        "recovered_text_size"   : f"{txt_w}x{txt_h}",
        "output_path"           : output_path,
    }


# ══════════════════════════════════════════════════════════════════════════════
#  FLASK INTEGRATION  (called by app.py)
# ══════════════════════════════════════════════════════════════════════════════

def embed_image2_from_request(carrier_file, payload: bytes, stego_key: str) -> bytes:
    """
    Convenience wrapper for Flask file upload objects.
    carrier_file — werkzeug FileStorage or any object with .read()
    """
    return embed_image2(carrier_file.read(), payload, stego_key)


# ══════════════════════════════════════════════════════════════════════════════
#  CLI  (standalone usage)
# ══════════════════════════════════════════════════════════════════════════════

def _print_banner():
    print("""
╔══════════════════════════════════════════════╗
║    StegoPS — Image Engine 2                  ║
║    Height-Expansion + Pixel Scrambling       ║
╚══════════════════════════════════════════════╝
""")


def _print_result(result: dict):
    print("\n✅  Operation complete:")
    for k, v in result.items():
        print(f"   {k:<35} {v}")
    print()


if __name__ == "__main__":
    import sys
    import argparse

    if sys.platform == "win32":
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")

    _print_banner()

    parser = argparse.ArgumentParser(
        description="StegoPS Image Engine 2 — Height-Expansion Steganography",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  Encode (hide text inside image):
    python image_engine2.py encode photo.png "My secret message" mykey123 output.png

  Decode (reveal hidden text):
    python image_engine2.py decode output.png mykey123 revealed.png
        """
    )

    sub = parser.add_subparsers(dest="command")

    ep = sub.add_parser("encode", help="Hide text inside a carrier image")
    ep.add_argument("input",  help="Carrier image path (PNG or JPEG)")
    ep.add_argument("text",   help="Secret text to hide")
    ep.add_argument("key",    help="Stego key")
    ep.add_argument("output", help="Output stego image path (.png recommended)")

    dp = sub.add_parser("decode", help="Reveal hidden text from a stego image")
    dp.add_argument("input",  help="Stego image path")
    dp.add_argument("key",    help="Stego key")
    dp.add_argument("output", help="Output path for revealed text image")

    args = parser.parse_args()

    if args.command == "encode":
        try:
            _print_result(encode(args.input, args.text, args.key, args.output))
        except Exception as e:
            print(f"\n❌  Error: {e}", file=sys.stderr)
            sys.exit(1)

    elif args.command == "decode":
        try:
            _print_result(decode(args.input, args.key, args.output))
        except Exception as e:
            print(f"\n❌  Error: {e}", file=sys.stderr)
            sys.exit(1)

    else:
        parser.print_help()
