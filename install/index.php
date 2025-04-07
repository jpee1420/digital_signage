<?php
session_start();
require_once '../config.php';

// If database already exists and is connected, redirect to main page
if (!defined('DB_NEEDS_INSTALLATION') || (defined('DB_NEEDS_INSTALLATION') && !DB_NEEDS_INSTALLATION && !DB_CONNECTION_ERROR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle step navigation
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        // Step 1: Save database connection details
        $db_host = $_POST['db_host'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        $db_name = $_POST['db_name'];
        
        // Test connection
        $testConn = @new mysqli($db_host, $db_user, $db_pass);
        
        if ($testConn->connect_error) {
            $_SESSION['install_error'] = "Error connecting to database server: " . $testConn->connect_error;
            $currentStep = 1;
        } else {
            // Save to session for later steps
            $_SESSION['db_config'] = [
                'host' => $db_host,
                'user' => $db_user,
                'pass' => $db_pass,
                'name' => $db_name
            ];
            
            $currentStep = 2;
            header("Location: index.php?step=2");
            exit;
        }
        
        $testConn->close();
    } elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        // Step 2: Create database and import schema
        if (!isset($_SESSION['db_config'])) {
            $_SESSION['install_error'] = "Database configuration not found. Please go back to step 1.";
            $currentStep = 1;
            header("Location: index.php?step=1");
            exit;
        }
        
        $config = $_SESSION['db_config'];
        $conn = new mysqli($config['host'], $config['user'], $config['pass']);
        
        if ($conn->connect_error) {
            $_SESSION['install_error'] = "Error connecting to database server: " . $conn->connect_error;
            $currentStep = 1;
            header("Location: index.php?step=1");
            exit;
        }
        
        // Create database if it doesn't exist
        $conn->query("CREATE DATABASE IF NOT EXISTS `{$config['name']}`");
        
        if ($conn->error) {
            $_SESSION['install_error'] = "Error creating database: " . $conn->error;
            $currentStep = 2;
        } else {
            $conn->select_db($config['name']);
            
            // Import schema based on choice
            $sqlFile = '';
            
            if (isset($_POST['import_choice']) && $_POST['import_choice'] === 'custom' && isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] == 0) {
                // User uploaded a custom SQL file
                $sqlFile = file_get_contents($_FILES['sql_file']['tmp_name']);
            } else {
                // Use default SQL file
                $sqlFile = file_get_contents('../digital_signage.sql');
            }
            
            // Split SQL commands and execute them
            $sqlCommands = explode(';', $sqlFile);
            $success = true;
            
            foreach ($sqlCommands as $command) {
                $command = trim($command);
                if (!empty($command)) {
                    if (!$conn->query($command . ';')) {
                        $_SESSION['install_error'] = "Error importing SQL: " . $conn->error . " in command: " . substr($command, 0, 100) . "...";
                        $success = false;
                        break;
                    }
                }
            }
            
            if ($success) {
                // Update config.php with the new database settings
                $configFile = file_get_contents('../config.php');
                $configFile = preg_replace('/\$db_host = \'(.*?)\';/', "\$db_host = '{$config['host']}';", $configFile);
                $configFile = preg_replace('/\$db_user = \'(.*?)\';/', "\$db_user = '{$config['user']}';", $configFile);
                $configFile = preg_replace('/\$db_pass = \'(.*?)\';/', "\$db_pass = '{$config['pass']}';", $configFile);
                $configFile = preg_replace('/\$db_name = \'(.*?)\';/', "\$db_name = '{$config['name']}';", $configFile);
                
                file_put_contents('../config.php', $configFile);
                
                // Move to next step
                $currentStep = 3;
                header("Location: index.php?step=3");
                exit;
            }
        }
        
        $conn->close();
    }
}

// Page title based on step
$pageTitle = "Installation Wizard";
switch ($currentStep) {
    case 1:
        $stepTitle = "Step 1: Database Configuration";
        break;
    case 2:
        $stepTitle = "Step 2: Database Setup";
        break;
    case 3:
        $stepTitle = "Step 3: Installation Complete";
        break;
    default:
        $stepTitle = "Installation Wizard";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .install-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .install-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 0;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: #fff;
            line-height: 30px;
            position: relative;
            z-index: 1;
        }
        .step.active .step-number {
            background: #007bff;
        }
        .step.completed .step-number {
            background: #28a745;
        }
        .step-label {
            margin-top: 5px;
            color: #6c757d;
        }
        .step.active .step-label {
            color: #007bff;
            font-weight: bold;
        }
        .step.completed .step-label {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-header">
                <h1><i class="fas fa-tools"></i> Schedule Management System</h1>
                <h3><?php echo $stepTitle; ?></h3>
            </div>
            
            <div class="step-indicator">
                <div class="step <?php echo $currentStep >= 1 ? 'active' : ''; ?> <?php echo $currentStep > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Database Configuration</div>
                </div>
                <div class="step <?php echo $currentStep >= 2 ? 'active' : ''; ?> <?php echo $currentStep > 2 ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Import Schema</div>
                </div>
                <div class="step <?php echo $currentStep >= 3 ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['install_error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['install_error']; ?>
                    <?php unset($_SESSION['install_error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($currentStep == 1): ?>
                <!-- Step 1: Database Configuration -->
                <div class="card mb-4">
                    <div class="card-body">
                        <p>Please enter your database connection details below. If you're not sure about these, contact your web host or server administrator.</p>
                        
                        <form method="post" action="index.php">
                            <input type="hidden" name="step" value="1">
                            
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                <div class="form-text">In most cases, this will be "localhost".</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" value="">
                                <div class="form-text">Leave blank for no password (common for local development).</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="digital_signage" required>
                                <div class="form-text">The database will be created if it doesn't exist.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                            <a href="test_connection.php" class="btn btn-outline-secondary ms-2" target="_blank">Troubleshoot Connection</a>
                        </form>
                    </div>
                </div>
            
            <?php elseif ($currentStep == 2): ?>
                <!-- Step 2: Database Setup -->
                <div class="card mb-4">
                    <div class="card-body">
                        <p>The database connection was successful! Now you can create the database schema.</p>
                        
                        <form method="post" action="index.php" enctype="multipart/form-data">
                            <input type="hidden" name="step" value="2">
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="import_choice" id="default_import" value="default" checked>
                                    <label class="form-check-label" for="default_import">
                                        Use default schema (digital_signage.sql)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="import_choice" id="custom_import" value="custom">
                                    <label class="form-check-label" for="custom_import">
                                        Upload custom SQL file
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3" id="sqlFileGroup" style="display:none;">
                                <label for="sql_file" class="form-label">Select SQL File</label>
                                <input class="form-control" type="file" id="sql_file" name="sql_file" accept=".sql">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Database & Import Schema</button>
                        </form>
                    </div>
                </div>
            
            <?php elseif ($currentStep == 3): ?>
                <!-- Step 3: Installation Complete -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                        <h2 class="mt-3">Installation Complete!</h2>
                        <p>Your Schedule Management System has been successfully installed.</p>
                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary btn-lg mt-3">Go to Dashboard</a>
                        
                        <div class="alert alert-warning mt-4 text-start">
                            <h5><i class="fas fa-exclamation-triangle"></i> Security Recommendation</h5>
                            <p>For security reasons, it's recommended to protect or delete the installation directory after completing setup.</p>
                            <p>You can protect the directory by uncommenting the protection rules in <code>install/.htaccess</code> file.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4 text-muted">
                <small>Schedule Management System Installation Wizard</small>
            </div>
        </div>
    </div>
    
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle custom SQL file upload option
        const customImportRadio = document.getElementById('custom_import');
        const defaultImportRadio = document.getElementById('default_import');
        const sqlFileGroup = document.getElementById('sqlFileGroup');
        
        if (customImportRadio && defaultImportRadio && sqlFileGroup) {
            customImportRadio.addEventListener('change', function() {
                if (this.checked) {
                    sqlFileGroup.style.display = 'block';
                }
            });
            
            defaultImportRadio.addEventListener('change', function() {
                if (this.checked) {
                    sqlFileGroup.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html> 