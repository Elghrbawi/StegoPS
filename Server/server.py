import os, json, hashlib, time, base64
from functools import wraps
from flask import Flask, request, jsonify, abort
from flask_cors import CORS
import image as img_steg
import audio as aud_steg
from chain_engine import encrypt_chain, decrypt_chain
import audio  # for wrap_file_payload / unwrap_file_payload

app = Flask(__name__)
CORS(app)
SECRET = os.environ.get('STEGOPS_SECRET', 'stegops_internal_secret_2025')

STEG_METHODS = {
    'image': [
        {'slug': 'lsb',         'name': 'LSB Classic',    'desc': '1-bit per RGB channel — highest capacity'},
        {'slug': 'xor_pixel',   'name': 'XOR Pixel',      'desc': 'Key-stream XOR on LSBs — keyed steg'},
        {'slug': 'alpha',       'name': 'Alpha Channel',   'desc': 'Hidden in transparency plane (PNG only)'},
        {'slug': 'img_in_img',  'name': 'Image-in-Image',  'desc': '4-bit nibble merge — embed any file inside images'},
        {'slug': 'dct',         'name': 'DCT JPEG',        'desc': 'Mid-frequency DCT coefficients — WhatsApp-resistant'},
        {'slug': 'pixel_vault', 'name': 'Pixel Vault',     'desc': 'Payload hidden as extra pixel rows — key-seeded shuffle + XOR'},
    ],
    'audio': [
        {'slug': 'wav_lsb', 'name': 'WAV LSB',       'desc': 'Least-significant bit across audio samples — highest capacity'},
        {'slug': 'phase',   'name': 'Phase Coding',   'desc': 'Encode data in DFT phase of 1 KB segments'},
        {'slug': 'aahe',    'name': 'AAHE Adaptive',  'desc': 'Psychoacoustic masking + keyed scatter + 2nd-LSB QIM — most imperceptible'},
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

def _decode_payload(data) -> tuple[bytes, str]:
    """
    Decode the payload from the request.
    Returns (raw_bytes, payload_type_str).

    For file/binary payloads the bytes are wrapped with filename metadata
    so they can be reconstructed on extraction.
    """
    raw   = data.get('payload', '')
    ptype = data.get('payload_type', 'text')

    if ptype in ('media', 'file_b64'):
        file_bytes = base64.b64decode(raw)
        # Wrap with filename so extract can offer proper download
        filename = data.get('payload_filename', 'hidden_file')
        wrapped  = audio.wrap_file_payload(filename, file_bytes)
        return wrapped, 'file'

    # Plain text
    txt = raw if isinstance(raw, str) else raw.decode('utf-8', errors='replace')
    return txt.encode('utf-8'), 'text'

def _encode_result(plain: bytes, method: str):
    """
    Build the JSON response for an extracted payload.
    Detects wrapped file payloads and returns filename + base64 content.
    """
    # Try to unwrap a file payload first
    file_info = audio.unwrap_file_payload(plain)
    if file_info:
        filename, content = file_info
        return jsonify({
            'success':   True,
            'payload':   base64.b64encode(content).decode(),
            'is_binary': True,
            'is_file':   True,
            'filename':  filename,
            'steg_method': method,
        })

    # Try UTF-8 text
    try:
        return jsonify({
            'success':   True,
            'payload':   plain.decode('utf-8'),
            'is_binary': False,
            'is_file':   False,
            'steg_method': method,
        })
    except UnicodeDecodeError:
        # Generic binary blob
        return jsonify({
            'success':   True,
            'payload':   base64.b64encode(plain).decode(),
            'is_binary': True,
            'is_file':   False,
            'steg_method': method,
        })


# ── ROUTES ────────────────────────────────────────────────────────────────────
@app.route('/health', methods=['GET', 'POST'])
def health():
    import shutil
    ffmpeg_ok = shutil.which('ffmpeg') is not None
    return jsonify({
        'status':     'ok',
        'version':    '4.1',
        'ffmpeg':     ffmpeg_ok,
        'algorithms': ['caesar','vigenere','aes_256','chacha20','netrunner','des','des3'],
        'audio_steg': ['wav_lsb','phase','aahe'],
    })

@app.route('/api/methods/<file_type>', methods=['GET', 'POST'])
@auth
def methods(file_type):
    payload_type = request.args.get('payload', 'text')
    meths = STEG_METHODS.get(file_type, [])
    # Mark methods that support any payload type (all do via binary wrapping)
    return jsonify(meths)

@app.route('/api/capacity', methods=['POST'])
@auth
def capacity():
    data      = request.get_json(force=True, silent=True) or {}
    file_type = data.get('file_type', 'image')
    method    = data.get('steg_method', 'lsb')
    path      = data.get('carrier_path', '')
    if not path or not os.path.exists(path):
        return jsonify({'error': f'Carrier not found: {path}'}), 400
    mod = _STEG_MOD.get(file_type)
    if not mod:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    try:
        cap_fn = getattr(mod, '_carrier_capacity', None)
        if cap_fn:
            caps = cap_fn(path)
            cap  = caps.get(method, 0) if isinstance(caps, dict) else int(caps)
        else:
            cap = 0
        return jsonify({
            'success':       True,
            'capacity_bytes': cap,
            'capacity_kb':   round(cap / 1024, 2),
            'file_size':     os.path.getsize(path),
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 400


@app.route('/api/embed', methods=['POST'])
@auth
def embed():
    data        = request.get_json(force=True, silent=True) or {}
    carrier     = data.get('carrier_path', '')
    out_dir     = data.get('output_dir', '')
    stego_key   = data.get('stego_key', '')
    method      = data.get('steg_method', 'lsb')
    file_type   = data.get('file_type', 'image')
    op_id       = data.get('op_id', 0)
    steps       = _parse_steps(data)

    if not carrier or not os.path.exists(carrier):
        return jsonify({'error': f'Carrier not found: {carrier}'}), 400
    if not out_dir:
        return jsonify({'error': 'output_dir required'}), 400
    os.makedirs(out_dir, exist_ok=True)

    # Decode payload (handles text + file/binary with filename wrapping)
    try:
        payload_bytes, _ = _decode_payload(data)
    except Exception as e:
        return jsonify({'error': f'Payload decode error: {e}'}), 400

    # Encrypt
    try:
        encrypted = encrypt_chain(payload_bytes, stego_key, steps)
    except Exception as e:
        return jsonify({'error': f'Encryption failed: {e}'}), 400

    # Output file name
    ext      = 'jpg' if file_type == 'image' and method == 'dct' else _EXT.get(file_type, 'bin')
    out_name = f'stego_{op_id}_{hashlib.md5(f"{op_id}{time.time()}".encode()).hexdigest()[:6]}.{ext}'
    out_path = os.path.join(out_dir, out_name)

    mod = _STEG_MOD.get(file_type)
    if not mod:
        return jsonify({'error': f'Unsupported carrier type: {file_type}'}), 400

    try:
        mod.embed(carrier, encrypted, out_path, stego_key, method)
    except ValueError as e:
        return jsonify({'error': str(e)}), 400
    except Exception as e:
        return jsonify({'error': f'Steg failed: {e}'}), 500

    actual     = getattr(mod.embed, '_last_actual_method', None) or method
    wa_resistant = actual in getattr(mod, 'WHATSAPP_RESISTANT', set())

    resp = {
        'success':          True,
        'output_filename':  out_name,
        'output_path':      out_path,
        'payload_size':     len(encrypted),
        'carrier_size':     os.path.getsize(carrier),
        'output_size':      os.path.getsize(out_path) if os.path.exists(out_path) else 0,
        'steg_method':      actual,
        'layers':           len(steps),
        'whatsapp_compatible': wa_resistant,
        'steg_completed':
            f'{actual.upper()} steganography embedded successfully' +
            (' — WhatsApp-resistant (MozJPEG 80, 4:2:0)' if wa_resistant
             else ' — use DCT for WhatsApp survival'),
    }
    if actual != method:
        resp['fallback_note'] = (
            f'Payload too large for {method.upper()} — embedded with {actual.upper()} instead.'
        )
    return jsonify(resp)


@app.route('/api/extract', methods=['POST'])
@auth
def extract():
    data      = request.get_json(force=True, silent=True) or {}
    stego     = data.get('stego_path', '')
    stego_key = data.get('stego_key', '')
    method    = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')

    if not stego or not os.path.exists(stego):
        return jsonify({'error': f'File not found: {stego}'}), 400
    mod = _STEG_MOD.get(file_type)
    if not mod:
        return jsonify({'error': f'Unsupported type: {file_type}'}), 400

    try:
        encrypted = mod.extract(stego, stego_key, method)
    except Exception as e:
        return jsonify({'error': f'Extract failed: {e}'}), 500

    try:
        plain = decrypt_chain(encrypted, stego_key, _parse_steps(data))
    except Exception as e:
        return jsonify({'error': f'Decryption failed: {e}'}), 400

    return _encode_result(plain, method)


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False, threaded=True)
