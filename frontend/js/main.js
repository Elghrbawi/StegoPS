/* ── GLITCH TRANSITION ── */
function triggerGlitchTransition(url, isNewUser) {
const ov = document.getElementById('glitch-overlay');
if (!ov) { window.location.href = url; return; }
ov.innerHTML = '<div class="gl-base"></div><div class="gl-scan"></div><div class="gl-r"></div><div class="gl-g"></div><div class="gl-b"></div><div class="gl-line"></div><div class="gl-txt">'+(isNewUser?'IDENTITY CREATED':'ACCESS GRANTED')+'</div>';
ov.classList.add('active');
setTimeout(() => { window.location.href = url; }, 980);
}
/* ── NAV SWITCH ANIMATION ── */
function navSwitch(e, url, dir) {
e.preventDefault();
const card = document.getElementById('auth-card');
if (!card) { window.location.href = url; return false; }
card.classList.add(dir === 'right' ? 'slide-out-left' : 'slide-out-right');
setTimeout(() => { window.location.href = url + '?from=' + (dir === 'right' ? 'login' : 'register'); }, 320);
return false;
}
/* ── ERROR HELPERS ── */
function showInlineError(el, msg) {
if (!el) return;
el.textContent = msg;
el.style.display = 'flex';
el.classList.remove('show');
void el.offsetWidth;
el.classList.add('show');
}
function clearError(el) {
if (!el) return;
el.style.display = 'none';
el.textContent = '';
}
function showInputError(input, errEl, msg) {
input.classList.add('input-error');
showInlineError(errEl, msg);
setTimeout(() => input.classList.remove('input-error'), 3000);
}
/* ── SET LOADING STATE ── */
function setLoading(btn, loading) {
if (!btn) return;
const txt = btn.querySelector('.btn-text');
const ldr = btn.querySelector('.btn-loader');
if (txt) txt.style.display = loading ? 'none' : 'inline';
if (ldr) ldr.style.display = loading ? 'inline' : 'none';
btn.disabled = loading;
}
/* ── PASSWORD SHOW/HIDE ── */
function togglePass(id, btn) {
const input = document.getElementById(id) || btn.closest('.input-wrap').querySelector('input');
if (!input) return;
input.type = input.type === 'password' ? 'text' : 'password';
btn.style.opacity = input.type === 'text' ? '1' : '.6';
}
/* ── PASSWORD STRENGTH ── */
function initPasswordStrength(inputId, wrapId, barId, labelId, checkIds) {
const input = document.getElementById(inputId);
const wrap  = document.getElementById(wrapId);
const bar   = document.getElementById(barId);
const label = document.getElementById(labelId);
if (!input) return;
input.addEventListener('input', () => {
const v = input.value;
if (!v) { wrap.style.display = 'none'; return; }
wrap.style.display = 'block';
const c = { len: v.length >= 8, upper: /[A-Z]/.test(v), num: /[0-9]/.test(v), sym: /[^a-zA-Z0-9]/.test(v) };
const score = (c.len?25:0)+(c.upper?25:0)+(c.num?25:0)+(c.sym?25:0);
bar.style.width = score + '%';
const colors = ['#ff4d6d','#ff7043','#ffb347','#00e5c8'];
const labels = ['Weak','Fair','Good','Strong'];
const idx = Math.min(3, Math.floor(score / 26));
bar.style.background = colors[idx];
label.textContent = score === 0 ? '—' : labels[idx];
label.style.color = score === 0 ? 'var(--text-muted)' : colors[idx];
const keys = ['len','upper','num','sym'];
checkIds.forEach((id, i) => { document.getElementById(id)?.classList.toggle('pass', c[keys[i]]); });
});
}
/* ── REALTIME FIELD CHECK ── */
function initFieldCheck(inputId, statusId, hintId, type, url) {
const input  = document.getElementById(inputId);
const status = document.getElementById(statusId);
const hint   = document.getElementById(hintId);
if (!input) return;
let timer = null;
input.addEventListener('input', () => {
clearTimeout(timer);
const v = input.value.trim();
if (!v) {
if (status) { status.textContent = ''; status.className = 'input-status'; }
if (hint) { hint.textContent = type === 'username' ? '' : ''; hint.className = 'field-hint'; }
input.style.borderColor = '';
return;
}
if (status) { status.textContent = '◌'; status.className = 'input-status checking'; }
timer = setTimeout(async () => {
try {
const fd = new FormData();
fd.append('type', type);
fd.append('value', v);
const res = await fetch(url, { method: 'POST', body: fd });
const data = await res.json();
if (status) { status.textContent = data.available ? '✓' : '✗'; status.className = 'input-status ' + (data.available ? 'ok' : 'err'); }
if (hint) { hint.textContent = data.message || ''; hint.className = 'field-hint ' + (data.available ? 'ok' : 'err'); }
input.style.borderColor = data.available ? 'var(--accent)' : 'var(--danger)';
} catch(e) { if (status) { status.textContent = ''; status.className = 'input-status'; } }
}, 500);
});
input.addEventListener('blur', () => {
if (!input.value.trim()) { input.style.borderColor = ''; if (status) { status.textContent = ''; status.className = 'input-status'; } }
});
}
/* ── EMAIL HINT (soft, no blocking) ── */
function initEmailHint(inputId, statusId, hintId) {
const input  = document.getElementById(inputId);
const hint   = document.getElementById(hintId);
const status = document.getElementById(statusId);
if (!input) return;
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
input.addEventListener('input', () => {
const v = input.value.trim();
if (!v) { if (hint) { hint.textContent = 'Not required — but useful for account recovery'; hint.className = 'field-hint'; hint.style.color = 'var(--text-muted)'; } if (status) { status.textContent = ''; status.className = 'input-status'; } return; }
const valid = emailRegex.test(v);
if (status) { status.textContent = valid ? '✓' : '?'; status.className = 'input-status ' + (valid ? 'ok' : 'checking'); }
if (hint) { hint.textContent = valid ? 'Looks good!' : 'Unusual format — double check?'; hint.className = 'field-hint ' + (valid ? 'ok' : ''); hint.style.color = valid ? '' : 'var(--text-muted)'; }
});
}
/* ── BG CANVAS (particles) ── */
function initBgCanvas() {
const canvas = document.getElementById('bg-canvas');
if (!canvas) return;
const ctx = canvas.getContext('2d');
let W = canvas.width  = window.innerWidth;
let H = canvas.height = window.innerHeight;
const pts = Array.from({ length: 60 }, () => ({
x: Math.random() * W, y: Math.random() * H,
vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35,
r: Math.random() * 1.4 + .4, o: Math.random() * .5 + .1
}));
let raf;
function draw() {
ctx.clearRect(0, 0, W, H);
pts.forEach(p => {
p.x += p.vx; p.y += p.vy;
if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;
ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
ctx.fillStyle = `rgba(0,229,200,${p.o})`; ctx.fill();
});
for (let i = 0; i < pts.length; i++) {
for (let j = i + 1; j < pts.length; j++) {
const dx = pts[i].x - pts[j].x, dy = pts[i].y - pts[j].y;
const d = Math.sqrt(dx * dx + dy * dy);
if (d < 110) {
ctx.beginPath(); ctx.moveTo(pts[i].x, pts[i].y); ctx.lineTo(pts[j].x, pts[j].y);
ctx.strokeStyle = `rgba(0,229,200,${.07 * (1 - d / 110)})`; ctx.lineWidth = .6; ctx.stroke();
}
}
}
raf = requestAnimationFrame(draw);
}
draw();
window.addEventListener('resize', () => { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; });
}
/* ── CHAIN BUILDER ── */
const chainColors = {
classical:  { bg:'rgba(124,58,237,0.15)', color:'#a78bfa', border:'#7c3aed', num:'#7c3aed' },
symmetric:  { bg:'rgba(37,99,235,0.15)',  color:'#60a5fa', border:'#2563eb', num:'#2563eb' },
asymmetric: { bg:'rgba(5,150,105,0.15)',  color:'#34d399', border:'#059669', num:'#059669' },
custom:     { bg:'rgba(217,119,6,0.15)',  color:'#fbbf24', border:'#d97706', num:'#d97706' },
hashing:    { bg:'rgba(220,38,38,0.15)',  color:'#f87171', border:'#dc2626', num:'#dc2626' },
};
let chain = [];
function addToChain(name, category, slug) { chain.push({ name, category, slug, key: '' }); renderChain(); }
function removeFromChain(idx) { chain.splice(idx, 1); renderChain(); }
function clearChain() { chain = []; renderChain(); }
function renderChain() {
const container = document.getElementById('chain-steps');
if (!container) return;
const countEl = document.getElementById('chain-count');
if (countEl) countEl.textContent = chain.length;
const strength = Math.min(100, chain.length * 6 + (chain.some(c=>c.category==='symmetric')?22:0) + (chain.some(c=>c.category==='asymmetric')?18:0) + (chain.some(c=>c.category==='hashing')?10:0));
const sfill = document.getElementById('strength-fill');
const sval  = document.getElementById('strength-val');
if (sfill) sfill.style.width = strength + '%';
if (sval)  sval.textContent  = strength + '%';
const chainInput = document.getElementById('chain-data');
if (chainInput) chainInput.value = JSON.stringify(chain);
container.innerHTML = '';
if (chain.length === 0) {
container.innerHTML = '<div class="chain-empty"><span style="font-size:26px">⛓</span><span>← Click algorithms to build pipeline</span><span style="font-size:9px;opacity:.5">Stack ciphers for stronger encryption</span></div>';
return;
}
chain.forEach((step, i) => {
const c = chainColors[step.category];
if (i > 0) { const arrow = document.createElement('div'); arrow.className = 'step-arrow'; arrow.textContent = '↓'; container.appendChild(arrow); }
const el = document.createElement('div');
el.className = 'chain-step';
el.style.cssText = `background:${c.bg};border-color:${c.border}`;
el.innerHTML = `<div class="step-num" style="background:${c.num};color:#fff">${i+1}</div><div style="flex:1;min-width:0"><div class="step-name" style="color:${c.color}">${step.name}</div><div class="step-cat">${step.category.toUpperCase()}</div></div><input type="password" class="step-key" placeholder="Key..." value="${step.key.replace(/"/g,'&quot;')}" onchange="updateStepKey(${i},this.value)"/><button class="step-remove" onclick="removeFromChain(${i})" type="button">✕</button>`;
container.appendChild(el);
});
}
function updateStepKey(idx, val) { if (chain[idx]) { chain[idx].key = val; const i = document.getElementById('chain-data'); if (i) i.value = JSON.stringify(chain); } }
/* ── MEDIA SWITCH ── */
const mediaInfo = {
image: { icon:'🖼', sub:'PNG, JPG, BMP supported',    technique:'LSB Steganography' },
audio: { icon:'🎵', sub:'WAV, MP3 supported',          technique:'Phase Coding / Echo Hiding' },
video: { icon:'🎬', sub:'MP4, AVI supported',           technique:'Frame-based LSB Embedding' },
};
function switchMedia(type) {
['image','audio','video'].forEach(t => document.getElementById('mt-'+t)?.classList.toggle('active', t===type));
const m = mediaInfo[type];
const icon = document.getElementById('carrier-icon'); if (icon) icon.textContent = m.icon;
const sub  = document.getElementById('carrier-sub');  if (sub)  sub.textContent  = m.sub;
const tech = document.getElementById('carrier-technique'); if (tech) tech.textContent = m.technique;
const inp  = document.getElementById('media-type-input'); if (inp) inp.value = type;
}
function switchPayloadType(type) {
document.getElementById('pt-text')?.classList.toggle('active', type==='text');
document.getElementById('pt-file')?.classList.toggle('active', type==='file');
const ta = document.getElementById('payload-text'); if (ta) ta.style.display = type==='text'?'block':'none';
const fa = document.getElementById('payload-file'); if (fa) fa.style.display = type==='file'?'block':'none';
const inp = document.getElementById('payload-type-input'); if (inp) inp.value = type;
}
/* ── FILE UPLOAD ── */
function handleFileSelect(input, previewId) {
const file = input.files[0];
if (!file) return;
const preview = document.getElementById(previewId);
if (!preview) return;
const icon = file.type.startsWith('image') ? '🖼' : file.type.startsWith('audio') ? '🎵' : '🎬';
preview.innerHTML = `<div class="upload-preview"><span style="font-size:22px;flex-shrink:0">${icon}</span><div><div class="upload-preview-name">${file.name}</div><div class="upload-preview-size">${(file.size/1024/1024).toFixed(2)} MB</div></div></div>`;
if (file.type.startsWith('image')) {
const reader = new FileReader();
reader.onload = e => { const img = document.createElement('img'); img.src = e.target.result; img.style.cssText = 'max-width:100%;max-height:110px;border-radius:8px;margin-top:8px;display:block;object-fit:contain'; preview.appendChild(img); };
reader.readAsDataURL(file);
}
}
/* ── DRAG AND DROP ── */
document.addEventListener('DOMContentLoaded', () => {
document.querySelectorAll('.upload-zone').forEach(zone => {
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
e.preventDefault(); zone.classList.remove('dragover');
const inp = zone.nextElementSibling;
if (inp && inp.tagName === 'INPUT' && e.dataTransfer.files.length) {
inp.files = e.dataTransfer.files;
inp.dispatchEvent(new Event('change'));
}
});
});
document.querySelectorAll('.alert').forEach(el => {
setTimeout(() => { el.style.transition = 'opacity .5s,transform .5s'; el.style.opacity = '0'; el.style.transform = 'translateY(-6px)'; setTimeout(() => el.remove(), 500); }, 5000);
});
});
/* ── SIDEBAR TOGGLE ── */
function toggleSidebar() {
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebar-overlay');
const hamburger = document.getElementById('hamburger');
if (!sidebar) return;
sidebar.classList.toggle('open');
if (overlay) overlay.classList.toggle('open');
if (hamburger) hamburger.classList.toggle('open');
}
/* ── COPY TEXT ── */
function copyText(text, btn) {
navigator.clipboard.writeText(text).then(() => { const o = btn.textContent; btn.textContent = '✓ Copied!'; setTimeout(() => btn.textContent = o, 2000); });
}
/* ── TOGGLE SWITCH ── */
function toggleSwitch(el) {
el.classList.toggle('on');
const t = el.dataset.target;
if (t) { const el2 = document.getElementById(t); if (el2) el2.style.display = el.classList.contains('on') ? 'block' : 'none'; }
}
