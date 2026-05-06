import wave, struct, math
import numpy as np

_MAGIC = b'\xDE\xAD'

def _pack(data: bytes) -> bytes:
    return _MAGIC + len(data).to_bytes(4, 'big') + data

def _bits_str(data: bytes) -> str:
    return ''.join(format(b, '08b') for b in data)

def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    if method == 'wav_lsb':
        _embed_wav_lsb(carrier_path, data, out_path)
    elif method == 'phase':
        _embed_phase(carrier_path, data, out_path)
    else:
        raise ValueError(f'Unknown audio steg method: {method}')

def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'wav_lsb':
        return _extract_wav_lsb(stego_path)
    elif method == 'phase':
        return _extract_phase(stego_path)
    else:
        raise ValueError(f'Unknown audio steg method: {method}')

def _read_wav(path: str):
    with wave.open(path, 'rb') as wf:
        params = wf.getparams()
        raw = wf.readframes(wf.getnframes())
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    samples = np.frombuffer(raw, dtype=dtype).copy()
    return params, samples

def _write_wav(path: str, params, samples: np.ndarray):
    dtype = np.int16 if params.sampwidth == 2 else np.int8
    with wave.open(path, 'wb') as wf:
        wf.setparams(params)
        wf.writeframes(samples.astype(dtype).tobytes())

def _embed_wav_lsb(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    payload = _pack(data)
    bits = _bits_str(payload)
    if len(bits) > len(samples):
        raise ValueError(f'Payload too large. Capacity: {len(samples)//8} bytes')
    for i, bit in enumerate(bits):
        samples[i] = (int(samples[i]) & ~1) | int(bit)
    _write_wav(out_path, params, samples)

def _extract_wav_lsb(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    magic_len = (len(_MAGIC) + 4) * 8
    header_bits = ''.join(str(int(s) & 1) for s in samples[:magic_len])
    header_bytes = bytes(int(header_bits[i:i+8], 2) for i in range(0, magic_len, 8))
    if header_bytes[:2] != _MAGIC:
        raise ValueError('No hidden payload found')
    length = int.from_bytes(header_bytes[2:6], 'big')
    total_bits = magic_len + length * 8
    if total_bits > len(samples):
        raise ValueError('Payload length exceeds audio samples')
    all_bits = ''.join(str(int(s) & 1) for s in samples[:total_bits])
    payload_bits = all_bits[magic_len:]
    return bytes(int(payload_bits[i:i+8], 2) for i in range(0, len(payload_bits), 8))

SEG_LEN = 1024

def _embed_phase(carrier_path: str, data: bytes, out_path: str):
    params, samples = _read_wav(carrier_path)
    mono = samples if samples.ndim == 1 else samples[:, 0]
    payload = _pack(data)
    bits = [int(b) for byte in payload for b in format(byte, '08b')]
    n_segs = len(mono) // SEG_LEN
    if len(bits) > n_segs:
        raise ValueError(f'Payload too large for phase coding. Max: {n_segs // 8} bytes')
    result = mono.astype(np.float64).copy()
    for i, bit in enumerate(bits):
        seg = result[i * SEG_LEN:(i + 1) * SEG_LEN]
        fft = np.fft.rfft(seg)
        mid = len(fft) // 2
        mag = np.abs(fft[mid])
        phase = np.pi / 2 if bit == 1 else 0.0
        fft[mid] = mag * np.exp(1j * phase)
        result[i * SEG_LEN:(i + 1) * SEG_LEN] = np.fft.irfft(fft)[:SEG_LEN]
    out_samples = np.clip(result, -32768, 32767).astype(np.int16)
    _write_wav(out_path, params, out_samples)

def _extract_phase(stego_path: str) -> bytes:
    params, samples = _read_wav(stego_path)
    mono = samples if samples.ndim == 1 else samples[:, 0]
    n_segs = len(mono) // SEG_LEN
    raw_bits = []
    for i in range(n_segs):
        seg = mono[i * SEG_LEN:(i + 1) * SEG_LEN].astype(np.float64)
        fft = np.fft.rfft(seg)
        mid = len(fft) // 2
        phase = np.angle(fft[mid])
        raw_bits.append(1 if phase > np.pi / 4 else 0)
    magic_and_len = (len(_MAGIC) + 4) * 8
    header_bits = raw_bits[:magic_and_len]
    header = bytes(int(''.join(str(b) for b in header_bits[i:i+8]), 2) for i in range(0, magic_and_len, 8))
    if header[:2] != _MAGIC:
        raise ValueError('No hidden payload found')
    length = int.from_bytes(header[2:6], 'big')
    payload_bits = raw_bits[magic_and_len:magic_and_len + length * 8]
    return bytes(int(''.join(str(b) for b in payload_bits[i:i+8]), 2) for i in range(0, len(payload_bits), 8))