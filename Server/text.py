import struct, zlib, hashlib

_MAGIC = b'\xD7\xA9'

def _pack(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _MAGIC + len(data).to_bytes(4, 'big') + crc + data

def _unpack(raw: bytes) -> bytes:
    if len(raw) < 10 or raw[:2] != _MAGIC: raise ValueError('No hidden text payload found (magic mismatch)')
    length     = int.from_bytes(raw[2:6], 'big')
    stored_crc = int.from_bytes(raw[6:10], 'big')
    payload    = raw[10:10 + length]
    if len(payload) < length: raise ValueError('Truncated payload — stego text may be clipped')
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc: raise ValueError('CRC mismatch — wrong key or corrupted payload')
    return payload

def _to_bits(data: bytes):
    return [int(b) for byte in data for b in format(byte, '08b')]

def _from_bits(bits) -> bytes:
    n = (len(bits) // 8) * 8
    return bytes(int(''.join(str(b) for b in bits[i:i+8]), 2) for i in range(0, n, 8))

def _keystream(key: str, length: int) -> bytes:
    if not key: return bytes(length)
    buf = bytearray(); i = 0; kb = key.encode()
    while len(buf) < length:
        buf += hashlib.sha256(kb + i.to_bytes(4, 'big')).digest(); i += 1
    return bytes(buf[:length])

def _xor(data: bytes, key: str) -> bytes:
    if not key: return data
    ks = _keystream(key, len(data))
    return bytes(a ^ b for a, b in zip(data, ks))

def embed(cover_text: str, payload: bytes, key: str, method: str) -> str:
    packed = _pack(_xor(payload, key))
    if   method == 'zwsp':        return _embed_zwsp(cover_text, packed)
    elif method == 'homoglyph':   return _embed_homoglyph(cover_text, packed)
    elif method == 'unicode_tag': return _embed_unicode_tag(cover_text, packed)
    elif method == 'whitespace':  return _embed_whitespace(cover_text, packed)
    elif method == 'combining':   return _embed_combining(cover_text, packed)
    raise ValueError(f'Unknown text steg method: {method}')

def extract(stego_text: str, key: str, method: str) -> bytes:
    if   method == 'zwsp':        raw = _extract_zwsp(stego_text)
    elif method == 'homoglyph':   raw = _extract_homoglyph(stego_text)
    elif method == 'unicode_tag': raw = _extract_unicode_tag(stego_text)
    elif method == 'whitespace':  raw = _extract_whitespace(stego_text)
    elif method == 'combining':   raw = _extract_combining(stego_text)
    else: raise ValueError(f'Unknown text steg method: {method}')
    return _xor(_unpack(raw), key)

def capacity_bytes(cover_text: str, method: str) -> int:
    if method == 'zwsp':        return max(0, max(0, len(cover_text.split(' ')) - 1) // 8 - 10)
    if method == 'homoglyph':   return max(0, sum(1 for c in cover_text if c in _HOMO_MAP) // 8 - 10)
    if method == 'unicode_tag': return max(0, len(cover_text) * 4 - 10)
    if method == 'whitespace':  return max(0, cover_text.count(' ') // 8 - 10)
    if method == 'combining':   return max(0, sum(1 for c in cover_text if c.isalpha()) // 8 - 10)
    return 0

_ZW0 = '\u200B'
_ZW1 = '\u200C'
_ZWB = '\u200D'

def _embed_zwsp(text: str, data: bytes) -> str:
    words = text.split(' ')
    bits  = _to_bits(data)
    gaps  = len(words) - 1
    if len(bits) > gaps:
        raise ValueError(f'Cover text too short for ZWSP steg — need {len(bits)} inter-word positions, have {gaps}. Add more text or reduce payload.')
    result = []; bit_idx = 0
    for i, word in enumerate(words):
        result.append(word)
        if i < len(words) - 1:
            if bit_idx < len(bits):
                result.append(_ZW1 if bits[bit_idx] else _ZW0)
                bit_idx += 1
                if bit_idx % 8 == 0 and bit_idx < len(bits): result.append(_ZWB)
            result.append(' ')
    return ''.join(result)

def _extract_zwsp(text: str) -> bytes:
    bits = []
    for c in text:
        if   c == _ZW0: bits.append(0)
        elif c == _ZW1: bits.append(1)
    if len(bits) < 80: raise ValueError('ZWSP: no payload found — insufficient zero-width characters')
    return _from_bits(bits)

_HOMO_MAP = {
    'a': '\u0430', 'e': '\u0435', 'o': '\u043E', 'p': '\u0440', 'c': '\u0441',
    'x': '\u0445', 'y': '\u0443', 'i': '\u0456', 'A': '\u0410', 'B': '\u0412',
    'E': '\u0415', 'H': '\u041D', 'K': '\u041A', 'M': '\u041C', 'O': '\u041E',
    'P': '\u0420', 'T': '\u0422', 'X': '\u0425', 'C': '\u0421', 'I': '\u0406',
}
_HOMO_REV = {v: k for k, v in _HOMO_MAP.items()}

def _embed_homoglyph(text: str, data: bytes) -> str:
    bits = _to_bits(data)
    subs = [i for i, c in enumerate(text) if c in _HOMO_MAP]
    if len(bits) > len(subs):
        raise ValueError(f'Homoglyph: cover text has only {len(subs)} substitutable chars, need {len(bits)}. Use longer text or reduce payload.')
    embed_at = set(subs[:len(bits)])
    result = []; bit_idx = 0
    for i, c in enumerate(text):
        if i in embed_at and bit_idx < len(bits):
            result.append(_HOMO_MAP[c] if bits[bit_idx] else c); bit_idx += 1
        else: result.append(c)
    return ''.join(result)

def _extract_homoglyph(text: str) -> bytes:
    bits = []
    for c in text:
        if c in _HOMO_MAP:   bits.append(0)
        elif c in _HOMO_REV: bits.append(1)
    if len(bits) < 80: raise ValueError('Homoglyph: no payload found — text has too few substitutable characters')
    return _from_bits(bits)

_TAG_BASE  = 0xE0000
_TAG_FENCE = '\uFEFF'

def _embed_unicode_tag(text: str, data: bytes) -> str:
    tags = ''.join(chr(_TAG_BASE + b) for b in data)
    return text + _TAG_FENCE + tags + _TAG_FENCE

def _extract_unicode_tag(text: str) -> bytes:
    result = bytearray(); inside = False
    for c in text:
        if c == _TAG_FENCE:
            if inside: break
            inside = True; continue
        if inside and (_TAG_BASE + 1) <= ord(c) <= (_TAG_BASE + 0xFF): result.append(ord(c) - _TAG_BASE)
    if not result:
        for c in text:
            if (_TAG_BASE + 1) <= ord(c) <= (_TAG_BASE + 0xFF): result.append(ord(c) - _TAG_BASE)
    if len(result) < 10: raise ValueError('Unicode tag: no payload found in stego text')
    return bytes(result)

_SP  = '\u0020'
_NBS = '\u00A0'

def _embed_whitespace(text: str, data: bytes) -> str:
    bits   = _to_bits(data)
    spaces = [i for i, c in enumerate(text) if c == _SP]
    if len(bits) > len(spaces): raise ValueError(f'Whitespace steg: need {len(bits)} spaces, cover text has {len(spaces)}.')
    sub = {spaces[i]: bits[i] for i in range(len(bits))}
    return ''.join((_NBS if sub.get(i) else c) if c == _SP else c for i, c in enumerate(text))

def _extract_whitespace(text: str) -> bytes:
    bits = [1 if c == _NBS else 0 if c == _SP else None for c in text]
    bits = [b for b in bits if b is not None]
    if len(bits) < 80: raise ValueError('Whitespace steg: no payload found — text has too few spaces')
    return _from_bits(bits)

_CGJ = '\u034F'
_SHY = '\u00AD'

def _embed_combining(text: str, data: bytes) -> str:
    bits  = _to_bits(data)
    alpha = [i for i, c in enumerate(text) if c.isalpha()]
    if len(bits) > len(alpha): raise ValueError(f'Combining steg: need {len(bits)} alpha positions, cover text has {len(alpha)}.')
    embed_map = {alpha[i]: bits[i] for i in range(len(bits))}
    result = []
    for i, c in enumerate(text):
        result.append(c)
        if i in embed_map: result.append(_SHY if embed_map[i] else _CGJ)
    return ''.join(result)

def _extract_combining(text: str) -> bytes:
    bits = []; prev_alpha = False
    for c in text:
        if prev_alpha:
            if c == _CGJ: bits.append(0); prev_alpha = False; continue
            elif c == _SHY: bits.append(1); prev_alpha = False; continue
        prev_alpha = c.isalpha()
    if len(bits) < 80: raise ValueError('Combining steg: no payload found')
    return _from_bits(bits)
