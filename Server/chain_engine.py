import importlib

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

def encrypt_chain(data: bytes, stego_key: str, steps: list) -> bytes:
    result = data
    for step in steps:
        slug    = step.get('slug', '')
        key     = step.get('key') or stego_key
        mod_name = ALGO_MAP.get(slug)
        if not mod_name:
            continue
        mod = importlib.import_module(mod_name)
        result = mod.encrypt(result, key)
    return result

def decrypt_chain(data: bytes, stego_key: str, steps: list) -> bytes:
    result = data
    for step in reversed(steps):
        slug     = step.get('slug', '')
        key      = step.get('key') or stego_key
        mod_name = ALGO_MAP.get(slug)
        if not mod_name:
            continue
        mod = importlib.import_module(mod_name)
        result = mod.decrypt(result, key)
    return result
