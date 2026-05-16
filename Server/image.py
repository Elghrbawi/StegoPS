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
    i   = 0
    kb  = key.encode('utf-8')
    while len(buf) < length:
        buf += hashlib.sha256(kb + i.to_bytes(4, 'big')).digest()
        i   += 1
    return bytes(buf[:length])

def _check_capacity(pixels_count: int, channels: int, bpc: int, payload_size: int):
    cap = pixels_count * channels * bpc // 8
    if payload_size > cap:
        raise ValueError(
            f'Payload too large ({payload_size} bytes). '
            f'Carrier capacity: {cap} bytes'
        )

def _carrier_capacity(carrier_path: str) -> dict:
    """Return max payload bytes for each steg method on this carrier."""
    img  = Image.open(carrier_path)
    w, h = img.size
    px   = w * h
    try:
        dct_cap = _jpeg_dct_capacity(carrier_path)
    except Exception:
        dct_cap = 0
    return {
        'dct':        dct_cap,
        'lsb':        px * 3 // 8 - 10,
        'img_in_img': px * 3 * 4 // 8 - 10,
    }

def _dct_fits(carrier_path: str, payload_size: int) -> bool:
    try:
        return _carrier_capacity(carrier_path)['dct'] >= payload_size
    except Exception:
        return False


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
            max_kb = cap['img_in_img'] // 1024
            raise ValueError(
                f'Payload too large ({payload_size:,} bytes) for this carrier. '
                f'Maximum capacity (img_in_img): {max_kb:,} KB. '
                f'Use a larger carrier image.'
            )

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

embed._last_actual_method = None


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
    needed     = len(magic_bits) + 32 + 32   # 16 + 32 + 32 = 80 bits
    header     = ''.join(str(b) for b in bits[:needed])
    if header[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden payload found (magic mismatch)')
    length     = int(header[len(magic_bits):len(magic_bits) + 32], 2)
    stored_crc = int(header[len(magic_bits) + 32:len(magic_bits) + 64], 2)
    total_bits = needed + length * 8
    if total_bits > len(bits):
        raise ValueError('Payload length exceeds available data')
    payload_bits = ''.join(str(b) for b in bits[needed:needed + length * 8])
    payload      = bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')
    return payload


# ── LSB ───────────────────────────────────────────────────────────────────────
def _embed_lsb(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGB')
    flat    = list(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 1, len(payload))
    bits = _bits(payload)
    for i, bit in enumerate(bits):
        flat[i] = (flat[i] & 0xFE) | int(bit)
    Image.frombytes('RGB', img.size, bytes(flat)).save(out_path, 'PNG')

def _extract_lsb(stego_path: str) -> bytes:
    img  = Image.open(stego_path).convert('RGB')
    flat = list(img.tobytes())
    return _read_payload_from_bits([b & 1 for b in flat])


# ── XOR Pixel ─────────────────────────────────────────────────────────────────
def _embed_xor(carrier_path: str, data: bytes, out_path: str, key: str):
    img     = Image.open(carrier_path).convert('RGB')
    flat    = list(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 1, len(payload))
    ks    = _keystream(key, len(payload))
    xored = bytes(a ^ b for a, b in zip(payload, ks))
    bits  = _bits(xored)
    for i, bit in enumerate(bits):
        flat[i] = (flat[i] & 0xFE) | int(bit)
    Image.frombytes('RGB', img.size, bytes(flat)).save(out_path, 'PNG')

def _extract_xor(stego_path: str, key: str) -> bytes:
    img            = Image.open(stego_path).convert('RGB')
    flat           = list(img.tobytes())
    magic_and_len  = len(_MAGIC) + 4 + 4   # 2 magic + 4 length + 4 CRC
    raw_bits       = [b & 1 for b in flat[:magic_and_len * 8]]
    raw_bytes      = bytes(
        int(''.join(str(b) for b in raw_bits[i:i+8]), 2)
        for i in range(0, len(raw_bits), 8)
    )
    header = bytes(a ^ b for a, b in zip(raw_bytes, _keystream(key, magic_and_len)))
    if header[:2] != _MAGIC:
        raise ValueError('No hidden payload found (magic mismatch)')
    length     = int.from_bytes(header[2:6], 'big')
    stored_crc = int.from_bytes(header[6:10], 'big')
    total          = magic_and_len + length
    all_raw_bits   = [b & 1 for b in flat[:total * 8]]
    all_raw        = bytes(
        int(''.join(str(b) for b in all_raw_bits[i:i+8]), 2)
        for i in range(0, len(all_raw_bits), 8)
    )
    plain   = bytes(a ^ b for a, b in zip(all_raw, _keystream(key, total)))
    payload = plain[magic_and_len:]
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')
    return payload


# ── Alpha Channel ─────────────────────────────────────────────────────────────
def _embed_alpha(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGBA')
    pixels  = list(img.getdata())
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
    img  = Image.open(stego_path).convert('RGBA')
    bits = [a & 1 for (_, _, _, a) in img.getdata()]
    return _read_payload_from_bits(bits)


# ── Image-in-Image ────────────────────────────────────────────────────────────
def _embed_img_in_img(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGB')
    flat    = bytearray(img.tobytes())
    payload = _pack(data)
    _check_capacity(img.width * img.height, 3, 4, len(payload))
    bits = _bits(payload)
    for i in range(0, len(bits), 4):
        flat[i // 4] = (flat[i // 4] & 0xF0) | int(bits[i:i+4], 2)
    Image.frombytes('RGB', img.size, bytes(flat)).save(out_path, 'PNG')

def _extract_img_in_img(stego_path: str) -> bytes:
    img      = Image.open(stego_path).convert('RGB')
    flat     = list(img.tobytes())
    bits     = ''.join(format(b & 0x0F, '04b') for b in flat)
    return _read_payload_from_bits([int(b) for b in bits], bpc=4)


# ── DCT steganography — real JPEG coefficient bitstream ──────────────────────
#
# Overview
# ────────
# This is genuine JPEG-coefficient steganography operating directly on the
# Huffman-encoded bitstream inside the JPEG file.  No pixel-space DCT
# round-trip, no jpegio, no C compiler — pure Python + stdlib only.
#
# How it works
# ────────────
# 1. Parse the JPEG file: read DHT (Huffman) tables, SOF (frame header for
#    image dimensions and component sampling), and SOS (start of scan).
# 2. Strip JPEG byte-stuffing (0xFF 0x00 → 0xFF) from the scan segment to
#    obtain the "logical" bitstream — a plain bytearray we can freely edit.
# 3. Huffman-decode the logical bitstream to locate every AC coefficient in
#    the Y (luma) channel.  For each non-zero AC coefficient with |value| ≥ 2
#    (category ≥ 2), record the absolute bit position of the VLI magnitude LSB.
#    Only |v| ≥ 2 is used because flipping the LSB of a category-N VLI field
#    (N ≥ 2) changes the decoded value by ±1 but never crosses a power-of-2
#    boundary, so the Huffman symbol (which encodes run + category) remains
#    valid.  |v| = 1 (category 1) is excluded because flipping its single VLI
#    bit would need a different category (0 or 2), breaking the bitstream.
# 4. Embed: set the VLI LSB at each recorded position to the payload bit.
# 5. Re-add byte-stuffing (insert 0x00 after any 0xFF) and splice the
#    modified scan segment back into the original file bytes.
# 6. Write the resulting bytearray as the output JPEG.  The file is a valid
#    JPEG that any standard decoder can open; the embedded bits survive
#    because we never touched the Huffman codes or structural markers.
#
# Extract is the reverse: destuff → Huffman-decode → read VLI LSBs → rebuild
# payload → verify CRC.
#
# Output   : JPEG (.jpg).  Carrier MUST be a JPEG file.
# Capacity : number of Y-channel AC coefficients with |v| ≥ 2, divided by 8,
#            minus 10 bytes (the fixed frame header).  Varies with image
#            content and JPEG quality.  Higher quality → more non-zero ACs.
# No extra dependencies beyond Pillow (used only for the fallback LSB/img_in_img
# paths) and the Python standard library.


def _jpeg_parse(data: bytearray) -> tuple:
    """
    Parse a JPEG bytearray and return:
      (dht, sof, comp_scan, sos_off)

    dht       : dict  (table_class, table_id) -> {(nbits, code): symbol}
    sof       : dict  with keys 'H', 'W', 'components', 'max_hs', 'max_vs'
    comp_scan : list of dicts {'id', 'dc', 'ac'} — one per scan component
    sos_off   : int   byte offset where the compressed scan data begins
    """
    pos = 0
    dht, sof, comp_scan, sos_off = {}, {}, [], None

    while pos < len(data) - 1:
        if data[pos] != 0xFF:
            pos += 1
            continue
        m = data[pos + 1]
        if m in (0xD8, 0xD9):
            pos += 2
            continue
        if m in (0x00, 0xFF):
            pos += 1
            continue
        seg_len = int.from_bytes(data[pos + 2:pos + 4], 'big')

        if m == 0xC4:   # DHT — Huffman table
            p2  = pos + 4
            end = pos + 2 + seg_len
            while p2 < end:
                tc_ti  = data[p2]; p2 += 1
                tc, ti = (tc_ti >> 4) & 0xF, tc_ti & 0xF
                counts = list(data[p2:p2 + 16]); p2 += 16
                codes  = {}
                code   = 0
                for nb in range(1, 17):
                    for _ in range(counts[nb - 1]):
                        sym = data[p2]; p2 += 1
                        codes[(nb, code)] = sym
                        code += 1
                    code <<= 1
                dht[(tc, ti)] = codes

        elif m in (0xC0, 0xC2):   # SOF — frame header
            sof['H'] = int.from_bytes(data[pos + 5:pos + 7], 'big')
            sof['W'] = int.from_bytes(data[pos + 7:pos + 9], 'big')
            nc = data[pos + 9]
            sof['components'] = [
                {
                    'id': data[pos + 10 + i * 3],
                    'hs': (data[pos + 11 + i * 3] >> 4) & 0xF,
                    'vs':  data[pos + 11 + i * 3]       & 0xF,
                    'qt':  data[pos + 12 + i * 3],
                }
                for i in range(nc)
            ]
            sof['max_hs'] = max(c['hs'] for c in sof['components'])
            sof['max_vs'] = max(c['vs'] for c in sof['components'])

        elif m == 0xDA:   # SOS — start of scan
            p2 = pos + 4
            nc = data[p2]; p2 += 1
            comp_scan = [
                {
                    'id': data[p2 + i * 2],
                    'dc': (data[p2 + i * 2 + 1] >> 4) & 0xF,
                    'ac':  data[p2 + i * 2 + 1]       & 0xF,
                }
                for i in range(nc)
            ]
            sos_off = pos + 2 + seg_len
            break

        pos += 2 + seg_len

    if sos_off is None:
        raise ValueError('No SOS marker found — file is not a valid baseline JPEG.')
    return dht, sof, comp_scan, sos_off


def _jpeg_destuff(data: bytearray, start: int) -> tuple:
    """
    Remove JPEG byte-stuffing from the scan segment starting at *start*.
    Returns (logical_bytes, scan_end) where:
      logical_bytes : bytearray of de-stuffed scan data
      scan_end      : byte offset in *data* immediately after the scan segment
    """
    logical = bytearray()
    p = start
    while p < len(data):
        b = data[p]
        if b == 0xFF and p + 1 < len(data):
            nb = data[p + 1]
            if nb == 0x00:
                logical.append(b); p += 2; continue   # stuffed 0xFF
            elif 0xD0 <= nb <= 0xD7:
                p += 2; continue                        # restart marker — skip
            else:
                break                                   # end-of-image or other marker
        logical.append(b); p += 1
    return logical, p


def _jpeg_restuff(logical: bytearray) -> bytearray:
    """Re-add 0x00 byte-stuffing after every 0xFF in scan data."""
    out = bytearray()
    for b in logical:
        out.append(b)
        if b == 0xFF:
            out.append(0x00)
    return out


def _get_lbit(logical: bytearray, lbit: int) -> int:
    """Read one bit from logical bytes at absolute bit position *lbit*."""
    return (logical[lbit // 8] >> (7 - lbit % 8)) & 1


def _set_lbit(logical: bytearray, lbit: int, v: int):
    """Write one bit into logical bytes at absolute bit position *lbit*."""
    b  = lbit // 8
    bo = 7 - lbit % 8
    if v:
        logical[b] |=  (1 << bo)
    else:
        logical[b] &= ~(1 << bo)


class _BitReader:
    """Sequential bit reader over a bytearray of logical (de-stuffed) bytes."""
    __slots__ = ('_lg', '_lb', '_nb', '_buf', 'lbit')

    def __init__(self, logical: bytearray):
        self._lg  = logical
        self._lb  = 0
        self._nb  = 0
        self._buf = 0
        self.lbit = 0

    def _load(self):
        self._buf = self._lg[self._lb]
        self._nb  = 8
        self._lb += 1

    def read_bit(self) -> int:
        if self._nb == 0:
            self._load()
        self._nb -= 1
        bit = (self._buf >> self._nb) & 1
        self.lbit += 1
        return bit

    def read_bits(self, n: int) -> int:
        v = 0
        for _ in range(n):
            v = (v << 1) | self.read_bit()
        return v


def _huff_decode(br: _BitReader, table: dict) -> int:
    code = 0
    for nb in range(1, 17):
        code = (code << 1) | br.read_bit()
        if (nb, code) in table:
            return table[(nb, code)]
    raise ValueError('Invalid Huffman code in JPEG scan data.')


def _vli_decode(n: int, bits: int) -> int:
    if n == 0:
        return 0
    return bits if bits >= (1 << (n - 1)) else bits - (1 << n) + 1


def _scan_safe_ac_positions(logical: bytearray, dht: dict,
                             sof: dict, comp_scan: list) -> list:
    """
    Huffman-decode the logical scan bytes and return a list of absolute bit
    positions (ints) — one per safe Y-channel AC coefficient.

    "Safe" means the VLI category is ≥ 2 (|decoded value| ≥ 2).
    Flipping the LSB of a category-N (N≥2) VLI value changes the decoded
    integer by ±1 but never changes the number of bits needed to represent
    it, so the Huffman symbol (run, category) stays valid throughout.
    Category-1 coefficients (|v|=1) are skipped because flipping their single
    VLI bit would require a different category.

    Returns a list of int — each is the absolute bit index (in the logical
    bytearray) of the VLI magnitude LSB for one usable AC coefficient.
    """
    br      = _BitReader(logical)
    W, H    = sof['W'], sof['H']
    max_hs  = sof['max_hs']
    max_vs  = sof['max_vs']
    mcu_w   = (W + max_hs * 8 - 1) // (max_hs * 8)
    mcu_h   = (H + max_vs * 8 - 1) // (max_vs * 8)
    cbi     = {c['id']: c for c in sof['components']}
    prev_dc = [0] * len(comp_scan)
    slots   = []

    for _mr in range(mcu_h):
        for _mc in range(mcu_w):
            for ci, cs in enumerate(comp_scan):
                ci_info = cbi[cs['id']]
                hs, vs  = ci_info['hs'], ci_info['vs']
                dc_t    = dht[(0, cs['dc'])]
                ac_t    = dht[(1, cs['ac'])]
                is_y    = (ci == 0)

                for _ in range(hs * vs):
                    # DC coefficient
                    cat = _huff_decode(br, dc_t)
                    if cat > 0:
                        raw = br.read_bits(cat)
                        prev_dc[ci] += _vli_decode(cat, raw)

                    # AC coefficients
                    k = 1
                    while k < 64:
                        sym = _huff_decode(br, ac_t)
                        if sym == 0x00:
                            break                          # EOB
                        if sym == 0xF0:
                            k += 16; continue             # ZRL (16 zeros)
                        run = (sym >> 4) & 0xF
                        cat =  sym       & 0xF
                        k  += run
                        if cat > 0:
                            vli_start = br.lbit
                            br.read_bits(cat)
                            # cat >= 2 → safe to flip VLI LSB
                            if is_y and cat >= 2:
                                slots.append(vli_start + cat - 1)
                        k += 1

    return slots


def _jpeg_dct_capacity(carrier_path: str) -> int:
    """
    Return the maximum payload size in bytes for DCT embedding on this carrier.
    Accepts any image format Pillow supports — non-JPEG files are converted to
    JPEG in memory first (same as _embed_dct) so the capacity figure is accurate.
    Returns 0 if the carrier cannot be parsed as / converted to a valid JPEG.
    """
    try:
        raw         = _to_jpeg_bytes(carrier_path)
        dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
        logical, _  = _jpeg_destuff(raw, sos_off)
        slots       = _scan_safe_ac_positions(logical, dht, sof, comp_scan)
        return max(0, len(slots) // 8 - 10)
    except Exception:
        return 0


def _to_jpeg_bytes(carrier_path: str) -> bytearray:
    """
    Return the raw bytes of *carrier_path* as a JPEG bytearray.
    If the file is already a JPEG (magic bytes 0xFF 0xD8) it is read as-is.
    Any other format (PNG, BMP, TIFF, WebP, ...) is opened with Pillow,
    converted to RGB, and encoded to JPEG in-memory at quality 95.
    This lets the DCT method accept any image Pillow can open as a carrier.
    """
    with open(carrier_path, 'rb') as f:
        magic = f.read(2)

    if magic == b'\xff\xd8':
        # Already a JPEG — read raw bytes directly, no re-encoding
        with open(carrier_path, 'rb') as f:
            return bytearray(f.read())

    # Non-JPEG carrier — convert to JPEG in memory
    from io import BytesIO
    img = Image.open(carrier_path).convert('RGB')
    buf = BytesIO()
    img.save(buf, format='JPEG', quality=95, subsampling=0)
    buf.seek(0)
    return bytearray(buf.read())


def _embed_dct(carrier_path: str, data: bytes, out_path: str):
    """
    Embed *data* into the JPEG bitstream of *carrier_path* by patching
    the VLI LSBs of safe Y-channel AC coefficients.
    Output is a valid JPEG written to *out_path*.

    Carrier can be any image format Pillow supports (JPEG, PNG, BMP, ...).
    Non-JPEG carriers are automatically converted to JPEG at quality 95
    before embedding — the original carrier file is never modified.
    """
    raw = _to_jpeg_bytes(carrier_path)

    dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
    logical, scan_end = _jpeg_destuff(raw, sos_off)
    slots   = _scan_safe_ac_positions(logical, dht, sof, comp_scan)

    payload = _pack(data)
    bits    = [int(b) for b in _bits(payload)]

    if len(bits) > len(slots):
        raise ValueError(
            f'Payload too large for DCT method — need {len(bits)} coefficient '
            f'slots, carrier has {len(slots)} safe AC coefficients '
            f'({max(0, len(slots) // 8 - 10)} bytes capacity). '
            f'Use a higher-quality JPEG carrier, or switch to LSB / img_in_img.'
        )

    for i, bit in enumerate(bits):
        _set_lbit(logical, slots[i], bit)

    # Rebuild: header markers + restuffed scan + trailing markers (EOI etc.)
    new_scan = _jpeg_restuff(logical)
    new_raw  = raw[:sos_off] + bytes(new_scan) + raw[scan_end:]

    with open(out_path, 'wb') as f:
        f.write(new_raw)


def _extract_dct(stego_path: str) -> bytes:
    """
    Extract the payload embedded by _embed_dct from *stego_path*.
    Raises ValueError if no valid payload is found or the CRC fails.
    """
    with open(stego_path, 'rb') as f:
        raw = bytearray(f.read())

    dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
    logical, _ = _jpeg_destuff(raw, sos_off)
    slots      = _scan_safe_ac_positions(logical, dht, sof, comp_scan)

    magic_bits = _bits(_MAGIC)
    hdr_n      = len(magic_bits) + 32 + 32   # 16 magic + 32 length + 32 CRC = 80

    if len(slots) < hdr_n:
        raise ValueError(
            'Not enough safe AC coefficients to read header — '
            'file is not a DCT stego image or JPEG quality is too low.'
        )

    hdr = ''.join(str(_get_lbit(logical, slots[i])) for i in range(hdr_n))

    if hdr[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden DCT payload found (magic mismatch)')

    length     = int(hdr[len(magic_bits):len(magic_bits) + 32], 2)
    stored_crc = int(hdr[len(magic_bits) + 32:len(magic_bits) + 64], 2)

    if hdr_n + length * 8 > len(slots):
        raise ValueError(
            'Payload length field exceeds available coefficients — '
            'wrong extraction method or corrupted file.'
        )

    pbits   = ''.join(
        str(_get_lbit(logical, slots[hdr_n + i]))
        for i in range(length * 8)
    )
    payload = bytes(int(pbits[i:i+8], 2) for i in range(0, len(pbits), 8))

    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong extraction method or corrupted data')

    return payload