<?php
// app_data.php

session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Configuration & Setup ---
$config_file = 'config.json';
$app_data_dir = 'App_Data/';
$registry_file = $app_data_dir . '_registry.json';

// --- Read branding config for display ---
if (!file_exists($config_file)) {
    die('Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);
$appName = $config['appName'] ?? 'Dashboard';
$appIcon = $config['appIcon'] ?? 'default_icon.png';
$accentColor = $config['accentColor'] ?? '#6366f1';

// --- Helper Functions for App Data ---
function get_app_registry($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function save_app_registry($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function generateApiKey() {
    $prefix = 'data_sk_';
    $random_part = bin2hex(random_bytes(16));
    return $prefix . $random_part;
}

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registry = get_app_registry($registry_file);

    // Create a new app
    if (isset($_POST['create_app'])) {
        $new_app_name = trim($_POST['new_app_name']);

        if (!empty($new_app_name)) {
            $app_id = 'app_' . uniqid();
            $registry[$app_id] = [
                'name' => htmlspecialchars($new_app_name),
                'apiKey' => generateApiKey(),
                'createdAt' => date('Y-m-d H:i:s')
            ];

            file_put_contents($app_data_dir . $app_id . '_schema.json', json_encode([]));
            file_put_contents($app_data_dir . $app_id . '_data.json', json_encode([]));

            save_app_registry($registry_file, $registry);

            header("Location: manage_app.php?id=$app_id");
            exit;
        }
    }

    // Delete an app
    if (isset($_POST['delete_app'])) {
        $app_id_to_delete = $_POST['app_id'];
        $confirmation_name = trim($_POST['confirmation_name']);

        if (isset($registry[$app_id_to_delete]) && $confirmation_name === $registry[$app_id_to_delete]['name']) {
            // Remove from registry
            unset($registry[$app_id_to_delete]);
            save_app_registry($registry_file, $registry);

            // Delete associated files
            $schema_file_to_delete = $app_data_dir . $app_id_to_delete . '_schema.json';
            $data_file_to_delete = $app_data_dir . $app_id_to_delete . '_data.json';
            if (file_exists($schema_file_to_delete)) { @unlink($schema_file_to_delete); }
            if (file_exists($data_file_to_delete)) { @unlink($data_file_to_delete); }

            $_SESSION['message'] = "App '{$confirmation_name}' has been permanently deleted.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Confirmation failed. App was not deleted.';
            $_SESSION['message_type'] = 'error';
        }
        header("Location: app_data.php");
        exit;
    }
}

// Get success/error messages from session after redirect
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

// Get the list of apps for display
$apps = get_app_registry($registry_file);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Data - <?= htmlspecialchars($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f1f5f9; --sidebar-bg: white; --card-bg: white; --card-border: #e2e8f0; --text-headings: #1e293b; --text-body: #475569; --text-muted: #64748b;
            --input-bg: white; --input-border: #cbd5e1; --accent: <?= htmlspecialchars($accentColor) ?>; --accent-text: white; --table-row-hover: #f8fafc;
        }
        html.dark {
            --bg-color: #0f172a; --sidebar-bg: #1e293b; --card-bg: #1e293b; --card-border: #334155; --text-headings: #f1f5f9; --text-body: #94a3b8; --text-muted: #64748b;
            --input-bg: #334155; --input-border: #475569; --table-row-hover: #334155;
        }
        body { background-color: var(--bg-color); color: var(--text-body); font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: background-color 0.3s, border-color 0.3s; }
        .form-input { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-headings); border-radius: 0.5rem; transition: background-color 0.3s, border-color 0.3s, color 0.3s; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); outline: none; }
        .btn-accent { background-color: var(--accent); color: var(--accent-text); }
        .btn-accent:hover { filter: brightness(90%); }
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
            <button id="sidebar-close" class="md:hidden p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700"><i class="ph ph-x text-xl text-headings"></i></button>
        </div>
        <nav class="mt-8 px-4 flex-grow">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
                <i class="ph ph-users-three text-xl"></i><span>Users</span>
            </a>
            <a href="app_data.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold btn-accent">
                 <i class="ph ph-database text-xl"></i><span>App Data</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
                <i class="ph ph-gear text-xl"></i><span>Settings</span>
            </a>
        </nav>
        <div class="p-4 mt-auto flex-shrink-0 border-t" style="border-color: var(--card-border);">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-full bg-slate-200 dark:bg-slate-700"><i class="ph ph-user text-lg text-headings"></i></div>
                <div><p class="text-sm font-semibold text-headings">Admin</p><p class="text-xs text-text-muted">Logged In</p></div>
            </div>
             <a href="logout.php" class="mt-4 flex items-center justify-center gap-2 w-full px-4 py-2 rounded-lg text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700/50 font-semibold text-sm"><i class="ph ph-sign-out text-lg"></i><span>Sign Out</span></a>
        </div>
    </aside>

    <main class="flex-1 md:ml-64 overflow-y-auto">
        <div class="container mx-auto px-4 sm:px-6 lg:px-10 py-8">
            <header class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-8" data-aos="fade-down">
                <div class="flex items-center gap-4">
                    <button id="menu-toggle" class="md:hidden p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700"><i class="ph ph-list text-2xl text-headings"></i></button>
                    <div>
                        <h1 class="text-xl md:text-3xl font-bold text-headings">App Data Management</h1>
                        <p class="mt-1 text-sm md:text-base text-text-muted">Create and manage simple JSON databases for your apps.</p>
                    </div>
                </div>
                <button onclick="openCreateAppModal()" class="w-full md:w-auto flex-shrink-0 flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2 px-4 rounded-lg transition-all ease-in-out duration-150 shadow-sm"><i class="ph ph-plus-circle text-lg"></i><span>Create New App</span></button>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" data-aos="fade-up">
                <?php if (empty($apps)): ?>
                    <div class="sm:col-span-2 lg:col-span-3 text-center p-12 card">
                        <svg class="mx-auto h-24 w-24 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        <h3 class="mt-4 text-lg font-semibold text-headings">No Apps Yet</h3>
                        <p class="mt-1 text-sm text-text-muted">Click "Create New App" to get started.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($apps as $id => $app): ?>
                    <div class="card p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-bold text-headings"><?= htmlspecialchars($app['name']) ?></h3>
                                    <p class="text-sm text-text-muted">Created: <?= (new DateTime($app['createdAt']))->format('M d, Y') ?></p>
                                </div>
                                <div class="p-3 rounded-lg bg-slate-100 dark:bg-slate-700">
                                    <i class="ph ph-database text-2xl" style="color: var(--accent);"></i>
                                </div>
                            </div>
                            <div class="mt-4 text-xs font-mono text-text-muted">App ID: <?= htmlspecialchars($id) ?></div>
                        </div>
                        <div class="mt-6 flex gap-2">
                           <a href="manage_app.php?id=<?= urlencode($id) ?>" class="flex-1 text-center font-semibold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-headings py-2 px-4 rounded-lg text-sm">Manage</a>
                           <button onclick="openDeleteAppModal('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($app['name']) ?>')" class="flex-shrink-0 p-2 text-red-500 hover:bg-red-100 dark:hover:bg-red-500/20 rounded-lg"><i class="ph ph-trash text-lg"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div id="modal-container" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 opacity-0 pointer-events-none z-50 transition-opacity duration-300"></div>
<div id="toast" class="fixed top-5 right-5 bg-green-600 text-white py-2 px-5 rounded-lg shadow-lg opacity-0 transform translate-y-5 transition-all duration-300 z-50"></div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 600, once: true, disable: 'mobile' });

        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarCloseBtn = document.getElementById('sidebar-close');
        function openSidebar() { sidebar.classList.remove('-translate-x-full'); sidebarOverlay.classList.remove('hidden'); }
        function closeSidebar() { sidebar.classList.add('-translate-x-full'); sidebarOverlay.classList.add('hidden'); }
        menuToggle.addEventListener('click', (e) => { e.stopPropagation(); openSidebar(); });
        sidebarCloseBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        const modalContainer = document.getElementById('modal-container');
        function openCreateAppModal() {
            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">Create New App Database</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="app_data.php" method="POST" class="mt-4">
                        <div class="space-y-4">
                            <div><label for="new_app_name" class="block text-sm font-medium text-text-body">App Name</label><input type="text" id="new_app_name" name="new_app_name" required class="form-input mt-1 block w-full px-4 py-2 transition" placeholder="e.g., My Movie App"></div>
                        </div>
                        <div class="mt-6 flex gap-4">
                            <button type="button" onclick="closeModal()" class="w-full bg-slate-200 dark:bg-slate-600 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-500 transition">Cancel</button>
                            <button type="submit" name="create_app" class="w-full flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2 px-4 rounded-lg transition"><i class="ph ph-plus-circle"></i>Create App</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

         function openDeleteAppModal(appId, appName) {
             modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b border-red-200 dark:border-red-500/30"><h3 class="text-xl font-semibold text-red-600 dark:text-red-400">Delete App</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="app_data.php" method="POST" class="mt-4">
                        <input type="hidden" name="app_id" value="${appId}">
                        <p class="text-sm text-text-body">This action is permanent and cannot be undone. It will permanently delete the '<strong>${appName}</strong>' app, its schema, and all of its data.</p>
                        <p class="mt-4 text-sm text-text-body">Please type <strong class="text-headings font-bold">${appName}</strong> to confirm.</p>
                        <div class="mt-2"><input type="text" id="confirmation_name" name="confirmation_name" required class="form-input w-full px-4 py-2 transition" onkeyup="validateDeleteConfirmation('${appName}')"></div>
                        <div class="mt-6">
                            <button type="submit" id="confirm-delete-btn" name="delete_app" class="w-full flex justify-center items-center gap-2 bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition opacity-50 cursor-not-allowed" disabled><i class="ph ph-trash"></i>I understand, delete this app</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

        function validateDeleteConfirmation(appName) {
            const confirmationInput = document.getElementById('confirmation_name');
            const deleteBtn = document.getElementById('confirm-delete-btn');
            if(confirmationInput.value === appName) {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                deleteBtn.disabled = true;
                deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        function showModal() { 
            modalContainer.classList.remove('opacity-0', 'pointer-events-none');
            setTimeout(() => { modalContainer.querySelector('.modal-content').classList.remove('scale-95'); }, 50);
        }
        function closeModal() { 
            const modalContent = modalContainer.querySelector('.modal-content');
            if (modalContent) { modalContent.classList.add('scale-95'); }
            modalContainer.classList.add('opacity-0');
            setTimeout(() => { modalContainer.classList.add('pointer-events-none'); }, 300);
        }
        window.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeModal(); } });
        modalContainer.addEventListener('click', (e) => { if (e.target === modalContainer) { closeModal(); } });

        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', () => { 
                const toast = document.getElementById('toast'); 
                toast.textContent = '<?= addslashes($message) ?>'; 
                toast.className = `fixed top-5 right-5 text-white py-2 px-5 rounded-lg shadow-lg opacity-100 transform translate-y-0 transition-all duration-300 z-50 <?= $message_type === 'success' ? 'bg-green-600' : 'bg-red-600' ?>`;
                setTimeout(() => { toast.style.opacity = '0'; }, 3000); 
            });
        <?php endif; ?>
    </script>
</body>
</html>
