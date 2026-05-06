import hashlib

# NETRUNNER — Red-Channel Keystream Cipher
# Replace this implementation with your own NETRUNNER code.
# The interface must expose: encrypt(data: bytes, key: str) -> bytes
#                           decrypt(data: bytes, key: str) -> bytes

def _red_keystream(key: str, length: int) -> bytes:
    # Simulates extracting the "red channel" from a key-derived color sequence.
    # Each SHA-256 block is treated as 32 pixels; only the red bytes (index 0,3,6...)
    # are used to form the keystream — mirroring red-channel extraction in RGB images.
    stream = bytearray()
    counter = 0
    key_bytes = key.encode('utf-8')
    red_marker = b'\xff\x00\x00'  # red channel seed marker
    while len(stream) < length:
        block = hashlib.sha256(key_bytes + red_marker + counter.to_bytes(4, 'big')).digest()
        stream.extend(block[i] for i in range(0, len(block), 3))
        counter += 1
    return bytes(stream[:length])

def encrypt(data: bytes, key: str, **kw) -> bytes:
    ks = _red_keystream(key, len(data))
    return bytes(a ^ b for a, b in zip(data, ks))

def decrypt(data: bytes, key: str, **kw) -> bytes:
    return encrypt(data, key)
