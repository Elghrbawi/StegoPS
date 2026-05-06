import hashlib
from PIL import Image

_MAGIC = b'\xDE\xAD'

def _bits(data: bytes) -> str:
    return ''.join(format(b, '08b') for b in data)

def _pack(data: bytes) -> bytes:
    return _MAGIC + len(data).to_bytes(4, 'big') + data

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

def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
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

def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'lsb':
        return _extract_lsb(stego_path)
    elif method == 'xor_pixel':
        return _extract_xor(stego_path, key)
    elif method == 'alpha':
        return _extract_alpha(stego_path)
    elif method == 'img_in_img':
        return _extract_img_in_img(stego_path)
    else:
        raise ValueError(f'Unknown image steg method: {method}')

def _read_payload_from_bits(bits: list, bpc: int = 1) -> bytes:
    magic_bits = _bits(_MAGIC)
    needed = len(magic_bits) + 32
    header = ''.join(str(b) for b in bits[:needed])
    if header[:len(magic_bits)] != magic_bits:
        raise ValueError('No hidden payload found (magic mismatch)')
    length = int(header[len(magic_bits):len(magic_bits)+32], 2)
    total_bits = needed + length * 8
    if total_bits > len(bits):
        raise ValueError('Payload length exceeds available data')
    payload_bits = ''.join(str(b) for b in bits[needed:needed + length * 8])
    return bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))

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
    magic_and_len = len(_MAGIC) + 4
    raw_bits = [b & 1 for b in flat[:magic_and_len * 8]]
    raw_bytes = bytes(int(''.join(str(b) for b in raw_bits[i:i+8]), 2) for i in range(0, len(raw_bits), 8))
    ks_head = _keystream(key, magic_and_len)
    header = bytes(a ^ b for a, b in zip(raw_bytes, ks_head))
    if header[:2] != _MAGIC:
        raise ValueError('No hidden payload found (magic mismatch)')
    length = int.from_bytes(header[2:6], 'big')
    total = magic_and_len + length
    all_raw_bits = [b & 1 for b in flat[:total * 8]]
    all_raw = bytes(int(''.join(str(b) for b in all_raw_bits[i:i+8]), 2) for i in range(0, len(all_raw_bits), 8))
    ks = _keystream(key, total)
    plain = bytes(a ^ b for a, b in zip(all_raw, ks))
    return plain[magic_and_len:]

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