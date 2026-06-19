import wave, struct, zlib, hashlib, os, tempfile, subprocess, shutil
import numpy as np

_MAGIC      = b'\xDE\xAD'
_AAHE_MAGIC = b'\xAA\xEE'
_HDR_LEN    = len(_MAGIC) + 4 + 4      # magic(2) + length(4) + crc(4)
SEG_LEN     = 1024
_PHASE_BIN  = SEG_LEN // 4

_AAHE_BIT_PLANE  = 1
_AAHE_WINDOW     = 256
_AAHE_PERCENTILE = 55
_AAHE_SALT_BITS  = 128

# Common absolute paths to search for ffmpeg when PATH is stripped (web server env)
_FFMPEG_SEARCH_PATHS = [
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    '/bin/ffmpeg',
    '/usr/sbin/ffmpeg',
    '/opt/ffmpeg/bin/ffmpeg',
    '/opt/local/bin/ffmpeg',
    '/snap/bin/ffmpeg',
]

WHATSAPP_RESISTANT = set()

# ── FILE-PAYLOAD WRAPPER ──────────────────────────────────────────────────────
# Binary/file payloads are wrapped so the filename survives round-trips:
#   [1 byte flag=0x46] [2 bytes name_len] [name bytes] [4 bytes orig_size] [content]
_FILE_FLAG = 0x46  # 'F'

def wrap_file_payload(filename: str, data: bytes) -> bytes:
    name_b = filename.encode('utf-8', errors='replace')[:255]
    header = bytes([_FILE_FLAG]) + len(name_b).to_bytes(2, 'big') + name_b + len(data).to_bytes(4, 'big')
    return header + data

def unwrap_file_payload(data: bytes):
    """Returns (filename, content) or None if not a wrapped file payload."""
    if len(data) < 8 or data[0] != _FILE_FLAG:
        return None
    name_len = int.from_bytes(data[1:3], 'big')
    if len(data) < 3 + name_len + 4:
        return None
    filename  = data[3:3 + name_len].decode('utf-8', errors='replace')
    orig_size = int.from_bytes(data[3 + name_len:3 + name_len + 4], 'big')
    content   = data[3 + name_len + 4:]
    if len(content) != orig_size:
        return None
    return filename, content

# ── PAYLOAD COMPRESSION ───────────────────────────────────────────────────────
_COMPRESS_MAGIC = b'\xC0\x4E'

def _maybe_compress(data: bytes) -> bytes:
    compressed = zlib.compress(data, level=9)
    if len(compressed) < len(data) * 0.95:
        return _COMPRESS_MAGIC + len(data).to_bytes(4, 'big') + compressed
    return data

def _maybe_decompress(data: bytes) -> bytes:
    if data[:2] == _COMPRESS_MAGIC:
        orig_size    = int.from_bytes(data[2:6], 'big')
        decompressed = zlib.decompress(data[6:])
        if len(decompressed) != orig_size:
            raise ValueError('Decompression size mismatch — data may be corrupt.')
        return decompressed
    return data

# ── FFMPEG / AUDIO CONVERSION ─────────────────────────────────────────────────
def _find_ffmpeg() -> str | None:
    """
    Find the ffmpeg binary, even when PATH is stripped (web-server environment).
    Tries shutil.which first, then a list of common absolute locations.
    Returns the absolute path or None.
    """
    found = shutil.which('ffmpeg')
    if found:
        return found
    for p in _FFMPEG_SEARCH_PATHS:
        if os.path.isfile(p) and os.access(p, os.X_OK):
            return p
    return None

def _convert_with_ffmpeg(ffmpeg_bin: str, src: str, dst: str) -> bool:
    """Run ffmpeg to convert src -> dst (16-bit mono 44100 Hz WAV). Returns True on success."""
    cmd = [ffmpeg_bin, '-y', '-i', src, '-ar', '44100', '-ac', '1', '-sample_fmt', 's16', dst]
    r   = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    return r.returncode == 0 and os.path.exists(dst) and os.path.getsize(dst) > 44

def _convert_with_miniaudio(src: str, dst: str) -> bool:
    """
    Convert any audio format to 16-bit mono 44100 Hz WAV using miniaudio.
    miniaudio is a pure-Python wheel with embedded C decoders — no external
    binaries needed.  Supports MP3, OGG (Vorbis), FLAC, WAV, AIFF, and more.
    """
    try:
        import miniaudio
        stream = miniaudio.stream_file(
            src,
            output_format=miniaudio.SampleFormat.SIGNED16,
            nchannels=1,
            sample_rate=44100,
        )
        chunks = bytearray()
        for chunk in stream:
            chunks.extend(chunk)
        if not chunks:
            return False
        samples = np.frombuffer(bytes(chunks), dtype=np.int16)
        with wave.open(dst, 'wb') as wf:
            wf.setnchannels(1)
            wf.setsampwidth(2)
            wf.setframerate(44100)
            wf.writeframes(samples.tobytes())
        return os.path.getsize(dst) > 44
    except Exception:
        return False

def _convert_with_pydub(ffmpeg_bin: str | None, src: str, dst: str) -> bool:
    """Convert using pydub, with explicit ffmpeg path if known."""
    try:
        from pydub import AudioSegment
        # Override pydub's ffmpeg path so it works even with stripped PATH
        if ffmpeg_bin:
            AudioSegment.converter = ffmpeg_bin
            AudioSegment.ffmpeg    = ffmpeg_bin
            # Also try to find ffprobe next to ffmpeg
            ffprobe = os.path.join(os.path.dirname(ffmpeg_bin), 'ffprobe')
            if os.path.isfile(ffprobe):
                AudioSegment.ffprobe = ffprobe
        ext   = os.path.splitext(src)[1].lstrip('.').lower() or 'mp3'
        audio = AudioSegment.from_file(src, format=ext)
        audio = audio.set_frame_rate(44100).set_channels(1).set_sample_width(2)
        audio.export(dst, format='wav')
        return os.path.exists(dst) and os.path.getsize(dst) > 44
    except Exception:
        return False

def _ensure_wav(path: str) -> tuple[str, bool]:
    """
    Return a 16-bit mono 44100 Hz WAV path for any audio input.
    WAV files pass through unchanged.  For everything else we try:
      1. miniaudio  — pure Python, no external binary (MP3/OGG/FLAC/WAV/AIFF)
      2. ffmpeg     — searched across common absolute paths, not just PATH
      3. pydub      — uses the ffmpeg we found (or lets pydub search itself)
    Raises ValueError with a clear message if none work.
    """
    if os.path.splitext(path)[1].lower() == '.wav':
        return path, False

    tmp = tempfile.NamedTemporaryFile(suffix='.wav', delete=False)
    tmp.close()
    tmp_path = tmp.name

    errors = []

    # 1 ── miniaudio (pure Python, no PATH dependency) ───────────────────────
    if _convert_with_miniaudio(path, tmp_path):
        return tmp_path, True
    errors.append('miniaudio: conversion failed or format not supported')

    # 2 ── ffmpeg via absolute path search ───────────────────────────────────
    ffmpeg_bin = _find_ffmpeg()
    if ffmpeg_bin:
        if _convert_with_ffmpeg(ffmpeg_bin, path, tmp_path):
            return tmp_path, True
        errors.append(f'ffmpeg ({ffmpeg_bin}): conversion failed')
    else:
        errors.append('ffmpeg: not found in PATH or common locations')

    # 3 ── pydub (configures its own ffmpeg search, may succeed on some hosts) ─
    if _convert_with_pydub(ffmpeg_bin, path, tmp_path):
        return tmp_path, True
    errors.append('pydub: conversion failed')

    # All methods failed
    try:
        os.unlink(tmp_path)
    except OSError:
        pass

    raise ValueError(
        'Could not convert audio to WAV. Tried miniaudio, ffmpeg, and pydub. '
        'Details: ' + ' | '.join(errors) + '. '
        'Fix: run  pip install miniaudio  on the server, or install ffmpeg.'
    )

# ── PACK / UNPACK ─────────────────────────────────────────────────────────────
def _pack(data: bytes, magic: bytes = _MAGIC) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return magic + len(data).to_bytes(4, 'big') + crc + data

def _unpack_header(hdr: bytes, magic: bytes, label: str):
    if hdr[:2] != magic:
        raise ValueError(
            f'{label}: no hidden payload found — wrong key, wrong method, '
            'or the audio was re-encoded after embedding.'
        )
    return int.from_bytes(hdr[2:6], 'big'), int.from_bytes(hdr[6:10], 'big')

def _verify_crc(payload: bytes, stored_crc: int, label: str) -> bytes:
    if (zlib.crc32(payload) & 0xFFFFFFFF) != stored_crc:
        raise ValueError(
            f'{label}: checksum mismatch — wrong key, or the audio was modified after embedding.'
        )
    return payload

# ── WAV I/O ───────────────────────────────────────────────────────────────────
def _read_wav(path: str):
    with wave.open(path, 'rb') as wf:
        params = wf.getparams()
        raw    = wf.readframes(wf.getnframes())
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    return params, np.frombuffer(raw, dtype=dtype).copy()

def _to_frames(params, flat: np.ndarray) -> np.ndarray:
    nch     = max(1, params.nchannels)
    n_frames = len(flat) // nch
    return flat[:n_frames * nch].reshape(n_frames, nch)

def _write_wav(path: str, params, samples: np.ndarray):
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    with wave.open(path, 'wb') as wf:
        wf.setparams(params)
        wf.writeframes(samples.reshape(-1).astype(dtype).tobytes())

def _sample_bounds(params):
    return (-32768, 32767) if params.sampwidth == 2 else (-128, 127)

# ── PUBLIC API ────────────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    embed._last_actual_method = method
    wav_path, is_tmp = _ensure_wav(carrier_path)
    try:
        payload = _maybe_compress(data)
        if   method == 'wav_lsb': _embed_wav_lsb(wav_path, payload, out_path)
        elif method == 'phase':   _embed_phase(wav_path, payload, out_path)
        elif method == 'aahe':    _embed_aahe(wav_path, payload, out_path, key)
        else: raise ValueError(f'Unknown audio steg method: {method}')
    finally:
        if is_tmp:
            try: os.unlink(wav_path)
            except OSError: pass

embed._last_actual_method = None


def extract(stego_path: str, key: str, method: str) -> bytes:
    wav_path, is_tmp = _ensure_wav(stego_path)
    try:
        if   method == 'wav_lsb': raw = _extract_wav_lsb(wav_path)
        elif method == 'phase':   raw = _extract_phase(wav_path)
        elif method == 'aahe':    raw = _extract_aahe(wav_path, key)
        else: raise ValueError(f'Unknown audio steg method: {method}')
        return _maybe_decompress(raw)
    finally:
        if is_tmp:
            try: os.unlink(wav_path)
            except OSError: pass


def _carrier_capacity(carrier_path: str) -> dict:
    wav_path, is_tmp = _ensure_wav(carrier_path)
    try:
        params, flat = _read_wav(wav_path)
        channel0  = _to_frames(params, flat)[:, 0]
        lsb_cap   = max(0, len(flat) // 8 - _HDR_LEN)
        n_segs    = len(channel0) // SEG_LEN
        phase_cap = max(0, n_segs // 8 - _HDR_LEN)
        try:
            weights   = _aahe_masking_weights(channel0)
            threshold = np.percentile(weights, _AAHE_PERCENTILE)
            candidates = int(np.count_nonzero(weights >= threshold))
            aahe_cap  = max(0, (candidates - _AAHE_SALT_BITS) // 8 - _HDR_LEN)
        except Exception:
            aahe_cap = 0
        return {'wav_lsb': lsb_cap, 'phase': phase_cap, 'aahe': aahe_cap}
    finally:
        if is_tmp:
            try: os.unlink(wav_path)
            except OSError: pass

# ── WAV LSB ───────────────────────────────────────────────────────────────────
def _embed_wav_lsb(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    payload = _pack(data)
    nbits   = len(payload) * 8
    if nbits > len(samples):
        cap_bytes = max(0, len(samples) // 8 - _HDR_LEN)
        raise ValueError(
            f'Payload too large for WAV LSB. '
            f'Carrier capacity: {cap_bytes:,} bytes, payload needs {len(data):,} bytes. '
            f'Use a longer audio file or try AAHE method.'
        )
    bits = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    samples[:nbits] = (samples[:nbits].astype(np.int16) & np.int16(-2)) | bits.astype(np.int16)
    _write_wav(out_path, params, samples)


def _extract_wav_lsb(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    hdr_bits = _HDR_LEN * 8
    if hdr_bits > len(samples):
        raise ValueError('WAV LSB: audio too short to contain a payload header.')
    raw_hdr  = np.packbits(samples[:hdr_bits].astype(np.uint8) & 1).tobytes()
    length, stored_crc = _unpack_header(raw_hdr, _MAGIC, 'WAV LSB')
    total    = hdr_bits + length * 8
    if total > len(samples):
        raise ValueError('WAV LSB: declared payload length exceeds available samples.')
    raw_pay  = np.packbits(samples[:total].astype(np.uint8) & 1).tobytes()
    return _verify_crc(raw_pay[_HDR_LEN:_HDR_LEN + length], stored_crc, 'WAV LSB')

# ── Phase Coding ──────────────────────────────────────────────────────────────
def _embed_phase(carrier_path: str, data: bytes, out_path: str):
    params, flat = _read_wav(carrier_path)
    frames  = _to_frames(params, flat)
    channel = frames[:, 0].astype(np.float64).copy()
    payload = _pack(data)
    bits    = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    n_segs  = len(channel) // SEG_LEN
    if len(bits) > n_segs:
        raise ValueError(
            f'Payload too large for Phase Coding. '
            f'Capacity: {max(0, n_segs//8 - _HDR_LEN):,} bytes — try WAV LSB or AAHE instead.'
        )
    lo, hi = _sample_bounds(params)
    for i, bit in enumerate(bits):
        seg = channel[i*SEG_LEN:(i+1)*SEG_LEN]
        fft = np.fft.rfft(seg)
        mag = np.abs(fft[_PHASE_BIN])
        fft[_PHASE_BIN] = mag * np.exp(1j * (np.pi/2 if bit else -np.pi/2))
        channel[i*SEG_LEN:(i+1)*SEG_LEN] = np.fft.irfft(fft, n=SEG_LEN)
    frames[:, 0] = np.clip(channel, lo, hi)
    out_flat = frames.reshape(-1)
    if len(out_flat) < len(flat):
        out_flat = np.concatenate([out_flat, flat[len(out_flat):]])
    _write_wav(out_path, params, out_flat)


def _extract_phase(stego_path: str) -> bytes:
    params, flat = _read_wav(stego_path)
    channel  = _to_frames(params, flat)[:, 0].astype(np.float64)
    n_segs   = len(channel) // SEG_LEN
    hdr_bits = _HDR_LEN * 8
    if hdr_bits > n_segs:
        raise ValueError('Phase Coding: audio too short to contain a payload header.')
    def read_bits(count):
        out = np.empty(count, dtype=np.uint8)
        for i in range(count):
            phase  = np.angle(np.fft.rfft(channel[i*SEG_LEN:(i+1)*SEG_LEN])[_PHASE_BIN])
            out[i] = 1 if phase > 0 else 0
        return out
    hdr    = np.packbits(read_bits(hdr_bits)).tobytes()
    length, stored_crc = _unpack_header(hdr, _MAGIC, 'Phase Coding')
    total_bits = hdr_bits + length * 8
    if total_bits > n_segs:
        raise ValueError('Phase Coding: declared payload length exceeds available segments.')
    raw = np.packbits(read_bits(total_bits)).tobytes()
    return _verify_crc(raw[_HDR_LEN:_HDR_LEN + length], stored_crc, 'Phase Coding')

# ── AAHE ──────────────────────────────────────────────────────────────────────
def _blake2(data: bytes, size: int = 32) -> bytes:
    return hashlib.blake2b(data, digest_size=size).digest()

def _aahe_rng(*parts: bytes) -> np.random.Generator:
    seed = int.from_bytes(_blake2(b''.join(parts), size=8), 'big')
    return np.random.default_rng(seed)

def _aahe_keystream(key: str, salt: bytes, n_bits: int) -> np.ndarray:
    return _aahe_rng((key or '').encode(), salt, b'ks').integers(0, 2, size=n_bits, dtype=np.uint8)

def _aahe_masking_weights(channel: np.ndarray) -> np.ndarray:
    stable   = (channel.astype(np.int64) >> (_AAHE_BIT_PLANE + 1)).astype(np.float64)
    n, w     = len(stable), _AAHE_WINDOW
    windows  = np.pad(stable, (0, (-n) % w)).reshape(-1, w)
    rms      = np.sqrt(np.mean(windows ** 2, axis=1)) + 1e-9
    return np.repeat(rms, w)[:n]

def _aahe_select_indices(weights: np.ndarray, n_bits: int, seed: bytes) -> np.ndarray:
    threshold  = np.percentile(weights, _AAHE_PERCENTILE)
    candidates = np.flatnonzero(weights >= threshold)
    if len(candidates) <= n_bits:
        return candidates
    scores = _aahe_rng(seed).random(len(candidates))
    order  = np.lexsort((scores, -weights[candidates]))
    return candidates[order[:n_bits]]

def _aahe_layout_seed(key: str) -> bytes:
    return _blake2((key or '').encode() + b'layout')

def _embed_aahe(carrier_path: str, data: bytes, out_path: str, key: str):
    params, flat = _read_wav(carrier_path)
    if params.sampwidth < 2:
        raise ValueError('AAHE requires 16-bit (or wider) PCM WAV audio.')
    frames  = _to_frames(params, flat)
    channel = frames[:, 0].astype(np.int64).copy()
    salt    = os.urandom(_AAHE_SALT_BITS // 8)
    payload = _pack(data, _AAHE_MAGIC)
    payload_bits = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    salt_bits    = np.unpackbits(np.frombuffer(salt, dtype=np.uint8))
    total_bits   = np.concatenate([
        salt_bits,
        payload_bits ^ _aahe_keystream(key, salt, len(payload_bits))
    ])
    weights = _aahe_masking_weights(channel)
    seed    = _aahe_layout_seed(key)
    indices = _aahe_select_indices(weights, len(total_bits), seed)
    if len(indices) < len(total_bits):
        raise ValueError(
            f'AAHE: carrier audio too short — need {len(total_bits):,} embed sites, '
            f'only {len(indices):,} available. Use a longer audio file.'
        )
    plane = 1 << _AAHE_BIT_PLANE
    for idx, bit in zip(indices, total_bits):
        s = int(channel[idx])
        channel[idx] = (s | plane) if bit else (s & ~plane)
    lo, hi = _sample_bounds(params)
    frames[:, 0] = np.clip(channel, lo, hi)
    _write_wav(out_path, params, frames)


def _extract_aahe(stego_path: str, key: str) -> bytes:
    params, flat = _read_wav(stego_path)
    channel = _to_frames(params, flat)[:, 0].astype(np.int64)
    weights = _aahe_masking_weights(channel)
    seed    = _aahe_layout_seed(key)

    def read_bits(count: int) -> np.ndarray:
        idx = _aahe_select_indices(weights, count, seed)
        if len(idx) < count:
            raise ValueError(
                'AAHE: audio too short to contain a payload, '
                'or it was re-encoded after embedding.'
            )
        return ((channel[idx] >> _AAHE_BIT_PLANE) & 1).astype(np.uint8)

    salt_bits = read_bits(_AAHE_SALT_BITS)
    salt      = np.packbits(salt_bits).tobytes()
    hdr_n     = _HDR_LEN * 8
    raw       = read_bits(_AAHE_SALT_BITS + hdr_n)
    hdr_bits  = (raw[_AAHE_SALT_BITS:] ^ _aahe_keystream(key, salt, hdr_n)) & 1
    length, stored_crc = _unpack_header(np.packbits(hdr_bits).tobytes(), _AAHE_MAGIC, 'AAHE')
    pay_n     = length * 8
    raw_all   = read_bits(_AAHE_SALT_BITS + hdr_n + pay_n)
    ks        = _aahe_keystream(key, salt, hdr_n + pay_n)
    pay_bits  = (raw_all[_AAHE_SALT_BITS + hdr_n:] ^ ks[hdr_n:]) & 1
    payload   = np.packbits(pay_bits).tobytes()[:length]
    return _verify_crc(payload, stored_crc, 'AAHE')
