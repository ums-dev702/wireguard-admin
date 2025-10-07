<?php
// Test the MikroTik script generation API

echo "=== Testing MikroTik Script Generation API ===\n\n";

// Test the direct URL call
$test_url = "http://localhost/Alvinkiveu.com_scripts/Wirgaurd_Admin/app/backend/generate_mikrotik_script.php";

echo "Testing URL: {$test_url}\n";
echo "Parameters needed: peer_id, interface\n\n";

// Check if the file exists
$file_path = __DIR__ . "/app/backend/generate_mikrotik_script.php";
if (file_exists($file_path)) {
    echo "✅ Backend script file exists\n";
} else {
    echo "❌ Backend script file NOT found at: {$file_path}\n";
}

// Check if includes exist
$auth_path = __DIR__ . "/includes/auth.php";
$functions_path = __DIR__ . "/includes/functions.php";

if (file_exists($auth_path)) {
    echo "✅ Auth include exists\n";
} else {
    echo "❌ Auth include NOT found at: {$auth_path}\n";
}

if (file_exists($functions_path)) {
    echo "✅ Functions include exists\n";
} else {
    echo "❌ Functions include NOT found at: {$functions_path}\n";
}

echo "\n=== Common Issues and Solutions ===\n";
echo "1. Authentication Issue:\n";
echo "   - Make sure user is logged in\n";
echo "   - Check session handling\n\n";

echo "2. Missing Parameters:\n";
echo "   - peer_id is required\n";
echo "   - interface is required\n\n";

echo "3. Database Issues:\n";
echo "   - Check if peer exists in database\n";
echo "   - Check if interface exists\n\n";

echo "4. Path Issues:\n";
echo "   - Check relative paths in JavaScript\n";
echo "   - Ensure proper URL encoding\n\n";

echo "=== JavaScript Debug Fix ===\n";
echo "Add better error handling in the fetch request:\n\n";

echo "try {\n";
echo "    const response = await fetch(url);\n";
echo "    console.log('Response status:', response.status);\n";
echo "    console.log('Response headers:', response.headers);\n";
echo "    \n";
echo "    if (!response.ok) {\n";
echo "        const errorText = await response.text();\n";
echo "        console.log('Error response:', errorText);\n";
echo "        throw new Error(`HTTP \${response.status}: \${errorText}`);\n";
echo "    }\n";
echo "    \n";
echo "    const scriptText = await response.text();\n";
echo "    console.log('Script loaded successfully');\n";
echo "} catch (error) {\n";
echo "    console.error('Detailed error:', error);\n";
echo "}\n";

echo "\n✅ Test completed!\n";
?>