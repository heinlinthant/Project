<?php
// user-check.php

header('Content-Type: application/json');

// --- Configuration ---
$user_data_file = 'usersData.json';
$config_file = 'config.json';
date_default_timezone_set('Asia/Yangon');

// --- Helper Functions ---
function getUsers($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveUsers($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getConfig($file) {
    if (!file_exists($file)) {
         // Default config if file doesn't exist
        return [
            'globalMessage' => '',
            'latestVersion' => '1.0.0',
            'minimumVersion' => '1.0.0'
        ];
    }
    return json_decode(file_get_contents($file), true) ?: [];
}


function sendResponse($data) {
    echo json_encode($data);
    exit();
}

// --- Main Logic ---

// 1. Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['permission' => 'no', 'reason' => 'Invalid request method.']);
}

// 2. Load configs
$users = getUsers($user_data_file);
$config = getConfig($config_file);

// 3. Get all data from POST request
$key = trim($_POST['key'] ?? '');
$deviceId = trim($_POST['deviceId'] ?? '');
$appVersion = trim($_POST['appVersion'] ?? '0.0.0'); // App should send its version

if (empty($key) || empty($deviceId)) {
    sendResponse(['permission' => 'no', 'reason' => 'Key and DeviceId are required.']);
}

// 4. Version Control Check
$minimumVersion = $config['minimumVersion'] ?? '1.0.0';
$latestVersion = $config['latestVersion'] ?? '1.0.0';

if (version_compare($appVersion, $minimumVersion, '<')) {
    sendResponse([
        'permission' => 'no', 
        'reason' => 'update_required',
        'message' => 'A critical update is required to continue using the app.',
        'latestVersion' => $latestVersion
    ]);
}

// 5. Find the user by key
$userFound = false;
$userIndex = -1;

foreach ($users as $index => $user) {
    if (isset($user['key']) && $user['key'] === $key) {
        $userFound = true;
        $userIndex = $index;
        break;
    }
}

if (!$userFound) {
    sendResponse(['permission' => 'no', 'reason' => 'Invalid key.']);
}

// 6. Check user status and device ID
$currentUser = &$users[$userIndex];

// Check for expiration
if (isset($currentUser['endDate']) && new DateTime() > new DateTime($currentUser['endDate'])) {
    if ($currentUser['status'] !== 'expired') {
        $currentUser['status'] = 'expired';
        saveUsers($user_data_file, $users);
    }
    sendResponse(['permission' => 'no', 'reason' => 'Subscription has expired.']);
}

// Check device ID
$storedDeviceId = $currentUser['deviceId'] ?? '';

if (empty($storedDeviceId)) {
    // First time use for this key, register the device
    $currentUser['deviceId'] = $deviceId;
    $currentUser['status'] = 'used';
    saveUsers($user_data_file, $users);
} elseif ($storedDeviceId !== $deviceId) {
    // Key is registered to a different device
    sendResponse(['permission' => 'no', 'reason' => 'Key is registered with another device.']);
}

// 7. If all checks pass, build the success response
$response = [
    'permission' => 'yes',
    'globalMessage' => $config['globalMessage'] ?? '',
    'userMessage' => $currentUser['userMessage'] ?? '',
];

// Add 'update available' info if user's version is older than latest
if (version_compare($appVersion, $latestVersion, '<')) {
    $response['updateAvailable'] = true;
    $response['latestVersion'] = $latestVersion;
}

sendResponse($response);
?>
