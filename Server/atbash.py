def encrypt(data: bytes, key: str = '', **kw) -> bytes: return bytes(255 - b for b in data)
def decrypt(data: bytes, key: str = '', **kw) -> bytes: return bytes(255 - b for b in data)
