<?php
// manage_app.php

session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Configuration & Setup ---
$app_id = $_GET['id'] ?? null;
if (!$app_id || !preg_match('/^app_[a-f0-9.]+$/', $app_id)) {
    header('Location: app_data.php');
    exit;
}

$config_file = 'config.json';
$app_data_dir = 'App_Data/';
$registry_file = $app_data_dir . '_registry.json';
$schema_file = $app_data_dir . $app_id . '_schema.json';
$data_file = $app_data_dir . $app_id . '_data.json';

// --- File Existence Checks ---
if (!file_exists($config_file) || !file_exists($registry_file) || !file_exists($schema_file) || !file_exists($data_file)) {
    die('A required application file is missing. Please check the App_Data directory.');
}

// --- Read configs ---
$config = json_decode(file_get_contents($config_file), true);
$registry = json_decode(file_get_contents($registry_file), true);
$schema = json_decode(file_get_contents($schema_file), true) ?: [];
$data_entries = json_decode(file_get_contents($data_file), true) ?: [];

$current_app_name = $registry[$app_id]['name'] ?? 'Unknown App';
$current_api_key = $registry[$app_id]['apiKey'] ?? 'No Key Found';
$appName = $config['appName'] ?? 'Dashboard';
$appIcon = $config['appIcon'] ?? 'default_icon.png';
$accentColor = $config['accentColor'] ?? '#6366f1';


// --- Helper Functions ---
function save_data($file, $data) { file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT)); }
function save_schema($file, $data) { file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT)); }
function save_app_registry($file, $data) { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }
function generateApiKey() { $prefix = 'data_sk_'; $random_part = bin2hex(random_bytes(16)); return $prefix . $random_part; }

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new schema key
    if (isset($_POST['add_key'])) {
        $new_key = trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['new_key_name'])); // Sanitize key name
        if (!empty($new_key) && !in_array($new_key, $schema)) {
            $schema[] = $new_key;
            save_schema($schema_file, $schema);
        }
    }

    // Delete a schema key
    if (isset($_POST['delete_key'])) {
        $key_to_delete = $_POST['key_name'];
        $schema = array_filter($schema, fn($k) => $k !== $key_to_delete);
        foreach ($data_entries as &$entry) { unset($entry[$key_to_delete]); }
        save_schema($schema_file, $schema);
        save_data($data_file, $data_entries);
    }

    // Regenerate API Key
    if (isset($_POST['regenerate_api_key'])) {
        $registry[$app_id]['apiKey'] = generateApiKey();
        save_app_registry($registry_file, $registry);
    }

    // Add new data entry
    if(isset($_POST['add_entry'])) {
        $new_entry = ['entry_id' => 'data_' . uniqid()];
        foreach($schema as $key) { $new_entry[$key] = htmlspecialchars(trim($_POST['data'][$key] ?? '')); }
        $data_entries[] = $new_entry;
        save_data($data_file, $data_entries);
    }

    // Edit data entry
    if(isset($_POST['edit_entry'])) {
        $entry_id_to_edit = $_POST['entry_id'];
        foreach($data_entries as &$entry) {
            if ($entry['entry_id'] === $entry_id_to_edit) {
                foreach($schema as $key) { $entry[$key] = htmlspecialchars(trim($_POST['data'][$key] ?? '')); }
                break;
            }
        }
        save_data($data_file, $data_entries);
    }

    // Delete data entry
    if(isset($_POST['delete_entry'])) {
        $entry_id_to_delete = $_POST['entry_id'];
        $data_entries = array_filter($data_entries, fn($entry) => $entry['entry_id'] !== $entry_id_to_delete);
        save_data($data_file, $data_entries);
    }

    // Delete the entire app
    if(isset($_POST['delete_app'])) {
        $confirmation_name = trim($_POST['confirmation_name']);
        if ($confirmation_name === $current_app_name) {
            unset($registry[$app_id]);
            save_app_registry($registry_file, $registry);

            if (file_exists($schema_file)) { @unlink($schema_file); }
            if (file_exists($data_file)) { @unlink($data_file); }

            $_SESSION['message'] = "App '{$current_app_name}' and all its data have been permanently deleted.";
            $_SESSION['message_type'] = 'success';
            header('Location: app_data.php');
            exit;
        } else {
             $_SESSION['delete_error'] = 'Confirmation name did not match. App deletion failed.';
             header("Location: manage_app.php?id=$app_id");
             exit;
        }
    }

    header("Location: manage_app.php?id=$app_id");
    exit;
}

$error_message = $_SESSION['delete_error'] ?? '';
unset($_SESSION['delete_error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?= htmlspecialchars($current_app_name) ?> - App Data</title>
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
                <div>
                    <div class="flex items-center gap-4">
                        <a href="app_data.php" class="p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700"><i class="ph ph-arrow-left text-2xl text-headings"></i></a>
                        <h1 class="text-xl md:text-3xl font-bold text-headings">Manage: <?= htmlspecialchars($current_app_name) ?></h1>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="lg:col-span-1 space-y-8">
                    <div class="card p-6" data-aos="fade-up">
                        <h2 class="text-xl font-semibold text-headings mb-4">API Information</h2>
                        <div><label class="block text-sm font-medium text-text-body mb-1">App ID</label><input type="text" value="<?= htmlspecialchars($app_id) ?>" readonly class="form-input w-full px-4 py-2 bg-slate-100 dark:bg-slate-800 cursor-not-allowed"></div>
                        <div class="mt-4"><label for="apiKey" class="block text-sm font-medium text-text-body mb-1">Secret API Key</label><div class="relative"><input type="text" id="apiKey" value="<?= htmlspecialchars($current_api_key) ?>" readonly class="form-input font-mono w-full px-4 py-2 bg-slate-100 dark:bg-slate-800 cursor-not-allowed"><button onclick="copyToClipboard('apiKey', this)" class="absolute inset-y-0 right-0 px-3 flex items-center text-text-muted hover:text-accent"><i class="ph ph-copy"></i></button></div></div>
                        <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" class="mt-4" onsubmit="return confirm('Generate a new API key? The old key will stop working immediately.');">
                            <button type="submit" name="regenerate_api_key" class="w-full text-sm font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300">Regenerate API Key</button>
                        </form>
                    </div>
                    <div class="card p-6" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="text-xl font-semibold text-headings mb-4">Data Schema</h2>
                        <p class="text-sm text-text-muted mb-6">Define the keys (fields) for your data.</p>
                        <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" class="flex gap-2">
                            <input type="text" name="new_key_name" placeholder="Enter new key name..." required class="form-input w-full px-4 py-2">
                            <button type="submit" name="add_key" class="btn-accent text-white font-bold p-2 rounded-lg"><i class="ph ph-plus text-xl"></i></button>
                        </form>
                        <div class="mt-6 space-y-2">
                            <?php if (empty($schema)): ?>
                                <p class="text-center text-text-muted text-sm py-4">No keys defined yet.</p>
                            <?php else: ?>
                                <?php foreach($schema as $key): ?>
                                <div class="flex justify-between items-center bg-slate-100 dark:bg-slate-700/50 p-2 rounded-lg">
                                    <span class="font-mono text-sm text-headings"><?= htmlspecialchars($key) ?></span>
                                    <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" onsubmit="return confirm('Delete this key? This will remove the data from all entries.');">
                                        <input type="hidden" name="key_name" value="<?= htmlspecialchars($key) ?>">
                                        <button type="submit" name="delete_key" class="p-1 text-red-500 hover:bg-red-100 dark:hover:bg-red-500/20 rounded-full"><i class="ph ph-trash text-lg"></i></button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                     <div class="card p-6 border-red-500/50 dark:border-red-500/40" data-aos="fade-up" data-aos-delay="200">
                        <h2 class="text-xl font-semibold text-red-600 dark:text-red-400 mb-4">Danger Zone</h2>
                        <?php if(!empty($error_message)): ?>
                             <div class="bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg mb-4 text-sm" role="alert"><?= $error_message ?></div>
                        <?php endif; ?>
                        <p class="text-sm text-text-muted mb-6">This action is permanent and cannot be undone.</p>
                         <button onclick="openDeleteAppModal()" class="w-full font-semibold bg-red-100 hover:bg-red-200 dark:bg-red-500/20 dark:hover:bg-red-500/30 text-red-600 dark:text-red-400 py-2 px-4 rounded-lg">Delete this App...</button>
                    </div>
                </div>

                <!-- Right Column: Data Management -->
                <div class="lg:col-span-2 space-y-8">
                    <div class="card p-6" data-aos="fade-up" data-aos-delay="150">
                         <h2 class="text-xl font-semibold text-headings mb-4">Add New Entry</h2>
                         <?php if(empty($schema)): ?>
                            <p class="text-text-muted text-center py-8">Please add at least one key to the schema to start adding data.</p>
                         <?php else: ?>
                            <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" class="space-y-4">
                                <?php foreach($schema as $key): ?>
                                <div>
                                    <label for="data_<?= htmlspecialchars($key) ?>" class="block text-sm font-medium text-text-body"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($key))) ?></label>
                                    <textarea id="data_<?= htmlspecialchars($key) ?>" name="data[<?= htmlspecialchars($key) ?>]" rows="2" class="form-input mt-1 w-full px-4 py-2"></textarea>
                                </div>
                                <?php endforeach; ?>
                                <div class="flex justify-end">
                                    <button type="submit" name="add_entry" class="flex items-center gap-2 btn-accent text-white font-semibold py-2 px-4 rounded-lg"><i class="ph ph-plus-circle"></i>Add Entry</button>
                                </div>
                            </form>
                         <?php endif; ?>
                    </div>

                    <div class="card overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                        <h2 class="text-xl font-semibold text-headings p-6 border-b border-slate-200 dark:border-slate-700">Content Entries (<?= count($data_entries) ?>)</h2>
                        <div class="overflow-x-auto">
                        <?php if (empty($data_entries)): ?>
                            <p class="text-center text-text-muted p-8">No data entries yet.</p>
                        <?php else: ?>
                            <table class="min-w-full">
                                <thead style="background-color: var(--table-header-bg);">
                                    <tr>
                                        <?php foreach($schema as $key): ?>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider"><?= htmlspecialchars($key) ?></th>
                                        <?php endforeach; ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y" style="border-color: var(--card-border);">
                                <?php foreach($data_entries as $entry): ?>
                                    <tr style="background-color: var(--card-bg);" class="hover:bg-table-row-hover">
                                        <?php foreach($schema as $key): ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-body"><?= substr($entry[$key] ?? '', 0, 30) . (strlen($entry[$key] ?? '') > 30 ? '...' : '') ?></td>
                                        <?php endforeach; ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <button onclick='openEditModal(<?= json_encode($entry) ?>)' class="p-2 rounded-md text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-500/20"><i class="ph ph-pencil-simple text-lg"></i></button>
                                                <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" onsubmit="return confirm('Delete this entry?');">
                                                    <input type="hidden" name="entry_id" value="<?= $entry['entry_id'] ?>">
                                                    <button type="submit" name="delete_entry" class="p-2 rounded-md text-red-600 hover:bg-red-100 dark:hover:bg-red-500/20"><i class="ph ph-trash text-lg"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="modal-container" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 opacity-0 pointer-events-none z-50 transition-opacity duration-300"></div>

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
        function openEditModal(entry) {
            let formFields = '';
            const schema = <?= json_encode($schema) ?>;
            schema.forEach(key => {
                const value = entry[key] || '';
                const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                formFields += `<div><label class="block text-sm font-medium text-text-body">${label}</label><textarea name="data[${key}]" rows="2" class="form-input mt-1 w-full px-4 py-2">${value}</textarea></div>`;
            });

            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">Edit Entry</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" class="mt-4">
                        <input type="hidden" name="entry_id" value="${entry.entry_id}">
                        <div class="space-y-4">${formFields}</div>
                        <div class="mt-6 flex gap-4">
                            <button type="button" onclick="closeModal()" class="w-full bg-slate-200 dark:bg-slate-600 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-500 transition">Cancel</button>
                            <button type="submit" name="edit_entry" class="w-full flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2 px-4 rounded-lg"><i class="ph ph-floppy-disk"></i>Save Changes</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

        function openDeleteAppModal() {
             modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b border-red-200 dark:border-red-500/30"><h3 class="text-xl font-semibold text-red-600 dark:text-red-400">Delete App</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="manage_app.php?id=<?= urlencode($app_id) ?>" method="POST" class="mt-4">
                        <p class="text-sm text-text-body">This action is permanent and cannot be undone. It will permanently delete the '<strong><?= htmlspecialchars($current_app_name) ?></strong>' app, its schema, and all of its data.</p>
                        <p class="mt-4 text-sm text-text-body">Please type <strong class="text-headings font-bold"><?= htmlspecialchars($current_app_name) ?></strong> to confirm.</p>
                        <div class="mt-2"><input type="text" id="confirmation_name" name="confirmation_name" required class="form-input w-full px-4 py-2 transition" onkeyup="validateDeleteConfirmation()"></div>
                        <div class="mt-6">
                            <button type="submit" id="confirm-delete-btn" name="delete_app" class="w-full flex justify-center items-center gap-2 bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition opacity-50 cursor-not-allowed" disabled><i class="ph ph-trash"></i>I understand, delete this app</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

        function validateDeleteConfirmation() {
            const confirmationInput = document.getElementById('confirmation_name');
            const deleteBtn = document.getElementById('confirm-delete-btn');
            const appName = "<?= htmlspecialchars($current_app_name) ?>";
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

        function copyToClipboard(elementId, button) {
            const input = document.getElementById(elementId);
            navigator.clipboard.writeText(input.value).then(() => {
                const originalIcon = button.innerHTML;
                button.innerHTML = '<i class="ph ph-check text-green-500"></i>';
                setTimeout(() => {
                    button.innerHTML = originalIcon;
                }, 1500);
            });
        }
    </script>
</body>
</html>
