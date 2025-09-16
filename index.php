<?php
// Autoload classes
spl_autoload_register(fn($class) =>
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
                font-family: Arial, sans-serif;
                background: #f4f6f9;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
            }
            .box {
                background: #fff;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            h1 {
                color: #e74c3c;
            }
            code {
                background: #f1f1f1;
                padding: 4px 6px;
                border-radius: 4px;
                font-size: 14px;
            }
            a {
                display: inline-block;
                margin-top: 15px;
                padding: 10px 20px;
                background: #3498db;
                color: #fff;
                text-decoration: none;
                border-radius: 5px;
            }
            a:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Configuration Missing</h1>
            <p>We could not find <code>config.php</code>.</p>
            <p>Please create it from <code>config_sample.php</code> before continuing.</p>
            <a href="config_sample.php" download>Download Sample Config</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

header("Location: login.php");
exit;
