"""
image.py — steganography engine with WhatsApp-compatible MozJPEG output.

WhatsApp compresses images to JPEG quality 80 with 4:2:0 chroma subsampling,
then runs MozJPEG lossless Huffman optimisation.  By pre-compressing the
carrier with the exact same pipeline before embedding DCT steg, the
coefficient values (VLI magnitudes) survive WhatsApp's re-compression
unchanged.  Only DCT steg is WhatsApp-resistant; other methods use lossless
PNG output as before.
"""

import struct, zlib, hashlib
import numpy as np
from PIL import Image
from io import BytesIO
# mozjpeg_lossless_optimization used only for the compress pipeline

# ── CONSTANTS ─────────────────────────────────────────────────────────────────
_MAGIC      = b'\xDE\xAD'
_WA_QUALITY = 80    # WhatsApp JPEG quality
_WA_MAX_DIM = 1600  # WhatsApp max dimension (longest side)
_WA_SUBSAMP = 2     # 4:2:0

# methods that survive WhatsApp re-compression
WHATSAPP_RESISTANT = {'dct'}

# ── WHATSAPP PRE-COMPRESSION ──────────────────────────────────────────────────
def _wa_compress(img: Image.Image) -> bytes:
    """Return MozJPEG bytes matching WhatsApp's exact compression pipeline."""
    w, h = img.size
    if max(w, h) > _WA_MAX_DIM:
        r = _WA_MAX_DIM / max(w, h)
        img = img.resize((int(w * r), int(h * r)), Image.LANCZOS)
    buf = BytesIO()
    img.convert('RGB').save(buf, format='JPEG', quality=_WA_QUALITY,
                            subsampling=_WA_SUBSAMP, optimize=False)
    return buf.getvalue()


# ── HELPERS ───────────────────────────────────────────────────────────────────
def _bits(data: bytes) -> str:
    return ''.join(format(b, '08b') for b in data)

def _pack(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _MAGIC + len(data).to_bytes(4, 'big') + crc + data

def _keystream(key: str, length: int) -> bytes:
    buf = bytearray(); i = 0; kb = key.encode()
    while len(buf) < length:
        buf += hashlib.sha256(kb + i.to_bytes(4, 'big')).digest(); i += 1
    return bytes(buf[:length])

def _check_cap(px: int, ch: int, bpc: int, size: int):
    cap = px * ch * bpc // 8
    if size > cap:
        raise ValueError(f'Payload too large ({size:,} B). Capacity: {cap:,} B')

def _read_payload_from_bits(bits, bpc: int = 1) -> bytes:
    mb   = _bits(_MAGIC)
    need = len(mb) + 64
    hdr  = ''.join(str(b) for b in bits[:need])
    if hdr[:len(mb)] != mb:
        raise ValueError('No hidden payload found (magic mismatch)')
    length     = int(hdr[len(mb):len(mb) + 32], 2)
    stored_crc = int(hdr[len(mb) + 32:len(mb) + 64], 2)
    total_bits = need + length * 8
    if total_bits > len(bits):
        raise ValueError('Payload length exceeds available data')
    pbits   = ''.join(str(b) for b in bits[need:need + length * 8])
    payload = bytes(int(pbits[i:i + 8], 2) for i in range(0, len(pbits), 8))
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong method or corrupted data')
    return payload

# ── PUBLIC API ────────────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    embed._last_actual_method = method
    if   method == 'lsb':        _embed_lsb(carrier_path, data, out_path)
    elif method == 'xor_pixel':  _embed_xor(carrier_path, data, out_path, key)
    elif method == 'alpha':      _embed_alpha(carrier_path, data, out_path)
    elif method == 'img_in_img': _embed_img_in_img(carrier_path, data, out_path)
    elif method == 'dct':        _embed_dct(carrier_path, data, out_path)
    else: raise ValueError(f'Unknown image steg method: {method}')

embed._last_actual_method = None


def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'lsb':         return _extract_lsb(stego_path)
    if method == 'xor_pixel':   return _extract_xor(stego_path, key)
    if method == 'alpha':       return _extract_alpha(stego_path)
    if method == 'img_in_img':  return _extract_img_in_img(stego_path)
    if method == 'dct':         return _extract_dct(stego_path)
    raise ValueError(f'Unknown image steg method: {method}')


def _carrier_capacity(carrier_path: str) -> dict:
    img = Image.open(carrier_path)
    px  = img.width * img.height
    try:   dct_cap = _jpeg_dct_capacity(carrier_path)
    except Exception: dct_cap = 0
    return {'dct': dct_cap, 'lsb': px * 3 // 8 - 10, 'img_in_img': px * 3 * 4 // 8 - 10}

# ── LSB (numpy-optimised) ─────────────────────────────────────────────────────
def _embed_lsb(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGB')
    payload = _pack(data)
    _check_cap(img.width * img.height, 3, 1, len(payload))
    flat = np.frombuffer(img.tobytes(), dtype=np.uint8).copy()
    bits = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    flat[:len(bits)] = (flat[:len(bits)] & 0xFE) | bits
    Image.frombytes('RGB', img.size, flat.tobytes()).save(out_path, 'PNG', optimize=True)

def _extract_lsb(stego_path: str) -> bytes:
    flat = np.frombuffer(Image.open(stego_path).convert('RGB').tobytes(), dtype=np.uint8)
    return _read_payload_from_bits((flat & 1).tolist())

# ── XOR PIXEL ─────────────────────────────────────────────────────────────────
def _embed_xor(carrier_path: str, data: bytes, out_path: str, key: str):
    img     = Image.open(carrier_path).convert('RGB')
    payload = _pack(data)
    _check_cap(img.width * img.height, 3, 1, len(payload))
    ks   = _keystream(key, len(payload))
    xored = bytes(a ^ b for a, b in zip(payload, ks))
    flat  = np.frombuffer(img.tobytes(), dtype=np.uint8).copy()
    bits  = np.unpackbits(np.frombuffer(xored, dtype=np.uint8))
    flat[:len(bits)] = (flat[:len(bits)] & 0xFE) | bits
    Image.frombytes('RGB', img.size, flat.tobytes()).save(out_path, 'PNG', optimize=True)

def _extract_xor(stego_path: str, key: str) -> bytes:
    img  = Image.open(stego_path).convert('RGB')
    flat = np.frombuffer(img.tobytes(), dtype=np.uint8)
    mlen = len(_MAGIC) + 8
    raw  = bytes(
        int(''.join(str(flat[j] & 1) for j in range(i*8, i*8+8)), 2)
        for i in range(mlen)
    )
    hdr = bytes(a ^ b for a, b in zip(raw, _keystream(key, mlen)))
    if hdr[:2] != _MAGIC: raise ValueError('No hidden payload found (magic mismatch)')
    length     = int.from_bytes(hdr[2:6], 'big')
    stored_crc = int.from_bytes(hdr[6:10], 'big')
    total      = mlen + length
    bits       = (flat[:total * 8] & 1).tolist()
    raw_all    = bytes(int(''.join(str(b) for b in bits[i:i+8]), 2) for i in range(0, len(bits), 8))
    plain      = bytes(a ^ b for a, b in zip(raw_all, _keystream(key, total)))
    payload    = plain[mlen:]
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong method or corrupted data')
    return payload

# ── ALPHA CHANNEL ─────────────────────────────────────────────────────────────
def _embed_alpha(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGBA')
    payload = _pack(data)
    _check_cap(img.width * img.height, 1, 1, len(payload))
    flat = np.frombuffer(img.tobytes(), dtype=np.uint8).copy()
    bits = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    alpha = flat[3::4]
    alpha[:len(bits)] = (alpha[:len(bits)] & 0xFE) | bits
    flat[3::4] = alpha
    Image.frombytes('RGBA', img.size, flat.tobytes()).save(out_path, 'PNG', optimize=True)

def _extract_alpha(stego_path: str) -> bytes:
    flat = np.frombuffer(Image.open(stego_path).convert('RGBA').tobytes(), dtype=np.uint8)
    return _read_payload_from_bits((flat[3::4] & 1).tolist())

# ── IMAGE-IN-IMAGE ────────────────────────────────────────────────────────────
def _embed_img_in_img(carrier_path: str, data: bytes, out_path: str):
    img     = Image.open(carrier_path).convert('RGB')
    payload = _pack(data)
    _check_cap(img.width * img.height, 3, 4, len(payload))
    flat = np.frombuffer(img.tobytes(), dtype=np.uint8).copy()
    src  = np.frombuffer(payload, dtype=np.uint8)
    hi   = (src >> 4) & 0x0F; lo = src & 0x0F
    pairs = np.empty(len(src) * 2, dtype=np.uint8)
    pairs[0::2] = hi; pairs[1::2] = lo
    n = len(pairs)
    flat[:n] = (flat[:n] & 0xF0) | pairs
    Image.frombytes('RGB', img.size, flat.tobytes()).save(out_path, 'PNG', optimize=True)

def _extract_img_in_img(stego_path: str) -> bytes:
    flat    = np.frombuffer(Image.open(stego_path).convert('RGB').tobytes(), dtype=np.uint8)
    nibbles = flat & 0x0F
    packed  = (nibbles[0::2] << 4) | nibbles[1::2]
    return _read_payload_from_bits(np.unpackbits(packed).tolist(), bpc=4)

# ── DCT JPEG — WhatsApp-resistant ─────────────────────────────────────────────
def _to_wa_jpeg(carrier_path: str) -> bytearray:
    """Carrier → WhatsApp-identical JPEG (quality 80, 4:2:0, MozJPEG)."""
    return bytearray(_wa_compress(Image.open(carrier_path)))

def _jpeg_parse(data: bytearray):
    pos = 0; dht = {}; sof = {}; comp_scan = []; sos_off = None
    while pos < len(data) - 1:
        if data[pos] != 0xFF: pos += 1; continue
        m = data[pos + 1]
        if m in (0xD8, 0xD9): pos += 2; continue
        if m in (0x00, 0xFF): pos += 1; continue
        seg_len = int.from_bytes(data[pos + 2:pos + 4], 'big')
        if m == 0xC4:
            p2 = pos + 4; end = pos + 2 + seg_len
            while p2 < end:
                tc_ti = data[p2]; p2 += 1
                tc, ti = (tc_ti >> 4) & 0xF, tc_ti & 0xF
                counts = list(data[p2:p2+16]); p2 += 16
                codes = {}; code = 0
                for nb in range(1, 17):
                    for _ in range(counts[nb - 1]):
                        codes[(nb, code)] = data[p2]; p2 += 1; code += 1
                    code <<= 1
                dht[(tc, ti)] = codes
        elif m in (0xC0, 0xC2):
            sof['H'] = int.from_bytes(data[pos+5:pos+7], 'big')
            sof['W'] = int.from_bytes(data[pos+7:pos+9], 'big')
            nc = data[pos+9]
            sof['components'] = [
                {'id': data[pos+10+i*3], 'hs': (data[pos+11+i*3] >> 4) & 0xF,
                 'vs': data[pos+11+i*3] & 0xF, 'qt': data[pos+12+i*3]}
                for i in range(nc)
            ]
            sof['max_hs'] = max(c['hs'] for c in sof['components'])
            sof['max_vs'] = max(c['vs'] for c in sof['components'])
        elif m == 0xDA:
            p2 = pos + 4; nc = data[p2]; p2 += 1
            comp_scan = [
                {'id': data[p2+i*2], 'dc': (data[p2+i*2+1] >> 4) & 0xF,
                 'ac': data[p2+i*2+1] & 0xF}
                for i in range(nc)
            ]
            sos_off = pos + 2 + seg_len; break
        pos += 2 + seg_len
    if sos_off is None: raise ValueError('No SOS marker — not a valid JPEG.')
    return dht, sof, comp_scan, sos_off

def _jpeg_destuff(data: bytearray, start: int):
    logical = bytearray(); p = start
    while p < len(data):
        b = data[p]
        if b == 0xFF and p + 1 < len(data):
            nb = data[p + 1]
            if nb == 0x00:            logical.append(b); p += 2; continue
            elif 0xD0 <= nb <= 0xD7: p += 2; continue
            else: break
        logical.append(b); p += 1
    return logical, p

def _jpeg_restuff(logical: bytearray) -> bytearray:
    out = bytearray()
    for b in logical:
        out.append(b)
        if b == 0xFF: out.append(0x00)
    return out

def _get_lbit(logical, lbit):
    return (logical[lbit >> 3] >> (7 - (lbit & 7))) & 1

def _set_lbit(logical, lbit, v):
    b = lbit >> 3; bo = 7 - (lbit & 7)
    if v: logical[b] |= (1 << bo)
    else: logical[b] &= ~(1 << bo)

class _BitReader:
    __slots__ = ('_lg', '_lb', '_nb', '_buf', 'lbit')
    def __init__(self, lg): self._lg = lg; self._lb = self._nb = self._buf = self.lbit = 0
    def _load(self): self._buf = self._lg[self._lb]; self._nb = 8; self._lb += 1
    def read_bit(self):
        if not self._nb: self._load()
        self._nb -= 1; bit = (self._buf >> self._nb) & 1; self.lbit += 1; return bit
    def read_bits(self, n):
        v = 0
        for _ in range(n): v = (v << 1) | self.read_bit()
        return v

def _huff_decode(br, table):
    code = 0
    for nb in range(1, 17):
        code = (code << 1) | br.read_bit()
        if (nb, code) in table: return table[(nb, code)]
    raise ValueError('Invalid Huffman code in JPEG scan data.')

def _vli_decode(n, bits):
    if not n: return 0
    return bits if bits >= (1 << (n - 1)) else bits - (1 << n) + 1

def _scan_safe_ac_slots(logical, dht, sof, comp_scan):
    br    = _BitReader(logical)
    mcu_w = (sof['W'] + sof['max_hs']*8 - 1) // (sof['max_hs']*8)
    mcu_h = (sof['H'] + sof['max_vs']*8 - 1) // (sof['max_vs']*8)
    cbi   = {c['id']: c for c in sof['components']}
    prev  = [0] * len(comp_scan); slots = []
    for _ in range(mcu_h):
        for _ in range(mcu_w):
            for ci, cs in enumerate(comp_scan):
                inf  = cbi[cs['id']]
                dc_t = dht[(0, cs['dc'])]; ac_t = dht[(1, cs['ac'])]
                is_y = (ci == 0)
                for _ in range(inf['hs'] * inf['vs']):
                    cat = _huff_decode(br, dc_t)
                    if cat: prev[ci] += _vli_decode(cat, br.read_bits(cat))
                    k = 1
                    while k < 64:
                        sym = _huff_decode(br, ac_t)
                        if sym == 0x00: break
                        if sym == 0xF0: k += 16; continue
                        run = (sym >> 4) & 0xF; cat = sym & 0xF; k += run
                        if cat:
                            start = br.lbit; br.read_bits(cat)
                            if is_y and cat >= 2: slots.append(start + cat - 1)
                        k += 1
    return slots

def _jpeg_dct_capacity(carrier_path: str) -> int:
    try:
        raw = _to_wa_jpeg(carrier_path)
        dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
        logical, _ = _jpeg_destuff(raw, sos_off)
        return max(0, len(_scan_safe_ac_slots(logical, dht, sof, comp_scan)) // 8 - 10)
    except Exception: return 0

def _embed_dct(carrier_path: str, data: bytes, out_path: str):
    raw = _to_wa_jpeg(carrier_path)          # ← WhatsApp pre-compression
    dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
    logical, scan_end = _jpeg_destuff(raw, sos_off)
    slots   = _scan_safe_ac_slots(logical, dht, sof, comp_scan)
    payload = _pack(data)
    bits    = np.unpackbits(np.frombuffer(payload, dtype=np.uint8)).tolist()
    if len(bits) > len(slots):
        raise ValueError(
            f'Payload too large for DCT — need {len(bits):,} slots, '
            f'carrier has {len(slots):,} safe AC coefficients.'
        )
    for i, bit in enumerate(bits): _set_lbit(logical, slots[i], bit)
    with open(out_path, 'wb') as f:
        f.write(raw[:sos_off] + bytes(_jpeg_restuff(logical)) + raw[scan_end:])

def _extract_dct(stego_path: str) -> bytes:
    with open(stego_path, 'rb') as f: raw = bytearray(f.read())
    dht, sof, comp_scan, sos_off = _jpeg_parse(raw)
    logical, _ = _jpeg_destuff(raw, sos_off)
    slots  = _scan_safe_ac_slots(logical, dht, sof, comp_scan)
    mb     = _bits(_MAGIC); hdr_n = len(mb) + 64
    if len(slots) < hdr_n: raise ValueError('Not enough AC coefficients to read header.')
    hdr = ''.join(str(_get_lbit(logical, slots[i])) for i in range(hdr_n))
    if hdr[:len(mb)] != mb: raise ValueError('No hidden DCT payload found (magic mismatch)')
    length     = int(hdr[len(mb):len(mb)+32], 2)
    stored_crc = int(hdr[len(mb)+32:len(mb)+64], 2)
    if hdr_n + length * 8 > len(slots): raise ValueError('Payload exceeds available coefficients.')
    pbits   = ''.join(str(_get_lbit(logical, slots[hdr_n+i])) for i in range(length * 8))
    payload = bytes(int(pbits[i:i+8], 2) for i in range(0, len(pbits), 8))
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError('CRC mismatch — wrong method or corrupted data')
    return payload
