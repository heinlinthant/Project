<?php
// login.php
session_start();

// If user is already logged in, redirect to the dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$config_file = 'config.json';
// Read config for branding
if (!file_exists($config_file)) {
    die('Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);
$appName = $config['appName'] ?? 'Dashboard';
$appIcon = $config['appIcon'] ?? 'default_icon.png';
$accentColor = $config['accentColor'] ?? '#6366f1';


$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correct_passcode = $config['passcode'];

    $submitted_passcode = $_POST['passcode'] ?? '';

    if ($submitted_passcode === $correct_passcode) {
        // Passcode is correct, set session variable and redirect
        $_SESSION['loggedin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid passcode. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --bg-color: #f1f5f9; --card-bg: white; --card-border: #e2e8f0; --text-headings: #1e293b; --text-body: #475569; --input-bg: white;
            --input-border: #cbd5e1; --accent: <?= htmlspecialchars($accentColor) ?>;
        }
        html.dark {
            --bg-color: #0f172a; --card-bg: #1e293b; --card-border: #334155; --text-headings: #f1f5f9; --text-body: #94a3b8; --input-bg: #334155;
            --input-border: #475569;
        }
        body { background-color: var(--bg-color); color: var(--text-body); font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s;}
        .card { background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); transition: background-color 0.3s, border-color 0.3s;}
        .form-input { background-color: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-headings); border-radius: 0.5rem; transition: background-color 0.3s, border-color 0.3s, color 0.3s;}
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2); outline: none; }
        .btn-accent { background-color: var(--accent); color: white; }
        .btn-accent:hover { filter: brightness(90%); }
    </style>
     <script>
        // Apply theme on initial load to avoid flash
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-sm">
        <div class="flex flex-col items-center mb-6">
             <img src="<?= htmlspecialchars($appIcon) ?>" alt="App Icon" class="w-16 h-16 rounded-xl object-cover mb-4 shadow-md" onerror="this.src='https://placehold.co/64x64/6366f1/ffffff?text=D'; this.onerror=null;">
             <h1 class="text-3xl font-bold text-headings"><?= htmlspecialchars($appName) ?></h1>
        </div>
        <div class="card p-8">
            <h2 class="text-xl font-bold text-center text-headings mb-1">Admin Access</h2>
            <p class="text-sm text-center text-text-body mb-6">Please enter the passcode to continue</p>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 dark:bg-red-500/20 border border-red-400 dark:border-red-500/30 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div>
                    <label for="passcode" class="block text-sm font-medium text-text-body">Passcode</label>
                    <input type="password" id="passcode" name="passcode" required autofocus class="form-input w-full mt-1 px-4 py-2">
                </div>
                <div class="mt-6">
                    <button type="submit" class="w-full flex justify-center items-center gap-2 btn-accent font-semibold py-2.5 px-4 rounded-lg transition">
                        <i class="ph ph-lock-key-open"></i>
                        Unlock
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
