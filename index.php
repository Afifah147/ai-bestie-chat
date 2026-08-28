<?php
// Memanggil API Key dari file terpisah yang aman dari GitHub Secret Scanning
include 'config.php'; 

// --- BLOK PENANGANAN AJAX (BACKEND) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajax"])) {
    header('Content-Type: application/json');
    $user_prompt = $_POST["prompt"] ?? "";
    $image_base64 = $_POST["image_base64"] ?? "";
    $image_mime = $_POST["image_mime"] ?? "";
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $api_key;

    $parts = [];

    if (!empty($image_base64) && !empty($image_mime)) {
        $parts[] = [
            "inline_data" => [
                "mime_type" => $image_mime,
                "data" => $image_base64
            ]
        ];
    }

    if (!empty($user_prompt)) {
        $parts[] = ["text" => $user_prompt];
    } else {
        $parts[] = ["text" => "Tolong jelaskan gambar ini."];
    }

    $data = [
        "systemInstruction" => [
            "parts" => [
                ["text" => "Kamu adalah sahabat karib (bestie) ala karakter anime yang asyik, suportif, ramah, dan menyenangkan. Gunakan bahasa Indonesia yang santai, akrab, dan hangat. Jika pengguna mengirimkan gambar, berikan analisis atau tanggapan yang natural."]
            ]
        ],
        "contents" => [
            ["parts" => $parts]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo json_encode(["status" => "error", "message" => "Koneksi terputus: " . curl_error($ch)]);
        exit;
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(["status" => "success", "message" => $result['candidates'][0]['content']['parts'][0]['text']]);
    } else {
        echo json_encode(["status" => "error", "message" => "Terjadi kesalahan sistem dari server. Detail:\n" . $response]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Bestie - Instant Local Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at top left, #fbcfe8, #f3e8ff, #e0e7ff);
            --app-bg: rgba(255, 255, 255, 0.9);
            --text-color: #334155;
            --header-bg: rgba(255, 255, 255, 0.8);
            --bubble-ai: #ffffff;
            --bubble-ai-border: #fbcfe8;
            --input-bg: #ffffff;
            --input-border: #fbcfe8;
        }

        body.dark-mode {
            --bg-gradient: radial-gradient(circle at top left, #1e1b4b, #312e81, #0f172a);
            --app-bg: rgba(30, 41, 59, 0.85);
            --text-color: #e2e8f0;
            --header-bg: rgba(15, 23, 42, 0.7);
            --bubble-ai: #1e293b;
            --bubble-ai-border: rgba(129, 140, 248, 0.3);
            --input-bg: rgba(15, 23, 42, 0.8);
            --input-border: rgba(255, 255, 255, 0.2);
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            transition: background 0.3s ease;
        }
        
        .chat-app-container {
            width: 100%;
            max-width: 800px;
            height: 90vh;
            background: var(--app-bg);
            border-radius: 25px;
            box-shadow: 0 10px 30px 0 rgba(244, 114, 182, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(244, 114, 182, 0.2);
            background: var(--header-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-glow {
            color: #db2777;
            font-weight: 800;
            margin-bottom: 0;
            font-size: 1.1rem;
        }

        .online-status {
            font-size: 0.75rem;
            color: #10b981;
            font-weight: 600;
        }

        .header-tools {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .btn-tool {
            background: rgba(244, 114, 182, 0.1);
            border: 1px solid rgba(244, 114, 182, 0.3);
            color: #db2777;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-tool:hover { background: #f472b6; color: white; }

        .voice-select {
            background: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--input-border);
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 8px;
            outline: none;
        }

        .chat-box {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .bubble {
            max-width: 80%;
            padding: 14px 18px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .bubble-user {
            background: #f472b6;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .bubble-ai {
            background: var(--bubble-ai);
            color: var(--text-color);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border: 1px solid var(--bubble-ai-border);
        }

        .chat-img-preview {
            max-width: 200px;
            border-radius: 10px;
            margin-bottom: 8px;
            display: block;
        }

        .bubble-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .btn-speaker {
            background: transparent;
            border: none;
            color: #db2777;
            font-size: 1.1rem;
            padding: 0;
            cursor: pointer;
        }
        .btn-speaker:hover { color: #831843; }

        .btn-react {
            background: transparent;
            border: none;
            color: #cbd5e1;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-react.liked { color: #ef4444; }

        .chat-input-area {
            padding: 15px 20px;
            background: var(--header-bg);
            border-top: 1px solid rgba(244, 114, 182, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .form-control-chat {
            flex: 1;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-color);
            border-radius: 25px;
            padding: 12px 20px;
            resize: none;
            height: 50px;
            line-height: 24px;
        }
        .form-control-chat:focus { outline: none; border-color: #f472b6; }

        .btn-action {
            background: transparent;
            border: none;
            color: #db2777;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 0 4px;
        }

        .btn-wa-send {
            background: #f472b6;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            flex-shrink: 0;
        }
        .btn-wa-send:hover { background: #db2777; }

        .typing-indicator {
            align-self: flex-start;
            color: #db2777;
            font-size: 0.85rem;
            font-style: italic;
            display: none;
            padding-left: 10px;
        }

        #emojiPicker {
            position: absolute;
            bottom: 80px;
            left: 20px;
            background: white;
            border: 1px solid #fbcfe8;
            border-radius: 15px;
            padding: 12px;
            width: 330px;
            height: 220px;
            display: none;
            overflow-y: auto;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            box-shadow: 0 8px 25px rgba(244, 114, 182, 0.2);
            z-index: 100;
        }
        #emojiPicker span { font-size: 1.3rem; text-align: center; cursor: pointer; padding: 4px; }
        #emojiPicker span:hover { background: #fdf2f8; }

        #filePreviewContainer {
            position: absolute;
            bottom: 75px;
            left: 20px;
            background: white;
            padding: 5px 10px;
            border-radius: 12px;
            border: 1px solid #fbcfe8;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 99;
        }
        #filePreviewContainer img { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="chat-app-container">
            <!-- Header -->
            <div class="chat-header">
                <div>
                    <h4 class="header-glow">🌸 AI Bestie 🌸</h4>
                    <span class="online-status" id="onlineStatus">● Online</span>
                </div>
                <div class="header-tools">
                    <select id="voiceStyle" class="voice-select" title="Pilih Gaya Suara">
                        <option value="imut">🌸 Imut (Ceria)</option>
                        <option value="semangat">⚡ Semangat</option>
                        <option value="cool">😎 Cool / Santai</option>
                        <option value="normal">✨ Normal</option>
                    </select>
                    <button type="button" class="btn-tool" onclick="toggleDarkMode()" title="Ganti Tema"><i class="bi bi-moon-stars-fill" id="themeIcon"></i></button>
                    <button type="button" class="btn-tool" onclick="clearHistory()" title="Hapus Riwayat">Hapus</button>
                </div>
            </div>
            
            <!-- Area Obrolan -->
            <div class="chat-box" id="chatBox"></div>

            <!-- Indikator Loading -->
            <div class="px-4 pb-2 typing-indicator" id="typingIndicator">
                Bestie lagi ngetik... ✨
            </div>

            <!-- Panel Emoji -->
            <div id="emojiPicker">
                <span onclick="insertEmoji('😀')">😀</span><span onclick="insertEmoji('😂')">😂</span><span onclick="insertEmoji('😊')">😊</span><span onclick="insertEmoji('🥰')">🥰</span><span onclick="insertEmoji('😍')">😍</span><span onclick="insertEmoji('😎')">😎</span><span onclick="insertEmoji('🤔')">🤔</span>
                <span onclick="insertEmoji('❤️')">❤️</span><span onclick="insertEmoji('✨')">✨</span><span onclick="insertEmoji('🔥')">🔥</span><span onclick="insertEmoji('🎉')">🎉</span><span onclick="insertEmoji('🚀')">🚀</span><span onclick="insertEmoji('☕')">☕</span><span onclick="insertEmoji('👍')">👍</span>
            </div>

            <!-- Preview Gambar -->
            <div id="filePreviewContainer">
                <img id="previewImg" src="" alt="Preview">
                <span id="previewName" class="small text-muted"></span>
                <button type="button" class="btn btn-sm text-danger p-0 ms-2" onclick="removeImage()"><i class="bi bi-x-circle-fill"></i></button>
            </div>

            <!-- Area Input -->
            <div class="chat-input-area">
                <button type="button" class="btn-action" onclick="toggleEmojiPicker()" title="Pilih Emoji"><i class="bi bi-emoji-smile"></i></button>
                <input type="file" id="imageInput" accept="image/*" style="display: none;" onchange="handleImageSelect(event)">
                <button type="button" class="btn-action" onclick="document.getElementById('imageInput').click()" title="Kirim Gambar"><i class="bi bi-image"></i></button>
                <textarea class="form-control-chat" id="promptInput" placeholder="Ketik pesan..." rows="1"></textarea>
                <button type="button" class="btn-wa-send" id="btnSend" title="Kirim"><i class="bi bi-send-fill" style="margin-left: -3px; margin-top: 2px;"></i></button>
            </div>
        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        const chatBox = document.getElementById('chatBox');
        const promptInput = document.getElementById('promptInput');
        const btnSend = document.getElementById('btnSend');
        const typingIndicator = document.getElementById('typingIndicator');
        const emojiPicker = document.getElementById('emojiPicker');
        const onlineStatus = document.getElementById('onlineStatus');
        const voiceStyleSelect = document.getElementById('voiceStyle');
        
        let selectedImageBase64 = "";
        let selectedImageMime = "";

        function playSendSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(580, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.05, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.15);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.15);
            } catch (e) {}
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('ai_dark_mode', isDark);
            document.getElementById('themeIcon').className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }

        if (localStorage.getItem('ai_dark_mode') === 'true') {
            document.body.classList.add('dark-mode');
            document.getElementById('themeIcon').className = 'bi bi-sun-fill';
        }

        function scrollToBottom() { chatBox.scrollTop = chatBox.scrollHeight; }
        function toggleEmojiPicker() { emojiPicker.style.display = emojiPicker.style.display === 'grid' ? 'none' : 'grid'; }
        function insertEmoji(emoji) { promptInput.value += emoji; promptInput.focus(); }

        window.addEventListener('click', function(e) {
            if (!e.target.closest('#emojiPicker') && !e.target.closest('.bi-emoji-smile')) {
                emojiPicker.style.display = 'none';
            }
        });

        function bacaTeks(text) {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel(); 
                const kalimat = new SpeechSynthesisUtterance(text);
                kalimat.lang = 'id-ID';

                const style = voiceStyleSelect.value;
                if (style === 'imut') {
                    kalimat.pitch = 1.5;
                    kalimat.rate = 1.15;
                } else if (style === 'semangat') {
                    kalimat.pitch = 1.2; 
                    kalimat.rate = 1.3;
                } else if (style === 'cool') {
                    kalimat.pitch = 0.7;
                    kalimat.rate = 0.9;
                } else {
                    kalimat.pitch = 1.0;
                    kalimat.rate = 1.0;
                }

                window.speechSynthesis.speak(kalimat);
            } else {
                alert("Browser tidak mendukung suara.");
            }
        }

        function handleImageSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 3 * 1024 * 1024) { alert("Maksimal 3MB."); return; }
            selectedImageMime = file.type;
            const reader = new FileReader();
            reader.onload = function(e) {
                selectedImageBase64 = e.target.result.split(',')[1];
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('previewName').textContent = file.name.substring(0, 10) + '...';
                document.getElementById('filePreviewContainer').style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }

        function removeImage() {
            selectedImageBase64 = ""; selectedImageMime = "";
            document.getElementById('imageInput').value = "";
            document.getElementById('filePreviewContainer').style.display = 'none';
        }

        document.addEventListener("DOMContentLoaded", loadChatHistory);

        function saveMessageToStorage(sender, text, imgData = null, liked = false) {
            let history = JSON.parse(localStorage.getItem('ai_chat_fast_voice')) || [];
            history.push({ sender, text, img: imgData, liked });
            localStorage.setItem('ai_chat_fast_voice', JSON.stringify(history));
        }

        function updateLikeInStorage(index, likedStatus) {
            let history = JSON.parse(localStorage.getItem('ai_chat_fast_voice')) || [];
            if (history[index]) { history[index].liked = likedStatus; localStorage.setItem('ai_chat_fast_voice', JSON.stringify(history)); }
        }

        function loadChatHistory() {
            let history = JSON.parse(localStorage.getItem('ai_chat_fast_voice')) || [];
            if (history.length === 0) {
                addMessageToDOM('ai', 'Halo bestie! Kode udah dipisah pakai config.php jadi aman buat di-push ke GitHub. ✨🌸', false, 0);
            } else {
                history.forEach((item, index) => addMessageToDOM(item.sender, item.text, item.img, item.liked, index));
            }
        }

        function clearHistory() {
            if (confirm("Hapus riwayat?")) {
                localStorage.removeItem('ai_chat_fast_voice');
                chatBox.innerHTML = '';
                addMessageToDOM('ai', 'Riwayat dibersihkan! 🌸', false, 0);
            }
        }

        function addMessageToDOM(sender, text, imgData = null, liked = false, msgIndex = null) {
            const bubble = document.createElement('div');
            if (sender === 'user') {
                bubble.className = 'bubble bubble-user';
                if (imgData) {
                    const imgEl = document.createElement('img');
                    imgEl.src = imgData; imgEl.className = 'chat-img-preview'; bubble.appendChild(imgEl);
                }
                if (text) { const t = document.createElement('div'); t.textContent = text; bubble.appendChild(t); }
            } else {
                bubble.className = 'bubble bubble-ai';
                const textContainer = document.createElement('div');
                textContainer.innerHTML = text.replace(/\n/g, '<br>');
                bubble.appendChild(textContainer);

                const footer = document.createElement('div');
                footer.className = 'bubble-footer';

                const speakerBtn = document.createElement('button');
                speakerBtn.className = 'btn-speaker';
                speakerBtn.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
                speakerBtn.onclick = () => bacaTeks(text);
                footer.appendChild(speakerBtn);

                const reactBtn = document.createElement('button');
                reactBtn.className = 'btn-react ' + (liked ? 'liked' : '');
                reactBtn.innerHTML = liked ? '<i class="bi bi-heart-fill"></i>' : '<i class="bi bi-heart"></i>';
                let currentIdx = msgIndex !== null ? msgIndex : chatBox.children.length;
                reactBtn.onclick = () => {
                    const isLiked = reactBtn.classList.toggle('liked');
                    reactBtn.innerHTML = isLiked ? '<i class="bi bi-heart-fill"></i>' : '<i class="bi bi-heart"></i>';
                    updateLikeInStorage(currentIdx, isLiked);
                };
                footer.appendChild(reactBtn);
                bubble.appendChild(footer);
            }
            chatBox.appendChild(bubble);
            scrollToBottom();
        }

        btnSend.addEventListener('click', sendMessage);
        promptInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        function sendMessage() {
            const text = promptInput.value.trim();
            const currentImgBase64 = selectedImageBase64;
            const currentImgMime = selectedImageMime;
            const currentImgDataUrl = currentImgBase64 ? `data:${currentImgMime};base64,${currentImgBase64}` : null;

            if (text === "" && !currentImgBase64) return;

            playSendSound();

            const historyLength = JSON.parse(localStorage.getItem('ai_chat_fast_voice'))?.length || 0;
            addMessageToDOM('user', text, currentImgDataUrl, false, historyLength);
            saveMessageToStorage('user', text, currentImgDataUrl, false);
            
            promptInput.value = ''; removeImage(); emojiPicker.style.display = 'none';
            onlineStatus.textContent = "● sedang mengetik..."; typingIndicator.style.display = 'block';

            const formData = new URLSearchParams();
            formData.append('ajax', '1');
            formData.append('prompt', text);
            formData.append('image_base64', currentImgBase64);
            formData.append('image_mime', currentImgMime);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                typingIndicator.style.display = 'none'; onlineStatus.textContent = "● Online";
                if (data.status === 'success') {
                    const newLen = JSON.parse(localStorage.getItem('ai_chat_fast_voice'))?.length || 0;
                    addMessageToDOM('ai', data.message, null, false, newLen);
                    saveMessageToStorage('ai', data.message, null, false);
                } else { alert("Error: " + data.message); }
            })
            .catch(() => {
                typingIndicator.style.display = 'none'; onlineStatus.textContent = "● Online";
                alert("Masalah jaringan.");
            });
        }
    </script>
</body>
</html>