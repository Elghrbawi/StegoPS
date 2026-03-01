<?php ?>
</main>
</div>
<?php if($auth->isGuest()): ?>
<div id="upgrade-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal-card animate-in">
<div class="modal-title">Create Your Free Account</div>
<div class="modal-sub">Just a username and password. Email is optional.<br>Your local guest data will carry over.</div>
<div id="upgrade-error" class="field-error-msg" style="display:none"></div>
<div class="input-group">
<label class="input-label">Username <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">◎</span>
<input type="text" class="input has-icon" id="up-username" placeholder="Choose a username..." autocomplete="username">
<span class="input-status" id="up-uname-status"></span>
</div>
<div class="field-hint" id="up-uname-hint"></div>
</div>
<div class="input-group">
<label class="input-label">Password <span class="required-star">*</span></label>
<div class="input-wrap">
<span class="input-icon">⬡</span>
<input type="password" class="input has-icon" id="up-password" placeholder="Your password" autocomplete="new-password">
<button type="button" class="input-eye" onclick="togglePass('up-password',this)">◉</button>
</div>
</div>
<div class="input-group" style="margin-bottom:0">
<label class="input-label">Email <span class="optional-tag">(optional)</span></label>
<div class="input-wrap">
<span class="input-icon" style="font-size:12px;font-family:'Space Mono',monospace">@</span>
<input type="email" class="input has-icon" id="up-email" placeholder="your@email.com (optional)" autocomplete="email">
</div>
</div>
<div class="modal-actions">
<button class="btn btn-primary btn-full" id="upgrade-btn" onclick="doGuestUpgrade()">
<span class="btn-text">⬡ Create Account</span>
<span class="btn-loader" style="display:none">◌</span>
</button>
<button class="btn btn-ghost" onclick="document.getElementById('upgrade-modal').style.display='none'">Later</button>
</div>
</div>
</div>
<?php endif; ?>
<script src="<?= SITE_URL ?>/frontend/js/main.js"></script>
<?php if($auth->isGuest()): ?>
<script>
initFieldCheck('up-username','up-uname-status','up-uname-hint','username','<?= SITE_URL ?>/php/api/check_availability.php');
function doGuestUpgrade() {
const btn    = document.getElementById('upgrade-btn');
const errEl  = document.getElementById('upgrade-error');
const uname  = document.getElementById('up-username').value.trim();
const pw     = document.getElementById('up-password').value;
const email  = document.getElementById('up-email').value.trim();
if (!uname || !pw) { showInlineError(errEl, 'Fill in username and password'); return; }
setLoading(btn, true);
const guestData = localStorage.getItem('stego_guest_ops') || '[]';
fetch('<?= SITE_URL ?>/php/api/auth_ajax.php', { method:'POST', body: new URLSearchParams({action:'guest_upgrade',username:uname,password:pw,email,guest_data:guestData}) })
.then(r=>r.json()).then(d=>{
if (d.success) { localStorage.removeItem('stego_guest_ops'); triggerGlitchTransition(d.redirect, true); }
else { setLoading(btn,false); showInlineError(errEl, d.message); if(d.field) document.getElementById('up-'+d.field)?.classList.add('input-error'); }
}).catch(()=>setLoading(btn,false));
}
</script>
<?php endif; ?>
</body>
</html>
