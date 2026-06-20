import os, json, hashlib, time, base64, tempfile
from functools import wraps
from flask import Flask, request, jsonify, abort
from flask_cors import CORS
import importlib
import image as img_steg
import audio as aud_steg
from chain_engine import encrypt_chain, decrypt_chain
import test_algo as test_algo_module

app = Flask(__name__)
CORS(app)
SECRET = os.environ.get('STEGOPS_SECRET', 'stegops_internal_secret_2025')

STEG_METHODS = {
    'image': [
        {'slug': 'lsb',        'name': 'LSB Classic',    'desc': '1-bit per RGB channel — highest capacity'},
        {'slug': 'xor_pixel',  'name': 'XOR Pixel',      'desc': 'Key-stream XOR on LSBs — keyed steg'},
        {'slug': 'alpha',      'name': 'Alpha Channel',   'desc': 'Hidden in transparency plane (PNG only)'},
        {'slug': 'img_in_img', 'name': 'Image-in-Image',  'desc': '4-bit nibble merge — embed any file inside images'},
        {'slug': 'dct',        'name': 'DCT JPEG',        'desc': 'Mid-frequency DCT coefficients — WhatsApp-resistant'},
        {'slug': 'pixel_vault','name': 'Pixel Vault',     'desc': 'Payload hidden as extra pixel rows — key-seeded shuffle+XOR'},
    ],
    'audio': [
        {'slug': 'wav_lsb', 'name': 'WAV LSB',      'desc': 'Least-significant bit across audio samples'},
        {'slug': 'phase',   'name': 'Phase Coding',  'desc': 'Encode data in DFT phase of 1 KB segments'},
        {'slug': 'aahe',    'name': 'AAHE Adaptive', 'desc': 'Psychoacoustic masking + keyed scatter + 2nd-LSB QIM'},
    ],
}
_STEG_MOD = {'image': img_steg, 'audio': aud_steg}
_EXT      = {'image': 'png', 'audio': 'wav'}

def auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if request.headers.get('Authorization', '') != f'Bearer {SECRET}': abort(401)
        return f(*args, **kwargs)
    return decorated

def _parse_steps(data):
    steps = data.get('steps', [])
    if isinstance(steps, str):
        try: steps = json.loads(steps)
        except Exception: steps = []
    return steps

def _decode_payload(data):
    raw   = data.get('payload', '')
    ptype = data.get('payload_type', 'text')
    if ptype in ('media', 'file_b64'):
        return base64.b64decode(raw)
    return raw.encode('utf-8') if isinstance(raw, str) else raw

def _encode_result(plain, method):
    # Check if this is a wrapped file payload (wrap_file_payload from audio.py / image steg)
    unwrapped = aud_steg.unwrap_file_payload(plain)
    if unwrapped is not None:
        filename, content = unwrapped
        return jsonify({
            'success'    : True,
            'payload'    : base64.b64encode(content).decode(),
            'is_binary'  : True,
            'is_file'    : True,
            'filename'   : filename,
            'steg_method': method,
        })
    try:
        return jsonify({'success': True, 'payload': plain.decode('utf-8'),
                        'is_binary': False, 'is_file': False, 'filename': '',
                        'steg_method': method})
    except UnicodeDecodeError:
        return jsonify({'success': True, 'payload': base64.b64encode(plain).decode(),
                        'is_binary': True,  'is_file': False, 'filename': '',
                        'steg_method': method})

def _b64_to_tmp(b64_str, suffix='.png'):
    raw  = base64.b64decode(b64_str)
    if raw[:2] == b'\xff\xd8':
        suffix = '.jpg'
    elif raw[:4] == b'RIFF' and raw[8:12] == b'WAVE':
        suffix = '.wav'   # WAV files must keep .wav so _ensure_wav skips re-encoding
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
    tmp.write(raw); tmp.flush(); tmp.close()
    return tmp.name

@app.route('/health', methods=['GET', 'POST'])
def health():
    return jsonify({'status': 'ok', 'version': '4.1',
        'algorithms': ['caesar','vigenere','aes_256','chacha20','netrunner','des','des3'],
        'audio_steg': ['wav_lsb','phase','aahe']})

@app.route('/api/methods/<file_type>', methods=['GET', 'POST'])
@auth
def methods(file_type):
    return jsonify(STEG_METHODS.get(file_type, []))

@app.route('/api/capacity', methods=['POST'])
@auth
def capacity():
    data      = request.get_json(force=True, silent=True) or {}
    file_type = data.get('file_type', 'image')
    method    = data.get('steg_method', 'lsb')
    mod       = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    carrier_b64 = data.get('carrier_b64', '')
    carrier_path = data.get('carrier_path', '')
    tmp = None
    try:
        if carrier_b64:
            tmp = _b64_to_tmp(carrier_b64)
            carrier_path = tmp
        if not carrier_path or not os.path.exists(carrier_path):
            return jsonify({'error': 'Carrier not found'}), 400
        cap_fn = getattr(mod, '_carrier_capacity', None)
        if cap_fn:
            caps = cap_fn(carrier_path)
            cap  = caps.get(method, 0) if isinstance(caps, dict) else int(caps)
        else:
            cap = 0
        return jsonify({'success': True, 'capacity_bytes': cap,
                        'capacity_kb': round(cap / 1024, 2),
                        'file_size': os.path.getsize(carrier_path)})
    except Exception as e:
        return jsonify({'error': str(e)}), 400
    finally:
        if tmp and os.path.exists(tmp): os.unlink(tmp)

@app.route('/api/embed', methods=['POST'])
@auth
def embed():
    data      = request.get_json(force=True, silent=True) or {}
    stego_key = data.get('stego_key', '')
    method    = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')
    op_id     = data.get('op_id', 0)
    steps     = _parse_steps(data)
    mod = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported carrier type: {file_type}'}), 400
    carrier_b64  = data.get('carrier_b64', '')
    carrier_path = data.get('carrier_path', '')
    carrier_tmp  = None
    try:
        if carrier_b64:
            carrier_tmp  = _b64_to_tmp(carrier_b64)
            carrier_path = carrier_tmp
        if not carrier_path or not os.path.exists(carrier_path):
            return jsonify({'error': 'Carrier not provided or not found'}), 400
        payload_bytes = _decode_payload(data)
        # If this is a file payload, wrap it with the filename so extraction can recover it
        ptype = data.get('payload_type', 'text')
        if ptype in ('file_b64', 'media', 'file'):
            payload_filename = data.get('payload_filename', 'hidden_file')
            payload_bytes = aud_steg.wrap_file_payload(payload_filename, payload_bytes)
        try:
            encrypted = encrypt_chain(payload_bytes, stego_key, steps)
        except Exception as e:
            return jsonify({'error': f'Encryption failed: {e}'}), 400
        ext      = 'jpg' if file_type == 'image' and method == 'dct' else _EXT.get(file_type, 'bin')
        out_name = f'stego_{op_id}_{hashlib.md5(f"{op_id}{time.time()}".encode()).hexdigest()[:6]}.{ext}'
        out_dir = data.get('output_dir', '') or tempfile.mkdtemp()
        os.makedirs(out_dir, exist_ok=True)
        out_path = os.path.join(out_dir, out_name)
        try:
            mod.embed(carrier_path, encrypted, out_path, stego_key, method)
        except ValueError as e:
            return jsonify({'error': str(e)}), 400
        except Exception as e:
            return jsonify({'error': f'Steg failed: {e}'}), 500
        actual       = getattr(mod.embed, '_last_actual_method', None) or method
        wa_resistant = actual in getattr(mod, 'WHATSAPP_RESISTANT', set())
        resp = {
            'success'         : True,
            'output_filename' : out_name,
            'output_path'     : out_path,
            'payload_size'    : len(encrypted),
            'output_size'     : os.path.getsize(out_path) if os.path.exists(out_path) else 0,
            'steg_method'     : actual,
            'layers'          : len(steps),
            'whatsapp_compatible': wa_resistant,
            'steg_completed'  : (f'{actual.upper()} steganography embedded successfully' +
                                 (' — WhatsApp-resistant (MozJPEG 80, 4:2:0)' if wa_resistant
                                  else ' — use DCT for WhatsApp survival')),
        }
        if carrier_b64 and os.path.exists(out_path):
            with open(out_path, 'rb') as f:
                resp['output_b64'] = base64.b64encode(f.read()).decode()
            if not data.get('output_dir'):
                os.unlink(out_path)
        if actual != method:
            resp['fallback_note'] = (f'Payload too large for {method.upper()} — '
                                     f'embedded with {actual.upper()} instead.')
        return jsonify(resp)
    finally:
        if carrier_tmp and os.path.exists(carrier_tmp):
            os.unlink(carrier_tmp)

@app.route('/api/extract', methods=['POST'])
@auth
def extract():
    data      = request.get_json(force=True, silent=True) or {}
    stego_key = data.get('stego_key', '')
    method    = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')
    steps     = _parse_steps(data)
    mod = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    stego_b64  = data.get('stego_b64', '')
    stego_path = data.get('stego_path', '')
    stego_tmp  = None
    try:
        if stego_b64:
            stego_tmp  = _b64_to_tmp(stego_b64)
            stego_path = stego_tmp
        if not stego_path or not os.path.exists(stego_path):
            return jsonify({'error': 'Stego file not provided or not found'}), 400
        try:
            encrypted = mod.extract(stego_path, stego_key, method)
        except Exception as e:
            return jsonify({'error': f'Extract failed: {e}'}), 500
        try:
            plain = decrypt_chain(encrypted, stego_key, steps)
        except Exception as e:
            return jsonify({'error': f'Decryption failed: {e}'}), 400
        return _encode_result(plain, method)
    finally:
        if stego_tmp and os.path.exists(stego_tmp):
            os.unlink(stego_tmp)

@app.route('/api/test_algo', methods=['POST'])
@auth
def test_algo_route():
    data = request.get_json(force=True, silent=True) or {}
    algo_slug = data.get('algo_slug', '')
    plaintext = data.get('plaintext', 'Hello')
    key = data.get('key', 'testkey')
    if not algo_slug:
        return jsonify({'error': 'algo_slug required'}), 400
    try:
        from chain_engine import ALGO_MAP
        mod_name = ALGO_MAP.get(algo_slug)
        if not mod_name:
            return jsonify({'error': f'Unknown algorithm slug: {algo_slug}'}), 400
        result = test_algo_module.run_test(mod_name, plaintext, key)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False, threaded=True)