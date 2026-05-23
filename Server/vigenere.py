def encrypt(data: bytes, key: str, **kw) -> bytes:
    if not key: return data
    k = key.encode('utf-8')
    return bytes((b + k[i % len(k)]) % 256 for i, b in enumerate(data))
def decrypt(data: bytes, key: str, **kw) -> bytes:
    if not key: return data
    k = key.encode('utf-8')
    return bytes((b - k[i % len(k)]) % 256 for i, b in enumerate(data))
