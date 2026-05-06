import hashlib
from Crypto.Cipher import DES3
from Crypto.Util.Padding import pad, unpad

# Triple-DES (3DES / TDEA) — three successive DES operations.
# Uses a 24-byte (192-bit) key, giving 112-bit effective security.

def _key(k: str) -> bytes:
    """Derive a 24-byte 3DES key from an arbitrary string."""
    raw = hashlib.sha256(k.encode('utf-8')).digest()  # 32 bytes
    # DES3 requires a key where the three 8-byte sub-keys are not all equal
    # Use first 24 bytes (the SHA-256 digest guarantees variance)
    key24 = raw[:24]
    # pycryptodome will raise ValueError if sub-keys are identical; guard it
    try:
        DES3.adjust_key_parity(key24)
    except Exception:
        pass
    return key24

def _iv(k: str) -> bytes:
    """Derive an 8-byte IV from the key."""
    return hashlib.md5((k + '_3des_iv').encode('utf-8')).digest()[:8]

def encrypt(data: bytes, key: str, **kw) -> bytes:
    k  = _key(key)
    iv = _iv(key)
    cipher = DES3.new(k, DES3.MODE_CBC, iv)
    return cipher.encrypt(pad(data, DES3.block_size))

def decrypt(data: bytes, key: str, **kw) -> bytes:
    k  = _key(key)
    iv = _iv(key)
    cipher = DES3.new(k, DES3.MODE_CBC, iv)
    return unpad(cipher.decrypt(data), DES3.block_size)
