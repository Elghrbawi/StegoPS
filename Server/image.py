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
    n_tiles = (w // _DCT_BLOCK) * (h // _DCT_BLOCK)
    return {
        'dct':       n_tiles * _DCT_BITS_PER_TILE // 8 - 10,
        'lsb':       px * 3 // 8 - 10,
        'img_in_img': px * 3 * 4 // 8 - 10,  # 4 bits per channel byte
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
            _embed_dct(carrier_path, data, out_path)
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

# ── DCT steganography ────────────────────────────────────────────────────────
# Strategy: embed across all 3 YCbCr channels for maximum capacity.
# The image is split into non-overlapping 8×8 blocks. Within each block the
# 2D DCT is computed and payload bits are embedded into the LSBs of 4
# mid-frequency coefficients per channel (12 bits per block total).
# Embedding order: all Y blocks, then all Cb blocks, then all Cr blocks.
# Output is PNG (lossless) so coefficients are preserved exactly.
#
# Capacity: (W//8) * (H//8) * 12 bits / 8  -  10 header bytes (2 magic + 4 length + 4 CRC)
# e.g. 1000×1000 → ~18 KB;  3000×3000 → ~168 KB;  4000×4000 → ~300 KB

_DCT_BLOCK = 8
_DCT_QUANT = 25.0   # quantisation step

# 4 mid-frequency coefficient positions per block (zigzag ring around centre)
_DCT_COEFF_POSITIONS = [(3, 4), (4, 3), (4, 5), (5, 4)]
_DCT_BITS_PER_BLOCK  = len(_DCT_COEFF_POSITIONS)           # 4 bits/block/channel
_DCT_CHANNELS        = 3                                    # Y, Cb, Cr
_DCT_BITS_PER_TILE   = _DCT_BITS_PER_BLOCK * _DCT_CHANNELS # 12 bits per 8×8 tile


def _dct2(block: np.ndarray) -> np.ndarray:
    """2-D DCT-II via two successive 1-D DCTs (uses scipy if available, else numpy)."""
    try:
        from scipy.fft import dctn
        return dctn(block, norm='ortho')
    except ImportError:
        # Pure-numpy fallback: apply 1-D DCT twice
        return _dct1d_2d(block)


def _idct2(block: np.ndarray) -> np.ndarray:
    try:
        from scipy.fft import idctn
        return idctn(block, norm='ortho')
    except ImportError:
        return _idct1d_2d(block)


def _dct1d(x: np.ndarray) -> np.ndarray:
    """DCT-II of a 1-D array, orthonormal."""
    N = len(x)
    n = np.arange(N)
    k = n[:, None]
    M = np.cos(np.pi * k * (2 * n + 1) / (2 * N))
    y = M @ x
    y[0] *= np.sqrt(1 / N)
    y[1:] *= np.sqrt(2 / N)
    return y


def _idct1d(y: np.ndarray) -> np.ndarray:
    """Inverse DCT-II (DCT-III) of a 1-D array, orthonormal."""
    N = len(y)
    y = y.copy().astype(float)
    y[0] *= np.sqrt(1 / N)
    y[1:] *= np.sqrt(2 / N)
    n = np.arange(N)
    k = n[None, :]
    M = np.cos(np.pi * k * (2 * n[:, None] + 1) / (2 * N))
    return M @ y


def _dct1d_2d(block: np.ndarray) -> np.ndarray:
    tmp = np.apply_along_axis(_dct1d, 1, block.astype(float))
    return np.apply_along_axis(_dct1d, 0, tmp)


def _idct1d_2d(block: np.ndarray) -> np.ndarray:
    tmp = np.apply_along_axis(_idct1d, 0, block.astype(float))
    return np.apply_along_axis(_idct1d, 1, tmp)


def _iter_blocks(arr: np.ndarray):
    """Yield (row_start, col_start) for every full 8×8 block."""
    H, W = arr.shape
    for r in range(0, H - H % _DCT_BLOCK, _DCT_BLOCK):
        for c in range(0, W - W % _DCT_BLOCK, _DCT_BLOCK):
            yield r, c


def _dct_channel_bits(channel: np.ndarray, blocks: list, start: int, end: int,
                      write_bits: list = None) -> list:
    """
    Read or write bits [start, end) into/from a single channel's DCT coefficients.
    If write_bits is provided, modifies channel in-place and returns [].
    Otherwise returns the list of read bits.
    Each block contributes _DCT_BITS_PER_BLOCK bits.
    """
    result = []
    n_blocks = len(blocks)
    for blk_idx in range(n_blocks):
        base = blk_idx * _DCT_BITS_PER_BLOCK
        if base >= end:
            break
        if base + _DCT_BITS_PER_BLOCK <= start:
            continue
        r, c   = blocks[blk_idx]
        block  = channel[r:r+_DCT_BLOCK, c:c+_DCT_BLOCK]
        coeffs = _dct2(block)
        modified = False
        for slot, (cr, cc) in enumerate(_DCT_COEFF_POSITIONS):
            bit_pos = base + slot
            if bit_pos < start or bit_pos >= end:
                continue
            if write_bits is not None:
                q = round(coeffs[cr, cc] / _DCT_QUANT)
                q = (q & ~1) | int(write_bits[bit_pos - start])
                coeffs[cr, cc] = q * _DCT_QUANT
                modified = True
            else:
                q = round(coeffs[cr, cc] / _DCT_QUANT)
                result.append(q & 1)
        if modified:
            channel[r:r+_DCT_BLOCK, c:c+_DCT_BLOCK] = _idct2(coeffs)
    return result


def _embed_dct(carrier_path: str, data: bytes, out_path: str):
    img = Image.open(carrier_path).convert('YCbCr')
    arr = np.array(img, dtype=np.float64)

    payload  = _pack(data)
    bits     = list(_bits(payload))
    n_bits   = len(bits)

    blocks   = list(_iter_blocks(arr[:, :, 0]))
    n_blocks = len(blocks)
    capacity = n_blocks * _DCT_BITS_PER_TILE   # 12 bits per tile across 3 channels

    if n_bits > capacity:
        cap_kb = capacity // 8 // 1024
        needed_kb = n_bits // 8 // 1024
        raise ValueError(
            f'Payload too large for DCT method ({needed_kb} KB needed, '
            f'~{cap_kb} KB available). '
            f'Use a larger carrier image or switch to LSB / img_in_img method.'
        )

    # Spread bits across channels: first n_blocks*4 bits → Y, next → Cb, rest → Cr
    bits_per_ch = n_blocks * _DCT_BITS_PER_BLOCK
    for ch_idx in range(_DCT_CHANNELS):
        ch_start = ch_idx * bits_per_ch
        ch_end   = min(ch_start + bits_per_ch, n_bits)
        if ch_start >= n_bits:
            break
        ch_bits = bits[ch_start:ch_end]
        channel = arr[:, :, ch_idx]
        _dct_channel_bits(channel, blocks, 0, len(ch_bits), write_bits=ch_bits)
        arr[:, :, ch_idx] = np.clip(channel, 0, 255)

    Image.fromarray(arr.astype(np.uint8), 'YCbCr').convert('RGB').save(out_path, 'PNG')


def _extract_dct(stego_path: str) -> bytes:
    img = Image.open(stego_path).convert('YCbCr')
    arr = np.array(img, dtype=np.float64)

    magic_bits  = _bits(_MAGIC)
    header_bits = len(magic_bits) + 32 + 32  # 16 magic + 32 length + 32 CRC = 80 bits

    blocks      = list(_iter_blocks(arr[:, :, 0]))
    n_blocks    = len(blocks)
    bits_per_ch = n_blocks * _DCT_BITS_PER_BLOCK

    def _read_global(start: int, end: int) -> list:
        """Read bits from the 3-channel flattened stream."""
        result = []
        for ch_idx in range(_DCT_CHANNELS):
            ch_start = ch_idx * bits_per_ch
            ch_end   = ch_start + bits_per_ch
            lo = max(start, ch_start) - ch_start
            hi = min(end,   ch_end)   - ch_start
            if lo >= hi:
                continue
            result += _dct_channel_bits(arr[:, :, ch_idx], blocks, lo, hi)
            if len(result) >= (end - start):
                break
        return result

    # Phase 1: read header
    header_raw = _read_global(0, header_bits)
    if len(header_raw) < header_bits:
        raise ValueError('Not enough DCT capacity to read header')
    header = ''.join(str(b) for b in header_raw)
    if header[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden DCT payload found (magic mismatch)')

    length     = int(header[len(magic_bits):len(magic_bits) + 32], 2)
    stored_crc = int(header[len(magic_bits) + 32:len(magic_bits) + 64], 2)
    total_bits = header_bits + length * 8
    capacity   = n_blocks * _DCT_BITS_PER_TILE

    if total_bits > capacity:
        raise ValueError('DCT payload length field exceeds available capacity')

    # Phase 2: read payload bits
    payload_raw  = _read_global(header_bits, total_bits)
    payload_bits = ''.join(str(b) for b in payload_raw)
    payload = bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')
    return payload