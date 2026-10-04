<?php
require_once __DIR__ . '/../includes/layout.php';
require_role('tourist');

$db = Database::getInstance()->getConnection();
$user = current_user();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
    if (!verify_token($_POST['csrf_token'] ?? null)) {
        flash_message('error', 'Invalid security token.');
        redirect('/tourist/settings.php');
    }
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $db->prepare("UPDATE users SET theme = :theme WHERE id = :uid")
        ->execute([':theme' => $theme, ':uid' => $user_id]);
    ActivityLog::log($user_id, 'preferences_updated', "Theme preference set to {$theme}");
    flash_message('success', 'Appearance updated.');
    redirect('/tourist/settings.php');
}

$csrf = generate_token();
$theme = $user['theme'] ?? 'light';

render_page('tourist', 'settings.php', 'Settings', function () use ($user, $csrf, $theme) {
?>
<style>
.set-wrap{max-width:100%;margin:0 auto;display:flex;flex-direction:column;gap:18px;}
.set-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.04);overflow:hidden}
.set-card-head{display:flex;align-items:center;gap:12px;padding:18px 20px 14px}
.set-card-head .set-ico{width:36px;height:36px;border-radius:11px;background:rgba(12,110,94,.1);color:#0c6e5e;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.set-card-head h6{margin:0;font-weight:800;font-size:.95rem;color:var(--text-primary,#1e293b)}
.set-card-head p{margin:2px 0 0;font-size:.76rem;color:var(--text-muted,#64748b)}
.set-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;border-top:1px solid var(--border-color,#f1f5f9)}
.set-row .set-label{font-size:.86rem;font-weight:700;color:var(--text-primary,#1e293b)}
.set-row .set-desc{font-size:.74rem;color:var(--text-muted,#64748b);margin-top:2px}
.set-link{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-primary,#1e293b);font-size:.86rem;font-weight:600}
.set-link:hover{color:#0c6e5e}
.set-link i{width:30px;height:30px;border-radius:9px;background:rgba(12,110,94,.08);color:#0c6e5e;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.set-link .fa-chevron-right{width:auto;height:auto;background:none;color:var(--text-muted,#94a3b8);font-size:.7rem;margin-left:auto}
/* Theme switch — explicit colors so track/knob/icon stay visible in both modes */
.theme-switch #themeTrack{position:relative;transition:background .2s;width:44px;height:24px;border-radius:9999px;background:#e2e8f0;flex-shrink:0;cursor:pointer}
[data-theme="dark"] .theme-switch #themeTrack{background:#334155}
.theme-switch #themeTrack::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:9999px;background:#fff !important;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .2s}
.theme-switch input:checked + #themeTrack{background:#059669 !important}
.theme-switch input:checked + #themeTrack::after{transform:translateX(20px)}
#themeQuickBtn i{color:#fff !important}
.set-save{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:10px;border:none;background:linear-gradient(135deg,#0c6e5e,#10b981);color:#fff;font-size:.82rem;font-weight:700;cursor:pointer}
.set-save:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(12,110,94,.35)}
</style>

<div class="set-wrap">
    <div class="set-card">
        <div class="set-card-head">
            <span class="set-ico"><i class="fas fa-palette"></i></span>
            <div><h6>Appearance</h6><p>Customize how BINALGO looks for you</p></div>
        </div>
        <form method="POST" id="themeForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="update_theme" value="1">
            <input type="hidden" name="theme" id="theme" value="<?= $theme === 'dark' ? 'dark' : 'light' ?>">
            <div class="set-row">
                <div><div class="set-label">Dark Mode</div><div class="set-desc">Switch between light and dark themes across the app.</div></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="theme-switch" style="display:inline-flex;cursor:pointer;">
                        <input type="checkbox" id="theme_toggle" class="d-none" <?= $theme === 'dark' ? 'checked' : '' ?>>
                        <span id="themeTrack"></span>
                    </label>
                </div>
            </div>
            <div class="set-row">
                <div class="set-desc">Changes apply instantly. Save keeps them on all devices.</div>
                <button type="submit" class="set-save"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>

    <div class="set-card">
        <div class="set-card-head">
            <span class="set-ico"><i class="fas fa-user-cog"></i></span>
            <div><h6>Account</h6><p>Profile, security, and messages</p></div>
        </div>
        <div class="set-row"><a class="set-link w-100" href="<?= BASE_URL ?>/tourist/profile.php"><i class="fas fa-user"></i> My Profile <i class="fas fa-chevron-right"></i></a></div>
        <div class="set-row"><a class="set-link w-100" href="<?= BASE_URL ?>/tourist/profile.php?tab=security"><i class="fas fa-shield-alt"></i> Security &amp; Password <i class="fas fa-chevron-right"></i></a></div>
        <div class="set-row"><a class="set-link w-100" href="<?= BASE_URL ?>/tourist/notifications.php"><i class="fas fa-bell"></i> Notifications <i class="fas fa-chevron-right"></i></a></div>
    </div>
</div>

<script>
(function() {
    var toggle = document.getElementById('theme_toggle');
    var theme = document.getElementById('theme');
    var quickBtn = document.getElementById('themeQuickBtn');
    var quickIcon = document.getElementById('themeQuickIcon');
    if (!toggle && !quickBtn) return;
    function baseUrl() {
        var m = document.querySelector('meta[name="base-url"]');
        return m ? m.content : '/Tourism';
    }
    function setMode(mode) {
        document.documentElement.setAttribute('data-theme', mode);
        try { localStorage.setItem('theme', mode); } catch (e) {}
        if (quickIcon) quickIcon.className = 'fas ' + (mode === 'dark' ? 'fa-sun' : 'fa-moon');
        if (toggle) toggle.checked = (mode === 'dark');
        if (theme) theme.value = mode;
        fetch(baseUrl() + '/api/theme.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'theme=' + encodeURIComponent(mode)
        }).catch(function(){});
    }
    if (toggle) toggle.addEventListener('change', function() { setMode(toggle.checked ? 'dark' : 'light'); });
    if (quickBtn) quickBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setMode(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });
})();
</script>
<?php }); ?>
