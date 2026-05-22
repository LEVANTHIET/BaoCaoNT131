<?php
// admin.php - File xử lý Logic (Controller) - Đã bổ sung bóc tách Gói cước & Băng thông
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Đặt múi giờ Việt Nam

// 1. XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// 2. THÔNG TIN TÀI KHOẢN ADMIN
$admin_email_correct = "admin@uit.edu.vn";
$admin_pass_correct = "admin";
$login_error = "";

// 3. XỬ LÝ ĐĂNG NHẬP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_email']) && isset($_POST['admin_pass'])) {
    if ($_POST['admin_email'] === $admin_email_correct && $_POST['admin_pass'] === $admin_pass_correct) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $login_error = "Thông tin đăng nhập không chính xác!";
    }
}

// 4. XỬ LÝ API (BACKEND CHO BẢNG ĐIỀU KHIỂN)
if (isset($_SESSION['admin_logged_in'])) {
    
    // API CẢI TIẾN: Lấy dữ liệu Real-time trực tiếp từ bảng access_history của MySQL
    if (isset($_GET['api']) && $_GET['api'] == 'get_logs') {
        header('Content-Type: application/json');
        
        // Cấu hình kết nối DB nội bộ cho trang Admin
        $db_host = '127.0.0.1;port=3306';
        $db_name = 'mydb';
        $db_user = 'root';
        $db_pass = '';
        
        $clients = [];
        
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Lấy danh sách lịch sử mới nhất xếp lên đầu
            $stmt = $pdo->query("SELECT * FROM access_history ORDER BY login_time DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $package = strtolower($row['package']);
                $status = $row['status'];
                // Quy đổi thông số Giới hạn thiết bị (Quota)
                if ($status === 'Success') {
                    $quota_info = ($package === 'premium') ? "Tối đa 2 thiết bị ✨" : "Tối đa 1 thiết bị";
                    $display_status = "Authorized";
                } else {
                    $quota_info = "Bị chặn Tường lửa ❌";
                    $display_status = "Denied (" . ($row['error_message'] ?? 'Sai mã') . ")";
                }

                $clients[] = [
                    'time'      => $row['login_time'],
                    'user'      => $row['username'],
                    'mac'       => $row['mac_address'],
                    'package'   => ucfirst($package), 
                    'quota'     => $quota_info,
                    'status'    => $display_status
                ];
            }
        } catch (PDOException $e) {
            $clients = [];
        }
        
        echo json_encode($clients);
        exit();
    }

    // API: Thực thi lệnh Router (Chặn / Cho phép)
    if (isset($_GET['api']) && $_GET['api'] == 'execute') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        $mac = isset($data['mac']) ? trim($data['mac']) : '';

        $mac = str_replace(["IP/MAC: ", "MAC: ", "IP: "], "", $mac);
        $mac = trim($mac);

        if (empty($mac)) {
            echo json_encode(['success' => false, 'message' => "Lỗi: Không tìm thấy định danh thiết bị!"]);
            exit();
        }

        $command = ($action === 'block') ? "ndsctl deauth $mac" : "ndsctl auth $mac";
        
        $router_ip = '192.168.1.1';
        $ssh_user = 'root';
        $ssh_pass = 'admin'; 

        if (function_exists('ssh2_connect')) {
            $connection = @ssh2_connect($router_ip, 22);
            if ($connection && @ssh2_auth_password($connection, $ssh_user, $ssh_pass)) {
                @ssh2_exec($connection, $command);
                echo json_encode(['success' => true, 'message' => "Lệnh điều khiển đã được Router thực thi thành công!"]);
            } else {
                echo json_encode(['success' => false, 'message' => "Lỗi: Không thể kết nối SSH vào Router!"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => "Lỗi: Máy chủ XAMPP chưa cài thư viện SSH2!"]);
        }
        exit();
    }
}

// 5. GỌI FILE GIAO DIỆN (VIEW)
require_once 'admin_view.php';
?>