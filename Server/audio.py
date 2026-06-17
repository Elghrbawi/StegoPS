import wave, struct, zlib, hashlib, os, tempfile
import numpy as np

_MAGIC      = b'\xDE\xAD'
_AAHE_MG    = b'\xAA\xEE'
SEG_LEN     = 1024
_AAHE_BIT_PLANE  = 1
_AAHE_WINDOW     = 256
_AAHE_PERCENTILE = 55

# ── MP3 / non-WAV conversion ───────────────────────────────────────────────────
def _ensure_wav(path: str) -> tuple[str, bool]:
    """Return (wav_path, is_tmp). Converts MP3/OGG/FLAC/etc to WAV if needed."""
    ext = os.path.splitext(path)[1].lower()
    if ext in ('.wav',):
        return path, False
    try:
        from pydub import AudioSegment
    except ImportError:
        raise ValueError(
            "pydub is required for non-WAV audio. Install: pip install pydub"
        )
    fmt_map = {
        '.mp3': 'mp3', '.ogg': 'ogg', '.flac': 'flac',
        '.aac': 'aac', '.m4a': 'mp4', '.opus': 'opus',
        '.wma': 'asf', '.aiff': 'aiff', '.au': 'au',
    }
    fmt = fmt_map.get(ext, ext.lstrip('.'))
    audio = AudioSegment.from_file(path, format=fmt)
    tmp = tempfile.NamedTemporaryFile(suffix='.wav', delete=False)
    tmp.close()
    audio = audio.set_frame_rate(44100).set_channels(1).set_sample_width(2)
    audio.export(tmp.name, format='wav')
    return tmp.name, True

# ── Helpers ────────────────────────────────────────────────────────────────────
def _pack(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _MAGIC + len(data).to_bytes(4, 'big') + crc + data

def _pack_aahe(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _AAHE_MG + len(data).to_bytes(4, 'big') + crc + data

def _bits(data: bytes) -> str:
    return ''.join(format(b, '08b') for b in data)

def _read_wav(path: str):
    with wave.open(path, 'rb') as wf:
        params = wf.getparams()
        raw    = wf.readframes(wf.getnframes())
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    return params, np.frombuffer(raw, dtype=dtype).copy()

def _write_wav(path: str, params, samples: np.ndarray):
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    with wave.open(path, 'wb') as wf:
        wf.setparams(params)
        wf.writeframes(samples.astype(dtype).tobytes())

# ── Public API ─────────────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    wav_path, is_tmp = _ensure_wav(carrier_path)
    try:
        if   method == 'wav_lsb': _embed_wav_lsb(wav_path, data, out_path)
        elif method == 'phase':   _embed_phase(wav_path, data, out_path)
        elif method == 'aahe':    _embed_aahe(wav_path, data, out_path, key)
        else: raise ValueError(f'Unknown audio steg method: {method}')
    finally:
        if is_tmp:
            try: os.unlink(wav_path)
            except OSError: pass

def extract(stego_path: str, key: str, method: str) -> bytes:
    wav_path, is_tmp = _ensure_wav(stego_path)
    try:
        if   method == 'wav_lsb': return _extract_wav_lsb(wav_path)
        elif method == 'phase':   return _extract_phase(wav_path)
        elif method == 'aahe':    return _extract_aahe(wav_path, key)
        raise ValueError(f'Unknown audio steg method: {method}')
    finally:
        if is_tmp:
            try: os.unlink(wav_path)
            except OSError: pass

# ── WAV LSB — optimised with numpy vectorisation ──────────────────────────────
def _embed_wav_lsb(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    payload = _pack(data)
    nbits   = len(payload) * 8
    if nbits > len(samples):
        raise ValueError(f'Payload too large. Capacity: {len(samples) // 8} bytes')
    bits = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    samples[:nbits] = (samples[:nbits].astype(np.int16) & np.int16(-2)) | bits.astype(np.int16)
    _write_wav(out_path, params, samples)

def _extract_wav_lsb(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    hdr_bytes = len(_MAGIC) + 4 + 4          # magic + length + crc
    hdr_bits  = hdr_bytes * 8
    raw_hdr   = np.packbits(samples[:hdr_bits].astype(np.uint8) & 1).tobytes()
    if raw_hdr[:2] != _MAGIC:
        raise ValueError('No hidden payload found')
    length = int.from_bytes(raw_hdr[2:6], 'big')
    total  = hdr_bits + length * 8
    if total > len(samples):
        raise ValueError('Payload length exceeds audio samples')
    raw_pay = np.packbits(samples[:total].astype(np.uint8) & 1).tobytes()
    return raw_pay[hdr_bytes:hdr_bytes + length]

# ── Phase Coding ───────────────────────────────────────────────────────────────
def _embed_phase(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    mono    = samples if samples.ndim == 1 else samples[:, 0]
    payload = _pack(data)
    bits    = [int(b) for byte in payload for b in format(byte, '08b')]
    n_segs  = len(mono) // SEG_LEN
    if len(bits) > n_segs:
        raise ValueError(f'Payload too large for phase coding. Max: {n_segs // 8} bytes')
    result = mono.astype(np.float64).copy()
    for i, bit in enumerate(bits):
        seg = result[i * SEG_LEN:(i + 1) * SEG_LEN]
        fft = np.fft.rfft(seg)
        mid = len(fft) // 2
        fft[mid] = np.abs(fft[mid]) * np.exp(1j * (np.pi / 2 if bit else 0.0))
        result[i * SEG_LEN:(i + 1) * SEG_LEN] = np.fft.irfft(fft)[:SEG_LEN]
    _write_wav(out_path, params, np.clip(result, -32768, 32767).astype(np.int16))

def _extract_phase(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    mono   = samples if samples.ndim == 1 else samples[:, 0]
    n_segs = len(mono) // SEG_LEN
    bits   = []
    for i in range(n_segs):
        fft = np.fft.rfft(mono[i * SEG_LEN:(i + 1) * SEG_LEN].astype(np.float64))
        bits.append(1 if np.angle(fft[len(fft) // 2]) > np.pi / 4 else 0)
    hdr_bytes = len(_MAGIC) + 4 + 4
    hlen = hdr_bytes * 8
    hdr  = bytes(int(''.join(str(b) for b in bits[i:i+8]), 2) for i in range(0, hlen, 8))
    if hdr[:2] != _MAGIC: raise ValueError('No hidden payload found')
    length = int.from_bytes(hdr[2:6], 'big')
    pbits  = bits[hlen:hlen + length * 8]
    return bytes(int(''.join(str(b) for b in pbits[i:i+8]), 2) for i in range(0, len(pbits), 8))

# ── AAHE ───────────────────────────────────────────────────────────────────────
def _blake2(data: bytes, size: int = 32) -> bytes:
    return hashlib.blake2b(data, digest_size=size).digest()

def _aahe_keystream(key: str, salt: bytes, n_bits: int) -> np.ndarray:
    kb  = (key or '').encode()
    out = np.zeros(n_bits, dtype=np.uint8)
    filled = 0; counter = 0
    while filled < n_bits:
        block = _blake2(kb + salt + counter.to_bytes(4, 'big'))
        chunk = np.unpackbits(np.frombuffer(block, dtype=np.uint8))
        take  = min(len(chunk), n_bits - filled)
        out[filled:filled + take] = chunk[:take]
        filled += take; counter += 1
    return out

def _aahe_masking_weights(mono: np.ndarray) -> np.ndarray:
    stable = (mono >> (_AAHE_BIT_PLANE + 1)).astype(np.float64)
    n      = len(stable)
    w      = _AAHE_WINDOW
    energy = np.zeros(n, dtype=np.float64)
    for start in range(0, n, w):
        seg = stable[start:start + w]
        rms = float(np.sqrt(np.mean(seg ** 2)) + 1e-9)
        energy[start:start + len(seg)] = rms
    return energy

def _aahe_select_indices(weights: np.ndarray, n_bits: int, layout_seed: bytes) -> np.ndarray:
    threshold  = np.percentile(weights, _AAHE_PERCENTILE)
    candidates = np.flatnonzero(weights >= threshold)
    if len(candidates) < n_bits: return candidates
    scores = np.array([
        int.from_bytes(_blake2(layout_seed + int(idx).to_bytes(4, 'big'), size=8), 'big')
        for idx in candidates
    ], dtype=np.uint64)
    order = np.lexsort((scores, -weights[candidates]))
    return candidates[order[:n_bits]]

def _aahe_to_mono(params, samples: np.ndarray) -> np.ndarray:
    if samples.ndim == 1: return samples.astype(np.int64)
    return (np.sum(samples.astype(np.int64), axis=1) // samples.shape[1]).astype(np.int64)

def _aahe_layout_seed(params, key: str) -> bytes:
    meta = struct.pack('>IIB', params.framerate, params.nframes, params.nchannels)
    return _blake2(meta + (key or '').encode() + b'aahe-layout')

def _embed_aahe(carrier_path: str, data: bytes, out_path: str, key: str):
    params, samples = _read_wav(carrier_path)
    if params.sampwidth < 2: raise ValueError('AAHE requires 16-bit PCM WAV (sampwidth ≥ 2)')
    salt    = os.urandom(16)
    payload = _pack_aahe(data)
    bits_in = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    mono    = _aahe_to_mono(params, samples)
    weights = _aahe_masking_weights(mono)
    seed    = _aahe_layout_seed(params, key)
    salt_bits  = np.unpackbits(np.frombuffer(salt, dtype=np.uint8))
    total_bits = np.concatenate([salt_bits, bits_in])
    indices = _aahe_select_indices(weights, len(total_bits), seed)
    if len(indices) < len(total_bits):
        raise ValueError(f'AAHE: cover audio too short — need {len(total_bits)} sites, have {len(indices)}. Use longer WAV or smaller payload.')
    key_stream = _aahe_keystream(key, salt, len(bits_in))
    total_bits[len(salt_bits):] ^= key_stream
    plane = 1 << _AAHE_BIT_PLANE
    stego = mono.copy()
    for idx, bit in zip(indices[:len(total_bits)], total_bits):
        s = int(stego[idx])
        if ((s >> _AAHE_BIT_PLANE) & 1) != int(bit):
            stego[idx] = (s | plane) if bit else (s & ~plane)
    out_samples = samples.copy().astype(np.int64)
    lo, hi = (-32768, 32767) if params.sampwidth == 2 else (-(2**31), 2**31 - 1)
    if samples.ndim > 1:
        for ch in range(params.nchannels):
            out_samples[:, ch] = np.clip(stego, lo, hi)
    else:
        out_samples = np.clip(stego, lo, hi)
    dtype = np.int16 if params.sampwidth == 2 else np.int32
    _write_wav(out_path, params, out_samples.astype(dtype))

def _extract_aahe(stego_path: str, key: str) -> bytes:
    params, samples = _read_wav(stego_path)
    mono    = _aahe_to_mono(params, samples)
    weights = _aahe_masking_weights(mono)
    seed    = _aahe_layout_seed(params, key)
    salt_bit_count = 128
    salt_indices   = _aahe_select_indices(weights, salt_bit_count, seed)
    salt_bits      = np.array([(int(mono[i]) >> _AAHE_BIT_PLANE) & 1 for i in salt_indices], dtype=np.uint8)
    salt = np.packbits(salt_bits).tobytes()
    hdr_bit_count  = 80
    total_pre_bits = salt_bit_count + hdr_bit_count
    pre_indices    = _aahe_select_indices(weights, total_pre_bits, seed)
    hdr_bits_raw   = np.array(
        [(int(mono[pre_indices[i]]) >> _AAHE_BIT_PLANE) & 1 for i in range(salt_bit_count, total_pre_bits)], dtype=np.uint8)
    hdr_bits = (hdr_bits_raw ^ _aahe_keystream(key, salt, hdr_bit_count)) & 1
    hdr      = np.packbits(hdr_bits).tobytes()
    if hdr[:2] != _AAHE_MG: raise ValueError('AAHE: no payload found — wrong method or key')
    length     = int.from_bytes(hdr[2:6], 'big')
    stored_crc = int.from_bytes(hdr[6:10], 'big')
    payload_bits_count = length * 8
    total_bits         = salt_bit_count + hdr_bit_count + payload_bits_count
    all_indices        = _aahe_select_indices(weights, total_bits, seed)
    pay_bits_raw       = np.array(
        [(int(mono[all_indices[i]]) >> _AAHE_BIT_PLANE) & 1 for i in range(salt_bit_count + hdr_bit_count, total_bits)], dtype=np.uint8)
    ks_pay   = _aahe_keystream(key, salt, hdr_bit_count + payload_bits_count)
    pay_bits = (pay_bits_raw ^ ks_pay[hdr_bit_count:]) & 1
    payload  = np.packbits(pay_bits).tobytes()[:length]
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc: raise ValueError('AAHE: CRC mismatch — wrong key or corrupted audio')
    return payload