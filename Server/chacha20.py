import hashlib
from Crypto.Cipher import ChaCha20
def _key(k: str) -> bytes: return hashlib.sha256(k.encode()).digest()
def encrypt(data: bytes, key: str, **kw) -> bytes:
    c = ChaCha20.new(key=_key(key))
    return c.nonce + c.encrypt(data)
def decrypt(data: bytes, key: str, **kw) -> bytes:
    return ChaCha20.new(key=_key(key), nonce=data[:8]).decrypt(data[8:])
