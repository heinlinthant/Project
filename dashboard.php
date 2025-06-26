<?php
// dashboard.php

session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Configuration & Setup ---
$json_file = 'usersData.json';
$config_file = 'config.json';
$users_per_page = 10;
date_default_timezone_set('Asia/Yangon');

// --- Read branding config for display ---
if (!file_exists($config_file)) {
    die('Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);
$appName = $config['appName'] ?? 'Dashboard';
$appIcon = $config['appIcon'] ?? 'default_icon.png';
$accentColor = $config['accentColor'] ?? '#6366f1';


// --- Helper Functions ---
function getUsers($file) { if (!file_exists($file)) file_put_contents($file, '[]'); return json_decode(file_get_contents($file), true) ?: []; }
function saveUsers($file, $data) { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }
function generateKey() { $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $numbers = '0123456789'; $key = ''; for ($i=0; $i<3; $i++) $key.=$letters[rand(0,25)]; for ($i=0; $i<4; $i++) $key.=$numbers[rand(0,9)]; return str_shuffle($key); }

function generateCardId($existing_users) {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $existing_card_ids = array_column($existing_users, 'cardId');

    do {
        $cardId_parts = [];
        for ($i=0; $i<2; $i++) { $cardId_parts[] = $letters[rand(0, strlen($letters) - 1)]; }
        for ($i=0; $i<3; $i++) { $cardId_parts[] = $numbers[rand(0, strlen($numbers) - 1)]; }
        shuffle($cardId_parts);
        $cardId = implode('', $cardId_parts);
    } while (in_array($cardId, $existing_card_ids));

    return $cardId;
}


// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getUsers($json_file);

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $subscriptions = filter_input(INPUT_POST, 'subscriptions', FILTER_VALIDATE_INT);
        if (!empty($username) && $subscriptions > 0) {
            $startDate = new DateTime(); $endDate = clone $startDate; $endDate->modify("+" . $subscriptions . " days");
            $newUser = [
                'id' => uniqid('user_', true),'cardId' => generateCardId($users), 'username' => htmlspecialchars($username),'key' => generateKey(),'subscriptions' => $subscriptions,'startDate' => $startDate->format('Y-m-d H:i:s'),'endDate' => $endDate->format('Y-m-d H:i:s'),'deviceId' => '','status' => 'new','notes' => '', 'userMessage' => ''
            ];
            $users[] = $newUser;
            saveUsers($json_file, $users);
            $_SESSION['message'] = "User '" . htmlspecialchars($username) . "' added successfully!";
            $_SESSION['message_type'] = 'success';
            $_SESSION['new_user_data'] = $newUser;
        } else { $_SESSION['message'] = "Invalid input."; $_SESSION['message_type'] = 'error'; }
    } elseif (isset($_POST['edit_user'])) {
        $userIdToEdit = $_POST['user_id']; $newUsername = trim($_POST['username']); $newSubscriptions = filter_input(INPUT_POST, 'subscriptions', FILTER_VALIDATE_INT); $newNotes = trim($_POST['notes']); $newUserMessage = trim($_POST['userMessage']); $userUpdated = false;
        foreach ($users as &$user) {
            if ($user['id'] === $userIdToEdit && !empty($newUsername) && $newSubscriptions > 0) {
                $user['username'] = htmlspecialchars($newUsername); $user['subscriptions'] = $newSubscriptions; $user['notes'] = htmlspecialchars($newNotes); $user['userMessage'] = htmlspecialchars($newUserMessage);
                $startDate = new DateTime($user['startDate']); $endDate = clone $startDate; $endDate->modify("+" . $newSubscriptions . " days"); $user['endDate'] = $endDate->format('Y-m-d H:i:s');
                $userUpdated = true; $_SESSION['message'] = "User '{$user['username']}' updated!"; $_SESSION['message_type'] = 'success'; break;
            }
        }
        unset($user);
        if ($userUpdated) saveUsers($json_file, $users); else { $_SESSION['message'] = "Invalid data for update."; $_SESSION['message_type'] = 'error'; }
    } elseif (isset($_POST['delete_user'])) {
        $userIdToDelete = $_POST['user_id']; $initialUserCount = count($users);
        $users = array_filter($users, fn($user) => $user['id'] !== $userIdToDelete);
        if (count($users) < $initialUserCount) { saveUsers($json_file, array_values($users)); $_SESSION['message'] = "User deleted."; $_SESSION['message_type'] = 'success'; } 
        else { $_SESSION['message'] = "Error: User not found."; $_SESSION['message_type'] = 'error'; }
    } elseif (isset($_POST['reset_device_id'])) {
        $userIdToReset = $_POST['user_id'];
        $userFound = false;
        foreach($users as &$user) {
            if($user['id'] === $userIdToReset) {
                $user['deviceId'] = '';
                if ($user['status'] === 'used') { $user['status'] = 'new'; }
                $userFound = true;
                break;
            }
        }
        unset($user);
        if ($userFound) { saveUsers($json_file, $users); $_SESSION['message'] = 'Device ID has been reset.'; $_SESSION['message_type'] = 'success'; } 
        else { $_SESSION['message'] = 'Error: User not found.'; $_SESSION['message_type'] = 'error'; }
    }
    $query = http_build_query(['filter' => ($_GET['filter'] ?? 'all'), 'sort' => ($_GET['sort'] ?? 'newest'), 'search' => ($_GET['search'] ?? '')]);
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . $query); 
    exit();
}

// --- Data Loading and Processing for Display ---
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
$new_user_data = $_SESSION['new_user_data'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type'], $_SESSION['new_user_data']);

$all_users = getUsers($json_file);

// Update status for expired users
$statusChanged = false;
foreach ($all_users as &$user) { if (isset($user['status']) && $user['status'] !== 'expired' && isset($user['endDate']) && (new DateTime() > new DateTime($user['endDate']))) { $user['status'] = 'expired'; $statusChanged = true; } }
unset($user);
if ($statusChanged) saveUsers($json_file, $all_users);

// --- Sort users by start date, newest first ---
$sort_option = trim($_GET['sort'] ?? 'newest');
if (!empty($all_users)) {
    usort($all_users, function($a, $b) use ($sort_option) {
        switch ($sort_option) {
            case 'oldest': return (strtotime($a['startDate'] ?? 0)) <=> (strtotime($b['startDate'] ?? 0));
            case 'expiring_soon':
                $now = new DateTime();
                $endA = isset($a['endDate']) ? new DateTime($a['endDate']) : (new DateTime())->modify('+1000 years');
                $endB = isset($b['endDate']) ? new DateTime($b['endDate']) : (new DateTime())->modify('+1000 years');
                if ($endA < $now) return 1; if ($endB < $now) return -1;
                return $endA <=> $endB;
            case 'sub_high': return ($b['subscriptions'] ?? 0) <=> ($a['subscriptions'] ?? 0);
            case 'sub_low': return ($a['subscriptions'] ?? 0) <=> ($b['subscriptions'] ?? 0);
            case 'name_az': return strcasecmp($a['username'] ?? '', $b['username'] ?? '');
            case 'name_za': return strcasecmp($b['username'] ?? '', $a['username'] ?? '');
            default: return (strtotime($b['startDate'] ?? 0)) <=> (strtotime($a['startDate'] ?? 0));
        }
    });
}

// --- Statistics Logic (calculated on all users) ---
$stats = [ 'total' => count($all_users), 'active' => 0, 'new' => 0, 'expired' => 0 ];
foreach($all_users as $user) {
    $status = $user['status'] ?? 'unknown'; 
    switch($status) { case 'used': $stats['active']++; break; case 'new': $stats['new']++; break; case 'expired': $stats['expired']++; break; }
}

// Filter users after sorting
$search_query = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['filter'] ?? 'all');
$display_users = $all_users;
if (!empty($search_query)) { $display_users = array_filter($display_users, fn($user) => stripos($user['username'], $search_query) !== false || stripos($user['key'], $search_query) !== false || (isset($user['cardId']) && strcasecmp($user['cardId'], $search_query) === 0)); }
if ($filter_status !== 'all') { $display_users = array_filter($display_users, fn($user) => ($user['status'] ?? '') === $filter_status); }

// --- Pagination Logic (applied after sorting and filtering) ---
$total_users_to_display = count($display_users);
$total_pages = $users_per_page > 0 ? ceil($total_users_to_display / $users_per_page) : 1;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
if ($current_page === false || $current_page > $total_pages) $current_page = 1;
$offset = ($current_page - 1) * $users_per_page;
$paginated_users = array_slice(array_values($display_users), $offset, $users_per_page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        :root {
            --bg-color: #f1f5f9; --sidebar-bg: white; --card-bg: white; --card-border: #e2e8f0; --text-headings: #1e293b; --text-body: #475569; --text-muted: #64748b;
            --input-bg: white; --input-border: #cbd5e1; --accent: <?= htmlspecialchars($accentColor) ?>; --accent-text: white; --accent-hover: #4f46e5; --table-header-bg: #f8fafc; --table-row-hover: #f8fafc;
        }

        html.dark {
            --bg-color: #0f172a; --sidebar-bg: #1e293b; --card-bg: #1e293b; --card-border: #334155; --text-headings: #f1f5f9; --text-body: #94a3b8; --text-muted: #64748b;
            --input-bg: #334155; --input-border: #475569; --table-header-bg: #1e293b; --table-row-hover: #334155;
        }

        body { background-color: var(--bg-color); color: var(--text-body); font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
        .card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); transition: background-color 0.3s, border-color 0.3s, transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
        .form-input { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-headings); border-radius: 0.5rem; transition: background-color 0.3s, border-color 0.3s, color 0.3s; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); outline: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-new { background-color: #eff6ff; color: #1d4ed8; } .dark .status-new { background-color: #1e3a8a; color: #bfdbfe; }
        .status-used { background-color: #f0fdf4; color: #15803d; } .dark .status-used { background-color: #14532d; color: #bbf7d0; }
        .status-expired { background-color: #fef2f2; color: #b91c1c; } .dark .status-expired { background-color: #7f1d1d; color: #fecaca; }
        .action-btn-group { position: relative; }
        .action-btn { transition: background-color 0.2s; }
        .action-btn .tooltip { visibility: hidden; opacity: 0; position: absolute; bottom: 125%; left: 50%; transform: translateX(-50%); background-color: #1e293b; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; white-space: nowrap; transition: opacity 0.2s, visibility 0.2s; pointer-events: none; }
        .action-btn:hover .tooltip { visibility: visible; opacity: 1; }
        .stat-card-icon { width: 3.5rem; height: 3.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .filter-btn { background-color: transparent; border: 1px solid transparent; color: var(--text-muted); border-radius: 0.5rem; padding: 0.375rem 1rem; font-size: 0.875rem; font-weight: 500; transition: all 0.2s ease-in-out; }
        .filter-btn:hover { color: var(--text-headings); background-color: var(--table-row-hover); }
        .filter-btn.active { background-color: var(--accent); color: var(--accent-text); box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1); }
        .dark .filter-btn.active { color: var(--accent-text); }
        #theme-toggle { width: 56px; height: 32px; border-radius: 9999px; background-color: #e2e8f0; cursor: pointer; position: relative; transition: background-color 0.3s ease; }
        #theme-toggle:hover { background-color: #cbd5e1; }
        html.dark #theme-toggle { background-color: #4f46e5; }
        html.dark #theme-toggle:hover { background-color: #4338ca; }
        #theme-toggle .thumb { width: 24px; height: 24px; background-color: white; border-radius: 50%; position: absolute; top: 4px; left: 4px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        html.dark #theme-toggle .thumb { transform: translateX(24px); }
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
<div id="welcome-card-template" class="fixed top-0 left-0 w-[420px] -translate-x-full" style="font-family: 'Inter', sans-serif;">
    <div id="card-content" class="rounded-2xl shadow-2xl overflow-hidden bg-gradient-to-br from-slate-50 to-indigo-100 dark:from-slate-800 dark:to-indigo-900/50 p-1">
       <div class="bg-slate-100/80 dark:bg-slate-900/80 backdrop-blur-sm rounded-[14px] p-8">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <img id="card-app-icon" src="" alt="App Icon" class="w-14 h-14 rounded-xl object-cover shadow-md">
                    <div>
                         <h2 id="card-app-name" class="text-xl font-bold text-slate-800 dark:text-slate-100"></h2>
                         <p class="text-sm text-slate-500 dark:text-slate-400">Subscription Activated</p>
                    </div>
                </div>
                <div id="card-qrcode" class="w-20 h-20 p-1 bg-white rounded-lg shadow-md"></div>
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-baseline py-2"><span class="text-slate-500 dark:text-slate-400">Username:</span><span id="card-username" class="font-semibold text-slate-700 dark:text-slate-200"></span></div>
                <div class="flex justify-between items-baseline py-2 border-t border-slate-200 dark:border-slate-700"><span class="text-slate-500 dark:text-slate-400">License Key:</span><span id="card-key" class="font-mono font-semibold text-indigo-500 dark:text-indigo-400 text-base"></span></div>
                <div class="flex justify-between items-baseline py-2 border-t border-slate-200 dark:border-slate-700"><span class="text-slate-500 dark:text-slate-400">Subscription Period:</span><span id="card-sub-period" class="font-semibold text-slate-700 dark:text-slate-200"></span></div>
            </div>
             <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                <div><p class="text-xs text-slate-500 dark:text-slate-400">Start Date</p><p id="card-start-date" class="text-xs font-semibold text-slate-700 dark:text-slate-200"></p></div>
                <div><p class="text-xs text-slate-500 dark:text-slate-400">Expiry Date</p><p id="card-end-date" class="text-xs font-semibold text-slate-700 dark:text-slate-200"></p></div>
            </div>
            <p class="text-xs text-center text-slate-400 dark:text-slate-500 mt-6">Card ID: <span id="card-id" class="font-mono"></span></p>
       </div>
    </div>
</div>


<div class="relative md:flex">
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-20 md:hidden hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 flex-shrink-0 z-30 -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col" style="background-color: var(--sidebar-bg); border-right: 1px solid var(--card-border);">
        <div class="p-6 flex items-center justify-between flex-shrink-0">
             <a href="dashboard.php" class="flex items-center gap-3">
                <img src="<?= htmlspecialchars($appIcon) ?>" alt="App Icon" class="w-10 h-10 rounded-lg object-cover" onerror="this.src='https://placehold.co/40x40/6366f1/ffffff?text=D'; this.onerror=null;">
                <h1 class="text-2xl font-bold text-headings"><?= htmlspecialchars($appName) ?></h1>
            </a>
            <button id="sidebar-close" class="md:hidden p-2 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700">
                <i class="ph ph-x text-xl text-headings transition-transform duration-300 ease-in-out"></i>
            </button>
        </div>
        <nav class="mt-8 px-4 flex-grow">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold btn-accent">
                <i class="ph ph-users-three text-xl"></i><span>Users</span>
            </a>
            <a href="app_data.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
                 <i class="ph ph-database text-xl"></i><span>App Data</span>
            </a>
             <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-text-body hover:bg-gray-100 dark:hover:bg-slate-700/50">
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
                        <h1 class="text-xl md:text-3xl font-bold text-headings">User Management</h1>
                        <p class="mt-1 text-sm md:text-base text-text-muted">Manage your users, subscriptions, and devices.</p>
                    </div>
                </div>
                <button id="theme-toggle"><div class="thumb"></div></button>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8" data-aos="fade-up">
                <div class="card stat-card p-5"><div class="flex items-center gap-4"><div class="stat-card-icon bg-pink-100 dark:bg-pink-500/20"><i class="ph ph-users text-2xl text-pink-500 dark:text-pink-400"></i></div><div><p class="text-sm text-text-muted">Total Users</p><p class="text-2xl font-bold text-headings"><?= $stats['total'] ?></p></div></div></div>
                <div class="card stat-card p-5"><div class="flex items-center gap-4"><div class="stat-card-icon bg-green-100 dark:bg-green-500/20"><i class="ph ph-user-circle-check text-2xl text-green-500 dark:text-green-400"></i></div><div><p class="text-sm text-text-muted">Active Users</p><p class="text-2xl font-bold text-headings"><?= $stats['active'] ?></p></div></div></div>
                <div class="card stat-card p-5"><div class="flex items-center gap-4"><div class="stat-card-icon bg-blue-100 dark:bg-blue-500/20"><i class="ph ph-key text-2xl text-blue-500 dark:text-blue-400"></i></div><div><p class="text-sm text-text-muted">New Keys</p><p class="text-2xl font-bold text-headings"><?= $stats['new'] ?></p></div></div></div>
                <div class="card stat-card p-5"><div class="flex items-center gap-4"><div class="stat-card-icon bg-red-100 dark:bg-red-500/20"><i class="ph ph-user-circle-minus text-2xl text-red-500 dark:text-red-400"></i></div><div><p class="text-sm text-text-muted">Expired</p><p class="text-2xl font-bold text-headings"><?= $stats['expired'] ?></p></div></div></div>
            </div>

            <div class="card p-4 mb-8" data-aos="fade-up" data-aos-delay="100">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                     <button onclick="openAddUserModal()" class="w-full md:w-auto flex-shrink-0 flex justify-center items-center gap-2 btn-accent text-white font-semibold py-2 px-4 rounded-lg transition-all ease-in-out duration-150 shadow-sm"><i class="ph ph-plus-circle text-lg"></i><span>Add New User</span></button>
                    <form action="dashboard.php" method="GET" class="w-full md:max-w-xs"><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="ph ph-magnifying-glass text-text-muted"></i></div><input type="search" name="search" placeholder="Search by User, Key, or Card ID..." value="<?= htmlspecialchars($search_query) ?>" class="form-input block w-full pl-10 pr-3 py-2"></div><input type="hidden" name="filter" value="<?= htmlspecialchars($filter_status) ?>"><input type="hidden" name="sort" value="<?= htmlspecialchars($sort_option) ?>"></form>
                </div>
            </div>

            <div class="card overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                <div class="p-4 flex flex-col md:flex-row justify-between items-center gap-4 border-b border-slate-200 dark:border-slate-700">
                     <div class="flex items-center gap-2">
                         <h3 class="text-xl font-semibold text-headings whitespace-nowrap">User List</h3>
                         <form id="sortForm" action="dashboard.php" method="GET">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_status) ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                            <select name="sort" onchange="this.form.submit()" class="form-input text-sm py-1.5 pr-8">
                                <option value="newest" <?= $sort_option === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_option === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="expiring_soon" <?= $sort_option === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                                <option value="sub_high" <?= $sort_option === 'sub_high' ? 'selected' : '' ?>>Subscription (High-Low)</option>
                                <option value="sub_low" <?= $sort_option === 'sub_low' ? 'selected' : '' ?>>Subscription (Low-High)</option>
                                <option value="name_az" <?= $sort_option === 'name_az' ? 'selected' : '' ?>>Username (A-Z)</option>
                                <option value="name_za" <?= $sort_option === 'name_za' ? 'selected' : '' ?>>Username (Z-A)</option>
                            </select>
                        </form>
                     </div>
                     <div class="flex items-center gap-2 p-1 rounded-lg" style="background-color: var(--bg-color)">
                        <a href="?sort=<?= $sort_option ?>&filter=all&search=<?= urlencode($search_query) ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">All</a>
                        <a href="?sort=<?= $sort_option ?>&filter=new&search=<?= urlencode($search_query) ?>" class="filter-btn <?= $filter_status === 'new' ? 'active' : '' ?>">New</a>
                        <a href="?sort=<?= $sort_option ?>&filter=used&search=<?= urlencode($search_query) ?>" class="filter-btn <?= $filter_status === 'used' ? 'active' : '' ?>">Used</a>
                        <a href="?sort=<?= $sort_option ?>&filter=expired&search=<?= urlencode($search_query) ?>" class="filter-btn <?= $filter_status === 'expired' ? 'active' : '' ?>">Expired</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                <?php if (empty($paginated_users)): ?>
                    <div class="p-12 text-center">
                         <svg class="mx-auto h-24 w-24 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <h3 class="mt-4 text-lg font-semibold text-headings">No users found</h3>
                        <p class="mt-1 text-sm text-text-muted">No users match your current search, sort, and filter criteria.</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full">
                        <thead style="background-color: var(--table-header-bg);"><tr><th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Username / Card ID</th><th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Key</th><th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Status</th><th class="px-6 py-3 text-left text-xs font-medium text-text-muted uppercase tracking-wider">Actions</th></tr></thead>
                        <tbody class="divide-y" style="border-color: var(--card-border);">
                        <?php foreach ($paginated_users as $user): ?>
                            <tr style="background-color: var(--card-bg);" class="hover:bg-table-row-hover">
                                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-headings"><?= $user['username'] ?></div><div class="text-sm text-text-muted font-mono">ID: <?= $user['cardId'] ?? 'N/A' ?></div></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-text-body"><div class="flex items-center gap-2"><span class="font-mono" id="key-<?= $user['id'] ?>"><?= $user['key'] ?></span><button onclick="copyToClipboard('key-<?= $user['id'] ?>', '<?= $user['id'] ?>')" class="p-1 text-text-muted hover:text-indigo-600" title="Copy Key"><i id="copy-icon-<?= $user['id'] ?>" class="ph ph-copy"></i></button></div></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="status-badge status-<?= strtolower($user['status'] ?? 'unknown') ?>"><?= ucfirst($user['status'] ?? 'unknown') ?></span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><div class="flex items-center flex-wrap gap-2">
                                    <div class="action-btn-group"><button onclick='openDetailsModal(<?= json_encode($user) ?>)' class="action-btn rounded-md p-2 text-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-500/20"><i class="ph ph-eye text-lg"></i><span class="tooltip">View Details</span></button></div>
                                    <div class="action-btn-group"><button onclick='openEditModal(<?= json_encode($user) ?>)' class="action-btn rounded-md p-2 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-500/20"><i class="ph ph-pencil-simple text-lg"></i><span class="tooltip">Edit User</span></button></div>
                                    <?php if(!empty($user['deviceId'])): ?>
                                    <div class="action-btn-group">
                                        <form action="dashboard.php?<?= http_build_query(['filter' => $filter_status, 'sort' => $sort_option, 'page' => $current_page, 'search' => $search_query]) ?>" method="POST" onsubmit="return confirm('Reset Device ID for this user?');"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" name="reset_device_id" class="action-btn rounded-md p-2 text-amber-600 hover:bg-amber-100 dark:hover:bg-amber-500/20"><i class="ph ph-arrows-counter-clockwise text-lg"></i><span class="tooltip">Reset Device ID</span></button></form>
                                    </div>
                                    <?php endif; ?>
                                    <div class="action-btn-group">
                                        <form action="dashboard.php?<?= http_build_query(['filter' => $filter_status, 'sort' => $sort_option, 'page' => $current_page, 'search' => $search_query]) ?>" method="POST" onsubmit="return confirm('Delete this user?');"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" name="delete_user" class="action-btn rounded-md p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-500/20"><i class="ph ph-trash text-lg"></i><span class="tooltip">Delete User</span></button></form>
                                    </div>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
                <?php if($total_pages > 1): ?>
                <div class="p-4 flex items-center justify-between border-t" style="border-color: var(--card-border);">
                    <a href="?page=<?= max(1, $current_page - 1) ?>&search=<?= urlencode($search_query) ?>&filter=<?= $filter_status ?>&sort=<?= $sort_option ?>" class="px-4 py-2 text-sm border rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 <?= $current_page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" style="border-color: var(--input-border); color: var(--text-body);">Previous</a>
                    <span class="text-sm text-text-muted">Page <?= $current_page ?> of <?= $total_pages ?></span>
                    <a href="?page=<?= min($total_pages, $current_page + 1) ?>&search=<?= urlencode($search_query) ?>&filter=<?= $filter_status ?>&sort=<?= $sort_option ?>" class="px-4 py-2 text-sm border rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 <?= $current_page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>" style="border-color: var(--input-border); color: var(--text-body);">Next</a>
                </div>
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

        // --- Theme Switcher ---
        const themeToggleBtn = document.getElementById('theme-toggle');
        themeToggleBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });

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

        // --- Clipboard & Toast ---
        function copyToClipboard(elId, uId) { navigator.clipboard.writeText(document.getElementById(elId).innerText).then(() => { const icon = document.getElementById('copy-icon-' + uId); if(icon){ icon.className = 'ph ph-check-fat text-green-600'; showToast('Key copied!'); setTimeout(() => { icon.className = 'ph ph-copy text-text-muted'; }, 2000); } }); }
        function showToast(message, type = 'success') { 
            const toast = document.getElementById('toast'); 
            toast.textContent = message; 
            toast.className = `fixed top-5 right-5 text-white py-2 px-5 rounded-lg shadow-lg opacity-100 transform translate-y-0 transition-all duration-300 z-50 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
            setTimeout(() => { toast.style.opacity = '0'; }, 3000); 
        }

        // --- Modal Logic ---
        const modalContainer = document.getElementById('modal-container');
        function openDetailsModal(user) {
            const notesHTML = user.notes ? user.notes.replace(/\n/g, '<br>') : '<span class="text-text-muted">N/A</span>';
            const messageHTML = user.userMessage ? user.userMessage.replace(/\n/g, '<br>') : '<span class="text-text-muted">N/A</span>';
            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">User Details</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-200 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between"><span class="font-medium text-text-muted">Username:</span><span class="font-mono text-text-body">${user.username}</span></div>
                        <div class="flex justify-between"><span class="font-medium text-text-muted">Card ID:</span><span class="font-mono text-text-body">${user.cardId || 'N/A'}</span></div>
                        <div class="flex justify-between"><span class="font-medium text-text-muted">Key:</span><span class="font-mono text-text-body">${user.key}</span></div>
                        <div class="flex justify-between"><span class="font-medium text-text-muted">Device ID:</span><span class="font-mono text-text-body break-all">${user.deviceId || '<span class="text-text-muted">N/A</span>'}</span></div>
                        <div class="flex justify-between items-center"><span class="font-medium text-text-muted">Status:</span><span class="status-badge status-${(user.status || 'unknown').toLowerCase()}">${(user.status || 'unknown').charAt(0).toUpperCase() + (user.status || 'unknown').slice(1)}</span></div>
                        <div class="border-t pt-3 mt-3" style="border-color: var(--card-border);"><strong class="font-medium text-text-muted">Message for User:</strong><p class="mt-1 text-text-body whitespace-pre-wrap">${messageHTML}</p></div>
                        <div class="border-t pt-3 mt-3" style="border-color: var(--card-border);"><strong class="font-medium text-text-muted">Private Notes:</strong><p class="mt-1 text-text-body whitespace-pre-wrap">${notesHTML}</p></div>
                    </div>
                    <button onclick="closeModal()" class="mt-6 w-full bg-slate-200 dark:bg-slate-700 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600 transition">Close</button>
                </div>`;
            showModal();
        }

        function openAddUserModal() {
            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">Add New User</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="dashboard.php" method="POST" class="mt-4">
                        <div class="space-y-4">
                            <div><label class="block text-sm font-medium text-text-body">Username</label><input type="text" name="username" required class="form-input mt-1 block w-full px-4 py-2 transition"></div>
                            <div><label class="block text-sm font-medium text-text-body">Subscriptions (Days)</label><input type="number" name="subscriptions" required min="1" class="form-input mt-1 block w-full px-4 py-2 transition"></div>
                        </div>
                        <div class="mt-6 flex gap-4">
                            <button type="button" onclick="closeModal()" class="w-full bg-slate-200 dark:bg-slate-600 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-500 transition">Cancel</button>
                            <button type="submit" name="add_user" class="w-full flex justify-center items-center gap-2 bg-green-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-green-700 transition"><i class="ph ph-plus-circle"></i>Add User</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

        function openEditModal(user) {
            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">Edit User</h3><button onclick="closeModal()" class="p-1 rounded-full text-text-muted hover:bg-slate-100 dark:hover:bg-slate-700 transition"><i class="ph ph-x text-xl"></i></button></div>
                    <form action="dashboard.php" method="POST" class="mt-4">
                        <input type="hidden" name="user_id" value="${user.id}">
                        <div class="space-y-4">
                            <div><label class="block text-sm font-medium text-text-body">Username</label><input type="text" name="username" value="${user.username}" required class="form-input mt-1 block w-full px-4 py-2 transition"></div>
                            <div><label class="block text-sm font-medium text-text-body">Subscriptions (Days)</label><input type="number" name="subscriptions" value="${user.subscriptions}" required min="1" class="form-input mt-1 block w-full px-4 py-2 transition"></div>
                            <div><label class="block text-sm font-medium text-text-body">Message for User</label><textarea name="userMessage" rows="3" class="form-input mt-1 block w-full px-4 py-2 transition" placeholder="This message will be sent to the user's app.">${user.userMessage || ''}</textarea></div>
                            <div><label class="block text-sm font-medium text-text-body">Private Notes</label><textarea name="notes" rows="3" class="form-input mt-1 block w-full px-4 py-2 transition" placeholder="Private notes, not visible to the user.">${user.notes || ''}</textarea></div>
                        </div>
                        <div class="mt-6 flex gap-4">
                            <button type="button" onclick="closeModal()" class="w-full bg-slate-200 dark:bg-slate-600 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-500 transition">Cancel</button>
                            <button type="submit" name="edit_user" class="w-full flex justify-center items-center gap-2 bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition"><i class="ph ph-floppy-disk"></i>Save Changes</button>
                        </div>
                    </form>
                </div>`;
            showModal();
        }

        function showWelcomeCardPrompt(userData) {
            const cardPreviewContainer = document.createElement('div');
            cardPreviewContainer.innerHTML = `<div class="w-full overflow-hidden mx-auto my-4 rounded-xl" style="aspect-ratio: 420/410;"><div id="welcome-card-preview" class="origin-top-left" style="transform: scale(0.65); width: 420px;"></div></div>`;

            modalContainer.innerHTML = `
                <div class="modal-content card w-full max-w-md mx-auto p-6 transform scale-95 transition-transform duration-300">
                    <div class="flex justify-between items-center pb-3 border-b" style="border-color: var(--card-border);"><h3 class="text-xl font-semibold text-headings">Success!</h3></div>
                    <div class="mt-4">
                        <p class="text-text-body mb-4">User '${userData.username}' was added. Do you want to generate this welcome card?</p>
                        ${cardPreviewContainer.innerHTML}
                    </div>
                     <div class="mt-6 flex gap-4">
                        <button type="button" onclick="closeModal()" class="w-full bg-slate-200 dark:bg-slate-600 text-headings font-semibold py-2 px-4 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-500 transition">No, Thanks</button>
                        <button type="button" id="generate-card-btn" class="w-full flex justify-center items-center gap-2 bg-green-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-green-700 transition"><i class="ph ph-download-simple"></i>Generate Card</button>
                    </div>
                </div>`;

            populateWelcomeCard(userData, 'welcome-card-preview');

            document.getElementById('generate-card-btn').addEventListener('click', () => {
                generateWelcomeCard(userData);
                closeModal();
            });
            showModal();
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

        <?php if (!empty($message) && !empty($new_user_data)): ?>
            document.addEventListener('DOMContentLoaded', () => { showWelcomeCardPrompt(<?= json_encode($new_user_data) ?>); });
        <?php elseif (!empty($message)): ?>
             document.addEventListener('DOMContentLoaded', () => { showToast(<?= json_encode($message) ?>, <?= json_encode($message_type) ?>); });
        <?php endif; ?>

        function populateWelcomeCard(userData, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const template = document.getElementById('welcome-card-template').cloneNode(true);
            container.innerHTML = template.innerHTML;

            container.querySelector('#card-app-icon').src = '<?= htmlspecialchars($appIcon) ?>';
            container.querySelector('#card-app-name').innerText = '<?= htmlspecialchars($appName) ?>';
            container.querySelector('#card-username').innerText = userData.username;
            container.querySelector('#card-key').innerText = userData.key;
            container.querySelector('#card-sub-period').innerText = `${userData.subscriptions} Days`;
            container.querySelector('#card-id').innerText = userData.cardId;
            container.querySelector('#card-start-date').innerText = new Date(userData.startDate).toLocaleString();
            container.querySelector('#card-end-date').innerText = new Date(userData.endDate).toLocaleString();

            if(document.documentElement.classList.contains('dark')) { container.querySelector('#card-content').classList.add('dark'); } 
            else { container.querySelector('#card-content').classList.remove('dark'); }

            const qrCodeContainer = container.querySelector('#card-qrcode');
            qrCodeContainer.innerHTML = '';
            new QRCode(qrCodeContainer, {
                text: userData.key,
                width: 72,
                height: 72,
                colorDark : "#1e293b",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }

        function generateWelcomeCard(userData) {
            const cardEl = document.getElementById('welcome-card-template');
            populateWelcomeCard(userData, 'welcome-card-template');

            const renderCanvas = () => {
                setTimeout(() => { 
                     html2canvas(cardEl, { width: 420, backgroundColor: null, useCORS: true, logging: false, scale: 3 }).then(canvas => {
                        const link = document.createElement('a');
                        link.download = `welcome-${userData.username}.jpg`;
                        link.href = canvas.toDataURL('image/jpeg', 0.95);
                        link.click();
                    });
                }, 200);
            }

            const img = cardEl.querySelector('#card-app-icon');
            if (img.complete && img.naturalHeight !== 0) { renderCanvas(); } 
            else {
                img.onload = renderCanvas;
                img.onerror = () => {
                    img.src = 'https://placehold.co/64x64/6366f1/ffffff?text=D';
                    setTimeout(renderCanvas, 100);
                };
            }
        }
    </script>
</body>
</html>
