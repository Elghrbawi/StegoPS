import hashlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
def _key(k: str) -> bytes: return hashlib.sha256(k.encode()).digest()
def encrypt(data: bytes, key: str, **kw) -> bytes:
    c = AES.new(_key(key), AES.MODE_CBC)
    return c.iv + c.encrypt(pad(data, 16))
def decrypt(data: bytes, key: str, **kw) -> bytes:
    return unpad(AES.new(_key(key), AES.MODE_CBC, data[:16]).decrypt(data[16:]), 16)
