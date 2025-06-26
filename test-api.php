<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Page for user-check.php</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* gray-100 */
        }
        .font-mono {
            font-family: 'Fira Code', monospace;
        }
    </style>
</head>
<body class="antialiased text-gray-800">

    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full space-y-8">

            <div class="text-center">
                <i class="ph ph-plugs-connected text-5xl text-indigo-600"></i>
                <h1 class="text-3xl font-bold text-gray-900 mt-4">API Test Page</h1>
                <p class="mt-2 text-md text-gray-600">Check permissions using <code class="font-mono bg-gray-200 px-1 py-0.5 rounded">user-check.php</code></p>
                <p class="mt-4 text-sm text-gray-500">
                    <a href="dashboard.php" class="font-medium text-indigo-600 hover:text-indigo-500 transition">
                        &larr; Back to Dashboard
                    </a>
                </p>
            </div>

            <form id="api-test-form" class="mt-8 bg-white p-8 rounded-2xl shadow-lg space-y-6">
                <div>
                    <label for="key" class="block text-sm font-medium text-gray-700">Key</label>
                    <input type="text" id="key" name="key" placeholder="Enter key from dashboard" required class="mt-1 block w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label for="deviceId" class="block text-sm font-medium text-gray-700">Device ID</label>
                    <input type="text" id="deviceId" name="deviceId" placeholder="Enter any device ID" required class="mt-1 block w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                        <i class="ph ph-paper-plane-tilt"></i>
                        Check Permission
                    </button>
                </div>
            </form>

            <div id="response-container" class="mt-6 bg-white p-6 rounded-2xl shadow-lg hidden">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i id="response-icon" class="mr-3"></i>
                    <span id="response-status">Server Response</span>
                </h3>
                <pre id="response-json" class="mt-4 bg-gray-800 text-white text-sm rounded-lg p-4 overflow-x-auto font-mono"></pre>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('api-test-form').addEventListener('submit', function(event) {
            // Prevent the default browser form submission
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseContainer = document.getElementById('response-container');
            const responseJsonEl = document.getElementById('response-json');
            const responseStatusEl = document.getElementById('response-status');
            const responseIconEl = document.getElementById('response-icon');

            // Show loading state
            responseContainer.classList.remove('hidden');
            responseStatusEl.textContent = 'Checking...';
            responseIconEl.className = 'ph ph-circle-notch animate-spin text-2xl text-gray-500';
            responseJsonEl.textContent = 'Waiting for server...';

            // Send data using the Fetch API
            fetch('user_check.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Display the JSON response, nicely formatted
                responseJsonEl.textContent = JSON.stringify(data, null, 2);

                // Update status text and icon based on permission
                if (data.permission === 'yes') {
                    responseStatusEl.textContent = 'Permission Granted';
                    responseIconEl.className = 'ph ph-check-circle text-2xl text-green-500';
                    responseJsonEl.classList.remove('border-red-500');
                    responseJsonEl.classList.add('border-green-500');
                } else {
                    responseStatusEl.textContent = 'Permission Denied';
                    responseIconEl.className = 'ph ph-x-circle text-2xl text-red-500';
                }
            })
            .catch(error => {
                // Handle network errors or issues with the server
                console.error('Error:', error);
                responseStatusEl.textContent = 'Request Failed';
                responseIconEl.className = 'ph ph-warning-circle text-2xl text-yellow-500';
                responseJsonEl.textContent = `An error occurred:\n${error.message}\n\nCheck the browser console and ensure user-check.php is accessible.`;
            });
        });
    </script>
</body>
</html>
