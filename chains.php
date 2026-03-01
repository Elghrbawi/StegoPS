<?php
// File: chains.php
$pageTitle  = 'Chain Builder';
$activePage = 'chains';
require_once 'php/includes/header.php';
require_once 'php/includes/chain_manager.php';

$manager  = new ChainManager();
$algos    = $manager->getAllAlgorithms();
$chains   = $manager->getUserChains($user['user_id']);

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save') {
            $steps = json_decode($_POST['chain_data'] ?? '[]', true) ?: [];
            $result = $manager->saveChain(
                $user['user_id'],
                trim($_POST['chain_name'] ?? ''),
                $steps,
                trim($_POST['chain_desc'] ?? '')
            );
            $result['success'] ? ($success = $result['message']) : ($error = $result['message']);
        } elseif ($_POST['action'] === 'delete') {
            $manager->deleteChain((int)$_POST['chain_id'], $user['user_id']);
            $success = 'Chain deleted.';
        }
        $chains = $manager->getUserChains($user['user_id']);
    }
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Chain Builder</div>
    <div class="page-sub">// DRAG ALGORITHMS TO BUILD YOUR ENCRYPTION PIPELINE</div>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="POST">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="chain_data" id="chain-data" value="[]">

  <div class="chain-builder">
    <!-- Palette -->
    <div class="algo-palette">
      <?php foreach ($algos as $category => $algoList): ?>
      <div style="margin-bottom:20px">
        <div class="algo-cat-title"><?= ucfirst($category) ?></div>
        <?php foreach ($algoList as $algo): ?>
        <div class="algo-chip <?= $category ?>"
             onclick="addToChain('<?= addslashes($algo['algo_name']) ?>','<?= $category ?>','<?= $algo['algo_slug'] ?>')">
          <span><?= htmlspecialchars($algo['algo_name']) ?></span>
          <span style="opacity:.6">+</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pipeline -->
    <div class="pipeline-wrap">
      <div class="pipeline-header">
        <div style="font-family:'Space Mono',monospace;font-size:11px;letter-spacing:1px;color:var(--text-dim)">
          PIPELINE — <span id="chain-count">0</span> LAYERS
        </div>
        <div class="strength-bar-wrap">
          <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)">STRENGTH</span>
          <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
          <span style="font-family:'Space Mono',monospace;font-size:12px;color:var(--accent);font-weight:700" id="strength-val">0%</span>
        </div>
        <button type="button" class="btn btn-ghost" onclick="clearChain()" style="padding:6px 12px;font-size:11px">✕ Clear</button>
      </div>

      <div class="chain-steps-list" id="chain-steps">
        <div class="chain-empty">
          <span style="font-size:32px">⛓</span>
          <span>← Click algorithms to add them</span>
        </div>
      </div>

      <div class="divider"></div>
      <div class="grid-2" style="margin-bottom:16px">
        <div class="input-group" style="margin-bottom:0">
          <label class="input-label">Chain Name</label>
          <input type="text" name="chain_name" class="input" placeholder="e.g. MyChain-v1" required>
        </div>
        <div class="input-group" style="margin-bottom:0">
          <label class="input-label">Description (optional)</label>
          <input type="text" name="chain_desc" class="input" placeholder="What is this chain for?">
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full">💾 Save Chain</button>
    </div>
  </div>
</form>

<!-- Saved Chains -->
<?php if (!empty($chains)): ?>
<div class="divider"></div>
<div class="card-title">Saved Chains</div>
<div class="card" style="margin-top:12px">
  <table class="data-table">
    <thead><tr><th>Chain Name</th><th>Layers</th><th>Uses</th><th>Created</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($chains as $c): ?>
      <tr>
        <td style="font-weight:600;color:var(--text)"><?= htmlspecialchars($c['chain_name']) ?></td>
        <td><span class="badge badge-success"><?= $c['layer_count'] ?> layers</span></td>
        <td style="font-family:'Space Mono',monospace;font-size:11px"><?= $c['use_count'] ?></td>
        <td style="font-family:'Space Mono',monospace;font-size:10px;color:var(--text-muted)"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this chain?')">
            <input type="hidden" name="action"   value="delete">
            <input type="hidden" name="chain_id" value="<?= $c['chain_id'] ?>">
            <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:10px">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once 'php/includes/footer.php'; ?>
