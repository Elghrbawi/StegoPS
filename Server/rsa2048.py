import hashlib
from Crypto.PublicKey import RSA
from Crypto.Cipher import PKCS1_OAEP, AES
from Crypto.Random import get_random_bytes
from Crypto.Util.Padding import pad, unpad
# ── KEY CACHE ─────────────────────────────────────────────────────────────────
_CACHE: dict = {}
class _DRNG:
    def __init__(self, seed):
        self._state = seed; self._buf = b''
    def read(self, n):
        while len(self._buf) < n:
            self._state = hashlib.sha256(self._state + b'\x00').digest()
            self._buf += self._state
        out, self._buf = self._buf[:n], self._buf[n:]
        return out
def _keypair(k: str) -> RSA.RsaKey:
    if k not in _CACHE:
        _CACHE[k] = RSA.generate(2048, randfunc=_DRNG(hashlib.sha256(k.encode()).digest()).read)
    return _CACHE[k]
# ── API ───────────────────────────────────────────────────────────────────────
def encrypt(data: bytes, key: str, **kw) -> bytes:
    rsa = _keypair(key)
    aes_key = get_random_bytes(32)
    enc_key = PKCS1_OAEP.new(rsa.publickey()).encrypt(aes_key)
    c = AES.new(aes_key, AES.MODE_CBC)
    return len(enc_key).to_bytes(4, 'big') + enc_key + c.iv + c.encrypt(pad(data, 16))
def decrypt(data: bytes, key: str, **kw) -> bytes:
    rsa = _keypair(key)
    n = int.from_bytes(data[:4], 'big')
    aes_key = PKCS1_OAEP.new(rsa).decrypt(data[4:4+n])
    return unpad(AES.new(aes_key, AES.MODE_CBC, data[4+n:4+n+16]).decrypt(data[4+n+16:]), 16)
