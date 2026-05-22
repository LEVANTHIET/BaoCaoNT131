<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập hệ thống Wi-Fi</title>
    <script src="tailwind.js"></script>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { 
        font-family: 'Inter', sans-serif; 
        background-image: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.85)), url('images/background.jpg');
        background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;
    }
    .glass-card { background: rgba(24, 24, 27, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(63, 63, 70, 0.5); }
    </style>
</head>
<body class="bg-[#0a0a0a] text-zinc-50 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-[400px] space-y-6">
        <div class="flex justify-center">
            <div class="bg-zinc-800 p-2 rounded-lg border border-zinc-700 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-zinc-200"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
        </div>

        <div class="glass-card rounded-xl p-8 shadow-2xl w-full max-w-[400px]">
            <div class="text-center space-y-2 mb-6">
                <h1 class="text-2xl font-semibold tracking-tight text-white">Kết nối Wi-Fi</h1>
                <p class="text-sm text-zinc-400">Vui lòng nhập mã truy cập của bạn</p>
            </div>

            <?php if (!$is_valid_portal): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3 rounded-lg text-xs text-center mb-6 font-medium">
                    ⚠️ Lỗi: Không thể xác thực thiết bị.<br>Vui lòng kết nối bằng Wi-Fi trên điện thoại.
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="" class="space-y-4" onsubmit="return handleFormSubmit();">
                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
                <input type="hidden" name="tok" value="<?php echo htmlspecialchars($tok); ?>">
                <input type="hidden" name="redir" value="<?php echo htmlspecialchars($redir); ?>">

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-zinc-200">Mã truy cập (Voucher Code)</label>
                    </div>
                    <input type="password" name="passcode" required placeholder="Nhập mã..." class="flex h-10 w-full rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-zinc-400 transition-colors">
                </div>

                <div class="flex items-start space-x-3 pt-1 pb-1">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" required class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 accent-zinc-200 cursor-pointer">
                    </div>
                    <label for="terms" class="text-xs text-zinc-400 leading-relaxed cursor-pointer select-none">
                        Tôi đồng ý với <a href="#" class="underline hover:text-white">Điều khoản dịch vụ</a>
                    </label>
                </div>

                <?php if (!empty($error) && $is_valid_portal): ?>
                    <p class="text-xs text-red-400 font-medium bg-red-500/10 border border-red-500/20 p-2 rounded"><?php echo $error; ?></p>
                <?php endif; ?>

                <button type="submit" id="submitBtn"
                    <?php echo !$is_valid_portal ? 'disabled' : ''; ?> 
                    class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors bg-zinc-50 text-zinc-900 hover:bg-zinc-200 h-10 px-4 py-2 w-full mt-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Kết nối ngay
                </button>
            </form>
        </div>
    </div>

    <script>
    function handleFormSubmit() {
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-zinc-900 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Đang xác thực...`;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        return true;
    }
    </script>
</body>
</html>