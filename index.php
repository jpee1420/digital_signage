<?php
require_once 'config.php';

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
            
            if (($type === 'image' && !in_array($extension, $allowedImageTypes)) ||
                ($type === 'video' && !in_array($extension, $allowedVideoTypes))) {
                die("Invalid file type. Allowed types for images: " . implode(', ', $allowedImageTypes) . 
                    ". Allowed types for videos: " . implode(', ', $allowedVideoTypes));
            }
            
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
                $stmt = $pdo->prepare("UPDATE media SET name = ?, duration = ?, path = ? WHERE id = ?");
                $stmt->execute([$name, $duration, $targetPath, $id]);
            }
        } else {
            // Only update name and duration if no new file
            $stmt = $pdo->prepare("UPDATE media SET name = ?, duration = ? WHERE id = ?");
            $stmt->execute([$name, $duration, $id]);
        }
        
        header('Location: index.php?success=edit');
        exit;
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
        
        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file types
        $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedVideoTypes = ['mp4', 'webm', 'ogg'];
        
        if (($type === 'image' && !in_array($extension, $allowedImageTypes)) ||
            ($type === 'video' && !in_array($extension, $allowedVideoTypes))) {
            die("Invalid file type");
        }
        
        $filename = uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $pdo->prepare("INSERT INTO media (name, type, path, duration, display_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $targetPath, $duration, $next_order]);
            header('Location: index.php?success=add');
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
    'edit' => 'Content updated successfully!'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signage Manager</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .draggable {
            cursor: move;
        }
        .dragging {
            opacity: 0.5;
            background: #f8f9fa;
        }
        .drag-handle {
            cursor: move;
            color: #6c757d;
            margin-right: 10px;
        }
        .thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        .video-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            background-color: #000;
        }
        .thumbnail-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 4px;
            color: #6c757d;
        }
        .thumbnail-icon i {
            font-size: 1.2rem;
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

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Add New Content</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Content Type</label>
                                <select name="type" class="form-select" id="contentType">
                                    <option value="image">Image</option>
                                    <option value="video">Video</option>
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
                                                                   accept="<?= $item['type'] === 'image' ? 
                                                                          'image/jpeg,image/png,image/gif' : 
                                                                          'video/mp4,video/webm,video/ogg' ?>">
                                                            <small class="form-text text-muted">
                                                                <?php if ($item['type'] === 'image'): ?>
                                                                    Allowed formats: JPG, JPEG, PNG, GIF
                                                                <?php else: ?>
                                                                    Allowed formats: MP4, WebM, OGG
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
            </div>
        </div>
    </div>
    
    <script src="js/bootstrap.bundle.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
    <script src="sortable/Sortable.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script> -->
    <script>
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

        // Initialize drag-and-drop with improved order handling
        new Sortable(document.getElementById('sortableList'), {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function() {
                const rows = document.querySelectorAll('.draggable');
                const orderData = Array.from(rows).map((row, index) => ({
                    id: row.dataset.id,
                    order: index + 1
                }));
                
                // Show loading state
                const loadingToast = document.createElement('div');
                loadingToast.className = 'position-fixed top-0 end-0 p-3';
                loadingToast.style.zIndex = '5000';
                loadingToast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <strong class="me-auto">Updating order...</strong>
                        </div>
                    </div>
                `;
                document.body.appendChild(loadingToast);
                
                // Send order update
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'update_order=1&order_data=' + encodeURIComponent(JSON.stringify(orderData))
                })
                .then(response => response.text())
                .then(result => {
                    console.log('Order update result:', result);
                    loadingToast.remove();
                    
                    // Show success message
                    const successToast = document.createElement('div');
                    successToast.className = 'position-fixed top-0 end-0 p-3';
                    successToast.style.zIndex = '5000';
                    successToast.innerHTML = `
                        <div class="toast show bg-success text-white" role="alert">
                            <div class="toast-body">
                                Order updated successfully
                            </div>
                        </div>
                    `;
                    document.body.appendChild(successToast);
                    setTimeout(() => successToast.remove(), 2000);
                })
                .catch(error => {
                    console.error('Error updating order:', error);
                    loadingToast.remove();
                    
                    // Show error message
                    const errorToast = document.createElement('div');
                    errorToast.className = 'position-fixed top-0 end-0 p-3';
                    errorToast.style.zIndex = '5000';
                    errorToast.innerHTML = `
                        <div class="toast show bg-danger text-white" role="alert">
                            <div class="toast-body">
                                Error updating order
                            </div>
                        </div>
                    `;
                    document.body.appendChild(errorToast);
                    setTimeout(() => errorToast.remove(), 3000);
                });
            }
        });

        // Handle active toggle
        document.querySelectorAll('.active-toggle').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const id = this.dataset.id;
                const is_active = this.checked ? 1 : 0;
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_active=1&id=${id}&is_active=${is_active}`
                });
            });
        });

        // Add this at the beginning of your script section
        // Load video thumbnails
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
    </script>
</body>
</html> 