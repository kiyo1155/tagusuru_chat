<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
if(!isLoggedIn()){ redirect('login.php'); }

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'];
$company_id = $_SESSION['company_id'] ?? null;

// ログアウト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    redirect('login.php');
}

// 案件取得
if($user_role === 'vendor' && $company_id){
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE vendor_company_id=? ORDER BY created_at DESC");
    $stmt->execute([$company_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if(!$rows) $rows = [];

$company_name = '';
if (!empty($_SESSION['company_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $company_name = $stmt->fetchColumn() ?: '';
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ダッシュボード</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{font-family:Arial,sans-serif;background:#f9f9f9;margin:0;}
.header{display:flex;justify-content:space-between;align-items:center;padding:10px 24px;background:#000;color:#fff;}
.header img{width:120px;height:120px;object-fit:contain;}
.header .userinfo{display:flex;align-items:center;gap:12px;}
.header .logout-btn{background:#fff;color:#000;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;}
/* --- レスポンシブ対応 --- */
@media (max-width: 600px) {
    .header {
        flex-direction: row;      /* 横並び */
        align-items: center;      /* 縦位置を中央揃え */
        gap: 8px;
    }
    .header img {
        width: 60px;              /* さらに小さめにしてバランス取り */
        height: 60px;
    }
    .userinfo {
        display: flex;
        align-items: center;
        gap: 8px;                 /* ログアウトボタンと名前の間隔 */
    }
    .userinfo form {
        margin: 0;
    }
    .logout-btn {
        padding: 4px 8px;
        font-size: 0.8rem;
        white-space: nowrap;      /* 改行しない */
    }
    .userinfo span {
        display: flex;
        flex-direction: column;   /* 会社名と名前を縦並び */
        font-size: 0.85rem;
        line-height: 1.2;
    }
}

.contentbox{max-width:900px;margin:40px auto 0;padding:0 16px;}
#searchContainer{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
#searchStatus{flex:1;}
#addProjectBtn{background:#000;color:#fff;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;}
.card{margin-bottom:16px;cursor:pointer;}
.card-body{display:flex;justify-content:space-between;align-items:center;}
.status{font-weight:700;}
.modal-header,.modal-footer{display:flex;justify-content:space-between;align-items:center;}
.modal-footer{justify-content:flex-end;gap:8px;}
.footer{background:#000;color:#fff;text-align:center;padding:20px 5vw;border-top:1px solid #222;}
.footertext{margin:0;font-size:.9rem;color:#ccc;}
#chatPopup{display:none;position:fixed;right:20px;bottom:20px;width:300px;height:400px;background:#fff;border:1px solid #ccc;box-shadow:0 2px 10px rgba(0,0,0,0.2);z-index:1000;flex-direction:column;}
#chatHeader{background:#000;color:#fff;padding:6px 12px;display:flex;justify-content:space-between;align-items:center;}
#chatBody{flex:1;padding:8px;overflow-y:auto;background:#f1f1f1;}
#chatInputContainer{display:flex;}
#chatInput{flex:1;padding:6px;border:1px solid #ccc;border-radius:4px 0 0 4px;}
#chatSendBtn{padding:6px 12px;border:none;background:#000;color:#fff;border-radius:0 4px 4px 0;cursor:pointer;}
.pagination{margin-top:12px;text-align:center;}
.pagination button{margin:0 2px;}
.pagination button.active{font-weight:bold;}
</style>
</head>
<body>

<div class="header">
    <img src="logo.png" alt="タグスルのロゴ">
    <div class="userinfo">
        <form action="" method="post">
            <button type="submit" name="logout" class="logout-btn">ログアウト</button>
        </form>
        <span>
            <?= htmlspecialchars($company_name) ?><br>
            <?= htmlspecialchars($user_name) ?>さん
        </span>
    </div>
</div>


<div class="contentbox">
    <div id="searchContainer">
        <input type="text" id="searchStatus" class="form-control" placeholder="進捗状況で検索（依頼中/制作中/発送済/納品済）">
        <?php if ($user_role !== 'vendor'): ?>
        <button id="addProjectBtn">＋</button>
        <?php endif; ?>
    </div>

    <div id="projectList"></div>
    <div class="pagination" id="pagination"></div>
</div>

<!-- モーダル -->
<div class="modal" tabindex="-1" id="projectModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">案件フォーム</h5>
        <button type="button" class="btn-close" id="modalCloseBtn"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="projectIdInput" value="">
        <input type="text" class="form-control mb-2" id="projectNameInput" placeholder="案件名">
        <?php if($user_role !== 'vendor'): ?>
    <label for="vendorCompanySelect">受注者会社</label>
    <select id="vendorCompanySelect" class="form-control mb-2">
        <option value="">選択してください</option>
        <?php
        // vendor 役割のユーザーが所属する会社一覧
        $stmt = $pdo->query("SELECT DISTINCT c.id, c.name FROM companies c 
                             JOIN users u ON u.company_id=c.id 
                             WHERE u.role='vendor' ORDER BY c.name");
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($vendors as $v){
            echo "<option value='{$v['id']}'>".htmlspecialchars($v['name'])."</option>";
        }
        ?>
        </select>
        <?php endif; ?>
        <input type="date" class="form-control mb-2" id="deadlineInput" placeholder="納期">
        <input type="text" class="form-control mb-2" id="deliveryInput" placeholder="納品場所">
        <input type="text" class="form-control mb-2" id="dataPathInput" placeholder="データ格納場所">
        <select id="statusSelect" class="form-control mb-2" disabled>
            <option value="保存中">保存中</option>
            <option value="依頼中">依頼中</option>
            <option value="制作中">制作中</option>
            <option value="発送済">発送済</option>
            <option value="納品済">納品済</option>
        </select>
      </div>
    <div class="modal-footer">
         <button class="btn btn-secondary" id="tempSaveBtn">一時保存</button> 
         <button class="btn btn-warning" id="submitBtn">依頼</button> 
         <button class="btn btn-primary" id="chatBtn">チャット開始</button> 
         <button class="btn btn-secondary" id="copyBtn">コピー</button> 
         <button class="btn btn-danger" id="deleteBtn">削除</button> 
    </div>
    </div>
  </div>
</div>

<!-- チャットポップ -->
<div id="chatPopup">
    <div id="chatHeader">
        <span id="chatTitle">チャット</span>
        <button id="closeChatBtn" class="btn btn-sm btn-light">×</button>
    </div>
    <div id="chatBody"></div>
    <div id="chatInputContainer">
        <input type="text" id="chatInput" placeholder="メッセージを入力">
        <button id="chatSendBtn">送信</button>
    </div>
</div>

<div class="footer">
    <p class="footertext">copyrights 2026 TAGUSURU All Rights Reserved.</p>
</div>

<script>
let projects = <?= json_encode($rows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>;
function escapeHTML(str) {
    if(!str) return '';
    return str.replace(/[&<>"']/g, m => ({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#39;'
    }[m]));
}


document.addEventListener('DOMContentLoaded', ()=>{

    const modal = document.getElementById('projectModal');
    const addBtn = document.getElementById('addProjectBtn'); // client のみ存在
    const closeBtn = document.getElementById('modalCloseBtn');
    const projectList = document.getElementById('projectList');
    const searchInput = document.getElementById('searchStatus');
    const projectIdInput = document.getElementById('projectIdInput');
    const projectNameInput = document.getElementById('projectNameInput');
    const companyInput = document.getElementById('vendorCompanySelect');
    const deadlineInput = document.getElementById('deadlineInput');
    const deliveryInput = document.getElementById('deliveryInput');
    const dataPathInput = document.getElementById('dataPathInput');
    const statusSelect = document.getElementById('statusSelect');
    const submitBtn = document.getElementById('submitBtn');
    const tempSaveBtn = document.getElementById('tempSaveBtn');
    const chatBtn = document.getElementById('chatBtn');
    const copyBtn = document.getElementById('copyBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const chatPopup = document.getElementById('chatPopup');
    const chatTitle = document.getElementById('chatTitle');
    const closeChatBtn = document.getElementById('closeChatBtn');
    const chatBody = document.getElementById('chatBody');
    const chatInput = document.getElementById('chatInput');
    const chatSendBtn = document.getElementById('chatSendBtn');
    const pagination = document.getElementById('pagination');
    const currentUserId = <?= json_encode($user_id) ?>;

    const userRole = <?= json_encode($user_role) ?>; // 'client' or 'vendor'
    const vendorEditableStatuses = ['制作中','発送済','納品済'];
    let currentPage = 1;
    const perPage = 10;
    let currentProjectId = null;
    let editingChatId = null;

    // --- 案件リスト描画 ---
    function renderList(){
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        const pageData = projects.slice(start, end);
        projectList.innerHTML = '';
        pageData.forEach(p=>{
            const div = document.createElement('div');
            div.className = 'card';
            div.dataset.id = p.id;
            div.dataset.status = p.status;
         
        const badgeHTML = p.new ? '<span class="new-badge" style="color:red;margin-left:8px;">新着あり</span>' : '';
        div.innerHTML = `
            <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <span>${escapeHTML(p.name)}</span>
                    ${badgeHTML}
                </div>
                <span class="status">${p.status}</span>
            </div>
        `;
        projectList.appendChild(div);
    });
    renderPagination();
}

    function renderPagination(){
        const totalPages = Math.ceil(projects.length / perPage);
        pagination.innerHTML = '';
        for(let i=1; i<=totalPages; i++){
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.type = "button";
            if(i === currentPage) btn.classList.add('active');
            btn.addEventListener('click', ()=>{currentPage=i; renderList();});
            pagination.appendChild(btn);
        }
    }

    renderList();

    // --- 新規案件作成（clientのみ） ---
    if(addBtn){
        addBtn.addEventListener('click', ()=>{
            currentProjectId = null;
            projectIdInput.value = '';
            projectNameInput.value = '';
            if(companyInput) companyInput.value = '';
            deadlineInput.value = '';
            deliveryInput.value = '';
            dataPathInput.value = '';
            statusSelect.value = '保存中';
            submitBtn.style.display='inline-block';
            tempSaveBtn.style.display='inline-block';
            chatBtn.style.display='none';
            copyBtn.style.display='none';
            deleteBtn.style.display='none';
            statusSelect.disabled = true; // clientはステータス変更不可
            modal.style.display='block';
        });
    }

    closeBtn.addEventListener('click', ()=>{ modal.style.display='none'; });
    window.addEventListener('click', e=>{ if(e.target==modal) modal.style.display='none'; });

    // --- 案件クリック ---
    projectList.addEventListener('click', e=>{
        const card = e.target.closest('.card');
        if(!card) return;
        const p = projects.find(pr=>pr.id==card.dataset.id);
        if(!p) return;

        currentProjectId = p.id;
        projectIdInput.value = p.id;
        projectNameInput.value = p.name;
        if(companyInput) companyInput.value = p.vendor_company_id || '';
        deadlineInput.value = p.deadline;
        deliveryInput.value = p.delivery;
        dataPathInput.value = p.data_path;
        statusSelect.value = p.status;

        tempSaveBtn.style.display='inline-block';
        chatBtn.style.display='inline-block';
        copyBtn.style.display='inline-block';
        deleteBtn.style.display='inline-block';
        submitBtn.style.display = (userRole==='client' && p.status==='保存中')?'inline-block':'none';


          // vendorならコピー非表示
        if(userRole === 'vendor'){
            if(tempSaveBtn) tempSaveBtn.style.display = 'none';
            if(copyBtn) copyBtn.style.display = 'none';
        } else {
            if(tempSaveBtn) tempSaveBtn.style.display = 'inline-block';
            if(copyBtn) copyBtn.style.display = 'inline-block';
        }

        // vendorならステータス選択肢を制御
        if(userRole==='vendor'){
        const vendorEditableStatuses = ['制作中','発送済','納品済'];
        [...statusSelect.options].forEach(opt => {
            // vendorが設定可能なステータスだけ有効にする
            opt.disabled = !vendorEditableStatuses.includes(opt.value);
        });
        statusSelect.disabled = false; // select自体は有効
    } else {
        statusSelect.disabled = true; // clientは変更不可
    }

    modal.style.display = 'block';
});


    // --- 保存処理 ---
function saveProject(status){
    // clientが依頼済の場合、statusを上書きしない
    let saveStatus = status;
    if(userRole==='client' && currentProjectId){
        const currentProject = projects.find(pr=>pr.id==currentProjectId);
        if(currentProject && currentProject.status !== '保存中'){
            saveStatus = currentProject.status; // 依頼済ならステータスは変更しない
        }
    }

    const data = new FormData();
    data.append('id', projectIdInput.value || '');
    data.append('name', projectNameInput.value);
    data.append('status', saveStatus);
    if(companyInput) data.append('vendor_company_id', companyInput.value);
    data.append('deadline', deadlineInput.value);
    data.append('delivery_location', deliveryInput.value);
    data.append('data_path', dataPathInput.value);

    fetch('save.php', { method:'POST', body:data })
    .then(r => r.json())
    .then(res => {
        if(res.success){
            if(projectIdInput.value){ // 既存案件更新
                const idx = projects.findIndex(pr => pr.id == projectIdInput.value);
                if(idx >= 0){
                    projects[idx] = {
                        ...projects[idx],
                        status: saveStatus,
                        name: projectNameInput.value,
                        vendor_company_id: companyInput ? companyInput.value : '',
                        deadline: deadlineInput.value,
                        delivery: deliveryInput.value,
                        data_path: dataPathInput.value
                    };
                }
            } else { // client の新規案件作成
                const newProject = {
                    id: res.id,
                    name: projectNameInput.value,
                    status: saveStatus,
                    vendor_company_id: companyInput ? companyInput.value : '',
                    deadline: deadlineInput.value,
                    delivery: deliveryInput.value,
                    data_path: dataPathInput.value
                };
                projects.unshift(newProject);
                projectIdInput.value = res.id;
                currentProjectId = res.id;
            }
            renderList();
            modal.style.display = 'none';
        } else {
            alert('保存に失敗しました: ' + (res.error || '不明なエラー'));
        }
    }).catch(err => {
        console.error(err);
        alert('通信エラーが発生しました');
    });
}

// --- 一時保存ボタン ---
tempSaveBtn.addEventListener('click', () => {
    // vendor は新規案件作成不可
    if(userRole === 'vendor' && !currentProjectId){
        alert('新規案件はベンダー側で作成できません');
        return;
    }
    saveProject('保存中');
});


    submitBtn.addEventListener('click', ()=>{ saveProject('依頼中'); });

    // --- 削除 ---
    deleteBtn.addEventListener('click', ()=>{
        if(!projectIdInput.value) return;
        if(!confirm('本当に削除しますか？')) return;
        fetch('delete.php', { method:'POST', body:new URLSearchParams({id: projectIdInput.value}) })
        .then(r=>r.json())
        .then(res=>{
            if(res.success){
                projects = projects.filter(p=>p.id != projectIdInput.value);
                renderList();
                modal.style.display='none';
            } else { alert('削除に失敗しました'); }
        }).catch(err=>{ console.error(err); alert('通信エラーが発生しました'); });
    });

    // --- コピー ---
    copyBtn.addEventListener('click', ()=>{
        if(!projectIdInput.value) return;
        projectIdInput.value = '';
        submitBtn.style.display='inline-block';
        alert('案件をコピーしました。必要に応じて編集して保存してください。');
    });

    // --- ステータス変更 ---
    statusSelect.addEventListener('change', ()=>{
        if(userRole==='vendor' && !vendorEditableStatuses.includes(statusSelect.value)){
            alert('このステータスは変更できません');
            const currentProject = projects.find(pr=>pr.id==currentProjectId);
            statusSelect.value = currentProject.status;
            return;
        }
        if(userRole==='client'){
            alert('クライアントはステータスを変更できません');
            const currentProject = projects.find(pr=>pr.id==currentProjectId);
            statusSelect.value = currentProject.status;
            return;
        }
        if(projectIdInput.value){
            saveProject(statusSelect.value);
        }
    });

    // --- チャット ---
    chatBtn.addEventListener('click', ()=>{
        currentProjectId = projectIdInput.value;
        editingChatId = null;
        chatPopup.style.display='flex';
        const name = projectNameInput.value.trim() || '案件';
        chatTitle.textContent = `チャット_${name}`;
        chatBody.innerHTML = '';
        loadChats(currentProjectId);
    });

    chatSendBtn.addEventListener('click', ()=>{
        const msg = chatInput.value.trim();
        if(!msg || !currentProjectId) return;

        const formData = new FormData();
        formData.append('project_id', currentProjectId);
        formData.append('message', msg);
        if(editingChatId) formData.append('edit_id', editingChatId);

        fetch('chat_save.php', { method:'POST', body: formData })
        .then(r=>r.json())
        .then(res=>{
            if(res.success){
                chatInput.value = '';
                editingChatId = null;
                loadChats(currentProjectId);
            } else { alert(res.message || '送信に失敗しました'); }
        }).catch(err=>{ console.error(err); alert('通信エラー'); });
    });

    closeChatBtn.addEventListener('click', ()=>{
        chatPopup.style.display='none';
        chatBody.innerHTML='';
        currentProjectId=null;
        editingChatId=null;
    });

    function loadChats(projectId){
        fetch(`chat_load.php?project_id=${projectId}`)
        .then(r=>r.json())
        .then(chats=>{
            chatBody.innerHTML='';
            chats.forEach(c=>{
                const p = document.createElement('p');
                p.dataset.chatId = c.id;
                const editedText = c.edited ? '（修正済）' : '';
                p.innerHTML = `<strong>${escapeHTML(c.user_name)}:</strong> ${escapeHTML(c.message)} ${editedText}`;
                if(c.user_id == currentUserId){
                    const editBtn = document.createElement('button');
                    editBtn.textContent='編集';
                    editBtn.style.marginLeft='8px';
                    editBtn.addEventListener('click', ()=>{
                        chatInput.value = c.message;
                        editingChatId = c.id;
                        chatInput.focus();
                    });
                    p.appendChild(editBtn);
                }
                chatBody.appendChild(p);
            });
            chatBody.scrollTop = chatBody.scrollHeight;
        });
    }

    // --- 検索 ---
    searchInput.addEventListener('input', ()=>{
        const filter = searchInput.value.trim();
        document.querySelectorAll('#projectList .card').forEach(card=>{
            card.style.display = card.dataset.status.includes(filter) ? '' : 'none';
        });
    });

});

function checkUpdates(){
    fetch('check_updates.php')
    .then(r=>r.json())
    .then(updates=>{
        updates.forEach(u=>{
            const card = document.querySelector(`.card[data-id="${u.project_id}"]`);
            if(card && !card.querySelector('.new-badge')){
                const badge = document.createElement('span');
                badge.className = 'new-badge';
                badge.textContent = '新着あり';
                badge.style.color = 'red';
                badge.style.marginLeft = '8px';
                card.querySelector('.status').after(badge);
            }
        });
    });
}

// 5秒おきにチェック
setInterval(checkUpdates, 5000);


projectList.addEventListener('click', e=>{
    const card = e.target.closest('.card');
    if(card) card.querySelector('.new-badge')?.remove();
});

chatBtn.addEventListener('click', ()=>{
    const card = document.querySelector(`.card[data-id="${currentProjectId}"]`);
    card?.querySelector('.new-badge')?.remove();
});



</script>

</body>
</html>

