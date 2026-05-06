import hashlib, os
from Crypto.PublicKey import RSA
from Crypto.Cipher import PKCS1_OAEP, AES
from Crypto.Random import get_random_bytes
from Crypto.Util.Padding import pad, unpad

_KEY_CACHE: dict = {}

class _DeterministicRNG:
    def __init__(self, seed: bytes):
        self._state = seed
        self._buf = b''
    def read(self, n: int) -> bytes:
        while len(self._buf) < n:
            self._state = hashlib.sha256(self._state + b'\x00').digest()
            self._buf += self._state
        out, self._buf = self._buf[:n], self._buf[n:]
        return out

def _get_keypair(key_str: str) -> RSA.RsaKey:
    if key_str in _KEY_CACHE:
        return _KEY_CACHE[key_str]
    seed = hashlib.sha256(key_str.encode('utf-8')).digest()
    rsa_key = RSA.generate(2048, randfunc=_DeterministicRNG(seed).read)
    _KEY_CACHE[key_str] = rsa_key
    return rsa_key

def encrypt(data: bytes, key: str, **kw) -> bytes:
    rsa_key = _get_keypair(key)
    aes_key = get_random_bytes(32)
    cipher_rsa = PKCS1_OAEP.new(rsa_key.publickey())
    enc_aes_key = cipher_rsa.encrypt(aes_key)
    cipher_aes = AES.new(aes_key, AES.MODE_CBC)
    ct = cipher_aes.encrypt(pad(data, 16))
    enc_len = len(enc_aes_key).to_bytes(4, 'big')
    return enc_len + enc_aes_key + cipher_aes.iv + ct

def decrypt(data: bytes, key: str, **kw) -> bytes:
    rsa_key = _get_keypair(key)
    enc_len = int.from_bytes(data[:4], 'big')
    enc_aes_key = data[4:4 + enc_len]
    iv = data[4 + enc_len:4 + enc_len + 16]
    ct = data[4 + enc_len + 16:]
    cipher_rsa = PKCS1_OAEP.new(rsa_key)
    aes_key = cipher_rsa.decrypt(enc_aes_key)
    cipher_aes = AES.new(aes_key, AES.MODE_CBC, iv)
    return unpad(cipher_aes.decrypt(ct), 16)
