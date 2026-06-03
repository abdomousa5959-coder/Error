<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

$BASE_API_URL = "https://blanchedalmond-dugong-106131.hostingersite.com/channels.php";
$CONFIG_FILE = __DIR__ . "/pinned_channel.txt";

// معالجة تسجيل الدخول للأدمن
if (isset($_POST['admin_pass']) && $_POST['admin_pass'] == '9999') {
    $_SESSION['is_admin'] = true;
}
// تسجيل الخروج
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// حفظ القناة المثبتة برابطها واسمها ولوجوها الجديد للجميع
if (isset($_POST['save_pinned']) && isset($_SESSION['is_admin'])) {
    $pinned_data = [
        'stream_url' => $_POST['pinned_url'],
        'name' => $_POST['pinned_name'],
        'image' => $_POST['pinned_img']
    ];
    @file_put_contents($CONFIG_FILE, json_encode($pinned_data, JSON_UNESCAPED_UNICODE));
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// قراءة بيانات القناة المثبتة لتعرض لجميع الزوار
$pinned_channel = null;
if (file_exists($CONFIG_FILE)) {
    $content = @file_get_contents($CONFIG_FILE);
    if ($content) {
        $pinned_channel = json_decode($content, true);
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'stream') {
    $url = null;
    if (isset($_GET['url'])) {
        $url = urldecode($_GET['url']);
    }

    if ($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36']);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_string = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if (strpos($content_type, 'mpegurl') !== false || strpos($body, '#EXTM3U') === 0) {
            header("Content-Type: application/vnd.apple.mpegurl");
            $base_url = dirname($final_url) . '/';
            $lines = explode("\n", $body);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] == '#') {
                    echo $line . "\n";
                } else {
                    $seg_url = (strpos($line, 'http') === 0) ? $line : $base_url . $line;
                    echo $_SERVER['PHP_SELF'] . '?action=stream&url=' . urlencode($seg_url) . "\n";
                }
            }
        } else {
            header("Content-Type: " . ($content_type ?: "video/mp4"));
            echo $body;
        }
        exit();
    }
}

function fetchOstoraData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$current_cat = $_GET['cat'] ?? null;
$current_sub = $_GET['sub'] ?? null;

if ($current_sub !== null) {
    $target_url = $BASE_API_URL . "?cat=" . urlencode($current_sub);
    $api_data = fetchOstoraData($target_url);
    $page_title = $api_data['title'] ?? "القنوات المتاحة";
    $view_type = "channels";
} elseif ($current_cat !== null) {
    $target_url = $BASE_API_URL . "?cat=" . urlencode($current_cat);
    $api_data = fetchOstoraData($target_url);
    $page_title = $api_data['title'] ?? "الباقات الفرعية";
    
    if (isset($api_data['items'][0]['is_stream']) && $api_data['items'][0]['is_stream'] == true) {
        $view_type = "channels";
    } else {
        $view_type = "sub_categories";
    }
} else {
    $api_data = fetchOstoraData($BASE_API_URL);
    $page_title = $api_data['title'] ?? "الباقات الرئيسية";
    $view_type = "main_categories";
}

$items = $api_data['items'] ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>𝕒𝕓𝕕𝕠 𝕞𝕠𝕦𝕤𝕒</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        :root {
            --bg-dark: #000000;
            --bg-panel: #0d0d0d;
            --text-light: #ffffff;
            --text-gray: #888888;
            --accent-white: #ffffff;
            --border-color: #1a1a1a;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: 'Cairo', sans-serif;
            margin: 0; padding: 0; overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        .main-header {
            display: flex; flex-direction: column; align-items: center; padding: 20px 15px; background-color: #000;
            border-bottom: 2px solid #111; position: sticky; top: 0; z-index: 100; gap: 15px;
        }
        .header-top {
            display: flex; justify-content: space-between; align-items: center; width: 100%;
        }
        .main-header .back-btn { color: var(--text-light); text-decoration: none; font-size: 16px; display: flex; align-items: center; gap: 8px; font-weight: bold; }
        .main-header .logo { font-family: 'Cairo'; font-size: 24px; font-weight: 900; color: #dc2626; user-select: none; }
        
        /* جعل حرف الـ m كزر مخفي مدمج تماماً */
        .secret-trigger {
            cursor: pointer;
            color: #dc2626;
            display: inline-block;
        }

        .nav-buttons {  
            display: flex;  
            gap: 12px;  
            margin-top: 5px;  
            justify-content: center;  
            width: 100%;  
        }  
        .nav-btn {  
            background: rgba(255,255,255,0.08);  
            color: #fff;  
            padding: 8px 18px;  
            border-radius: 20px;  
            text-decoration: none;  
            font-size: 14px;  
            font-weight: bold;  
            transition: 0.3s;  
            border: 1px solid rgba(255,255,255,0.1);  
            display: flex;  
            align-items: center;  
            gap: 6px;  
        }  
        .nav-btn:hover { background: #dc2626; border-color: transparent; }

        .section-title {
            text-align: center; margin: 15px 0; font-size: 16px; font-weight: bold; color: var(--text-gray);
        }

        .container { padding: 0 15px 40px 15px; max-width: 600px; margin: 0 auto; }

        .list-vertical {
            display: flex; flex-direction: column; gap: 12px;
        }
        .list-vertical .item-card {
            background-color: var(--bg-panel); border: 1px solid var(--border-color);
            border-radius: 12px; display: flex; align-items: center; justify-content: space-between;
            padding: 12px 20px; text-decoration: none; color: var(--text-light); transition: 0.2s;
        }
        .list-vertical .item-card:hover { border-color: var(--accent-white); }
        .list-vertical .item-card .title { font-size: 15px; font-weight: 700; }
        .list-vertical .item-card img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #222; }

        .grid-three-columns {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
        }
        .grid-three-columns .item-card {
            background-color: var(--bg-panel); border: 1px solid var(--border-color);
            border-radius: 8px; overflow: hidden; text-decoration: none; color: var(--text-light);
            display: flex; flex-direction: column; align-items: center; text-align: center; padding: 15px 5px; transition: 0.2s;
        }
        .grid-three-columns .item-card:hover { border-color: var(--accent-white); transform: translateY(-2px); }
        .grid-three-columns .item-card img { width: 100%; aspect-ratio: 1/1; max-width: 65px; object-fit: contain; border-radius: 6px; margin-bottom: 8px; }
        .grid-three-columns .item-card .title { font-size: 12px; font-weight: 600; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* ستايل زر التثبيت الخاص بالأدمن على الكروت */
        .admin-pin-badge {
            background: #dc2626; color: #fff; font-size: 10px; font-weight: bold; padding: 3px 8px;
            border-radius: 4px; margin-top: 5px; border: 1px solid #fff; z-index: 5;
        }
        .admin-pin-badge:hover { background: #fff; color: #000; }

        .player-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.98); z-index: 9999; align-items: center; justify-content: center; padding: 12px; box-sizing: border-box;
        }
        .player-content {
            background: var(--bg-panel); border: 1px solid #222; width: 100%; max-width: 600px; border-radius: 14px; overflow: hidden;
        }
        .player-header {
            display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #1a1a1a;
        }
        .player-header h3 { margin: 0; font-size: 14px; font-family: 'Cairo'; }
        .player-header .close-btn { color: #fff; cursor: pointer; font-size: 20px; transition: 0.2s; }
        .player-header .close-btn:hover { color: #ff4444; }

        .video-container { width: 100%; background: #000; aspect-ratio: 16/9; }
        video { width: 100%; height: 100%; object-fit: contain; }

        .server-box { padding: 15px; }
        .server-title { font-size: 13px; margin-bottom: 10px; color: var(--text-gray); text-align: right; }
        .btn-play-now { background: #fff; color: #000; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; cursor: pointer; margin-bottom: 10px; font-family: 'Orbitron'; font-size: 14px; }
        .split-btns { display: flex; gap: 10px; }
        .split-btns button { flex: 1; border: 1px solid #333; background: #111; color: #fff; padding: 10px; border-radius: 6px; font-size: 12px; cursor: pointer; font-family: 'Orbitron'; }
    </style>
</head>
<body>

<div class="main-header">
    <div class="header-top">
        <?php if ($view_type != 'main_categories'): ?>
            <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
        <?php else: ?>
            <div style="width: 50px;"></div>
        <?php endif; ?>
        
        <!-- الزر السري مدمج تماماً في حرف الـ m من كلمة mousa -->
        <div class="logo">𝕒𝕓𝕕ο <span class="secret-trigger" onclick="triggerAdmin()">𝕞</span>𝕠𝕦𝕤𝕒</div>
        
        <div style="width: 50px;"></div>
    </div>
    
    <div class="nav-buttons">
        <a href="http://ab.gt.tc" target="_blank" class="nav-btn"><i class="fas fa-film"></i> الأفلام</a>
        <a href="http://eng.gt.tc" target="_blank" class="nav-btn"><i class="fas fa-video"></i> المسلسلات</a>
    </div>
</div>

<div class="container" style="margin-top: 10px; padding-bottom: 0;">
    <!-- فورم مخفي تماماً لإرسال كلمة المرور واستقبال طلبات الحفظ -->
    <form id="adminAuthForm" method="POST" style="display:none;">
        <input type="hidden" name="admin_pass" id="adminPassInput">
    </form>
    <form id="pinChannelForm" method="POST" style="display:none;">
        <input type="hidden" name="save_pinned" value="1">
        <input type="hidden" name="pinned_name" id="form_p_name">
        <input type="hidden" name="pinned_img" id="form_p_img">
        <input type="hidden" name="pinned_url" id="form_p_url">
    </form>

    <?php if (isset($_SESSION['is_admin'])): ?>
        <div style="text-align: center; margin-bottom: 10px;">
            <span style="color: #dc2626; font-size: 12px; background: #111; padding: 4px 10px; border-radius: 10px; border: 1px dashed #dc2626;">
                وضع الإدارة نشط 🔓 | <a href="?logout=1" style="color: #fff; text-decoration: none; font-weight: bold;">تسجيل خروج</a>
            </span>
        </div>
    <?php endif; ?>

    <!-- عرض القناة المثبتة لجميع زوار الموقع في الواجهة الرئيسية -->
    <?php if ($view_type == 'main_categories' && $pinned_channel): ?>
        <div class="section-title" style="margin-top: 0; color: #fff;">⭐ القناة المثبتة حالياً</div>
        <div class="list-vertical" style="margin-bottom: 25px;">
            <div class="item-card" style="cursor: pointer; border: 1px solid #dc2626;" onclick="openFloatingPlayer('<?php echo addslashes($pinned_channel['name']); ?>', '<?php echo addslashes($pinned_channel['stream_url']); ?>')">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?php echo htmlspecialchars(!empty($pinned_channel['image']) ? $pinned_channel['image'] : 'https://img.icons8.com/ios-filled/100/ffffff/tv.png'); ?>" onerror="this.src='https://img.icons8.com/ios-filled/100/ffffff/tv.png'">
                    <span class="title" style="color: #fff; font-weight: 900;"><?php echo htmlspecialchars($pinned_channel['name']); ?></span>
                </div>
                <i class="fas fa-play-circle" style="color: #dc2626; font-size: 22px;"></i>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="section-title"><?php echo htmlspecialchars($page_title); ?></div>

<div class="container">
    <?php if ($view_type == 'main_categories'): ?>
        <div class="list-vertical">
            <?php foreach ($items as $item): ?>
                <a href="?cat=<?php echo urlencode($item['id']); ?>" class="item-card">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" onerror="this.src='https://img.icons8.com/ios-filled/50/ffffff/video-playlist.png'">
                        <span class="title"><?php echo htmlspecialchars($item['name']); ?></span>
                    </div>
                    <i class="fas fa-chevron-left" style="color: var(--text-gray); font-size: 14px;"></i>
                </a>
            <?php endforeach; ?>
        </div>

    <?php elseif ($view_type == 'sub_categories'): ?>
        <div class="grid-three-columns">
            <?php foreach ($items as $item): ?>
                <a href="?cat=<?php echo urlencode($current_cat); ?>&sub=<?php echo urlencode($item['id']); ?>" class="item-card">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" onerror="this.src='https://img.icons8.com/ios-filled/100/ffffff/folder-invoices.png'">
                    <span class="title"><?php echo htmlspecialchars($item['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    <?php elseif ($view_type == 'channels'): ?>
        <div class="grid-three-columns">
            <?php foreach ($items as $item): 
                $stream_url = $item['stream_url'] ?? '';
                $clean_name = addslashes($item['name']);
                $clean_url = addslashes($stream_url);
                $clean_img = addslashes($item['image'] ?? '');
            ?>
                <div class="item-card" style="cursor: pointer; position: relative;" onclick="openFloatingPlayer('<?php echo $clean_name; ?>', '<?php echo $clean_url; ?>')">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" onerror="this.src='https://img.icons8.com/ios-filled/100/ffffff/tv.png'">
                    <span class="title"><?php echo htmlspecialchars($item['name']); ?></span>
                    
                    <!-- زر التثبيت البصري الذكي يظهر للأدمن فقط هنا مباشرة دون أي لود أو بطء -->
                    <?php if (isset($_SESSION['is_admin'])): ?>
                        <div class="admin-pin-badge" onclick="event.stopPropagation(); processPin('<?php echo $clean_name; ?>', '<?php echo $clean_img; ?>', '<?php echo $clean_url; ?>')">
                            تثبيت 📌
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="player-modal" id="playerModal">
    <div class="player-content">
        <div class="player-header">
            <h3 id="modalChannelName">اسم القناة</h3>
            <i class="fas fa-xmark close-btn" onclick="closeFloatingPlayer()"></i>
        </div>
        
        <div class="video-container">
            <video id="floatingVideo" controls autoplay playsinline></video>
        </div>
        
        <div class="server-box">
            <div class="server-title">سيرفر البث المباشر (نظام البروكسي المدمج)</div>
            <button class="btn-play-now" id="btnPlayNow">PLAY NOW</button>
            <div class="split-btns">
                <button id="btnCopyProxy">PROXY LINK</button>
                <button id="btnCopyDirect">DIRECT LINK</button>
            </div>
        </div>
    </div>
</div>

<script>
    const currentDomain = window.location.origin + window.location.pathname;
    const playerModal = document.getElementById('playerModal');
    const modalChannelName = document.getElementById('modalChannelName');
    const videoTag = document.getElementById('floatingVideo');
    const btnPlayNow = document.getElementById('btnPlayNow');
    const btnCopyProxy = document.getElementById('btnCopyProxy');
    const btnCopyDirect = document.getElementById('btnCopyDirect');
    let hlsPlayer = null;

    // سكربت طلب الباسورد السري
    function triggerAdmin() {
        let password = prompt("أدخل كلمة المرور السرية:");
        if (password) {
            document.getElementById('adminPassInput').value = password;
            document.getElementById('adminAuthForm').submit();
        }
    }

    // سكربت التثبيت السريع والتعديل عند الضغط على زر التثبيت
    function processPin(defaultName, defaultImg, streamUrl) {
        let newName = prompt("تعديل اسم القناة المثبتة:", defaultName);
        if (newName === null) return; 
        
        let newImg = prompt("رابط لوجو القناة (اتركه كما هو أو غيره):", defaultImg);
        if (newImg === null) return;

        document.getElementById('form_p_name').value = newName;
        document.getElementById('form_p_img').value = newImg;
        document.getElementById('form_p_url').value = streamUrl;
        document.getElementById('pinChannelForm').submit();
    }

    function openFloatingPlayer(name, rawStreamUrl) {
        const proxyUrl = currentDomain + '?action=stream&url=' + encodeURIComponent(rawStreamUrl);
        
        modalChannelName.innerText = name;
        playerModal.style.display = 'flex';
        
        btnPlayNow.onclick = () => {
            loadStream(proxyUrl);
            makeFullscreenAndLandscape(); 
        };
        
        btnCopyProxy.onclick = function() { copyToClipboard(proxyUrl, this, 'PROXY'); };
        btnCopyDirect.onclick = function() { copyToClipboard(rawStreamUrl, this, 'DIRECT'); };
        
        loadStream(proxyUrl);
        makeFullscreenAndLandscape(); 
    }

    function makeFullscreenAndLandscape() {
        if (videoTag.requestFullscreen) {
            videoTag.requestFullscreen();
        } else if (videoTag.webkitRequestFullscreen) { 
            videoTag.webkitRequestFullscreen();
        } else if (videoTag.mozRequestFullScreen) {
            videoTag.mozRequestFullScreen();
        } else if (videoTag.msRequestFullscreen) {
            videoTag.msRequestFullscreen();
        }

        if (screen.orientation && screen.orientation.lock) {
            screen.orientation.lock('landscape').catch(function(error) {
                console.log("إغلاق قفل الدوران التلقائي في جهازك مفعل، يرجى تدوير الهاتف يدوياً.");
            });
        }
    }

    function closeFloatingPlayer() {
        if (document.exitFullscreen) { document.exitFullscreen(); }
        else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
        
        if (screen.orientation && screen.orientation.unlock) {
            screen.orientation.unlock();
        }

        if (hlsPlayer) { hlsPlayer.destroy(); }
        videoTag.pause();
        videoTag.src = "";
        playerModal.style.display = 'none';
    }

    function loadStream(streamUrl) {
        if (!streamUrl || streamUrl.trim() === "" || streamUrl.endsWith('url=')) {
            alert('عذراً، لا يوجد رابط بث مباشر متاح لهذه القناة حالياً.');
            return;
        }

        if (Hls.isSupported()) {
            if (hlsPlayer) { hlsPlayer.destroy(); }
            hlsPlayer = new Hls();
            hlsPlayer.loadSource(streamUrl);
            hlsPlayer.attachMedia(videoTag);
            hlsPlayer.on(Hls.Events.MANIFEST_PARSED, () => videoTag.play());
        } else if (videoTag.canPlayType('application/vnd.apple.mpegurl')) {
            videoTag.src = streamUrl;
            videoTag.play();
        }
    }

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            if(playerModal.style.display === 'flex') {
                closeFloatingPlayer();
            }
        }
    });
    document.addEventListener('webkitfullscreenchange', () => {
        if (!document.webkitFullscreenElement) {
            if(playerModal.style.display === 'flex') {
                closeFloatingPlayer();
            }
        }
    });

    function copyToClipboard(text, element, defaultText) {
        navigator.clipboard.writeText(text);
        element.innerText = 'COPIED!';
        setTimeout(() => { 
            element.innerText = defaultText + ' LINK'; 
        }, 1500);
    }

    window.onclick = function(event) {
        if (event.target == playerModal) {
            closeFloatingPlayer();
        }
    }
</script>
</body>
</html>
