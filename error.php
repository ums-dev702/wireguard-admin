<?php
$errorCode = $_GET['code'] ?? '404';
$errorMessages = [
    '400' => 'Bad Request',
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Page Not Found',
    '500' => 'Internal Server Error'
];

$title = $errorMessages[$errorCode] ?? 'Error';
$message = '';

switch ($errorCode) {
    case '400':
        $message = 'The request could not be understood by the server.';
        break;
    case '401':
        $message = 'You need to authenticate to access this resource.';
        break;
    case '403':
        $message = 'You don\'t have permission to access this resource.';
        break;
    case '404':
        $message = 'The page you are looking for could not be found.';
        break;
    case '500':
        $message = 'The server encountered an internal error.';
        break;
    default:
        $message = 'An unexpected error occurred.';
}

http_response_code($errorCode);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $errorCode ?> - <?= $title ?> | WireGuard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .error-float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
        <div class="glass-effect rounded-2xl p-8 backdrop-blur-lg">
            <div class="error-float mb-6">
                <i class="fas fa-exclamation-triangle text-6xl text-yellow-400"></i>
            </div>
            
            <h1 class="text-4xl font-bold text-white mb-2"><?= $errorCode ?></h1>
            <h2 class="text-xl text-gray-200 mb-4"><?= $title ?></h2>
            <p class="text-gray-300 mb-8"><?= $message ?></p>
            
            <div class="space-y-4">
                <a href="/" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-home mr-2"></i>
                    Go Home
                </a>
                
                <button onclick="history.back()" class="block w-full px-6 py-3 bg-white bg-opacity-20 text-white rounded-lg hover:bg-opacity-30 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Go Back
                </button>
            </div>
        </div>
        
        <p class="text-gray-300 text-sm mt-6">
            <i class="fas fa-shield-alt mr-2"></i>
            WireGuard Admin - Professional VPN Management
        </p>
    </div>
</body>
</html>
