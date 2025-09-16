<?php
// Autoload classes
spl_autoload_register(
    fn($class) =>
    str_starts_with($class, 'WireGuardAdmin\\')
        && file_exists($f = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, 15)) . '.php')
        && require $f
);

if (!file_exists('config.php')) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Configuration Required</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #74ebd5, #9face6);
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
            }

            .box {
                background: #fff;
                padding: 40px 30px;
                border-radius: 15px;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
                text-align: center;
                max-width: 520px;
                animation: fadeIn 1s ease-in-out;
            }

            h1 {
                color: #e74c3c;
                margin-bottom: 15px;
                font-size: 28px;
            }

            p {
                font-size: 16px;
                color: #555;
                line-height: 1.6;
            }

            code {
                background: #f4f4f4;
                padding: 5px 8px;
                border-radius: 6px;
                font-size: 14px;
                font-family: "Courier New", monospace;
                color: #c0392b;
            }

            .icon {
                font-size: 50px;
                color: #e74c3c;
                margin-bottom: 15px;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 25px;
                background: #3498db;
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            a:hover {
                background: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(41, 128, 185, 0.3);
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
        <script src="https://kit.fontawesome.com/yourkit.js" crossorigin="anonymous"></script>
    </head>

    <body>
        <div class="box">
            <div class="icon">⚠️</div>
            <h1>Configuration Missing</h1>
            <p>We could not find <code>config.php</code>.</p>
            <p>Please create it from <code>config_sample.php</code> before continuing.</p>
        </div>
    </body>

    </html>
<?php
    exit;
}

header("Location: login.php");
exit;
