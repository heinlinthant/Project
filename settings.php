<?php
// settings.php

session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$config_file = 'config.json';
$success_message = '';
$error_message = '';

// --- Read current config for display ---
if (!file_exists($config_file)) {
    die('Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);
$appName = $config['appName'] ?? 'Dashboard';
$appIcon = $config['appIcon'] ?? 'default_icon.png';
$accentColor = $config['accentColor'] ?? '#6366f1';
$globalMessage = $config['globalMessage'] ?? '';
$apiBaseUrl = $config['apiBaseUrl'] ?? '';
$latestVersion = $config['latestVersion'] ?? '1.0.0';
$minimumVersion = $config['minimumVersion'] ?? '1.0.0';


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? '';

    // --- Passcode Change Logic ---
    if ($form_type === 'change_passcode') {
        $current_passcode = $_POST['current_passcode'] ?? '';
        $new_passcode = $_POST['new_passcode'] ?? '';
        $confirm_passcode = $_POST['confirm_passcode'] ?? '';

        if (empty($current_passcode) || empty($new_passcode) || empty($confirm_passcode)) {
            $error_message = 'All passcode fields are required.';
        } elseif ($current_passcode !== $config['passcode']) {
            $error_message = 'Incorrect current passcode.';
        } elseif ($new_passcode !== $confirm_passcode) {
            $error_message = 'New passcodes do not match.';
        } elseif (strlen($new_passcode) < 6) {
            $error_message = 'New passcode must be at least 6 characters long.';
        } else {
            $config['passcode'] = $new_passcode;
            $success_message = 'Passcode updated successfully!';
        }
    }

    // --- Branding Change Logic ---
    if ($form_type === 'change_branding') {
        $newAppName = trim($_POST['appName']);
        if (!empty($newAppName)) {
            $config['appName'] = htmlspecialchars($newAppName);
            $appName = $config['appName'];
        }

        $newAccentColor = $_POST['accentColor'] ?? '#6366f1';
        if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $newAccentColor)) {
            $config['accentColor'] = $newAccentColor;
            $accentColor = $newAccentColor;
        } else {
            $error_message = 'Invalid accent color format. ';
        }

        if (isset($_FILES['appIcon']) && $_FILES['appIcon']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            $file = $_FILES['appIcon'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];

            if ($file['size'] > $maxSize) {
                $error_message .= 'File size is too large (Max 5MB).';
            } elseif (!in_array($file['type'], $allowedTypes)) {
                $error_message .= 'Invalid file type (JPG, PNG, GIF, SVG only).';
            } else {
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('icon_', true) . '.' . $fileExtension;
                $newFilePath = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
                    $oldIconPath = $config['appIcon'];
                    if ($oldIconPath !== 'default_icon.png' && file_exists($oldIconPath)) { @unlink($oldIconPath); }
                    $config['appIcon'] = $newFilePath;
                    $appIcon = $newFilePath;
                } else { $error_message .= 'Error uploading file.'; }
            }
        }
         if(empty($error_message)) $success_message = 'Branding updated successfully!';
    }

    // --- App Control Settings Logic ---
    if($form_type === 'change_app_control') {
        $config['globalMessage'] = htmlspecialchars(trim($_POST['globalMessage']));
        $globalMessage = $config['globalMessage'];

        $config['latestVersion'] = htmlspecialchars(trim($_POST['latestVersion']));
        $latestVersion = $config['latestVersion'];

        $config['minimumVersion'] = htmlspecialchars(trim($_POST['minimumVersion']));
        $minimumVersion = $config['minimumVersion'];

        $newApiBaseUrl = rtrim(trim($_POST['apiBaseUrl']), '/');
        if (filter_var($newApiBaseUrl, FILTER_VALIDATE_URL) || empty($newApiBaseUrl)) {
             $config['apiBaseUrl'] = $newApiBaseUrl;
             $apiBaseUrl = $config['apiBaseUrl'];
             $success_message = "App Control settings saved!";
        } else {
            $error_message = "Invalid API Base URL format.";
        }
    }

    // Save changes to config file
    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
     // To prevent re-submission on refresh, redirect back to the same tab
     $tab = $form_type === 'change_passcode' ? 'security' : ($form_type === 'change_app_control' ? 'app_control' : 'branding');
     header("Location: settings.php?tab=$tab&success=" . urlencode($success_message) . "&error=" . urlencode($error_message));
     exit();
}

// Get messages from URL parameters after redirect
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$active_tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'branding';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f1f5f9; --sidebar-bg: white; --card-bg: white; --card-border: #e2e8f0; --text-headings: #1e293b; --text-body: #475569; --text-muted: #64748b;
            --input-bg: white; --input-border: #cbd5e1; --accent: <?= htmlspecialchars($accentColor) ?>; --accent-text: white; --table-header-bg: #f8fafc; --table-row-hover: #f8fafc;
        }
        html.dark {
            --bg-color: #0f172a; --sidebar-bg: #1e293b; --card-bg: #1e293b; --card-border: #334155; --text-headings: #f1f5f9; --text-body: #94a3b8; --text-muted: #64748b;
            --input-bg: #334155; --input-border: #475569; --table-header-bg: #1e293b; --table-row-hover: #334155;
        }
        body { background-color: var(--bg-color); color: var(--text-body); font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: background-color 0.3s, border-color 0.3s; }
        .form-input { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-headings); border-radius: 0.5rem; transition: background-color 0.3s, border-color 0.3s, color 0.3s; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); outline: none; }
        .btn-accent { background-color: var(--accent); color: var(--accent-text); }
        .btn-accent:hover { filter: brightness(90%); }
        .tab { color: var(--text-muted); border-bottom: 2px solid transparent; padding-bottom: 0.5rem; font-weight: 500;}
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        #tab-content { position: relative; transition: height 0.3s ease-in-out; }
        .tab-pane { transition: opacity 0.3s ease-in-out; position: absolute; width: 100%; top: 0; left: 0; opacity: 0; pointer-events: none; }
        .tab-pane.active { position: relative; opacity: 1; pointer-events: auto; }
    </style>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="antialiased">
<div class="relative md:flex">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-20 md:hidden hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 flex-shrink-0 z-30 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col" style="background-color: var(--sidebar-bg); border-right: 1px solid var(--card-border);">
        <div class="p-6 flex items-center justify-between flex-shrink-0">
            <a href="dashboard.php" class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($appIcon) ?>" alt="App Icon" class="w-10 h-10 rounded-lg object-cover" onerror="this.src='https://placehold.co/40x40/6366f1/ffffff?text=D'; this.onerror=null;">
                <h1 class="text-2xl font-bold text-headings"><?= htmlspecialchars($appName) ?></h1>
            </a>
            <button id="sidebar-close" class="md:hidden p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700"><i class="ph ph-x text-xl text-headings"></i></button>
        </div>
        <nav class="mt-8 px-4 flex-grow">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
                <i class="ph ph-users-three text-xl"></i><span>Users</span>
            </a>
            <a href="app_data.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
                 <i class="ph ph-database text-xl"></i><span>App Data</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold btn-accent">
                <i class="ph ph-gear text-xl"></i><span>Settings</span>
            </a>
        </nav>
        <div class="p-4 mt-auto flex-shrink-0 border-t" style="border-color: var(--card-border);">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-full bg-slate-200 dark:bg-slate-700">
                     <i class="ph ph-user text-lg text-headings"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-headings">Admin</p>
                    <p class="text-xs text-text-muted">Logged In</p>
                </div>
            </div>
             <a href="logout.php" class="mt-4 flex items-center justify-center gap-2 w-full px-4 py-2 rounded-lg text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700/50 font-semibold text-sm"><i class="ph ph-sign-out text-lg"></i><span>Sign Out</span></a>
        </div>
    </aside>

    <main class="flex-1 md:ml-64 overflow-y-auto">
        <div class="container mx-auto px-4 sm:px-6 lg:px-10 py-8">
            <header class="flex justify-between items-center mb-8" data-aos="fade-down">
                <div class="flex items-center gap-4">
                    <button id="menu-toggle" class="md:hidden p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700"><i class="ph ph-list text-2xl text-headings"></i></button>
                    <div>
                        <h1 class="text-xl md:text-3xl font-bold text-headings">Settings</h1>
                        <p class="mt-1 text-sm md:text-base text-text-muted">Manage your dashboard branding and security.</p>
                    </div>
                </div>
            </header>

            <div class="max-w-2xl mx-auto" data-aos="fade-up">
                <div class="mb-6 border-b border-slate-200 dark:border-slate-700">
                    <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                        <button class="tab" data-tab="branding">Branding</button>
                        <button class="tab" data-tab="security">Security</button>
                        <button class="tab" data-tab="app_control">App Control</button>
                        <button class="tab" data-tab="api_test">API Test</button>
                    </nav>
                </div>

                <div id="tab-content">
                    <div id="branding" class="tab-pane">
                         <form action="settings.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="form_type" value="change_branding">
                            <div class="card p-6 md:p-8 space-y-6">
                                <?php if ($active_tab === 'branding' && !empty($success_message)): ?><div class="bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg" role="alert"><?= $success_message ?></div><?php endif; ?>
                                <?php if ($active_tab === 'branding' && !empty($error_message)): ?><div class="bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg" role="alert"><?= $error_message ?></div><?php endif; ?>
                                <div><label for="appName" class="block text-sm font-medium text-text-body mb-1">App Name</label><input type="text" id="appName" name="appName" value="<?= htmlspecialchars($appName) ?>" class="form-input w-full px-4 py-2 transition"></div>
                                <div><label for="appIcon" class="block text-sm font-medium text-text-body mb-1">App Icon</label><div class="mt-1 flex items-center gap-4"><img src="<?= htmlspecialchars($appIcon) ?>?t=<?= time() ?>" alt="Current App Icon" class="w-16 h-16 rounded-lg object-cover bg-slate-200" onerror="this.src='https://placehold.co/64x64/6366f1/ffffff?text=<?= substr($appName, 0, 1) ?>'; this.onerror=null;"><input type="file" id="appIcon" name="appIcon" class="block w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-500/20 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/30"></div><p class="text-xs text-text-muted mt-2">Max 5MB. PNG, JPG, GIF, SVG.</p></div>
                                <div><label for="accentColor" class="block text-sm font-medium text-text-body mb-1">Accent Color</label><div class="flex items-center gap-2"><input type="color" id="accentColor" name="accentColor" value="<?= htmlspecialchars($accentColor) ?>" class="p-1 h-10 w-10 block bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 cursor-pointer rounded-lg"><input type="text" id="accentColorText" value="<?= htmlspecialchars($accentColor) ?>" class="form-input w-full px-4 py-2"></div></div>
                                <div class="pt-2"><button type="submit" name="change_branding" class="w-full flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2.5 px-4 rounded-lg transition shadow-sm">Save Branding</button></div>
                            </div>
                         </form>
                    </div>

                    <div id="security" class="tab-pane">
                        <form action="settings.php" method="POST">
                           <input type="hidden" name="form_type" value="change_passcode">
                           <div class="card p-6 md:p-8 space-y-6">
                               <?php if ($active_tab === 'security' && !empty($success_message)): ?><div class="bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg" role="alert"><?= $success_message ?></div><?php endif; ?>
                               <?php if ($active_tab === 'security' && !empty($error_message)): ?><div class="bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg" role="alert"><?= $error_message ?></div><?php endif; ?>
                               <div><label for="current_passcode" class="block text-sm font-medium text-text-body mb-1">Current Passcode</label><input type="password" id="current_passcode" name="current_passcode" required class="form-input w-full px-4 py-2 transition"></div>
                               <div><label for="new_passcode" class="block text-sm font-medium text-text-body mb-1">New Passcode</label><input type="password" id="new_passcode" name="new_passcode" required class="form-input w-full px-4 py-2 transition"></div>
                               <div><label for="confirm_passcode" class="block text-sm font-medium text-text-body mb-1">Confirm New Passcode</label><input type="password" id="confirm_passcode" name="confirm_passcode" required class="form-input w-full px-4 py-2 transition"></div>
                               <div class="pt-2"><button type="submit" name="change_passcode" class="w-full flex justify-center items-center gap-2 bg-slate-700 dark:bg-slate-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:bg-slate-800 dark:hover:bg-slate-500 transition shadow-sm">Update Passcode</button></div>
                           </div>
                        </form>
                    </div>

                    <div id="app_control" class="tab-pane">
                         <form action="settings.php" method="POST">
                            <input type="hidden" name="form_type" value="change_app_control">
                            <div class="card p-6 md:p-8 space-y-6">
                                <?php if ($active_tab === 'app_control' && !empty($success_message)): ?><div class="bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg" role="alert"><?= $success_message ?></div><?php endif; ?>
                                <?php if ($active_tab === 'app_control' && !empty($error_message)): ?><div class="bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg" role="alert"><?= $error_message ?></div><?php endif; ?>
                               <div><label for="globalMessage" class="block text-sm font-medium text-text-body mb-1">Global Broadcast Message</label><textarea id="globalMessage" name="globalMessage" rows="3" class="form-input w-full px-4 py-2 transition" placeholder="This message will be sent to all users via the API."><?= htmlspecialchars($globalMessage) ?></textarea></div>
                               <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div><label for="latestVersion" class="block text-sm font-medium text-text-body mb-1">Latest Version</label><input type="text" id="latestVersion" name="latestVersion" value="<?= htmlspecialchars($latestVersion)?>" class="form-input w-full px-4 py-2" placeholder="e.g., 1.2.1"></div>
                                    <div><label for="minimumVersion" class="block text-sm font-medium text-text-body mb-1">Minimum Required Version</label><input type="text" id="minimumVersion" name="minimumVersion" value="<?= htmlspecialchars($minimumVersion)?>" class="form-input w-full px-4 py-2" placeholder="e.g., 1.0.0"></div>
                               </div>
                               <div><label for="apiBaseUrl" class="block text-sm font-medium text-text-body mb-1">API Base URL</label><input type="url" id="apiBaseUrl" name="apiBaseUrl" value="<?= htmlspecialchars($apiBaseUrl) ?>" class="form-input w-full px-4 py-2 transition" placeholder="e.g., https://your-site.com/api/"><p class="text-xs text-text-muted mt-2">The full API endpoint will be displayed below.</p></div>
                               <div class="pt-2"><button type="submit" name="change_app_control" class="w-full flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2.5 px-4 rounded-lg transition shadow-sm">Save App Control Settings</button></div>
                           </div>
                        </form>
                    </div>

                    <div id="api_test" class="tab-pane">
                         <div class="card p-6 md:p-8 space-y-6">
                            <h3 class="text-lg font-semibold text-headings">API Endpoint Test</h3>
                            <form id="api-test-form" class="space-y-4">
                                <div><label for="test-key" class="block text-sm font-medium text-text-body">Key</label><input type="text" id="test-key" name="key" required class="form-input mt-1 w-full px-4 py-2"></div>
                                <div><label for="test-deviceId" class="block text-sm font-medium text-text-body">Device ID</label><input type="text" id="test-deviceId" name="deviceId" required class="form-input mt-1 w-full px-4 py-2"></div>
                                <div><label for="test-appVersion" class="block text-sm font-medium text-text-body">App Version</label><input type="text" id="test-appVersion" name="appVersion" required class="form-input mt-1 w-full px-4 py-2" placeholder="e.g., 1.0.0"></div>
                                <div class="pt-2"><button type="submit" class="w-full flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2.5 px-4 rounded-lg transition shadow-sm"><i class="ph ph-paper-plane-tilt"></i>Run Test</button></div>
                            </form>
                            <div id="response-container" class="hidden pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                                <h4 class="font-semibold text-headings mb-2">API Response:</h4>
                                <pre id="response-json" class="bg-slate-100 dark:bg-slate-800 text-sm rounded-lg p-4 overflow-x-auto"></pre>
                            </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 600, once: true, disable: 'mobile' });

        // --- Mobile Sidebar Toggle ---
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarCloseBtn = document.getElementById('sidebar-close');
        function openSidebar() { sidebar.classList.remove('-translate-x-full'); sidebarOverlay.classList.remove('hidden'); }
        function closeSidebar() { sidebar.classList.add('-translate-x-full'); sidebarOverlay.classList.add('hidden'); }
        menuToggle.addEventListener('click', (e) => { e.stopPropagation(); openSidebar(); });
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // --- Color Picker Sync ---
        const colorPicker = document.getElementById('accentColor');
        const colorText = document.getElementById('accentColorText');
        colorPicker.addEventListener('input', (e) => { colorText.value = e.target.value; });
        colorText.addEventListener('input', (e) => { colorPicker.value = e.target.value; });

        // --- Tabbed Interface ---
        const tabs = document.querySelectorAll('.tab');
        const tabContent = document.getElementById('tab-content');
        const panes = document.querySelectorAll('.tab-pane');
        const activeTabName = '<?= $active_tab ?>';

        function switchTab(tabEl) {
            const tabName = tabEl.dataset.tab;
            tabs.forEach(t => t.classList.remove('active'));
            tabEl.classList.add('active');

            const activePane = document.querySelector('.tab-pane.active');
            const newPane = document.getElementById(tabName);

            if (activePane) activePane.classList.remove('active');
            newPane.classList.add('active');

            tabContent.style.height = newPane.scrollHeight + 'px';
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab));
            if (tab.dataset.tab === activeTabName) {
                switchTab(tab);
            }
        });

        window.addEventListener('load', () => {
             const activePaneOnLoad = document.querySelector('.tab-pane.active');
             if (activePaneOnLoad) {
                tabContent.style.height = activePaneOnLoad.scrollHeight + 'px';
             }
        });

        // --- API Test Form ---
        document.getElementById('api-test-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const responseContainer = document.getElementById('response-container');
            const responseJsonEl = document.getElementById('response-json');

            responseContainer.classList.remove('hidden');
            responseJsonEl.textContent = 'Loading...';
            responseJsonEl.classList.remove('text-red-500', 'dark:text-red-400');

            const formData = new FormData(this);
            fetch('user-check.php', { method: 'POST', body: formData })
                .then(async response => {
                    const text = await response.text();
                    if (!response.ok) {
                        throw new Error(`Server Error (Status: ${response.status}):\n\n${text}`);
                    }
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (jsonError) {
                        throw new Error(`Unexpected response from server (not valid JSON):\n\n${text}`);
                    }
                })
                .then(data => {
                    responseJsonEl.textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    responseJsonEl.classList.add('text-red-500', 'dark:text-red-400');
                    responseJsonEl.textContent = error.message;
                });
        });
    </script>
</body>
</html>
