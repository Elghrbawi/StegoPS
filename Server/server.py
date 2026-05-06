import os, json, hashlib, time
from functools import wraps
from flask import Flask, request, jsonify, abort
from flask_cors import CORS
import image as img_steg
import audio as aud_steg
import video as vid_steg
from chain_engine import encrypt_chain, decrypt_chain

app  = Flask(__name__)
CORS(app)   # allow PHP on same host / different port to reach us

SECRET = os.environ.get('STEGOPS_SECRET', 'stegops_internal_secret_2025')

STEG_METHODS = {
    'image': [
        {'slug': 'lsb',        'name': 'LSB Classic',    'desc': '1-bit per RGB channel — highest capacity'},
        {'slug': 'xor_pixel',  'name': 'XOR Pixel',      'desc': 'Key-stream XOR on LSBs — keyed steg'},
        {'slug': 'alpha',      'name': 'Alpha Channel',   'desc': 'Hidden in transparency (PNG only)'},
        {'slug': 'img_in_img', 'name': 'Image-in-Image',  'desc': '4-bit nibble merge — embed files inside images'},
    ],
    'audio': [
        {'slug': 'wav_lsb', 'name': 'WAV LSB',      'desc': 'Least-significant bit in audio samples'},
        {'slug': 'phase',   'name': 'Phase Coding',  'desc': 'Encode data in DFT phase segments'},
    ],
    'video': [
        {'slug': 'frame_lsb', 'name': 'Frame LSB', 'desc': 'LSB steganography across all video frames'},
    ],
}

# ── AUTH ────────────────────────────────────────────────────────────────────
def auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        token = request.headers.get('Authorization', '')
        if token != f'Bearer {SECRET}':
            abort(401)
        return f(*args, **kwargs)
    return decorated

# ── HELPERS ─────────────────────────────────────────────────────────────────
def get_steg_mod(file_type):
    return {'image': img_steg, 'audio': aud_steg, 'video': vid_steg}.get(file_type)

# ── ROUTES ──────────────────────────────────────────────────────────────────
@app.route('/health', methods=['GET', 'POST'])
def health():
    return jsonify({
        'status':     'ok',
        'version':    '3.1',
        'algorithms': ['caesar', 'vigenere', 'aes_256', 'chacha20',
                       'netrunner', 'des', 'des3'],
    })

# Accept both GET and POST so PHP's callPython (which always POSTs) works fine
@app.route('/api/methods/<file_type>', methods=['GET', 'POST'])
@auth
def methods(file_type):
    return jsonify(STEG_METHODS.get(file_type, []))

@app.route('/api/embed', methods=['POST'])
@auth
def embed():
    data = request.get_json(force=True, silent=True) or {}

    carrier_path = data.get('carrier_path', '')
    output_dir   = data.get('output_dir', '')
    payload_raw  = data.get('payload', '')
    payload_type = data.get('payload_type', 'text')
    stego_key    = data.get('stego_key', '')
    steg_method  = data.get('steg_method', 'lsb')
    file_type    = data.get('file_type', 'image')
    steps        = data.get('steps', [])
    op_id        = data.get('op_id', 0)

    if isinstance(steps, str):
        try:
            steps = json.loads(steps)
        except Exception:
            steps = []

    if not carrier_path or not os.path.exists(carrier_path):
        return jsonify({'error': f'Carrier not found: {carrier_path}'}), 400
    if not output_dir:
        return jsonify({'error': 'output_dir required'}), 400

    os.makedirs(output_dir, exist_ok=True)

    # Decode payload
    if payload_type == 'file_b64':
        import base64
        payload_bytes = base64.b64decode(payload_raw)
    else:
        payload_bytes = payload_raw.encode('utf-8') if isinstance(payload_raw, str) else payload_raw

    # Encrypt chain
    try:
        encrypted = encrypt_chain(payload_bytes, stego_key, steps)
    except Exception as e:
        return jsonify({'error': f'Encryption failed: {e}'}), 400

    # Build output filename
    ext_map  = {'image': 'png', 'audio': 'wav', 'video': 'avi'}
    ext      = ext_map.get(file_type, 'bin')
    uid      = hashlib.md5(f'{op_id}{time.time()}'.encode()).hexdigest()[:6]
    out_name = f'stego_{op_id}_{uid}.{ext}'
    out_path = os.path.join(output_dir, out_name)

    steg_mod = get_steg_mod(file_type)
    if steg_mod is None:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400

    try:
        steg_mod.embed(carrier_path, encrypted, out_path, stego_key, steg_method)
    except ValueError as e:
        return jsonify({'error': str(e)}), 400
    except Exception as e:
        return jsonify({'error': f'Steg failed: {e}'}), 500

    carrier_size = os.path.getsize(carrier_path)
    output_size  = os.path.getsize(out_path) if os.path.exists(out_path) else 0

    return jsonify({
        'success':         True,
        'output_filename': out_name,
        'output_path':     out_path,
        'payload_size':    len(encrypted),
        'carrier_size':    carrier_size,
        'output_size':     output_size,
        'steg_method':     steg_method,
        'layers':          len(steps),
    })

@app.route('/api/extract', methods=['POST'])
@auth
def extract():
    data = request.get_json(force=True, silent=True) or {}

    stego_path  = data.get('stego_path', '')
    stego_key   = data.get('stego_key', '')
    steg_method = data.get('steg_method', 'lsb')
    file_type   = data.get('file_type', 'image')
    steps       = data.get('steps', [])

    if isinstance(steps, str):
        try:
            steps = json.loads(steps)
        except Exception:
            steps = []

    if not stego_path or not os.path.exists(stego_path):
        return jsonify({'error': f'File not found: {stego_path}'}), 400

    steg_mod = get_steg_mod(file_type)
    if steg_mod is None:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400

    try:
        encrypted = steg_mod.extract(stego_path, stego_key, steg_method)
    except Exception as first_err:
        # Image payloads may come from different embed methods; auto-fallback helps recovery.
        if file_type != 'image':
            return jsonify({'error': f'Extract failed: {first_err}'}), 500
        extracted = None
        last_err = first_err
        for m in ['lsb', 'xor_pixel', 'alpha', 'img_in_img']:
            if m == steg_method:
                continue
            try:
                extracted = steg_mod.extract(stego_path, stego_key, m)
                steg_method = m
                break
            except Exception as e:
                last_err = e
        if extracted is None:
            return jsonify({'error': f'Extract failed: {last_err}'}), 500
        encrypted = extracted

    try:
        plain = decrypt_chain(encrypted, stego_key, steps)
    except Exception as e:
        return jsonify({'error': f'Decryption failed: {e}'}), 400

    try:
        return jsonify({'success': True, 'payload': plain.decode('utf-8'), 'is_binary': False, 'steg_method': steg_method})
    except UnicodeDecodeError:
        import base64
        return jsonify({'success': True, 'payload': base64.b64encode(plain).decode(), 'is_binary': True, 'steg_method': steg_method})

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False, threaded=True)
