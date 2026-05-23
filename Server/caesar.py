def encrypt(data: bytes, key: str, **kw) -> bytes:
    shift = sum(ord(c) for c in key) % 256 if key else 13
    return bytes((b + shift) % 256 for b in data)
def decrypt(data: bytes, key: str, **kw) -> bytes:
    shift = sum(ord(c) for c in key) % 256 if key else 13
    return bytes((b - shift) % 256 for b in data)
