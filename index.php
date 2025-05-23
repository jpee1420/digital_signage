<?php
require_once 'config.php';

// If installation is needed, redirect to the installation wizard
if (defined('DB_NEEDS_INSTALLATION') && DB_NEEDS_INSTALLATION === true) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// If there's a database connection error, redirect to the installation wizard
if (defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR === true) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// Initialize PDO connection using the variables from config.php
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("PDO connection error: " . $e->getMessage());
    // Redirect to installation wizard
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// Handle file upload and URL edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_order'])) {
        // Handle order update
        $orders = json_decode($_POST['order_data'], true);
        if ($orders) {
            try {
                $pdo->beginTransaction();
                
                // First, update all items to a temporary high order to avoid unique constraint conflicts
                $stmt = $pdo->prepare("UPDATE media SET display_order = display_order + 10000");
                $stmt->execute();
                
                // Then update with the new order
                foreach ($orders as $item) {
                    $stmt = $pdo->prepare("UPDATE media SET display_order = ? WHERE id = ?");
                    $stmt->execute([(int)$item['order'], (int)$item['id']]);
                }
                
                $pdo->commit();
                exit('Order updated successfully');
            } catch (Exception $e) {
                $pdo->rollBack();
                exit('Error updating order: ' . $e->getMessage());
            }
        }
        exit('Invalid order data');
    }
    
    if (isset($_POST['toggle_active'])) {
        // Handle active status toggle
        $id = (int)$_POST['id'];
        $is_active = (int)$_POST['is_active'];
        $stmt = $pdo->prepare("UPDATE media SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $id]);
        exit('Status updated');
    }
    
    if (isset($_POST['edit_duration'])) {
        // Handle duration edit
        $id = (int)$_POST['id'];
        $duration = (int)$_POST['duration'];
        $stmt = $pdo->prepare("UPDATE media SET duration = ? WHERE id = ?");
        $stmt->execute([$duration, $id]);
        header('Location: index.php?success=edit');
        exit;
    }

    if (isset($_POST['edit_webpage'])) {
        // Handle webpage edit (URL, duration, and name)
        $id = (int)$_POST['id'];
        $name = $_POST['name'];
        $url = $_POST['url'];
        $duration = (int)$_POST['duration'];
        
        // Handle local URLs
        if (strpos($url, 'localhost') !== false || 
            strpos($url, '127.0.0.1') !== false || 
            strpos($url, $_SERVER['HTTP_HOST']) !== false) {
            
            // Remove any existing protocol
            $url = preg_replace("~^(?:f|ht)tps?://~i", "", $url);
            
            // Add http protocol
            $url = "http://" . $url;
        } else {
            // For non-local URLs
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "http://" . $url;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE media SET url = ?, duration = ?, name = ? WHERE id = ?");
        $stmt->execute([$url, $duration, $name, $id]);
        header('Location: index.php?success=edit');
        exit;
    }
    
    if (isset($_POST['edit_url'])) {
        // Handle URL edit
        $id = (int)$_POST['id'];
        $url = $_POST['url'];
        
        // Handle local URLs
        if (strpos($url, 'localhost') !== false || 
            strpos($url, '127.0.0.1') !== false || 
            strpos($url, $_SERVER['HTTP_HOST']) !== false) {
            
            // Remove any existing protocol
            $url = preg_replace("~^(?:f|ht)tps?://~i", "", $url);
            
            // Add http protocol
            $url = "http://" . $url;
        } else {
            // For non-local URLs
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "http://" . $url;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE media SET url = ? WHERE id = ?");
        $stmt->execute([$url, $id]);
        
        header('Location: index.php?success=edit');
        exit;
    }
    
    if (isset($_POST['edit_media'])) {
        $id = (int)$_POST['id'];
        $name = $_POST['name'];
        $duration = (int)$_POST['duration'];
        $type = $_POST['type'];
        
        if (isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
            $file = $_FILES['file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file types
            $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $allowedVideoTypes = ['mp4', 'webm', 'ogg'];
            $allowedMultimediaTypes = array_merge($allowedImageTypes, $allowedVideoTypes);
            
            if (($type === 'image' && !in_array($extension, $allowedImageTypes)) ||
                ($type === 'video' && !in_array($extension, $allowedVideoTypes)) ||
                ($type === 'multimedia' && !in_array($extension, $allowedMultimediaTypes))) {
                die("Invalid file type. Allowed types for multimedia: " . implode(', ', $allowedMultimediaTypes));
            }
            
            // Determine the actual media type based on extension
            $actualType = in_array($extension, $allowedImageTypes) ? 'image' : 'video';
            
            // Get the old file path to delete
            $stmt = $pdo->prepare("SELECT path FROM media WHERE id = ?");
            $stmt->execute([$id]);
            $oldPath = $stmt->fetch(PDO::FETCH_COLUMN);
            
            // Delete old file if it exists
            if ($oldPath && file_exists($oldPath)) {
                unlink($oldPath);
            }
            
            // Upload new file
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = uniqid() . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // For multimedia type, update the actual type (image or video)
                if ($type === 'multimedia') {
                    $stmt = $pdo->prepare("UPDATE media SET name = ?, duration = ?, path = ?, type = ? WHERE id = ?");
                    $stmt->execute([$name, $duration, $targetPath, $actualType, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE media SET name = ?, duration = ?, path = ? WHERE id = ?");
                    $stmt->execute([$name, $duration, $targetPath, $id]);
                }
            }
        } else {
            // Only update name and duration if no new file
            $stmt = $pdo->prepare("UPDATE media SET name = ?, duration = ? WHERE id = ?");
            $stmt->execute([$name, $duration, $id]);
        }
        
        header('Location: index.php?success=edit');
        exit;
    }
    
    // Handle ticker message status toggle
    if (isset($_POST['toggle_ticker'])) {
        $id = (int)$_POST['id'];
        $is_active = (int)$_POST['is_active'];
        $stmt = $pdo->prepare("UPDATE ticker_messages SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $id]);
        exit('Ticker status updated');
    }
    
    // Handle ticker message edit
    if (isset($_POST['edit_ticker'])) {
        $id = (int)$_POST['id'];
        $message = $_POST['message'];
        $type = $_POST['type'];
        
        // Handle target URL (only for schedule and custom types)
        if ($type === 'schedule' || $type === 'custom') {
            $target_url = isset($_POST['target_url']) ? $_POST['target_url'] : null;
            
            // Validate URL format
            if (!empty($target_url)) {
                // Handle local URLs (same as other URL handling)
                if (strpos($target_url, 'localhost') !== false || 
                    strpos($target_url, '127.0.0.1') !== false || 
                    strpos($target_url, $_SERVER['HTTP_HOST']) !== false) {
                    
                    // Remove any existing protocol
                    $target_url = preg_replace("~^(?:f|ht)tps?://~i", "", $target_url);
                    
                    // Add http protocol
                    $target_url = "http://" . $target_url;
                } else {
                    // For non-local URLs
                    if (!preg_match("~^(?:f|ht)tps?://~i", $target_url)) {
                        $target_url = "http://" . $target_url;
                    }
                }
            }
            
            $stmt = $pdo->prepare("UPDATE ticker_messages SET message = ?, target_url = ? WHERE id = ?");
            $stmt->execute([$message, $target_url, $id]);
        } else {
            // Default type doesn't have a target URL
            $stmt = $pdo->prepare("UPDATE ticker_messages SET message = ? WHERE id = ?");
            $stmt->execute([$message, $id]);
        }
        
        header('Location: index.php?success=edit');
        exit;
    }
    
    // Handle new ticker message
    if (isset($_POST['add_ticker'])) {
        $message = $_POST['message'];
        $type = $_POST['type']; // Should be 'custom'
        $target_url = isset($_POST['target_url']) ? $_POST['target_url'] : null;
        
        // Validate URL format if provided
        if (!empty($target_url)) {
            // Handle local URLs
            if (strpos($target_url, 'localhost') !== false || 
                strpos($target_url, '127.0.0.1') !== false || 
                strpos($target_url, $_SERVER['HTTP_HOST']) !== false) {
                
                // Remove any existing protocol
                $target_url = preg_replace("~^(?:f|ht)tps?://~i", "", $target_url);
                
                // Add http protocol
                $target_url = "http://" . $target_url;
            } else {
                // For non-local URLs
                if (!preg_match("~^(?:f|ht)tps?://~i", $target_url)) {
                    $target_url = "http://" . $target_url;
                }
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO ticker_messages (message, type, target_url) VALUES (?, ?, ?)");
        $stmt->execute([$message, $type, $target_url]);
        
        header('Location: index.php?success=add');
        exit;
    }
    
    // Handle ticker message order update
    if (isset($_POST['update_ticker_order'])) {
        $orders = json_decode($_POST['order_data'], true);
        if ($orders) {
            try {
                $pdo->beginTransaction();
                
                // First, update all items to a temporary high order to avoid unique constraint conflicts
                $stmt = $pdo->prepare("UPDATE ticker_messages SET display_order = display_order + 10000");
                $stmt->execute();
                
                // Then update with the new order
                foreach ($orders as $item) {
                    $stmt = $pdo->prepare("UPDATE ticker_messages SET display_order = ? WHERE id = ?");
                    $stmt->execute([(int)$item['order'], (int)$item['id']]);
                }
                
                $pdo->commit();
                exit('Ticker order updated successfully');
            } catch (Exception $e) {
                $pdo->rollBack();
                exit('Error updating ticker order: ' . $e->getMessage());
            }
        }
        exit('Invalid ticker order data');
    }
    
    // Regular file/URL upload handling
    $type = $_POST['type'];
    $name = $_POST['name'];
    $duration = (int)$_POST['duration'];
    
    // Get max display order
    $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM media");
    $next_order = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
    
    if ($type === 'webpage') {
        $url = $_POST['url'];
        
        // Handle local URLs
        if (strpos($url, 'localhost') !== false || 
            strpos($url, '127.0.0.1') !== false || 
            strpos($url, $_SERVER['HTTP_HOST']) !== false) {
            
            // Remove any existing protocol
            $url = preg_replace("~^(?:f|ht)tps?://~i", "", $url);
            
            // Add http protocol
            $url = "http://" . $url;
        } else {
            // For non-local URLs
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "http://" . $url;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO media (name, type, url, duration, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $type, $url, $duration, $next_order]);
        header('Location: index.php?success=add');
        exit;
    } else {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Check if file is selected
        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            header('Location: index.php?error=no_file&message=' . urlencode('Please select a file to upload.'));
            exit;
        }
        
        $file = $_FILES['file'];
        
        // Check for file upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'File upload error: ';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage .= 'The file is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage .= 'The file was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage .= 'Missing a temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage .= 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage .= 'A PHP extension stopped the file upload.';
                    break;
                default:
                    $errorMessage .= 'Unknown error.';
            }
            header('Location: index.php?error=upload_error&message=' . urlencode($errorMessage));
            exit;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file types
        $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedVideoTypes = ['mp4', 'webm', 'ogg'];
        $allowedMultimediaTypes = array_merge($allowedImageTypes, $allowedVideoTypes);
        
        // Check for invalid file types
        if (($type === 'image' && !in_array($extension, $allowedImageTypes)) ||
            ($type === 'video' && !in_array($extension, $allowedVideoTypes)) ||
            ($type === 'multimedia' && !in_array($extension, $allowedMultimediaTypes))) {
            
            $errorMessage = 'Invalid file type. ';
            if ($type === 'image') {
                $errorMessage .= 'Allowed types: ' . implode(', ', $allowedImageTypes);
            } elseif ($type === 'video') {
                $errorMessage .= 'Allowed types: ' . implode(', ', $allowedVideoTypes);
            } else {
                $errorMessage .= 'Allowed types: ' . implode(', ', $allowedMultimediaTypes);
            }
            
            header('Location: index.php?error=invalid_file_type&message=' . urlencode($errorMessage));
            exit;
        }
        
        // Determine the actual media type based on extension for multimedia
        $actualType = $type;
        if ($type === 'multimedia') {
            $actualType = in_array($extension, $allowedImageTypes) ? 'image' : 'video';
        }
        
        $filename = uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $pdo->prepare("INSERT INTO media (name, type, path, duration, display_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $actualType, $targetPath, $duration, $next_order]);
            header('Location: index.php?success=add');
            exit;
        } else {
            $errorMessage = 'Failed to upload file. Please try again.';
            header('Location: index.php?error=move_failed&message=' . urlencode($errorMessage));
            exit;
        }
    }
}

// Get all media ordered by display_order
$stmt = $pdo->query("SELECT * FROM media ORDER BY display_order ASC, created_at ASC");
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Success messages
$successMessages = [
    'add' => 'Content added successfully!',
    'edit' => 'Content updated successfully!',
    'delete' => 'Content deleted successfully!',
    'delete_ticker' => 'Ticker message deleted successfully!'
];

// Error messages
$errorMessages = [
    'cannot_delete_system' => 'System ticker messages cannot be deleted!',
    'no_file' => 'Please select a file to upload.',
    'upload_error' => 'File upload error occurred.',
    'invalid_file_type' => 'Invalid file type.',
    'move_failed' => 'Failed to upload file.',
    'not_found' => 'Content not found.',
    'error_file' => 'Error deleting file.',
    'error_db' => 'Error deleting from database.',
    'file_not_found' => 'File not found for deletion.'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signage Manager</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Use only local Font Awesome -->
    <link href="css/font-awesome/all.min.css" rel="stylesheet">
    <style>
        /* Font Awesome Fixes */
        .fa-grip-vertical:before { content: "\f58e"; }
        .fa-globe:before { content: "\f0ac"; }
        .fas {
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }
        
        @font-face {
            font-family: 'Font Awesome 6 Free';
            font-style: normal;
            font-weight: 900;
            font-display: block;
            src: url("webfonts/fa-solid-900.woff2") format("woff2"),
                 url("webfonts/fa-solid-900.ttf") format("truetype");
        }
        
        /* Drag and Drop Styling */
        .draggable, .draggable-ticker { cursor: move; }
        .dragging {
            opacity: 0.5;
            background: #f8f9fa;
        }
        .drag-handle, .drag-handle-ticker {
            cursor: move;
            color: #6c757d;
            margin-right: 10px;
        }
        
        /* Thumbnail Styles - Common properties */
        .thumbnail,
        .video-thumbnail,
        .thumbnail-icon {
            width: 40px;
            height: 40px;
            border-radius: 4px;
        }
        
        /* Image Thumbnails */
        .thumbnail {
            object-fit: cover;
        }
        
        /* Video Thumbnails */
        .video-thumbnail {
            object-fit: cover;
            background-color: #000;
        }
        .video-container {
            position: relative;
            width: 40px;
            height: 40px;
        }
        .video-container::after {
            content: '\f04b';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-shadow: 0 0 3px rgba(0,0,0,0.5);
            font-size: 0.8rem;
            pointer-events: none;
        }
        
        /* Webpage Thumbnails */
        .thumbnail-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .thumbnail-icon i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h1 class="mb-4">Digital Signage Manager</h1>
                
                <?php if (isset($_GET['success']) && isset($successMessages[$_GET['success']])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                    <?= htmlspecialchars($successMessages[$_GET['success']]) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                    setTimeout(function() {
                        const alert = document.getElementById('successAlert');
                        if (alert) {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        }
                    }, 3000);
                    
                    // Remove success parameter from URL after showing alert
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                </script>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
                    <?php if (isset($errorMessages[$_GET['error']])): ?>
                        <?= htmlspecialchars($errorMessages[$_GET['error']]) ?>
                    <?php elseif (isset($_GET['message'])): ?>
                        <?= htmlspecialchars(urldecode($_GET['message'])) ?>
                    <?php else: ?>
                        An error occurred.
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                    setTimeout(function() {
                        const alert = document.getElementById('errorAlert');
                        if (alert) {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        }
                    }, 5000);
                    
                    // Remove error parameter from URL after showing alert
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                </script>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Add New Content</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Content Type</label>
                                <select name="type" class="form-select" id="contentType">
                                    <option value="multimedia">Multimedia</option>
                                    <option value="webpage">Webpage</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Display Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Duration (seconds)</label>
                                <input type="number" name="duration" class="form-control" value="10" required>
                            </div>
                            
                            <div id="fileUpload" class="mb-3">
                                <label class="form-label">File</label>
                                <input type="file" name="file" class="form-control">
                            </div>
                            
                            <div id="urlInput" class="mb-3" style="display: none;">
                                <label class="form-label">Webpage URL</label>
                                <input type="url" name="url" class="form-control">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Content List</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px"></th>
                                        <th style="width: 60px"></th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="sortableList">
                                    <?php foreach ($media as $item): ?>
                                    <tr class="draggable" data-id="<?= $item['id'] ?>">
                                        <td><i class="fas fa-grip-vertical drag-handle"></i></td>
                                        <td>
                                            <?php if ($item['type'] === 'image'): ?>
                                                <img src="<?= htmlspecialchars($item['path']) ?>" 
                                                     alt="<?= htmlspecialchars($item['name']) ?>" 
                                                     class="thumbnail">
                                            <?php elseif ($item['type'] === 'video'): ?>
                                                <div class="video-container">
                                                    <video src="<?= htmlspecialchars($item['path']) ?>"
                                                           class="video-thumbnail"
                                                           preload="metadata"
                                                           muted></video>
                                                </div>
                                            <?php else: ?>
                                                <div class="thumbnail-icon">
                                                    <i class="fas fa-globe"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= htmlspecialchars($item['type']) ?></td>
                                        <td><?= htmlspecialchars($item['duration']) ?> seconds</td>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       class="form-check-input active-toggle" 
                                                       <?= $item['is_active'] ? 'checked' : '' ?>
                                                       data-id="<?= $item['id'] ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($item['type'] === 'webpage'): ?>
                                                <!-- Combined edit button for webpage -->
                                                <button type="button" 
                                                        class="btn btn-primary btn-sm me-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editWebpageModal<?= $item['id'] ?>">
                                                    Edit
                                                </button>
                                            <?php else: ?>
                                                <!-- Edit button for non-webpage items -->
                                                <button type="button" 
                                                        class="btn btn-primary btn-sm me-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editMediaModal<?= $item['id'] ?>">
                                                    Edit
                                                </button>
                                            <?php endif; ?>
                                            <a href="delete.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure?')">Delete</a>
                                        </td>
                                    </tr>
                                    
                                    <?php if ($item['type'] === 'webpage'): ?>
                                    <!-- Combined Edit Modal for webpage (URL, duration, and name) -->
                                    <div class="modal fade" id="editWebpageModal<?= $item['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Webpage</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="edit_webpage" value="1">
                                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Display Name</label>
                                                            <input type="text" 
                                                                   name="name" 
                                                                   class="form-control" 
                                                                   value="<?= htmlspecialchars($item['name']) ?>" 
                                                                   required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">URL</label>
                                                            <input type="url" 
                                                                   name="url" 
                                                                   class="form-control" 
                                                                   value="<?= htmlspecialchars($item['url']) ?>" 
                                                                   required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Duration (seconds)</label>
                                                            <input type="number" 
                                                                   name="duration" 
                                                                   class="form-control" 
                                                                   value="<?= htmlspecialchars($item['duration']) ?>" 
                                                                   required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Edit Modal for non-webpage items -->
                                    <div class="modal fade" id="editMediaModal<?= $item['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit <?= ucfirst($item['type']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="edit_media" value="1">
                                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="type" value="<?= $item['type'] ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Display Name</label>
                                                            <input type="text" 
                                                                   name="name" 
                                                                   class="form-control" 
                                                                   value="<?= htmlspecialchars($item['name']) ?>" 
                                                                   required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Duration (seconds)</label>
                                                            <input type="number" 
                                                                   name="duration" 
                                                                   class="form-control" 
                                                                   value="<?= htmlspecialchars($item['duration']) ?>" 
                                                                   required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Replace <?= ucfirst($item['type']) ?> (optional)</label>
                                                            <input type="file" 
                                                                   name="file" 
                                                                   class="form-control"
                                                                   accept="<?= ($item['type'] === 'image' || $item['type'] === 'video' || $item['type'] === 'multimedia') ? 
                                                                          'image/jpeg,image/png,image/gif,video/mp4,video/webm,video/ogg' : 
                                                                          '' ?>">
                                                            <small class="form-text text-muted">
                                                                <?php if ($item['type'] === 'image' || $item['type'] === 'video' || $item['type'] === 'multimedia'): ?>
                                                                    Allowed formats: JPG, JPEG, PNG, GIF, MP4, WebM, OGG
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <a href="display.php" class="btn btn-success" target="_blank">Launch Display</a>
                    </div>
                </div>
                
                <!-- Ticker Messages Management -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Ticker Messages</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTickerModal">
                                <i class="fas fa-plus"></i> Add Ticker Message
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get all ticker messages
                        $stmt = $pdo->query("SELECT * FROM ticker_messages ORDER BY display_order ASC, created_at ASC");
                        $tickerMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Separate messages by type
                        $multimediaMessages = [];
                        $webpageMessages = [];
                        
                        foreach ($tickerMessages as $message) {
                            if (empty($message['target_url'])) {
                                $multimediaMessages[] = $message;
                            } else {
                                $webpageMessages[] = $message;
                            }
                        }
                        ?>
                        
                        <!-- Tabs for message types -->
                        <ul class="nav nav-tabs mb-3" id="messageTypeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="multimedia-tab" data-bs-toggle="tab" 
                                        data-bs-target="#multimedia-messages" type="button" role="tab" 
                                        aria-controls="multimedia-messages" aria-selected="true">
                                    Multimedia Messages <span class="badge bg-primary"><?= count($multimediaMessages) ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="webpage-tab" data-bs-toggle="tab" 
                                        data-bs-target="#webpage-messages" type="button" role="tab" 
                                        aria-controls="webpage-messages" aria-selected="false">
                                    Webpage Messages <span class="badge bg-info"><?= count($webpageMessages) ?></span>
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="messageTypeContent">
                            <!-- Multimedia Messages Content -->
                            <div class="tab-pane fade show active" id="multimedia-messages" role="tabpanel" aria-labelledby="multimedia-tab">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Message</th>
                                                <th style="width: 80px;">Active</th>
                                                <th style="width: 160px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortableMultimediaList">
                                            <?php foreach ($multimediaMessages as $message): ?>
                                            <tr class="draggable-ticker multimedia-item" data-id="<?= $message['id'] ?>">
                                                <td>
                                                    <i class="fas fa-grip-vertical drag-handle-ticker me-2"></i>
                                                    <?= htmlspecialchars($message['message']) ?>
                                                </td>
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" 
                                                               class="form-check-input ticker-toggle" 
                                                               <?= $message['is_active'] ? 'checked' : '' ?>
                                                               data-id="<?= $message['id'] ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <button type="button" 
                                                                class="btn btn-primary btn-sm me-1" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editTickerModal<?= $message['id'] ?>">
                                                            Edit
                                                        </button>
                                                        <?php if ($message['type'] === 'custom'): ?>
                                                        <a href="delete_ticker.php?id=<?= $message['id'] ?>" 
                                                           class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Are you sure you want to delete this ticker message?')">
                                                            Delete
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Edit Ticker Message Modal -->
                                            <div class="modal fade" id="editTickerModal<?= $message['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Multimedia Ticker Message</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="edit_ticker" value="1">
                                                                <input type="hidden" name="id" value="<?= $message['id'] ?>">
                                                                <input type="hidden" name="type" value="<?= $message['type'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Message</label>
                                                                    <textarea name="message" 
                                                                             class="form-control" 
                                                                             rows="3" 
                                                                             required><?= htmlspecialchars($message['message']) ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Target URL (leave empty for Multimedia)</label>
                                                                    <input type="url" 
                                                                           name="target_url" 
                                                                           class="form-control">
                                                                    <small class="form-text text-muted">
                                                                        If empty, this message will show for all multimedia content. If URL is provided, this message will only show for matching webpages.
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary">Save changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($multimediaMessages)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No multimedia messages found.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Webpage Messages Content -->
                            <div class="tab-pane fade" id="webpage-messages" role="tabpanel" aria-labelledby="webpage-tab">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Message</th>
                                                <th>Target URL</th>
                                                <th style="width: 80px;">Active</th>
                                                <th style="width: 160px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortableWebpageList">
                                            <?php foreach ($webpageMessages as $message): ?>
                                            <tr class="draggable-ticker webpage-item" data-id="<?= $message['id'] ?>">
                                                <td>
                                                    <i class="fas fa-grip-vertical drag-handle-ticker me-2"></i>
                                                    <?= htmlspecialchars($message['message']) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($message['target_url']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" 
                                                               class="form-check-input ticker-toggle" 
                                                               <?= $message['is_active'] ? 'checked' : '' ?>
                                                               data-id="<?= $message['id'] ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <button type="button" 
                                                                class="btn btn-primary btn-sm me-1" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editTickerModal<?= $message['id'] ?>">
                                                            Edit
                                                        </button>
                                                        <?php if ($message['type'] === 'custom'): ?>
                                                        <a href="delete_ticker.php?id=<?= $message['id'] ?>" 
                                                           class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Are you sure you want to delete this ticker message?')">
                                                            Delete
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Edit Ticker Message Modal -->
                                            <div class="modal fade" id="editTickerModal<?= $message['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Webpage Ticker Message</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="edit_ticker" value="1">
                                                                <input type="hidden" name="id" value="<?= $message['id'] ?>">
                                                                <input type="hidden" name="type" value="<?= $message['type'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Message</label>
                                                                    <textarea name="message" 
                                                                             class="form-control" 
                                                                             rows="3" 
                                                                             required><?= htmlspecialchars($message['message']) ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Target URL</label>
                                                                    <input type="url" 
                                                                           name="target_url" 
                                                                           class="form-control"
                                                                           value="<?= htmlspecialchars($message['target_url']) ?>"
                                                                           required>
                                                                    <small class="form-text text-muted">
                                                                        The webpage URL this message will be shown for.
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary">Save changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($webpageMessages)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No webpage messages found.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add Ticker Message Modal -->
                <div class="modal fade" id="addTickerModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Ticker Message</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="add_ticker" value="1">
                                    <input type="hidden" name="type" value="custom">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea name="message" 
                                                class="form-control" 
                                                rows="3" 
                                                required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Target URL (leave empty for Multimedia)</label>
                                        <input type="url" 
                                                name="target_url" 
                                                class="form-control">
                                        <small class="form-text text-muted">
                                            If empty, this message will show for all multimedia content. If URL is provided, this message will only show for matching webpages.
                                        </small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="sortable/Sortable.min.js"></script>
    <script>
        // Sort functionality
        new Sortable(document.getElementById('sortableList'), {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'dragging',
            onEnd: function (evt) {
                // Get all rows in their new order
                const rows = document.querySelectorAll('#sortableList tr.draggable');
                const orderData = [];
                
                rows.forEach((row, index) => {
                    orderData.push({
                        id: row.dataset.id,
                        order: index + 1
                    });
                });
                
                // Send the new order to the server
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `update_order=1&order_data=${encodeURIComponent(JSON.stringify(orderData))}`
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                })
                .catch(error => {
                    console.error('Error updating order:', error);
                });
            }
        });
        
        // Content type change
        document.getElementById('contentType').addEventListener('change', function() {
            const fileUpload = document.getElementById('fileUpload');
            const urlInput = document.getElementById('urlInput');
            
            if (this.value === 'webpage') {
                fileUpload.style.display = 'none';
                urlInput.style.display = 'block';
            } else {
                fileUpload.style.display = 'block';
                urlInput.style.display = 'none';
            }
        });
        
        // Media active toggle
        document.querySelectorAll('.active-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const id = this.dataset.id;
                const isActive = this.checked ? 1 : 0;
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_active=1&id=${id}&is_active=${isActive}`
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                })
                .catch(error => {
                    console.error('Error toggling active status:', error);
                });
            });
        });
        
        // Ticker message active toggle
        document.querySelectorAll('.ticker-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const id = this.dataset.id;
                const isActive = this.checked ? 1 : 0;
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_ticker=1&id=${id}&is_active=${isActive}`
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data);
                })
                .catch(error => {
                    console.error('Error toggling ticker status:', error);
                });
            });
        });

        // Video thumbnail preview
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.video-thumbnail').forEach(video => {
                // Try to set the poster frame to a point slightly into the video
                video.addEventListener('loadeddata', function() {
                    if (video.readyState >= 2) {
                        video.currentTime = 1;
                    }
                });
                
                // Once we've seeked to the time, capture that frame
                video.addEventListener('seeked', function() {
                    video.pause();
                });
            });
        });

        // Sort functionality for ticker messages
        const tickerList = document.querySelector('.table tbody');
        if (tickerList) {
            new Sortable(tickerList, {
                handle: '.drag-handle-ticker',
                animation: 150,
                ghostClass: 'dragging',
                onEnd: function (evt) {
                    // Get all rows in their new order
                    const rows = document.querySelectorAll('.draggable-ticker');
                    const orderData = [];
                    
                    rows.forEach((row, index) => {
                        orderData.push({
                            id: row.dataset.id,
                            order: index + 1
                        });
                    });
                    
                    // Send the new order to the server
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `update_ticker_order=1&order_data=${encodeURIComponent(JSON.stringify(orderData))}`
                    })
                    .then(response => response.text())
                    .then(data => {
                        console.log(data);
                    })
                    .catch(error => {
                        console.error('Error updating ticker order:', error);
                    });
                }
            });
        }

        // Initialize sortable ticker message list
        new Sortable(document.getElementById('sortableMultimediaList'), {
            handle: '.drag-handle-ticker',
            animation: 150,
            ghostClass: 'dragging',
            onEnd: function(evt) {
                const items = Array.from(document.querySelectorAll('#sortableMultimediaList tr.multimedia-item')).map(
                    (item, index) => ({ id: item.dataset.id, order: index + 1 })
                );
                
                // Update the order in the database
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action: 'update_ticker_order',
                        items: items 
                    })
                });
            }
        });
        
        // Initialize sortable webpage message list
        new Sortable(document.getElementById('sortableWebpageList'), {
            handle: '.drag-handle-ticker',
            animation: 150,
            ghostClass: 'dragging',
            onEnd: function(evt) {
                const items = Array.from(document.querySelectorAll('#sortableWebpageList tr.webpage-item')).map(
                    (item, index) => ({ id: item.dataset.id, order: index + 1 })
                );
                
                // Update the order in the database
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action: 'update_ticker_order',
                        items: items 
                    })
                });
            }
        });
    </script>
</body>
</html> 