<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Installation Error</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.8s ease-out;
        }
        
        .header {
            background: linear-gradient(to right, #2c3e50, #4ca1af);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .content {
            padding: 30px;
        }
        
        .error-box {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .error-code {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            display: inline-block;
            color: #dc3545;
            font-weight: bold;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section h3 {
            color: #2c3e50;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }
        
        .steps {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .step {
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
        }
        
        .step-number {
            background: #4ca1af;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: bold;
        }
        
        .footer {
            background: #e9ecef;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        
        .button {
            display: inline-block;
            background: #4ca1af;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .button:hover {
            background: #2c3e50;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        
        @media (max-width: 600px) {
            .header h1 {
                font-size: 22px;
            }
            
            .content {
                padding: 20px;
            }
            
            .step {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-exclamation-triangle"></i> VPN Installation Error</h1>
            <p>We encountered a problem during the installation process</p>
        </div>
        
        <div class="content">
            <div class="error-box">
                <i class="fas fa-bug fa-2x" style="color: #dc3545;"></i>
                <div>
                    <strong>Error: Configuration File Missing</strong>
                    <p>The system could not locate the required configuration file for your VPN installation.</p>
                </div>
            </div>
            
            <div class="section">
                <h3><i class="fas fa-info-circle"></i> Error Details</h3>
                <p>The installation process failed because the main configuration file was not found. This is typically caused by:</p>
                <ul style="padding-left: 20px; margin: 15px 0;">
                    <li>Incomplete download of the installation package</li>
                    <li>File permission issues during extraction</li>
                    <li>Antivirus software blocking the configuration file</li>
                    <li>Corrupted installation files</li>
                </ul>
                <div class="error-code">Error Code: VPN-INSTALL-102 | File: config.php</div>
            </div>
            
            <div class="section">
                <h3><i class="fas fa-tools"></i> Resolution Steps</h3>
                <p>Please follow these steps to resolve the issue:</p>
                
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div>
                            <strong>Verify the installation package</strong>
                            <p>Ensure you have downloaded the complete VPN installation package from the official website.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div>
                            <strong>Check your antivirus software</strong>
                            <p>Temporarily disable your antivirus during installation or add the VPN software to the exclusion list.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div>
                            <strong>Create configuration file manually</strong>
                            <p>Copy <code>config_sample.php</code> to <code>config.php</code> and adjust the settings as needed.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <div>
                            <strong>Re-run the installation</strong>
                            <p>Restart the installation process after completing the above steps.</p>
                        </div>
                    </div>
                </div>
                
                <a href="#" class="button"><i class="fas fa-download"></i> Download Latest Version</a>
            </div>
            
            <div class="section">
                <h3><i class="fas fa-life-ring"></i> Need Further Assistance?</h3>
                <p>If you continue to experience issues, our support team is available to help:</p>
                <p style="margin-top: 15px;">
                    <a href="#" class="button"><i class="fas fa-envelope"></i> Contact Support</a>
                    <a href="#" class="button" style="background: #6c757d;"><i class="fas fa-book"></i> Knowledge Base</a>
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2023 SecureVPN | All Rights Reserved</p>
            <p>Error Page v1.2</p>
        </div>
    </div>
</body>
</html>