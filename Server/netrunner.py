# ── NETRUNNER CIPHER ─────────────────────────────────────────────────────────
class NetrunnerCipher:
    def __init__(self):
        self.CYBER_MASK = 0xF0
    def layer1_encrypt(self, values):
        n = len(values); shift = n % 26
        return [((v + shift) % 255 + i * shift) % 255 for i, v in enumerate(values)]
    def layer2_encrypt(self, values):
        return [(int(f"{v:08b}"[::-1], 2) + i) % 255 for i, v in enumerate(values)]
    def layer3_encrypt(self, values):
        return bytes(((v ^ ((self.CYBER_MASK + i) % 256)) + i) % 256 for i, v in enumerate(values))
    def layer1_decrypt(self, values):
        n = len(values); shift = n % 26
        return [((v % 255 - i * shift) % 255 - shift) % 255 for i, v in enumerate(values)]
    def layer2_decrypt(self, values):
        return [(int(f"{(v % 255 - i) % 255:08b}"[::-1], 2) % 255) for i, v in enumerate(values)]
    def layer3_decrypt(self, data):
        return [((b - i) % 256) ^ ((self.CYBER_MASK + i) % 256) for i, b in enumerate(data)]
    def encrypt_bytes(self, data):
        return self.layer3_encrypt(self.layer2_encrypt(self.layer1_encrypt(list(data))))
    def decrypt_bytes(self, data):
        return bytes(self.layer1_decrypt(self.layer2_decrypt(self.layer3_decrypt(data))))
# ── MODULE API ────────────────────────────────────────────────────────────────
_NR = NetrunnerCipher()
def encrypt(data: bytes, key: str, **kw) -> bytes:
    return _NR.encrypt_bytes(data)
def decrypt(data: bytes, key: str, **kw) -> bytes:
    return _NR.decrypt_bytes(data)
