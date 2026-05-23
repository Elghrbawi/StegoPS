import importlib
# ── ALGO MAP ──────────────────────────────────────────────────────────────────
ALGO_MAP = {
    'caesar':    'caesar',
    'vigenere':  'vigenere',
    'aes_256':   'aes256',
    'chacha20':  'chacha20',
    'netrunner': 'netrunner',
    'des':       'des_cipher',
    'des3':      'des3_cipher',
    'triple_des':'des3_cipher',
}
# ── CHAIN ─────────────────────────────────────────────────────────────────────
def encrypt_chain(data: bytes, stego_key: str, steps: list) -> bytes:
    for step in steps:
        mod = ALGO_MAP.get(step.get('slug', ''))
        if mod:
            data = importlib.import_module(mod).encrypt(data, step.get('key') or stego_key)
    return data
def decrypt_chain(data: bytes, stego_key: str, steps: list) -> bytes:
    for step in reversed(steps):
        mod = ALGO_MAP.get(step.get('slug', ''))
        if mod:
            data = importlib.import_module(mod).decrypt(data, step.get('key') or stego_key)
    return data
