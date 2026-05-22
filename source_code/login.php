<?php
// Bật session để thực hiện tính năng Session Lock (Khóa trùng luồng)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ================= CẤU HÌNH HỆ THỐNG =================
$db_host = '127.0.0.1;port=3306';
$db_name = 'mydb';
$db_user = 'root';      
$db_pass = '';          

$router_ip = '192.168.1.1';
$ssh_user  = 'root';
$ssh_pass  = 'admin'; 
// =====================================================

$tok = $_POST['tok'] ?? $_GET['tok'] ?? '';
$redir = $_POST['redir'] ?? $_GET['redir'] ?? 'http://google.com';

// 1. Phân giải danh tính ban đầu
$client_id = $_GET['clientmac'] ?? $_POST['client_id'] ?? '';
if (empty($client_id) && !empty($_GET['authaction'])) {
    if (preg_match('/clientmac=([a-fA-F0-9:-]+)/i', $_GET['authaction'], $matches)) {
        $client_id = strtolower($matches[1]);
    } elseif (preg_match('/clientip=([0-9\.]+)/', $_GET['authaction'], $matches)) {
        $client_id = $matches[1];
    }
}

$error = "";
$is_valid_portal = (!empty($tok) && !empty($client_id));

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ---- LOGIC 1: SESSION LOCK (Chống Race Condition) ----
    if (isset($_SESSION['is_processing']) && $_SESSION['is_processing'] === true) {
        exit("Hệ thống đang xử lý yêu cầu trước của bạn, vui lòng đợi...");
    }

    if (!$is_valid_portal) {
        $error = "Lỗi bảo mật: Không nhận diện được thiết bị!";
    } else {
        $password = trim($_POST['passcode'] ?? ''); 

        if (empty($password)) {
            $error = "Vui lòng nhập Mã truy cập.";
        } else {
            $_SESSION['is_processing'] = true;

            try {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // TỰ ĐỘNG XÓA LỊCH SỬ CŨ HƠN 3 NGÀY
                $delete_stmt = $pdo->prepare("DELETE FROM access_history WHERE login_time < NOW() - INTERVAL 3 DAY");
                $delete_stmt->execute();

                // Kiểm tra mã voucher
                $stmt = $pdo->prepare("SELECT * FROM users WHERE password = ? LIMIT 1");
                $stmt->execute([$password]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $username = $user['username']; 
                    $package = strtolower($user['package']); 
                    $real_mac = $client_id;
                    $connection = null;

                    // Kết nối SSH lấy MAC thật
                    if (function_exists('ssh2_connect')) {
                        $connection = @ssh2_connect($router_ip, 22);
                        if ($connection && @ssh2_auth_password($connection, $ssh_user, $ssh_pass)) {
                            if (filter_var($real_mac, FILTER_VALIDATE_IP)) {
                                $stream = @ssh2_exec($connection, "ndsctl status | grep -i '$real_mac'");
                                stream_set_blocking($stream, true);
                                $status_out = stream_get_contents($stream);
                                if (preg_match('/MAC:\s*([a-fA-F0-9:]+)/i', $status_out, $mac_matches)) {
                                    $real_mac = strtolower(trim($mac_matches[1]));
                                } else {
                                    $stream2 = @ssh2_exec($connection, "cat /proc/net/arp | grep -i '$real_mac'");
                                    stream_set_blocking($stream2, true);
                                    $arp_out = stream_get_contents($stream2);
                                    if (preg_match('/([a-fA-F0-9]{2}[:\-]){5}[a-fA-F0-9]{2}/i', $arp_out, $arp_matches)) {
                                        $real_mac = strtolower(trim($arp_matches[0]));
                                    }
                                }
                            }
                        }
                    }

                    // ĐỐI CHIẾU CHÉO ROUTER
                    $full_status = "";
                    if ($connection) {
                        $stream_status = @ssh2_exec($connection, "ndsctl status");
                        stream_set_blocking($stream_status, true);
                        $full_status = stream_get_contents($stream_status);
                    }

                    // LOGIC QUOTA & DỌN DẸP RÁC
                    $max_devices = ($package === 'premium') ? 2 : 1;
                    $can_connect = false;

                    $current_macs_str = $user['current_mac'] ?? '';
                    $active_macs = array_filter(explode(',', $current_macs_str));
                    $real_active_macs = [];

                    if (!empty($active_macs)) {
                        foreach ($active_macs as $mac_in_db) {
                            $mac_pos = stripos($full_status, $mac_in_db);
                            if ($mac_pos !== false) {
                                $client_block = substr($full_status, $mac_pos, 250);
                                if (stripos($client_block, 'State: Authenticated') !== false) {
                                    $real_active_macs[] = $mac_in_db; 
                                }
                            }
                        }
                    }

                    $active_macs = $real_active_macs;

                    // KIỂM TRA ĐIỀU KIỆN
                    if (in_array($real_mac, $active_macs)) {
                        $can_connect = true; 
                    } else {
                        if (count($active_macs) >= $max_devices) {
                            // BÁO LỖI QUOTA 1 LẦN DUY NHẤT Ở ĐÂY
                            $error = "Tài khoản đã đạt giới hạn ($max_devices thiết bị). Vui lòng dùng thiết bị cũ!";
                            $log_stmt = $pdo->prepare("INSERT INTO access_history (username, mac_address, package, status, error_message) VALUES (?, ?, ?, 'Failed', ?)");
                            $log_stmt->execute([$username, $real_mac, $package, $error]);
                        } else {
                            $can_connect = true;
                            array_push($active_macs, $real_mac); 
                        }
                    }

                    // CẤP QUYỀN
                    if ($can_connect) {
                        $ssh_success = false;
                        if ($connection) {
                            $auth_stream = @ssh2_exec($connection, "ndsctl auth $real_mac");
                            if ($auth_stream) {
                                $ssh_success = true;
                            }
                        }

                        if ($ssh_success || !$connection) {
                            $new_macs_str = implode(',', $active_macs);
                            $update_stmt = $pdo->prepare("UPDATE users SET current_mac = ?, last_login = NOW() WHERE id = ?");
                            $update_stmt->execute([$new_macs_str, $user['id']]);

                            $log_stmt = $pdo->prepare("INSERT INTO access_history (username, mac_address, package, status) VALUES (?, ?, ?, 'Success')");
                            $log_stmt->execute([$username, $real_mac, $package]);

                            unset($_SESSION['is_processing']);
                            header("Location: $redir");
                            exit();
                        } else {
                            $error = "Lỗi kết nối phần cứng Router. Vui lòng thử lại!";
                            $log_stmt = $pdo->prepare("INSERT INTO access_history (username, mac_address, package, status, error_message) VALUES (?, ?, ?, 'Failed', ?)");
                            $log_stmt->execute([$username, $real_mac, $package, $error]);
                        }
                    }
                } else {
                    // TRƯỜNG HỢP SAI VOUCHER CHÍNH THỨC NẰM NGOÀI CÙNG Ở ĐÂY
                    $error = "Mã truy cập không hợp lệ!";
                    $log_stmt = $pdo->prepare("INSERT INTO access_history (username, mac_address, package, status, error_message) VALUES ('Unknown', ?, 'None', 'Failed', ?)");
                    $log_stmt->execute([$client_id, $error]);
                }
            } catch (PDOException $e) {
                $error = "Lỗi hệ thống: " . $e->getMessage();
            }

            unset($_SESSION['is_processing']);
        }
    }
}

require_once 'login_view.php';
?>