import hashlib
from Crypto.Cipher import ChaCha20

def _key(k: str) -> bytes:
    return hashlib.sha256(k.encode('utf-8')).digest()

def encrypt(data: bytes, key: str, **kw) -> bytes:
    cipher = ChaCha20.new(key=_key(key))
    return cipher.nonce + cipher.encrypt(data)

def decrypt(data: bytes, key: str, **kw) -> bytes:
    nonce, ct = data[:8], data[8:]
    cipher = ChaCha20.new(key=_key(key), nonce=nonce)
    return cipher.decrypt(ct)