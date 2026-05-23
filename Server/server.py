import os, json, hashlib, time, base64
from functools import wraps
from flask import Flask, request, jsonify, abort
from flask_cors import CORS
import image as img_steg
import audio as aud_steg
import video as vid_steg
import text as txt_steg
from chain_engine import encrypt_chain, decrypt_chain

app = Flask(__name__)
CORS(app)
SECRET = os.environ.get('STEGOPS_SECRET', 'stegops_internal_secret_2025')
STEG_METHODS = {
    'image': [
        {'slug': 'lsb',        'name': 'LSB Classic',   'desc': '1-bit per RGB channel — highest capacity'},
        {'slug': 'xor_pixel',  'name': 'XOR Pixel',     'desc': 'Key-stream XOR on LSBs — keyed steg'},
        {'slug': 'alpha',      'name': 'Alpha Channel',  'desc': 'Hidden in transparency plane (PNG only)'},
        {'slug': 'img_in_img', 'name': 'Image-in-Image', 'desc': '4-bit nibble merge — embed any file inside images'},
        {'slug': 'dct',        'name': 'DCT JPEG',       'desc': 'Mid-frequency DCT coefficients — WhatsApp-resistant'},
    ],
    'audio': [
        {'slug': 'wav_lsb', 'name': 'WAV LSB',      'desc': 'Least-significant bit across audio samples'},
        {'slug': 'phase',   'name': 'Phase Coding',  'desc': 'Encode data in DFT phase of 1 KB segments'},
        {'slug': 'aahe',    'name': 'AAHE Adaptive', 'desc': 'Psychoacoustic masking + keyed scatter + 2nd-LSB QIM — most imperceptible'},
    ],
    'video': [
        {'slug': 'frame_lsb', 'name': 'Frame LSB', 'desc': 'LSB steganography across all video frames'},
    ],
    'text': [
        {'slug': 'zwsp',        'name': 'Zero-Width Space',    'desc': 'Invisible U+200B/C chars injected between words — survives copy-paste'},
        {'slug': 'homoglyph',   'name': 'Homoglyph Swap',      'desc': 'Cyrillic/Greek lookalike substitution — visually identical letters'},
        {'slug': 'unicode_tag', 'name': 'Unicode Tag Block',   'desc': 'Invisible tag-block chars (U+E0000) appended — zero render footprint'},
        {'slug': 'whitespace',  'name': 'Whitespace Variant',  'desc': 'SPACE vs NO-BREAK SPACE — pixel-perfect identical in every font'},
        {'slug': 'combining',   'name': 'Combining Diacritics','desc': 'CGJ + soft-hyphen after letters — invisible in all renderers'},
    ],
}
_STEG_MOD = {'image': img_steg, 'audio': aud_steg, 'video': vid_steg}
_EXT      = {'image': 'png', 'audio': 'wav', 'video': 'avi'}

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

def _decode_payload(data, text_only=False):
    raw = data.get('payload', '')
    ptype = data.get('payload_type', 'text')
    if not text_only and ptype in ('media', 'file_b64'): return base64.b64decode(raw)
    if not text_only and ptype == 'media': return base64.b64decode(raw)
    return raw.encode('utf-8') if isinstance(raw, str) else raw

def _encode_result(plain, method):
    try: return jsonify({'success': True, 'payload': plain.decode('utf-8'), 'is_binary': False, 'steg_method': method})
    except UnicodeDecodeError: return jsonify({'success': True, 'payload': base64.b64encode(plain).decode(), 'is_binary': True, 'steg_method': method})

@app.route('/health', methods=['GET', 'POST'])
def health():
    return jsonify({'status': 'ok', 'version': '4.0',
        'algorithms': ['caesar','vigenere','aes_256','chacha20','netrunner','des','des3'],
        'text_steg': ['zwsp','homoglyph','unicode_tag','whitespace','combining'],
        'audio_steg': ['wav_lsb','phase','aahe']})

@app.route('/api/methods/<file_type>', methods=['GET', 'POST'])
@auth
def methods(file_type):
    return jsonify(STEG_METHODS.get(file_type, []))

@app.route('/api/capacity', methods=['POST'])
@auth
def capacity():
    data = request.get_json(force=True, silent=True) or {}
    file_type = data.get('file_type', 'image')
    method = data.get('steg_method', 'lsb')
    if file_type == 'text':
        cap = txt_steg.capacity_bytes(data.get('cover_text', ''), method)
        return jsonify({'success': True, 'capacity_bytes': cap, 'capacity_kb': round(cap / 1024, 2)})
    path = data.get('carrier_path', '')
    if not path or not os.path.exists(path): return jsonify({'error': f'Carrier not found: {path}'}), 400
    mod = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    try:
        cap_fn = getattr(mod, '_carrier_capacity', None)
        if cap_fn:
            caps = cap_fn(path)
            cap = caps.get(method, 0) if isinstance(caps, dict) else int(caps)
        else: cap = 0
        return jsonify({'success': True, 'capacity_bytes': cap, 'capacity_kb': round(cap / 1024, 2), 'file_size': os.path.getsize(path)})
    except Exception as e: return jsonify({'error': str(e)}), 400

@app.route('/api/text/embed', methods=['POST'])
@auth
def text_embed():
    data = request.get_json(force=True, silent=True) or {}
    cover_text = data.get('cover_text', '')
    stego_key = data.get('stego_key', '')
    method = data.get('steg_method', 'zwsp')
    steps = _parse_steps(data)
    if not cover_text.strip(): return jsonify({'error': 'cover_text is required'}), 400
    raw = data.get('payload', '')
    payload_bytes = base64.b64decode(raw) if data.get('payload_type','text') == 'media' else (raw.encode('utf-8') if isinstance(raw, str) else raw)
    try: encrypted = encrypt_chain(payload_bytes, stego_key, steps)
    except Exception as e: return jsonify({'error': f'Encryption failed: {e}'}), 400
    try: stego_text = txt_steg.embed(cover_text, encrypted, stego_key, method)
    except ValueError as e: return jsonify({'error': str(e)}), 400
    except Exception as e: return jsonify({'error': f'Text steg failed: {e}'}), 500
    overhead = len(stego_text) - len(cover_text)
    return jsonify({'success': True, 'stego_text': stego_text, 'cover_length': len(cover_text),
        'stego_length': len(stego_text), 'overhead_chars': overhead, 'payload_size': len(encrypted),
        'steg_method': method, 'layers': len(steps),
        'steg_completed': f'{method.upper()} text steganography embedded — {overhead} invisible chars added'})

@app.route('/api/text/extract', methods=['POST'])
@auth
def text_extract():
    data = request.get_json(force=True, silent=True) or {}
    stego_text = data.get('stego_text', '')
    stego_key = data.get('stego_key', '')
    method = data.get('steg_method', 'zwsp')
    if not stego_text: return jsonify({'error': 'stego_text is required'}), 400
    try: encrypted = txt_steg.extract(stego_text, stego_key, method)
    except Exception as e: return jsonify({'error': f'Text extraction failed: {e}'}), 500
    try: plain = decrypt_chain(encrypted, stego_key, _parse_steps(data))
    except Exception as e: return jsonify({'error': f'Decryption failed: {e}'}), 400
    return _encode_result(plain, method)

@app.route('/api/embed', methods=['POST'])
@auth
def embed():
    data = request.get_json(force=True, silent=True) or {}
    carrier = data.get('carrier_path', '')
    out_dir = data.get('output_dir', '')
    stego_key = data.get('stego_key', '')
    method = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')
    op_id = data.get('op_id', 0)
    steps = _parse_steps(data)
    if not carrier or not os.path.exists(carrier): return jsonify({'error': f'Carrier not found: {carrier}'}), 400
    if not out_dir: return jsonify({'error': 'output_dir required'}), 400
    os.makedirs(out_dir, exist_ok=True)
    raw = data.get('payload', '')
    payload_bytes = base64.b64decode(raw) if data.get('payload_type','text') in ('media','file_b64') else (raw.encode('utf-8') if isinstance(raw, str) else raw)
    try: encrypted = encrypt_chain(payload_bytes, stego_key, steps)
    except Exception as e: return jsonify({'error': f'Encryption failed: {e}'}), 400
    ext = 'jpg' if file_type == 'image' and method == 'dct' else _EXT.get(file_type, 'bin')
    out_name = f'stego_{op_id}_{hashlib.md5(f"{op_id}{time.time()}".encode()).hexdigest()[:6]}.{ext}'
    out_path = os.path.join(out_dir, out_name)
    mod = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported carrier type: {file_type}'}), 400
    try: mod.embed(carrier, encrypted, out_path, stego_key, method)
    except ValueError as e: return jsonify({'error': str(e)}), 400
    except Exception as e: return jsonify({'error': f'Steg failed: {e}'}), 500
    actual = getattr(mod.embed, '_last_actual_method', None) or method
    wa_resistant = actual in getattr(mod, 'WHATSAPP_RESISTANT', set())
    resp = {'success': True, 'output_filename': out_name, 'output_path': out_path,
        'payload_size': len(encrypted), 'carrier_size': os.path.getsize(carrier),
        'output_size': os.path.getsize(out_path) if os.path.exists(out_path) else 0,
        'steg_method': actual, 'layers': len(steps), 'whatsapp_compatible': wa_resistant,
        'steg_completed': f'{actual.upper()} steganography embedded successfully' +
            (' — WhatsApp-resistant (MozJPEG 80, 4:2:0)' if wa_resistant else ' — use DCT for WhatsApp survival')}
    if actual != method:
        resp['fallback_note'] = f'Payload too large for {method.upper()} — embedded with {actual.upper()} instead.'
    return jsonify(resp)

@app.route('/api/extract', methods=['POST'])
@auth
def extract():
    data = request.get_json(force=True, silent=True) or {}
    stego = data.get('stego_path', '')
    stego_key = data.get('stego_key', '')
    method = data.get('steg_method', 'lsb')
    file_type = data.get('file_type', 'image')
    if not stego or not os.path.exists(stego): return jsonify({'error': f'File not found: {stego}'}), 400
    mod = _STEG_MOD.get(file_type)
    if not mod: return jsonify({'error': f'Unsupported type: {file_type}'}), 400
    try: encrypted = mod.extract(stego, stego_key, method)
    except Exception as e: return jsonify({'error': f'Extract failed: {e}'}), 500
    try: plain = decrypt_chain(encrypted, stego_key, _parse_steps(data))
    except Exception as e: return jsonify({'error': f'Decryption failed: {e}'}), 400
    return _encode_result(plain, method)

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False, threaded=True)
