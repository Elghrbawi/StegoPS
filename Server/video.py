import os, struct, zlib, tempfile, shutil
import cv2
import numpy as np

_MAGIC = b'\xDE\xAD'
_HDR   = len(_MAGIC) + 4 + 4          # magic + length(4) + crc(4)  = 10 bytes

def _pack(data: bytes) -> bytes:
    crc = struct.pack('>I', zlib.crc32(data) & 0xFFFFFFFF)
    return _MAGIC + len(data).to_bytes(4, 'big') + crc + data

# ── Public API ────────────────────────────────────────────────────────────────
def embed(carrier_path: str, data: bytes, out_path: str, key: str, method: str):
    if method == 'frame_lsb':
        _embed_stream(carrier_path, data, out_path)
    else:
        raise ValueError(f'Unknown video steg method: {method}')

def extract(stego_path: str, key: str, method: str) -> bytes:
    if method == 'frame_lsb':
        return _extract_stream(stego_path)
    raise ValueError(f'Unknown video steg method: {method}')

# ── Streaming embed — constant ~2-frame memory footprint ─────────────────────
def _embed_stream(carrier_path: str, data: bytes, out_path: str):
    payload  = _pack(data)
    bits     = np.unpackbits(np.frombuffer(payload, dtype=np.uint8))
    n_bits   = len(bits)
    bit_idx  = 0

    cap = cv2.VideoCapture(carrier_path)
    if not cap.isOpened():
        raise ValueError('Could not open video file')
    fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
    w   = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    h   = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    n_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

    # Capacity check (quick)
    bpf = w * h * 3          # bits per frame
    if n_bits > bpf * max(n_frames, 1):
        cap.release()
        raise ValueError(f'Payload too large. Max capacity ≈ {n_frames * bpf // 8:,} bytes')

    # Use lossless FFV1 (AVI container) — fallback XVID
    tmp_fd, tmp_path = tempfile.mkstemp(suffix='.avi')
    os.close(tmp_fd)

    writer = None
    fourcc_list = [
        cv2.VideoWriter_fourcc(*'FFV1'),
        cv2.VideoWriter_fourcc(*'HFYU'),
        cv2.VideoWriter_fourcc(*'XVID'),
        cv2.VideoWriter_fourcc(*'mp4v'),
    ]
    for fourcc in fourcc_list:
        writer = cv2.VideoWriter(tmp_path, fourcc, fps, (w, h))
        if writer.isOpened():
            break
        writer.release()

    if not writer or not writer.isOpened():
        cap.release()
        raise ValueError('Could not open video writer — check OpenCV codecs')

    try:
        while True:
            ret, frame = cap.read()
            if not ret:
                break
            if bit_idx < n_bits:
                flat   = frame.reshape(-1)                   # view, no copy
                end    = min(bit_idx + len(flat), n_bits)
                count  = end - bit_idx
                # numpy vectorised LSB patch — very fast
                chunk          = flat[:count].astype(np.int16)
                chunk          = (chunk & 0xFE) | bits[bit_idx:end].astype(np.int16)
                flat_out       = flat.copy()
                flat_out[:count] = chunk.astype(np.uint8)
                bit_idx = end
                writer.write(flat_out.reshape(frame.shape))
            else:
                writer.write(frame)                          # no modification
    finally:
        cap.release()
        writer.release()

    # Move to final destination
    shutil.move(tmp_path, out_path)


# ── Streaming extract — stops as soon as payload is complete ──────────────────
def _extract_stream(stego_path: str) -> bytes:
    cap = cv2.VideoCapture(stego_path)
    if not cap.isOpened():
        raise ValueError('Could not open video file')

    hdr_bits_needed = _HDR * 8
    accumulated: list[int] = []
    header_done  = False
    payload_len  = 0
    total_needed = hdr_bits_needed

    try:
        while True:
            ret, frame = cap.read()
            if not ret:
                break
            flat = frame.reshape(-1)
            # extract LSBs of this frame
            lsbs = (flat.astype(np.uint8) & 1).tolist()
            accumulated.extend(lsbs)

            if not header_done and len(accumulated) >= hdr_bits_needed:
                hdr_arr = np.array(accumulated[:hdr_bits_needed], dtype=np.uint8)
                hdr     = np.packbits(hdr_arr).tobytes()
                if hdr[:2] != _MAGIC:
                    raise ValueError('No hidden payload found')
                payload_len  = int.from_bytes(hdr[2:6], 'big')
                total_needed = hdr_bits_needed + payload_len * 8
                header_done  = True

            if header_done and len(accumulated) >= total_needed:
                break
    finally:
        cap.release()

    if not header_done:
        raise ValueError('No hidden payload found')
    if len(accumulated) < total_needed:
        raise ValueError('Payload truncated — video too short')

    pay_arr = np.array(accumulated[hdr_bits_needed:total_needed], dtype=np.uint8)
    return np.packbits(pay_arr).tobytes()[:payload_len]