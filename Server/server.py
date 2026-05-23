import os, json, hashlib, time, base64
from functools import wraps
from flask import Flask, request, jsonify, abort
from flask_cors import CORS
import image as img_steg
import audio as aud_steg
import video as vid_steg
from chain_engine import encrypt_chain, decrypt_chain
# ── APP ───────────────────────────────────────────────────────────────────────
app = Flask(__name__)
CORS(app)
SECRET = os.environ.get('STEGOPS_SECRET', 'stegops_internal_secret_2025')
STEG_METHODS = {
    'image': [
        {'slug': 'lsb',        'name': 'LSB Classic',   'desc': '1-bit per RGB channel — highest capacity'},
        {'slug': 'xor_pixel',  'name': 'XOR Pixel',     'desc': 'Key-stream XOR on LSBs — keyed steg'},
        {'slug': 'alpha',      'name': 'Alpha Channel',  'desc': 'Hidden in transparency (PNG only)'},
        {'slug': 'img_in_img', 'name': 'Image-in-Image', 'desc': '4-bit nibble merge — embed files inside images'},
        {'slug': 'dct',        'name': 'DCT',            'desc': 'Embed in mid-frequency DCT coefficients of 8×8 luma blocks — JPEG-style, perceptually robust'},
    ],
    'audio': [
        {'slug': 'wav_lsb', 'name': 'WAV LSB',     'desc': 'Least-significant bit in audio samples'},
        {'slug': 'phase',   'name': 'Phase Coding', 'desc': 'Encode data in DFT phase segments'},
    ],
    'video': [
        {'slug': 'frame_lsb', 'name': 'Frame LSB', 'desc': 'LSB steganography across all video frames'},
    ],
}
_STEG_MOD = {'image': img_steg, 'audio': aud_steg, 'video': vid_steg}
_EXT      = {'image': 'png', 'audio': 'wav', 'video': 'avi'}
# ── AUTH ──────────────────────────────────────────────────────────────────────
def auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if request.headers.get('Authorization', '') != f'Bearer {SECRET}':
            abort(401)
        return f(*args, **kwargs)
    return decorated
# ── ROUTES ────────────────────────────────────────────────────────────────────
@app.route('/health', methods=['GET', 'POST'])
def health():
    return jsonify({'status': 'ok', 'version': '3.1', 'algorithms': ['caesar', 'vigenere', 'aes_256', 'chacha20', 'netrunner', 'des', 'des3']})
@app.route('/api/methods/<file_type>', methods=['GET', 'POST'])
@auth
def methods(file_type):
    return jsonify(STEG_METHODS.get(file_type, []))
@app.route('/api/embed', methods=['POST'])
@auth
def embed():
    data        = request.get_json(force=True, silent=True) or {}
    carrier     = data.get('carrier_path', '')
    out_dir     = data.get('output_dir', '')
    payload_raw = data.get('payload', '')
    pay_type    = data.get('payload_type', 'text')
    stego_key   = data.get('stego_key', '')
    method      = data.get('steg_method', 'lsb')
    file_type   = data.get('file_type', 'image')
    op_id       = data.get('op_id', 0)
    steps       = data.get('steps', [])
    if isinstance(steps, str):
        try: steps = json.loads(steps)
        except Exception: steps = []
    if not carrier or not os.path.exists(carrier):
        return jsonify({'error': f'Carrier not found: {carrier}'}), 400
    if not out_dir:
        return jsonify({'error': 'output_dir required'}), 400
    os.makedirs(out_dir, exist_ok=True)
    payload_bytes = base64.b64decode(payload_raw) if pay_type == 'file_b64' else (payload_raw.encode('utf-8') if isinstance(payload_raw, str) else payload_raw)
    try: encrypted = encrypt_chain(payload_bytes, stego_key, steps)
    except Exception as e: return jsonify({'error': f'Encryption failed: {e}'}), 400
    ext      = 'jpg' if file_type == 'image' and method == 'dct' else _EXT.get(file_type, 'bin')
    uid      = hashlib.md5(f'{op_id}{time.time()}'.encode()).hexdigest()[:6]
    out_name = f'stego_{op_id}_{uid}.{ext}'
    out_path = os.path.join(out_dir, out_name)
    mod = _STEG_MOD.get(file_type)
    if not mod:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    try: mod.embed(carrier, encrypted, out_path, stego_key, method)
    except ValueError as e: return jsonify({'error': str(e)}), 400
    except Exception as e: return jsonify({'error': f'Steg failed: {e}'}), 500
    actual = getattr(mod.embed, '_last_actual_method', None) or method
    wa_resistant = actual in getattr(mod, 'WHATSAPP_RESISTANT', set())
    resp = {
        'success':              True,
        'output_filename':      out_name,
        'output_path':          out_path,
        'payload_size':         len(encrypted),
        'carrier_size':         os.path.getsize(carrier),
        'output_size':          os.path.getsize(out_path) if os.path.exists(out_path) else 0,
        'steg_method':          actual,
        'layers':               len(steps),
        'whatsapp_compatible':  wa_resistant,
        'steg_completed':       f'{actual.upper()} steganography embedded successfully'
                                + (' — WhatsApp-resistant (MozJPEG quality 80, 4:2:0)' if wa_resistant
                                   else ' — use DCT method for WhatsApp survival'),
    }
    if actual != method:
        resp['fallback_note'] = f'Payload too large for {method.upper()} — embedded with {actual.upper()} instead.'
    return jsonify(resp)
@app.route('/api/extract', methods=['POST'])
@auth
def extract():
    data      = request.get_json(force=True, silent=True) or {}
    stego     = data.get('stego_path', '')
    stego_key = data.get('stego_key', '')
    method    = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')
    steps     = data.get('steps', [])
    if isinstance(steps, str):
        try: steps = json.loads(steps)
        except Exception: steps = []
    if not stego or not os.path.exists(stego):
        return jsonify({'error': f'File not found: {stego}'}), 400
    mod = _STEG_MOD.get(file_type)
    if not mod:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    try: encrypted = mod.extract(stego, stego_key, method)
    except Exception as e: return jsonify({'error': f'Extract failed: {e}'}), 500
    try: plain = decrypt_chain(encrypted, stego_key, steps)
    except Exception as e: return jsonify({'error': f'Decryption failed: {e}'}), 400
    try:
        return jsonify({'success': True, 'payload': plain.decode('utf-8'), 'is_binary': False, 'steg_method': method})
    except UnicodeDecodeError:
        return jsonify({'success': True, 'payload': base64.b64encode(plain).decode(), 'is_binary': True, 'steg_method': method})
# ── MAIN ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False, threaded=True)
