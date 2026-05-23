def _r(b):
    if 65 <= b <= 90:  return (b - 65 + 13) % 26 + 65
    if 97 <= b <= 122: return (b - 97 + 13) % 26 + 97
    return b
def encrypt(data: bytes, key: str = '', **kw) -> bytes: return bytes(_r(b) for b in data)
def decrypt(data: bytes, key: str = '', **kw) -> bytes: return bytes(_r(b) for b in data)
