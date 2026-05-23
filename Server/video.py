import shutil
import cv2
import numpy as np
_MAGIC = b'\xDE\xAD'
def _pack(data: bytes) -> bytes: return _MAGIC + len(data).to_bytes(4, 'big') + data
def _read_frames(path: str):
    cap = cv2.VideoCapture(path)
    fps = cap.get(cv2.CAP_PROP_FPS)
    w = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH)); h = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    frames = []
    while True:
        ret, frame = cap.read()
        if not ret: break
        frames.append(frame.copy())
    cap.release()
    return frames, fps, w, h
def _write_frames(frames, fps, w, h, out_path):
    writer = cv2.VideoWriter(out_path, cv2.VideoWriter_fourcc(*'FFV1'), fps, (w, h))
    if not writer.isOpened():
        writer = cv2.VideoWriter(out_path, cv2.VideoWriter_fourcc(*'XVID'), fps, (w, h))
    for f in frames: writer.write(f)
    writer.release()
# ── EMBED / EXTRACT ───────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    if method == 'frame_lsb': _embed_frame_lsb(carrier_path, data, out_path)
    else: raise ValueError(f'Unknown video steg method: {method}')
def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'frame_lsb': return _extract_frame_lsb(stego_path)
    else: raise ValueError(f'Unknown video steg method: {method}')
# ── FRAME LSB ─────────────────────────────────────────────────────────────────
def _embed_frame_lsb(carrier_path: str, data: bytes, out_path: str):
    frames, fps, w, h = _read_frames(carrier_path)
    if not frames: raise ValueError('Could not read video frames')
    payload = _pack(data)
    bits = ''.join(format(b, '08b') for b in payload)
    bpf = w * h * 3
    if len(bits) > len(frames) * bpf: raise ValueError(f'Payload too large. Capacity: {len(frames)*bpf//8} bytes')
    bit_idx = 0
    for fi in range(len(frames)):
        if bit_idx >= len(bits): break
        flat = frames[fi].flatten().astype(np.int32)
        end = min(bit_idx + bpf, len(bits))
        for i, bi in enumerate(range(bit_idx, end)): flat[i] = (flat[i] & 0xFE) | int(bits[bi])
        frames[fi] = flat.astype(np.uint8).reshape(frames[fi].shape)
        bit_idx = end
    avi = out_path if out_path.endswith('.avi') else out_path.rsplit('.', 1)[0] + '.avi'
    _write_frames(frames, fps, w, h, avi)
    if avi != out_path: shutil.move(avi, out_path)
def _extract_frame_lsb(stego_path: str) -> bytes:
    frames, _, _, _ = _read_frames(stego_path)
    if not frames: raise ValueError('Could not read video frames')
    hlen = (len(_MAGIC) + 4) * 8
    all_bits = []; header_done = False; payload_len = 0; total_needed = hlen
    for frame in frames:
        flat = frame.flatten()
        all_bits.extend(int(v) & 1 for v in flat)
        if not header_done and len(all_bits) >= hlen:
            hbits = ''.join(str(b) for b in all_bits[:hlen])
            hdr = bytes(int(hbits[i:i+8], 2) for i in range(0, hlen, 8))
            if hdr[:2] != _MAGIC: raise ValueError('No hidden payload found')
            payload_len = int.from_bytes(hdr[2:6], 'big')
            total_needed = hlen + payload_len * 8; header_done = True
        if header_done and len(all_bits) >= total_needed: break
    if not header_done: raise ValueError('No hidden payload found')
    pbits = ''.join(str(b) for b in all_bits[hlen:total_needed])
    return bytes(int(pbits[i:i+8], 2) for i in range(0, len(pbits), 8))
