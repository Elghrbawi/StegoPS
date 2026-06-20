import importlib

def run_test(mod_name: str, plaintext: str, key: str) -> dict:
    try:
        mod = importlib.import_module(mod_name)
        plain_bytes = plaintext.encode('utf-8')
        enc = mod.encrypt(plain_bytes, key)
        dec = mod.decrypt(enc, key)
        return {
            "success": True,
            "encrypted": enc.hex(),
            "decrypted": dec.decode('utf-8', errors='replace')
        }
    except Exception as e:
        return {"error": str(e)}