import hashlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

def _key(k: str) -> bytes:
    return hashlib.sha256(k.encode('utf-8')).digest()

def encrypt(data: bytes, key: str, **kw) -> bytes:
    cipher = AES.new(_key(key), AES.MODE_CBC)
    return cipher.iv + cipher.encrypt(pad(data, 16))

def decrypt(data: bytes, key: str, **kw) -> bytes:
    iv, ct = data[:16], data[16:]
    cipher = AES.new(_key(key), AES.MODE_CBC, iv)
    return unpad(cipher.decrypt(ct), 16)