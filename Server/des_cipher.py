import hashlib
from Crypto.Cipher import DES
from Crypto.Util.Padding import pad, unpad

# DES — Data Encryption Standard (56-bit effective key, 8-byte block)
# Legacy cipher included for chain compatibility / educational purposes.

def _key(k: str) -> bytes:
    """Derive an 8-byte DES key from an arbitrary string."""
    return hashlib.md5(k.encode('utf-8')).digest()[:8]

def _iv(k: str) -> bytes:
    """Derive an 8-byte IV from the key."""
    return hashlib.md5((k + '_iv').encode('utf-8')).digest()[:8]

def encrypt(data: bytes, key: str, **kw) -> bytes:
    k  = _key(key)
    iv = _iv(key)
    cipher = DES.new(k, DES.MODE_CBC, iv)
    return cipher.encrypt(pad(data, DES.block_size))

def decrypt(data: bytes, key: str, **kw) -> bytes:
    k  = _key(key)
    iv = _iv(key)
    cipher = DES.new(k, DES.MODE_CBC, iv)
    return unpad(cipher.decrypt(data), DES.block_size)
