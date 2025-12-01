<?php

session_start();
require '../db.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Chat Admin - Trả lời khách</title>
<link href="../assets/css/admin-simple.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<style>
.container { padding:20px; }
.user-list { width:260px; float:left; border-right:1px solid #eaeaea; padding-right:12px; }
.chat-panel { margin-left:280px; }
.msg { padding:8px;border-radius:8px;margin-bottom:8px; }
.msg.user { background:#f1f5f9; text-align:left; }
.msg.admin { background:#dbeafe; text-align:right; }
.message-list { max-height:60vh; overflow:auto; padding:10px; background:#fff; border:1px solid #eee; border-radius:8px; }
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <h3>Chat admin</h3>
    <div class="user-list">
        <h5>Người dùng đang chat</h5>
        <ul id="users">
            <?php
            $r = $conn->query("SELECT user_id, MAX(created_at) AS last_at FROM chat_messages GROUP BY user_id ORDER BY last_at DESC");
            while ($row = $r->fetch_assoc()) {
                $uid = htmlspecialchars($row['user_id']);
                echo "<li><button class='user-btn' data-uid=\"{$uid}\">{$uid} <small>({$row['last_at']})</small></button></li>";
            }
            ?>
        </ul>
    </div>

    <div class="chat-panel">
        <h5 id="chat-with">Chọn người dùng để trả lời</h5>
        <div class="message-list" id="messageList"></div>
        <div style="margin-top:10px;">
            <textarea id="adminMessage" rows="3" style="width:100%"></textarea>
            <button id="sendBtn" style="margin-top:6px;">Gửi trả lời</button>
        </div>
    </div>
</div>

<script>
const usersEl = document.getElementById('users');
const messageList = document.getElementById('messageList');
const adminMessage = document.getElementById('adminMessage');
const sendBtn = document.getElementById('sendBtn');
let currentUser = null;
let pollInterval = null;

function appendMsg(m) {
    const div = document.createElement('div');
    div.className = 'msg ' + (m.sender === 'admin' ? 'admin' : 'user');
    div.innerHTML = `<div>${escapeHtml(m.message)}</div><small>${m.created_at}</small>`;
    messageList.appendChild(div);
    messageList.scrollTop = messageList.scrollHeight;
}
function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

usersEl.addEventListener('click', function(e){
    const btn = e.target.closest('.user-btn');
    if (!btn) return;
    const uid = btn.dataset.uid;
    currentUser = uid;
    document.getElementById('chat-with').textContent = 'Chat với: ' + uid;
    messageList.innerHTML = '';
    loadMessages(uid);
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(()=>loadMessages(uid,true), 2000);
});

async function loadMessages(uid, onlyNew=false){
    try {
        // Only fetch all messages; admin page keeps track client-side; simple: always fetch all for selected user
        const res = await fetch('../chat_api.php?user_id=' + encodeURIComponent(uid) + '&after_id=0');
        const data = await res.json();
        if (data.success) {
            messageList.innerHTML = '';
            data.messages.forEach(m => appendMsg(m));
        }
    } catch (e) { console.error(e); }
}

sendBtn.addEventListener('click', async function(){
    if (!currentUser) { alert('Chọn người dùng'); return; }
    const text = adminMessage.value.trim();
    if (!text) return;
    // send as admin
    try {
        const res = await fetch('../chat_api.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ user_id: currentUser, message: text, sender: 'admin' })
        });
        const json = await res.json();
        if (json.success) {
            adminMessage.value = '';
            // append immediately
            appendMsg({ sender:'admin', message: text, created_at: new Date().toISOString().slice(0,19).replace('T',' ') });
        } else {
            alert('Lỗi: ' + (json.message || 'unknown'));
        }
    } catch (e) {
        console.error(e);
        alert('Lỗi gửi');
    }
});
</script>
</body>
</html>