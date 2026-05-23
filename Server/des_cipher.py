import hashlib
from Crypto.Cipher import DES
from Crypto.Util.Padding import pad, unpad
def _key(k: str) -> bytes: return hashlib.md5(k.encode()).digest()[:8]
def _iv(k: str)  -> bytes: return hashlib.md5((k + '_iv').encode()).digest()[:8]
def encrypt(data: bytes, key: str, **kw) -> bytes:
    return DES.new(_key(key), DES.MODE_CBC, _iv(key)).encrypt(pad(data, DES.block_size))
def decrypt(data: bytes, key: str, **kw) -> bytes:
    return unpad(DES.new(_key(key), DES.MODE_CBC, _iv(key)).decrypt(data), DES.block_size)
