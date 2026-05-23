import hashlib
from Crypto.Cipher import DES3
from Crypto.Util.Padding import pad, unpad
def _key(k: str) -> bytes:
    key24 = hashlib.sha256(k.encode()).digest()[:24]
    try: DES3.adjust_key_parity(key24)
    except Exception: pass
    return key24
def _iv(k: str) -> bytes: return hashlib.md5((k + '_3des_iv').encode()).digest()[:8]
def encrypt(data: bytes, key: str, **kw) -> bytes:
    return DES3.new(_key(key), DES3.MODE_CBC, _iv(key)).encrypt(pad(data, DES3.block_size))
def decrypt(data: bytes, key: str, **kw) -> bytes:
    return unpad(DES3.new(_key(key), DES3.MODE_CBC, _iv(key)).decrypt(data), DES3.block_size)
