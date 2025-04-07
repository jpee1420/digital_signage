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

// Get all active media ordered by display_order
$stmt = $pdo->query("SELECT * FROM media WHERE is_active = 1 ORDER BY display_order ASC, created_at ASC");
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticker messages
$stmt = $pdo->query("SELECT * FROM ticker_messages WHERE is_active = 1");
$tickerMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize message arrays
$multimediaMessages = [];
$webpageMessages = [];

// Process ticker messages
foreach ($tickerMessages as $message) {
    // If there's a target URL, it's a webpage message
    if (!empty($message['target_url'])) {
        $webpageMessages[] = [
            'message' => $message['message'],
            'target_url' => $message['target_url']
        ];
    } else {
        // No target URL means it's a multimedia message
        $multimediaMessages[] = $message['message'];
    }
}

// Default fallback message if none in database
if (empty($multimediaMessages)) {
    $multimediaMessages[] = "Welcome to our Digital Signage System";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signage Display</title>
    <style>
        /* Reset and Base Styles */
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #000;
        }

        /* Content Container Styles */
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

        /* Media Styles */
        img, video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        
        /* iFrame Styles */
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #fff;
        }
        
        /* News Ticker Styles */
        .news-ticker {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 30px;
            background-color: rgba(0, 0, 0, 0.9);
            color: white;
            display: flex;
            align-items: center;
            overflow: hidden;
            z-index: 9999;
            pointer-events: none;
        }
        
        .ticker-content {
            white-space: nowrap;
            animation: ticker 30s linear infinite;
            padding-left: 100%;
            font-size: 16px;
        }
        
        @keyframes ticker {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-100%);
            }
        }
        
        .schedules-message {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php foreach ($media as $item): ?>
        <div class="content" data-duration="<?= htmlspecialchars($item['duration']) ?>" 
             data-type="<?= htmlspecialchars($item['type']) ?>"
             <?php if ($item['type'] === 'webpage'): ?>
                data-url="<?= htmlspecialchars($item['url']) ?>"
             <?php endif; ?>>
            <?php if ($item['type'] === 'image'): ?>
                <img src="<?= htmlspecialchars($item['path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <?php elseif ($item['type'] === 'video'): ?>
                <video src="<?= htmlspecialchars($item['path']) ?>" 
                       preload="metadata"
                       playsinline
                       controls
                       muted>
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($item['type'] === 'webpage'): ?>
                <iframe data-src="<?= htmlspecialchars($item['url']) ?>" 
                        allow="fullscreen"
                        loading="lazy"></iframe>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <!-- News Ticker -->
    <div class="news-ticker" id="newsTicker">
        <div class="ticker-content" id="tickerContent">
            <?= !empty($multimediaMessages) ? $multimediaMessages[0] : '' ?>
        </div>
    </div>

    <script>
        const contents = document.querySelectorAll('.content');
        let currentIndex = -1; // Start at -1 so first showNext() will show index 0
        let currentTimeout = null;
        const bufferTime = 1000; // 1 second buffer time in milliseconds
        
        // Initialize messages
        const multimediaMessages = <?= json_encode(array_map(function($msg) {
            return html_entity_decode($msg);
        }, $multimediaMessages)) ?>;
        
        const webpageMessages = <?= json_encode(array_map(function($msg) {
            return [
                'message' => html_entity_decode($msg['message']),
                'target_url' => $msg['target_url']
            ];
        }, $webpageMessages)) ?>;
        
        // Message counter for rotation
        let messageCounter = 0;
        
        function updateTicker(contentElement) {
            const ticker = document.getElementById('newsTicker');
            const tickerContent = document.getElementById('tickerContent');
            const contentType = contentElement.dataset.type;
            const contentUrl = contentElement.dataset.url || '';
            
            // For webpage content
            if (contentType === 'webpage') {
                // Look for matching URL
                let matchedMessages = [];
                
                for (const msg of webpageMessages) {
                    if (contentUrl.includes(msg.target_url)) {
                        matchedMessages.push(msg.message);
                    }
                }
                
                if (matchedMessages.length > 0) {
                    // If multiple messages, combine with bullet points
                    ticker.style.display = 'flex';
                    if (matchedMessages.length === 1) {
                        tickerContent.textContent = matchedMessages[0];
                    } else {
                        tickerContent.textContent = matchedMessages.join(' • ');
                    }
                } else {
                    // No messages for this webpage
                    ticker.style.display = 'none';
                }
            } 
            // For multimedia content (image/video)
            else if (contentType === 'image' || contentType === 'video') {
                ticker.style.display = 'flex';
                
                if (multimediaMessages.length === 1) {
                    // Single message
                    tickerContent.textContent = multimediaMessages[0];
                } else if (multimediaMessages.length > 1) {
                    // Multiple messages - either rotate or combine with bullets
                    tickerContent.textContent = multimediaMessages.join(' • ');
                }
            }
            
            // Reset animation to ensure smooth start
            tickerContent.style.animation = 'none';
            setTimeout(() => {
                tickerContent.style.animation = 'ticker 30s linear infinite';
            }, 10);
        }

        // Pause all videos and iframes
        function pauseAllMedia() {
            // Pause all videos
            document.querySelectorAll('video').forEach(video => {
                video.pause();
                video.currentTime = 0;
                video.muted = true;
            });
            
            // Stop all iframes
            document.querySelectorAll('iframe').forEach(iframe => {
                iframe.src = '';
            });
        }

        function showNext() {
            // Clear any existing timeout
            if (currentTimeout) {
                clearTimeout(currentTimeout);
            }

            // Hide current content if any is showing
            if (currentIndex >= 0) {
                // Get the current content
                const currentContent = contents[currentIndex];
                
                // Pause any video in the current content
                const currentVideo = currentContent.querySelector('video');
                if (currentVideo) {
                    currentVideo.pause();
                    currentVideo.currentTime = 0;
                    currentVideo.muted = true;
                }
                
                // Remove src from iframe to stop loading
                const currentIframe = currentContent.querySelector('iframe');
                if (currentIframe) {
                    currentIframe.src = '';
                }
                
                // Hide the current content
                currentContent.classList.remove('active');
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
            
            // Get the new content element
            const newContent = contents[currentIndex];
            
            // Show new content
            newContent.classList.add('active');
            
            // Update ticker based on current content
            updateTicker(newContent);
            
            // Get duration for current content
            const duration = parseInt(newContent.dataset.duration) * 1000;
            
            // Handle video content specially
            const video = newContent.querySelector('video');
            if (video) {
                // Reset video state
                video.currentTime = 0;
                video.muted = false;
                
                // Play the video
                const playPromise = video.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(_ => {
                        // Playback started successfully
                    }).catch(error => {
                        console.log("Video playback error:", error);
                        // If we couldn't play the video, move to the next content
                        currentTimeout = setTimeout(showNext, duration);
                    });
                }
                
                // Set up event handlers for this video
                video.onended = showNext;
                video.onerror = function() {
                    console.log("Video error occurred");
                    currentTimeout = setTimeout(showNext, bufferTime);
                };
                
                return;
            }
            
            // Handle iframe content
            const iframe = newContent.querySelector('iframe');
            if (iframe) {
                // Load the iframe source when it becomes active
                const src = iframe.getAttribute('data-src');
                if (src) {
                    iframe.src = src;
                }
            }
            
            // Schedule next content
            currentTimeout = setTimeout(showNext, duration);
        }

        // Ensure all media is initially paused
        pauseAllMedia();

        // Start if we have content
        if (contents.length > 0) {
            showNext(); // This will now show the first item (index 0)
        }
    </script>
</body>
</html> 