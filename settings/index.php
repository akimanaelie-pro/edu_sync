<?php 
$page = 'settings';
$pageTitle = 'Settings';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Handle checkboxes (not sent when unchecked)
    $checkboxes = ['mtn_momo_enabled', 'airtel_money_enabled', 'ai_risk_prediction', 'gamification_enabled', 'offline_mode'];
    foreach ($checkboxes as $cb) {
        $_POST[$cb] = $_POST[$cb] ?? '0';
    }
    
    foreach ($_POST as $key => $value) {
        if ($key !== 'save_settings') {
            // Use updateSetting which handles both insert/update
            $db->updateSetting($key, $value);
            error_log("Setting saved: $key = $value");
        }
    }
    redirect(SITE_URL . '/settings/');
}

require_once __DIR__ . '/../config/header.php';

$settings = [
    'school_name' => $db->getSetting('school_name', 'EduSync Nexus School'),
    'school_tagline' => $db->getSetting('school_tagline', 'Empowering Education'),
    'academic_year' => $db->getSetting('academic_year', '2025-2026'),
    'current_term' => $db->getSetting('current_term', 'Term 1'),
    'currency' => $db->getSetting('currency', 'RWF'),
    'sms_provider' => $db->getSetting('sms_provider', 'africastalking'),
    'mtn_momo_enabled' => $db->getSetting('mtn_momo_enabled', '0'),
    'airtel_money_enabled' => $db->getSetting('airtel_money_enabled', '0'),
    'ai_risk_prediction' => $db->getSetting('ai_risk_prediction', '1'),
    'gamification_enabled' => $db->getSetting('gamification_enabled', '1'),
    'offline_mode' => $db->getSetting('offline_mode', '0')
];
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-cog me-2"></i>System Settings</h4>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-school me-2"></i>School Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">School Name</label>
                        <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($settings['school_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="school_tagline" class="form-control" value="<?= htmlspecialchars($settings['school_tagline']) ?>">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="academic_year" class="form-control" value="<?= htmlspecialchars($settings['academic_year']) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Current Term</label>
                            <select name="current_term" class="form-select">
                                <option value="Term 1" <?= $settings['current_term'] === 'Term 1' ? 'selected' : '' ?>>Term 1</option>
                                <option value="Term 2" <?= $settings['current_term'] === 'Term 2' ? 'selected' : '' ?>>Term 2</option>
                                <option value="Term 3" <?= $settings['current_term'] === 'Term 3' ? 'selected' : '' ?>>Term 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency']) ?>">
                    </div>
                    <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment Integration</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="mtn_momo_enabled" value="1" id="mtnSwitch" <?= $settings['mtn_momo_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mtnSwitch">MTN MoMo Integration</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="airtel_money_enabled" value="1" id="airtelSwitch" <?= $settings['airtel_money_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="airtelSwitch">Airtel Money Integration</label>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI & Features</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="ai_risk_prediction" value="1" id="aiSwitch" <?= $settings['ai_risk_prediction'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="aiSwitch">AI Risk Prediction</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="gamification_enabled" value="1" id="gameSwitch" <?= $settings['gamification_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="gameSwitch">Gamification (XP & Badges)</label>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-wifi me-2"></i>Offline Mode</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="offline_mode" value="1" id="offlineSwitch" <?= $settings['offline_mode'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="offlineSwitch">Enable Offline Mode</label>
                </div>
                <small class="text-muted">Works with local SQLite database when offline</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database & Sync</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary" onclick="syncNow()">
                        <i class="fas fa-sync me-2"></i> Sync Now
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportDB()">
                        <i class="fas fa-download me-2"></i> Export Database
                    </button>
                    <button class="btn btn-outline-secondary" onclick="importDB()">
                        <i class="fas fa-upload me-2"></i> Import Database
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Security</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= SITE_URL ?>/config/change_password.php" class="btn btn-outline-secondary">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a>
                    <a href="<?= SITE_URL ?>/settings/audit_logs.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i> View Audit Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncNow() {
    showToast('Syncing data with server...');
    fetch('<?= SITE_URL ?>/api/sync.php?action=push', {method: 'POST'})
        .then(() => fetch('<?= SITE_URL ?>/api/sync.php?action=pull'))
        .then(() => showToast('Sync complete!'));
}

function exportDB() {
    window.location.href = '<?= SITE_URL ?>/api/export.php';
}

function importDB() {
    alert('Import feature - upload a backup file');
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
