import wave
import numpy as np
_MAGIC = b'\xDE\xAD'
SEG_LEN = 1024
def _pack(data: bytes) -> bytes: return _MAGIC + len(data).to_bytes(4, 'big') + data
def _bits(data: bytes) -> str: return ''.join(format(b, '08b') for b in data)
def _read_wav(path: str):
    with wave.open(path, 'rb') as wf:
        params = wf.getparams(); raw = wf.readframes(wf.getnframes())
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    return params, np.frombuffer(raw, dtype=dtype).copy()
def _write_wav(path: str, params, samples: np.ndarray):
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    with wave.open(path, 'wb') as wf:
        wf.setparams(params); wf.writeframes(samples.astype(dtype).tobytes())
# ── EMBED / EXTRACT ───────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    if method == 'wav_lsb':   _embed_wav_lsb(carrier_path, data, out_path)
    elif method == 'phase':   _embed_phase(carrier_path, data, out_path)
    else: raise ValueError(f'Unknown audio steg method: {method}')
def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'wav_lsb':   return _extract_wav_lsb(stego_path)
    elif method == 'phase':   return _extract_phase(stego_path)
    else: raise ValueError(f'Unknown audio steg method: {method}')
# ── WAV LSB ───────────────────────────────────────────────────────────────────
def _embed_wav_lsb(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    payload = _pack(data); bits = _bits(payload)
    if len(bits) > len(samples): raise ValueError(f'Payload too large. Capacity: {len(samples)//8} bytes')
    for i, bit in enumerate(bits): samples[i] = (int(samples[i]) & ~1) | int(bit)
    _write_wav(out_path, params, samples)
def _extract_wav_lsb(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    hlen = (len(_MAGIC) + 4) * 8
    hbits = ''.join(str(int(s) & 1) for s in samples[:hlen])
    hdr = bytes(int(hbits[i:i+8], 2) for i in range(0, hlen, 8))
    if hdr[:2] != _MAGIC: raise ValueError('No hidden payload found')
    length = int.from_bytes(hdr[2:6], 'big')
    total = hlen + length * 8
    if total > len(samples): raise ValueError('Payload length exceeds audio samples')
    all_bits = ''.join(str(int(s) & 1) for s in samples[:total])
    pbits = all_bits[hlen:]
    return bytes(int(pbits[i:i+8], 2) for i in range(0, len(pbits), 8))
# ── PHASE CODING ──────────────────────────────────────────────────────────────
def _embed_phase(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    mono = samples if samples.ndim == 1 else samples[:, 0]
    payload = _pack(data)
    bits = [int(b) for byte in payload for b in format(byte, '08b')]
    n_segs = len(mono) // SEG_LEN
    if len(bits) > n_segs: raise ValueError(f'Payload too large for phase coding. Max: {n_segs // 8} bytes')
    result = mono.astype(np.float64).copy()
    for i, bit in enumerate(bits):
        seg = result[i * SEG_LEN:(i + 1) * SEG_LEN]
        fft = np.fft.rfft(seg); mid = len(fft) // 2
        fft[mid] = np.abs(fft[mid]) * np.exp(1j * (np.pi / 2 if bit else 0.0))
        result[i * SEG_LEN:(i + 1) * SEG_LEN] = np.fft.irfft(fft)[:SEG_LEN]
    _write_wav(out_path, params, np.clip(result, -32768, 32767).astype(np.int16))
def _extract_phase(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    mono = samples if samples.ndim == 1 else samples[:, 0]
    n_segs = len(mono) // SEG_LEN
    raw_bits = []
    for i in range(n_segs):
        fft = np.fft.rfft(mono[i * SEG_LEN:(i + 1) * SEG_LEN].astype(np.float64))
        raw_bits.append(1 if np.angle(fft[len(fft) // 2]) > np.pi / 4 else 0)
    hlen = (len(_MAGIC) + 4) * 8
    hdr = bytes(int(''.join(str(b) for b in raw_bits[i:i+8]), 2) for i in range(0, hlen, 8))
    if hdr[:2] != _MAGIC: raise ValueError('No hidden payload found')
    length = int.from_bytes(hdr[2:6], 'big')
    pbits = raw_bits[hlen:hlen + length * 8]
    return bytes(int(''.join(str(b) for b in pbits[i:i+8]), 2) for i in range(0, len(pbits), 8))
