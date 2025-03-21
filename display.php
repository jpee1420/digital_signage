<?php
require_once 'config.php';

// Get all active media ordered by display_order
$stmt = $pdo->query("SELECT * FROM media WHERE is_active = 1 ORDER BY display_order ASC, created_at ASC");
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signage Display</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #000;
        }
        .content {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: none;
        }
        .content.active {
            display: block;
        }
        img, video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #fff;
        }
    </style>
</head>
<body>
    <?php foreach ($media as $item): ?>
        <div class="content" data-duration="<?= htmlspecialchars($item['duration']) ?>">
            <?php if ($item['type'] === 'image'): ?>
                <img src="<?= htmlspecialchars($item['path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <?php elseif ($item['type'] === 'video'): ?>
                <video src="<?= htmlspecialchars($item['path']) ?>" 
                       controls="false"
                       autoplay 
                       muted 
                       playsinline
                       preload="auto">
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($item['type'] === 'webpage'): ?>
                <iframe src="<?= htmlspecialchars($item['url']) ?>" 
                        allow="fullscreen"
                        loading="eager"></iframe>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <script>
        const contents = document.querySelectorAll('.content');
        let currentIndex = -1; // Start at -1 so first showNext() will show index 0
        let currentTimeout = null;
        const bufferTime = 1000; // 1 second buffer time in milliseconds

        function showNext() {
            // Clear any existing timeout
            if (currentTimeout) {
                clearTimeout(currentTimeout);
            }

            // Hide current content if any is showing
            if (currentIndex >= 0) {
                contents[currentIndex].classList.remove('active');
            }
            
            // Add buffer time before showing next content
            // For the first item, don't add buffer
            if (currentIndex >= 0) {
                setTimeout(showNextContent, bufferTime);
            } else {
                showNextContent();
            }
        }
        
        function showNextContent() {
            // Move to next content
            currentIndex = (currentIndex + 1) % contents.length;
            
            // Show new content
            contents[currentIndex].classList.add('active');
            
            // Get duration for current content
            const duration = parseInt(contents[currentIndex].dataset.duration) * 1000;
            
            // Handle video content specially
            const video = contents[currentIndex].querySelector('video');
            if (video) {
                video.currentTime = 0;
                video.play().catch(function(error) {
                    console.log("Video playback error:", error);
                    currentTimeout = setTimeout(showNext, duration);
                });
                
                video.onended = showNext;
                video.onerror = function() {
                    console.log("Video error occurred");
                    currentTimeout = setTimeout(showNext, bufferTime);
                };
                return;
            }
            
            // Handle iframe content
            const iframe = contents[currentIndex].querySelector('iframe');
            if (iframe) {
                try {
                    iframe.src = iframe.src;
                } catch (error) {
                    console.log("Iframe refresh error:", error);
                }
            }
            
            // Schedule next content
            currentTimeout = setTimeout(showNext, duration);
        }

        // Start if we have content
        if (contents.length > 0) {
            showNext(); // This will now show the first item (index 0)
        }

        // Add error handling for videos
        document.querySelectorAll('video').forEach(video => {
            video.addEventListener('error', function(e) {
                console.log("Video error:", e);
                showNext();
            });
        });
    </script>
</body>
</html> 