<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống Wi-Fi</title>
    <script src="tailwind.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Animation cho Toast */
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        .toast-enter { animation: slideIn 0.3s forwards; }
        .toast-leave { animation: slideOut 0.3s forwards; }
    </style>
</head>
<body class="bg-[#0a0a0a] min-h-screen text-zinc-50">

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="w-full max-w-[400px] bg-zinc-950 border border-zinc-800 rounded-xl shadow-2xl overflow-hidden">
            <div class="p-6 md:p-8 space-y-6">
                
                <div class="flex items-start justify-between gap-2">
                    <div class="space-y-1.5">
                        <h2 class="text-lg md:text-xl font-bold tracking-tight text-white">Login to your account</h2>
                        <p class="text-sm text-zinc-400">Enter your email below to login to your account</p>
                    </div>
                    <a href="#" class="text-xs font-medium text-zinc-400 hover:text-white transition-colors whitespace-nowrap mt-1.5">
                        Sign Up
                    </a>
                </div>

                <form method="POST" action="" class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium leading-none text-zinc-200">Email</label>
                        <input type="email" name="admin_email" required placeholder="admin@uit.edu.vn" 
                            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-white placeholder:text-zinc-600 focus:outline-none focus:ring-1 focus:ring-zinc-400 transition-colors">
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium leading-none text-zinc-200">Password</label>
                            <a href="#" class="text-xs font-medium text-zinc-400 hover:text-white transition-colors">Forgot your password?</a>
                        </div>
                        <input type="password" name="admin_pass" required 
                            class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-zinc-400 transition-colors">
                    </div>

                    <?php if (!empty($login_error)): ?>
                        <p class="text-xs font-medium text-red-500 text-center"><?php echo $login_error; ?></p>
                    <?php endif; ?>

                    <div class="pt-2 space-y-3">
                        <button type="submit" 
                            class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-50 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-200 transition-colors">
                            Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="p-4 md:p-8 max-w-3xl mx-auto">
        <div class="mb-8 flex items-center justify-between border-b border-zinc-800 pb-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-white">Quản lý truy cập Wi-Fi</h1>
                <p class="text-sm text-zinc-400 mt-1">Danh sách điều khiển trạng thái kết nối các thiết bị Real-time.</p>
            </div>
            
            <a href="?logout=true" class="px-4 py-2 bg-zinc-900 text-zinc-300 hover:text-white hover:bg-zinc-800 border border-zinc-800 rounded-md text-sm transition-colors flex items-center space-x-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Đăng xuất</span>
            </a>
        </div>

        <div class="bg-zinc-950 border border-zinc-800 rounded-xl shadow-2xl overflow-visible relative">
            <div id="client-list" class="divide-y divide-zinc-800">
                <div class="p-6 text-center text-zinc-500 text-sm">Đang tải dữ liệu kết nối...</div>
            </div>

            <div class="p-4 border-t border-zinc-800 flex items-center justify-between bg-zinc-900/50 rounded-b-xl">
                <span id="page-info" class="text-xs text-zinc-400">Trang 1 / 1</span>
                <div class="flex space-x-2">
                    <button id="btn-prev" class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs rounded-md disabled:opacity-50">Trước</button>
                    <button id="btn-next" class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs rounded-md disabled:opacity-50">Sau</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 flex flex-col space-y-2 z-50"></div>

    <script>
        let allClients = [];
        let currentPage = 1;
        const itemsPerPage = 5;
        let lastLogCount = 0;

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const bgColor = type === 'error' ? 'bg-red-500/20 border-red-500/50 text-red-400' : 'bg-green-500/20 border-green-500/50 text-green-400';
            toast.className = `toast-enter flex items-center p-3 border rounded-lg shadow-lg text-sm font-medium backdrop-blur-md ${bgColor}`;
            toast.innerHTML = message;
            
            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => {
                toast.classList.replace('toast-enter', 'toast-leave');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-toggle')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
            }
        });

        async function executeAction(action, mac) {
            try {
                const response = await fetch('?api=execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, mac })
                });
                const result = await response.json();
                
                if (result.success) {
                    const actionText = action === 'block' ? 'Đã chặn (Deauth)' : 'Đã mở khóa cấp phép';
                    showToast(`✅ ${actionText} thiết bị: ${mac}`);
                    fetchData(); 
                } else {
                    showToast(result.message, 'error'); 
                }
            } catch (error) {
                showToast('❌ Lỗi kết nối đến hệ thống máy chủ.', 'error');
            }
        }

        function renderList() {
            const listContainer = document.getElementById('client-list');
            listContainer.innerHTML = '';

            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const paginatedItems = allClients.slice(start, end);

            if (paginatedItems.length === 0) {
                listContainer.innerHTML = '<div class="p-6 text-center text-zinc-500 text-sm">Chưa có thiết bị nào trong lịch sử hệ thống.</div>';
                return;
            }

            paginatedItems.forEach((client, index) => {
                const html = `
                    <div class="p-4 hover:bg-white/[0.02] transition-colors flex items-center justify-between group">
                        <div class="flex items-center space-x-4 min-w-[150px]">
                            <div class="w-10 h-10 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 group-hover:text-white transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-white">${client.user}</h3>
                                <p class="text-xs text-zinc-500">ID: ${client.mac}</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-xs text-zinc-400">${client.time}</p>
                                <p class="text-xs font-medium text-green-400">${client.status}</p>
                            </div>
                            <div class="relative">
                                <button class="dropdown-toggle text-zinc-500 hover:text-white p-1 rounded-md" onclick="document.getElementById('menu-${index}').classList.toggle('hidden'); event.stopPropagation();">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                </button>
                                
                                <div id="menu-${index}" class="dropdown-menu hidden absolute right-0 top-6 mt-1 w-48 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl z-10 p-1">
                                    <button onclick="executeAction('allow', '${client.mac}')" class="w-full text-left px-3 py-2 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white rounded-md">✅ Cho phép (Allow)</button>
                                    <button onclick="executeAction('block', '${client.mac}')" class="w-full text-left px-3 py-2 text-xs text-red-400 hover:bg-red-500/10 hover:text-red-300 rounded-md">⛔ Chặn (Block)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                listContainer.insertAdjacentHTML('beforeend', html);
            });

            const totalPages = Math.ceil(allClients.length / itemsPerPage) || 1;
            document.getElementById('page-info').innerText = `Trang ${currentPage} / ${totalPages}`;
            document.getElementById('btn-prev').disabled = currentPage === 1;
            document.getElementById('btn-next').disabled = currentPage === totalPages;
        }

        async function fetchData() {
            try {
                const res = await fetch('?api=get_logs');
                const data = await res.json();
                
                if (lastLogCount > 0 && data.length > lastLogCount) {
                    const newClient = data[0]; 
                    showToast(`🚀 Thiết bị vừa tương tác hệ thống: <b>${newClient.user}</b>`);
                }
                
                lastLogCount = data.length;
                allClients = data;
                renderList();
            } catch (err) {
                console.error("Lỗi cập nhật danh sách log:", err);
            }
        }

        document.getElementById('btn-prev').onclick = () => { if(currentPage > 1) { currentPage--; renderList(); } };
        document.getElementById('btn-next').onclick = () => { if(currentPage < Math.ceil(allClients.length / itemsPerPage)) { currentPage++; renderList(); } };

        fetchData(); 
        setInterval(fetchData, 4000); 
    </script>
<?php endif; ?>

</body>
</html>