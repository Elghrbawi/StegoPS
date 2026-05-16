import hashlib
import struct
import zlib
import numpy as np
from PIL import Image

_MAGIC = b'\xDE\xAD'

def _bits(data: bytes) -> str:
    return ''.join(format(b, '08b') for b in data)

def _pack(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _MAGIC + len(data).to_bytes(4, 'big') + crc + data

def _keystream(key: str, length: int) -> bytes:
    buf = bytearray()
    i = 0
    kb = key.encode('utf-8')
    while len(buf) < length:
        buf += hashlib.sha256(kb + i.to_bytes(4, 'big')).digest()
        i += 1
    return bytes(buf[:length])

def _check_capacity(pixels_count: int, channels: int, bits_per_channel: int, payload_size: int):
    cap = pixels_count * channels * bits_per_channel // 8
    if payload_size > cap:
        raise ValueError(f'Payload too large ({payload_size} bytes). Carrier capacity: {cap} bytes')

def _carrier_capacity(carrier_path: str) -> dict:
    """Return capacity in bytes for each image steg method on this carrier."""
    img     = Image.open(carrier_path)
    w, h    = img.size
    px      = w * h
    return {
        'dct':        _jpeg_dct_capacity(carrier_path),   # real JPEG AC coefficients
        'lsb':        px * 3 // 8 - 10,
        'img_in_img': px * 3 * 4 // 8 - 10,
    }


def _dct_fits(carrier_path: str, payload_size: int) -> bool:
    try:
        return _carrier_capacity(carrier_path)['dct'] >= payload_size
    except Exception:
        return False


# Fallback cascade for DCT when payload is too large:
#   DCT  →  LSB  →  img_in_img
# actual_method is set to whichever method was finally used.
_DCT_FALLBACK_CHAIN = ['dct', 'lsb', 'img_in_img']


def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    payload_size = len(_pack(data))

    if method == 'dct':
        try:
            cap = _carrier_capacity(carrier_path)
        except Exception as e:
            raise ValueError(f'Cannot read carrier: {e}')

        if payload_size <= cap['dct']:
            used = 'dct'
        elif payload_size <= cap['lsb']:
            used = 'lsb'
        elif payload_size <= cap['img_in_img']:
            used = 'img_in_img'
        else:
            needed_kb = payload_size // 1024
            max_kb    = cap['img_in_img'] // 1024
            raise ValueError(
                f'Payload too large ({payload_size:,} bytes) for this carrier. '
                f'Maximum capacity with img_in_img: {max_kb:,} KB. '
                f'Use a larger carrier image (need ~{needed_kb * 8 // (3*4) + 1} KB image at minimum).'
            )

        # store which method was actually used so server.py can report it
        embed._last_actual_method = used
        if used == 'dct':
            _embed_dct(carrier_path, data, out_path)  # outputs JPEG
        elif used == 'lsb':
            _embed_lsb(carrier_path, data, out_path)
        else:
            _embed_img_in_img(carrier_path, data, out_path)
        return

    embed._last_actual_method = method
    if method == 'lsb':
        _embed_lsb(carrier_path, data, out_path)
    elif method == 'xor_pixel':
        _embed_xor(carrier_path, data, out_path, key)
    elif method == 'alpha':
        _embed_alpha(carrier_path, data, out_path)
    elif method == 'img_in_img':
        _embed_img_in_img(carrier_path, data, out_path)
    else:
        raise ValueError(f'Unknown image steg method: {method}')


embed._last_actual_method = None   # initialise attribute

def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'lsb':
        return _extract_lsb(stego_path)
    elif method == 'xor_pixel':
        return _extract_xor(stego_path, key)
    elif method == 'alpha':
        return _extract_alpha(stego_path)
    elif method == 'img_in_img':
        return _extract_img_in_img(stego_path)
    elif method == 'dct':
        return _extract_dct(stego_path)
    else:
        raise ValueError(f'Unknown image steg method: {method}')

def _read_payload_from_bits(bits: list, bpc: int = 1) -> bytes:
    magic_bits = _bits(_MAGIC)
    # header = 16 magic + 32 length + 32 crc = 80 bits
    needed = len(magic_bits) + 32 + 32
    header = ''.join(str(b) for b in bits[:needed])
    if header[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden payload found (magic mismatch)')
    length     = int(header[len(magic_bits):len(magic_bits) + 32], 2)
    stored_crc = int(header[len(magic_bits) + 32:len(magic_bits) + 64], 2)
    total_bits = needed + length * 8
    if total_bits > len(bits):
        raise ValueError('Payload length exceeds available data')
    payload_bits = ''.join(str(b) for b in bits[needed:needed + length * 8])
    payload = bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))
    actual_crc = zlib.crc32(payload) & 0xFFFFFFFF
    if actual_crc != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')
    return payload

def _embed_lsb(carrier_path: str, data: bytes, out_path: str):
    img = Image.open(carrier_path).convert('RGB')
    flat = list(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 1, len(payload))
    bits = _bits(payload)
    for i, bit in enumerate(bits):
        flat[i] = (flat[i] & 0xFE) | int(bit)
    out = Image.frombytes('RGB', img.size, bytes(flat))
    out.save(out_path, 'PNG')

def _extract_lsb(stego_path: str) -> bytes:
    img = Image.open(stego_path).convert('RGB')
    flat = list(img.tobytes())
    bits = [b & 1 for b in flat]
    return _read_payload_from_bits(bits)

def _embed_xor(carrier_path: str, data: bytes, out_path: str, key: str):
    img = Image.open(carrier_path).convert('RGB')
    flat = list(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 1, len(payload))
    ks = _keystream(key, len(payload))
    xored = bytes(a ^ b for a, b in zip(payload, ks))
    bits = _bits(xored)
    for i, bit in enumerate(bits):
        flat[i] = (flat[i] & 0xFE) | int(bit)
    Image.frombytes('RGB', img.size, bytes(flat)).save(out_path, 'PNG')

def _extract_xor(stego_path: str, key: str) -> bytes:
    img = Image.open(stego_path).convert('RGB')
    flat = list(img.tobytes())
    magic_and_len = len(_MAGIC) + 4 + 4  # 2 magic + 4 length + 4 CRC
    raw_bits = [b & 1 for b in flat[:magic_and_len * 8]]
    raw_bytes = bytes(int(''.join(str(b) for b in raw_bits[i:i+8]), 2) for i in range(0, len(raw_bits), 8))
    ks_head = _keystream(key, magic_and_len)
    header = bytes(a ^ b for a, b in zip(raw_bytes, ks_head))
    if header[:2] != _MAGIC:
        raise ValueError('No hidden payload found (magic mismatch)')
    length     = int.from_bytes(header[2:6], 'big')
    stored_crc = int.from_bytes(header[6:10], 'big')
    total = magic_and_len + length
    all_raw_bits = [b & 1 for b in flat[:total * 8]]
    all_raw = bytes(int(''.join(str(b) for b in all_raw_bits[i:i+8]), 2) for i in range(0, len(all_raw_bits), 8))
    ks = _keystream(key, total)
    plain = bytes(a ^ b for a, b in zip(all_raw, ks))
    payload = plain[magic_and_len:]
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')
    return payload

def _embed_alpha(carrier_path: str, data: bytes, out_path: str):
    img = Image.open(carrier_path).convert('RGBA')
    pixels = list(img.getdata())
    payload = _pack(data)
    _check_capacity(len(pixels), 1, 1, len(payload))
    bits = _bits(payload)
    new_pixels = []
    for idx, (r, g, b, a) in enumerate(pixels):
        if idx < len(bits):
            a = (a & 0xFE) | int(bits[idx])
        new_pixels.append((r, g, b, a))
    out = Image.new('RGBA', img.size)
    out.putdata(new_pixels)
    out.save(out_path, 'PNG')

def _extract_alpha(stego_path: str) -> bytes:
    img = Image.open(stego_path).convert('RGBA')
    pixels = list(img.getdata())
    bits = [p[3] & 1 for p in pixels]
    return _read_payload_from_bits(bits)

def _embed_img_in_img(carrier_path: str, data: bytes, out_path: str):
    img = Image.open(carrier_path).convert('RGB')
    flat = list(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 4, len(payload))
    bits = _bits(payload)
    i = 0
    for px in range(len(flat)):
        if i >= len(bits):
            break
        nibble_bits = bits[i:i+4].ljust(4, '0')
        nibble = int(nibble_bits, 2)
        flat[px] = (flat[px] & 0xF0) | nibble
        i += 4
    Image.frombytes('RGB', img.size, bytes(flat)).save(out_path, 'PNG')

def _extract_img_in_img(stego_path: str) -> bytes:
    img = Image.open(stego_path).convert('RGB')
    flat = list(img.tobytes())
    nibbles = [b & 0x0F for b in flat]
    bits = ''.join(format(n, '04b') for n in nibbles)
    bit_list = [int(b) for b in bits]
    return _read_payload_from_bits(bit_list, bpc=4)

# ── DCT steganography (pixel-space, pure Python — no jpegio / C++ needed) ────
# Strategy  : Convert carrier to YCbCr, split into 8×8 luma blocks, run a
#             2-D DCT on each block via scipy.fft.dctn, embed payload bits
#             into mid-frequency AC coefficients (strength-threshold gated),
#             IDCT back, reconstruct the image and save as JPEG.
#             No compiled C extension is required — only scipy + Pillow + numpy,
#             all of which install from pure-wheel packages on every platform.
#
# Capacity  : number_of_8x8_blocks × usable_mid_freq_AC_slots / 8  bytes.
#             A 1 MP image yields ~14 KB.  Computed at runtime.
#
# Robustness: bits are embedded with a strength delta (DCT_DELTA) large enough
#             to survive one JPEG encode/decode round-trip at quality ≥ 85.
#             The output is saved at quality=95 to minimise drift.
#
# Header    : same 10-byte frame as all other methods (MAGIC + LENGTH + CRC32).

import os as _os  # noqa: F811 (re-import for DCT section clarity)
from scipy.fft import dctn, idctn   # pure-Python wheels, no C++ build step

# Mid-frequency AC positions (zigzag indices 5-20) chosen for robustness
# vs. capacity trade-off.  DC (0,0) and very-high-frequency corners skipped.
_DCT_AC_SLOTS = [
    (0,2),(0,3),(1,2),(2,0),(2,1),(3,0),
    (1,3),(0,4),(0,5),(1,4),(2,2),(3,1),
    (4,0),(4,1),(3,2),(2,3),(1,5),(0,6),
]
_DCT_DELTA = 25.0   # embedding strength; survives JPEG re-encode at quality≥85


def _dct_block_positions(h: int, w: int):
    """Yield (row, col) top-left corners of all 8×8 blocks in an H×W plane."""
    for r in range(0, h - h % 8, 8):
        for c in range(0, w - w % 8, 8):
            yield r, c


def _jpeg_dct_capacity(carrier_path: str) -> int:
    """
    Return the number of payload bytes embeddable via DCT into this carrier.
    Works on any image format Pillow can open (JPEG, PNG, BMP, …).
    """
    img  = Image.open(carrier_path).convert('YCbCr')
    arr  = np.array(img, dtype=np.float64)
    h, w = arr.shape[:2]
    n_blocks = (h // 8) * (w // 8)
    bits_per_block = len(_DCT_AC_SLOTS)
    return max(0, n_blocks * bits_per_block // 8 - 10)


def _embed_dct(carrier_path: str, data: bytes, out_path: str):
    """
    Embed *data* into mid-frequency AC DCT coefficients of the Y (luma) channel.
    Carrier can be any Pillow-readable format; output is always JPEG.
    """
    img = Image.open(carrier_path).convert('YCbCr')
    arr = np.array(img, dtype=np.float64)
    Y   = arr[:, :, 0].copy()
    h, w = Y.shape

    payload = _pack(data)
    bits    = [int(b) for b in _bits(payload)]
    n_bits  = len(bits)

    # Count available slots first
    blocks = list(_dct_block_positions(h, w))
    total_slots = len(blocks) * len(_DCT_AC_SLOTS)
    if n_bits > total_slots:
        raise ValueError(
            f'Payload too large for DCT method — need {n_bits} coefficient slots, '
            f'carrier only has {total_slots} slots ({total_slots // 8} bytes capacity).'
        )

    bit_idx = 0
    for (r, c) in blocks:
        if bit_idx >= n_bits:
            break
        block = Y[r:r+8, c:c+8]
        D = dctn(block, norm='ortho')
        for (dr, dc) in _DCT_AC_SLOTS:
            if bit_idx >= n_bits:
                break
            # Quantise the coefficient to the nearest even/odd multiple of
            # DELTA/2 to encode the bit, then restore the fractional part.
            coef = D[dr, dc]
            slot = round(coef / (_DCT_DELTA / 2))
            # Force slot parity to match the bit
            if (slot % 2) != bits[bit_idx]:
                slot += 1 if bits[bit_idx] else -1
            D[dr, dc] = slot * (_DCT_DELTA / 2)
            bit_idx += 1
        Y[r:r+8, c:c+8] = idctn(D, norm='ortho')

    arr[:, :, 0] = np.clip(Y, 0, 255)
    out_img = Image.fromarray(arr.astype(np.uint8), 'YCbCr').convert('RGB')

    jpg_out = out_path if out_path.lower().endswith(('.jpg', '.jpeg')) else out_path
    out_img.save(jpg_out, 'JPEG', quality=95, subsampling=0)


def _extract_dct(stego_path: str) -> bytes:
    """
    Extract the payload embedded by _embed_dct.
    Works on any Pillow-readable JPEG (or other lossless re-save at quality≥85).
    """
    img = Image.open(stego_path).convert('YCbCr')
    arr = np.array(img, dtype=np.float64)
    Y   = arr[:, :, 0]
    h, w = Y.shape

    magic_bits        = _bits(_MAGIC)
    header_bits_count = len(magic_bits) + 32 + 32   # 80 bits

    collected: list[int] = []

    for (r, c) in _dct_block_positions(h, w):
        block = Y[r:r+8, c:c+8]
        D = dctn(block, norm='ortho')
        for (dr, dc) in _DCT_AC_SLOTS:
            slot = round(D[dr, dc] / (_DCT_DELTA / 2))
            collected.append(int(slot % 2 != 0))   # parity → bit

    def _bits_to_bytes(bit_list: list, start: int, n_bits: int) -> bytes:
        s = ''.join(str(b) for b in bit_list[start:start + n_bits])
        return bytes(int(s[i:i+8], 2) for i in range(0, n_bits, 8))

    if len(collected) < header_bits_count:
        raise ValueError('Image too small to contain a DCT payload')

    header_str = ''.join(str(b) for b in collected[:header_bits_count])

    if header_str[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden DCT payload found (magic mismatch)')

    length     = int(header_str[len(magic_bits):len(magic_bits) + 32], 2)
    stored_crc = int(header_str[len(magic_bits) + 32:len(magic_bits) + 64], 2)

    payload_start = header_bits_count
    payload_end   = payload_start + length * 8
    if payload_end > len(collected):
        raise ValueError('Payload length exceeds available DCT slots — image too small or not a stego file')

    payload_bits = ''.join(str(b) for b in collected[payload_start:payload_end])
    payload = bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))

    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')

    return payload