// ============================================================
// StegoPS — Main JavaScript
// File: frontend/js/main.js
// ============================================================

// ── CHAIN BUILDER ──
const chainColors = {
  classical:  { bg:'rgba(124,58,237,0.15)', color:'#a78bfa', border:'#7c3aed', num:'#7c3aed' },
  symmetric:  { bg:'rgba(37,99,235,0.15)',  color:'#60a5fa', border:'#2563eb', num:'#2563eb' },
  asymmetric: { bg:'rgba(5,150,105,0.15)',  color:'#34d399', border:'#059669', num:'#059669' },
  custom:     { bg:'rgba(217,119,6,0.15)',  color:'#fbbf24', border:'#d97706', num:'#d97706' },
  hashing:    { bg:'rgba(220,38,38,0.15)',  color:'#f87171', border:'#dc2626', num:'#dc2626' },
};

let chain = [];

function addToChain(name, category, slug) {
  chain.push({ name, category, slug, key: '' });
  renderChain();
}

function removeFromChain(idx) {
  chain.splice(idx, 1);
  renderChain();
}

function clearChain() {
  chain = [];
  renderChain();
}

function renderChain() {
  const container = document.getElementById('chain-steps');
  if (!container) return;

  document.getElementById('chain-count').textContent = chain.length;

  const strength = Math.min(100,
    chain.length * 6 +
    (chain.some(c => c.category === 'symmetric')  ? 22 : 0) +
    (chain.some(c => c.category === 'asymmetric') ? 18 : 0) +
    (chain.some(c => c.category === 'hashing')    ? 10 : 0)
  );
  document.getElementById('strength-fill').style.width = strength + '%';
  document.getElementById('strength-val').textContent  = strength + '%';

  // Update hidden input for form submission
  const chainInput = document.getElementById('chain-data');
  if (chainInput) chainInput.value = JSON.stringify(chain);

  container.innerHTML = '';
  if (chain.length === 0) {
    container.innerHTML = `
      <div class="chain-empty">
        <span style="font-size:32px">⛓</span>
        <span>← Click algorithms to add them</span>
        <span style="font-size:9px;opacity:.5">Build your encryption pipeline</span>
      </div>`;
    return;
  }

  chain.forEach((step, i) => {
    const c = chainColors[step.category];
    if (i > 0) {
      const arrow = document.createElement('div');
      arrow.className = 'step-arrow'; arrow.textContent = '↓';
      container.appendChild(arrow);
    }
    const el = document.createElement('div');
    el.className = 'chain-step';
    el.style.background = c.bg; el.style.borderColor = c.border;
    el.innerHTML = `
      <div class="step-num" style="background:${c.num};color:#fff">${i+1}</div>
      <div>
        <div class="step-name" style="color:${c.color}">${step.name}</div>
        <div class="step-cat">${step.category.toUpperCase()}</div>
      </div>
      <input type="password" class="step-key" placeholder="Enter key..."
             value="${step.key}" onchange="updateStepKey(${i}, this.value)" />
      <button class="step-remove" onclick="removeFromChain(${i})" type="button">✕</button>`;
    container.appendChild(el);
  });
}

function updateStepKey(idx, val) {
  if (chain[idx]) {
    chain[idx].key = val;
    const chainInput = document.getElementById('chain-data');
    if (chainInput) chainInput.value = JSON.stringify(chain);
  }
}

// ── MEDIA TYPE SWITCH ──
const mediaInfo = {
  image: { icon:'🖼', sub:'PNG, JPG, BMP supported', technique:'LSB Steganography' },
  audio: { icon:'🎵', sub:'WAV, MP3 supported',      technique:'Phase Coding / Echo Hiding' },
  video: { icon:'🎬', sub:'MP4, AVI supported',       technique:'Frame-based LSB Embedding' },
};

function switchMedia(type) {
  ['image','audio','video'].forEach(t => {
    const tab = document.getElementById('mt-' + t);
    if (tab) tab.classList.toggle('active', t === type);
  });
  const icon = document.getElementById('carrier-icon');
  const sub  = document.getElementById('carrier-sub');
  const tech = document.getElementById('carrier-technique');
  if (icon) icon.textContent = mediaInfo[type].icon;
  if (sub)  sub.textContent  = mediaInfo[type].sub;
  if (tech) tech.textContent = mediaInfo[type].technique;
  const input = document.getElementById('media-type-input');
  if (input) input.value = type;
}

// ── PAYLOAD TYPE SWITCH ──
function switchPayloadType(type) {
  document.getElementById('pt-text')?.classList.toggle('active', type === 'text');
  document.getElementById('pt-file')?.classList.toggle('active', type === 'file');
  const textArea = document.getElementById('payload-text');
  const fileArea = document.getElementById('payload-file');
  if (textArea) textArea.style.display = type === 'text' ? 'block' : 'none';
  if (fileArea) fileArea.style.display = type === 'file' ? 'block' : 'none';
  const input = document.getElementById('payload-type-input');
  if (input) input.value = type;
}

// ── TOGGLE ──
function toggleSwitch(el) {
  el.classList.toggle('on');
  const target = el.dataset.target;
  if (target) {
    const t = document.getElementById(target);
    if (t) t.style.display = el.classList.contains('on') ? 'block' : 'none';
  }
}

// ── COPY TO CLIPBOARD ──
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Copied!';
    setTimeout(() => btn.textContent = orig, 2000);
  });
}

// ── FLASH MESSAGES (auto-hide) ──
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });
});

// ── FILE UPLOAD PREVIEW ──
function handleFileSelect(input, previewId) {
  const file = input.files[0];
  if (!file) return;
  const preview = document.getElementById(previewId);
  if (preview) {
    preview.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px">
        <span style="font-size:24px">${file.type.startsWith('image') ? '🖼' : file.type.startsWith('audio') ? '🎵' : '🎬'}</span>
        <div>
          <div style="font-size:13px;color:var(--text)">${file.name}</div>
          <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">${(file.size/1024/1024).toFixed(2)} MB</div>
        </div>
      </div>`;
  }
}

// ── AJAX POST helper ──
async function postJSON(url, data) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return resp.json();
}
