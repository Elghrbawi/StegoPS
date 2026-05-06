"""NETRUNNER cipher module for chain_engine controller.

Exposes controller-compatible API:
    encrypt(data: bytes, key: str, **kw) -> bytes
    decrypt(data: bytes, key: str, **kw) -> bytes
"""


class NetrunnerCipher:
    def __init__(self) -> None:
        self.CYBER_MASK = 0xF0

    def layer1_encrypt(self, values: list[int]) -> list[int]:
        length = len(values)
        shift = length % 26
        out: list[int] = []
        for i, val in enumerate(values):
            shifted = (val + shift) % 256
            scrambled = (shifted + (i * shift)) % 256
            out.append(scrambled)
        return out

    def layer2_encrypt(self, values: list[int]) -> list[int]:
        out: list[int] = []
        for i, val in enumerate(values):
            reversed_bits = int(f"{val:08b}"[::-1], 2)
            shifted = (reversed_bits + i) % 256
            out.append(shifted)
        return out

    def layer3_encrypt(self, values: list[int]) -> bytes:
        out: list[int] = []
        for i, val in enumerate(values):
            dynamic_mask = (self.CYBER_MASK + i) % 256
            xored = val ^ dynamic_mask
            substituted = (xored + i) % 256
            out.append(substituted)
        return bytes(out)

    def layer3_decrypt(self, data: bytes) -> list[int]:
        out: list[int] = []
        for i, b in enumerate(data):
            unsubstituted = (b - i) % 256
            dynamic_mask = (self.CYBER_MASK + i) % 256
            unxored = unsubstituted ^ dynamic_mask
            out.append(unxored)
        return out

    def layer2_decrypt(self, values: list[int]) -> list[int]:
        out: list[int] = []
        for i, val in enumerate(values):
            unshifted = (val - i) % 256
            unreversed = int(f"{unshifted:08b}"[::-1], 2)
            out.append(unreversed)
        return out

    def layer1_decrypt(self, values: list[int]) -> list[int]:
        length = len(values)
        shift = length % 26
        out: list[int] = []
        for i, val in enumerate(values):
            unscrambled = (val - (i * shift)) % 256
            unshifted = (unscrambled - shift) % 256
            out.append(unshifted)
        return out

    def encrypt_bytes(self, data: bytes) -> bytes:
        stage1 = self.layer1_encrypt(list(data))
        stage2 = self.layer2_encrypt(stage1)
        return self.layer3_encrypt(stage2)

    def decrypt_bytes(self, data: bytes) -> bytes:
        stage3 = self.layer3_decrypt(data)
        stage2 = self.layer2_decrypt(stage3)
        stage1 = self.layer1_decrypt(stage2)
        return bytes(stage1)


_NETRUNNER = NetrunnerCipher()


def encrypt(data: bytes, key: str, **kw) -> bytes:
    # Controller passes key for all algorithms; NETRUNNER core is keyless by design.
    _ = key
    return _NETRUNNER.encrypt_bytes(data)


def decrypt(data: bytes, key: str, **kw) -> bytes:
    _ = key
    return _NETRUNNER.decrypt_bytes(data)
