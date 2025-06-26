<?php
// get_data.php

header('Content-Type: application/json');

// --- Configuration & Setup ---
$app_data_dir = 'App_Data/';
$registry_file = $app_data_dir . '_registry.json';

// --- Helper Functions ---
function get_app_registry($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function sendError($reason) {
    echo json_encode(['success' => false, 'error' => $reason]);
    exit();
}

// --- Main Logic ---

// 1. Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method.');
}

// 2. Get the required parameters from the POST request
$app_id = trim($_POST['appId'] ?? '');
$api_key = trim($_POST['apiKey'] ?? '');

if (empty($app_id) || empty($api_key)) {
    sendError('Required parameters missing: appId and apiKey.');
}

// 3. Validate the App ID and API Key
$registry = get_app_registry($registry_file);

if (!isset($registry[$app_id])) {
    sendError('Invalid App ID.');
}

if (!isset($registry[$app_id]['apiKey']) || $registry[$app_id]['apiKey'] !== $api_key) {
    sendError('Invalid API Key.');
}

// 4. If key is valid, retrieve the app data
$data_file = $app_data_dir . $app_id . '_data.json';

if (!file_exists($data_file)) {
    sendError('App data file not found.');
}

$app_data = json_decode(file_get_contents($data_file), true);

// 5. Send the successful response
echo json_encode([
    'success' => true,
    'data' => $app_data
]);

?>
