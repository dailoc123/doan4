<?php
session_start();
require_once 'db.php';
require_once 'functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin người dùng
$stmt = $conn->prepare("SELECT phone, address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Lấy voucher để hiển thị trong tab Vouchers (tích hợp từ user_vouchers.php)
$vouchers_sql = "SELECT v.*,
       COALESCE(uc.used,0) AS times_used
    FROM vouchers v
    LEFT JOIN (
        SELECT voucher_code, COUNT(*) AS used
        FROM orders
        WHERE voucher_code IS NOT NULL
        GROUP BY voucher_code
    ) uc ON uc.voucher_code = v.code
    WHERE (v.status = 1 OR v.status IS NULL)
      AND (v.expiry_date IS NULL OR v.expiry_date >= CURDATE())
    ORDER BY v.expiry_date IS NULL ASC, v.expiry_date ASC, v.created_at DESC";

$vouchers_result = $conn->query($vouchers_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang cá nhân | Luxury Store</title>
    <link rel="stylesheet" href="css/profile.css?v=<?= time() ?>" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#0b0b0c;--card:#111214;--muted:#8a8f98;--accent:#000;--primary:#0f1115;--ring:#1f6feb;--gradient:linear-gradient(135deg,#111214 0%,#15171b 50%,#0b0b0c 100%);--brand:linear-gradient(135deg,#000 0%,#333 100%)}
        body{background:#0a0b0c;color:#e7e9ee;font-family:'Poppins',sans-serif}
        .profile-container{max-width:1100px;margin:clamp(88px,10vh,120px) auto 40px;padding:0 20px}
        .profile-header{background:var(--gradient);border-radius:18px;padding:32px;display:flex;align-items:center;gap:16px;box-shadow:0 8px 24px rgba(0,0,0,.25)}
        .profile-avatar{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#0e1013;position:relative}
        .profile-avatar i{font-size:28px;color:#cfd3da}
        .profile-avatar::after{content:'';position:absolute;inset:-3px;border-radius:50%;padding:2px;background:conic-gradient(from 180deg at 50% 50%,#1f6feb,transparent,#6e40c9);-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:x;mask-composite:exclude}
        .profile-header h1{margin:0;font-size:24px}
        .profile-email{color:var(--muted);font-size:14px}
        .profile-tabs{margin:18px 0;display:flex;gap:10px;flex-wrap:wrap}
        .tab-btn{background:#0f1115;border:1px solid #20242b;color:#cfd3da;padding:10px 14px;border-radius:999px;transition:all .2s ease}
        .tab-btn.active{background:#1a1d22;color:#fff;border-color:#2b313a;box-shadow:0 6px 20px rgba(0,0,0,.25)}
        .tab-content{background:var(--card);border:1px solid #20242b;border-radius:16px;padding:22px;display:none;box-shadow:0 6px 20px rgba(0,0,0,.2)}
        .tab-content.active{display:block;animation:fadeIn .28s ease}
        @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
        .form-group{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
        .form-group label{font-size:13px;color:#cfd3da;display:flex;align-items:center;gap:8px}
        .form-group input{background:#0d0f12;border:1px solid #1f242c;color:#e7e9ee;border-radius:12px;padding:12px 14px;outline:none}
        .form-group input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
        .form-message{min-height:18px;color:#ff6b6b;font-size:12px}
        .submit-btn{display:inline-flex;align-items:center;gap:10px;background:var(--brand);color:#fff;border:none;border-radius:14px;padding:12px 16px;cursor:pointer;transition:transform .08s ease,box-shadow .15s ease}
        .submit-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(0,0,0,.35)}
        .success-message{background:#0e1711;border:1px solid #1d3326;color:#9be7c2;border-radius:12px;padding:10px 12px;display:flex;align-items:center;gap:10px;margin:14px 0}
        .success-message .close-message{background:transparent;border:none;color:#9be7c2;cursor:pointer}
        .coming-soon{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#cfd3da;min-height:160px}
        .vouchers-list .card{background:#0f1115;border:1px solid #22262d;border-radius:14px;color:#e7e9ee}
        .vouchers-list .card-body{padding:16px}
        .vouchers-list .badge{background:#1f6feb}
        .vouchers-list .btn{border-radius:10px}
        .vouchers-list .btn-primary{background:#1f6feb;border:none}
        .vouchers-list .btn-outline-secondary{border-color:#38404b;color:#cfd3da}
    </style>
</head>
<body>

<?php require 'header.php'; ?>

<div class="profile-container">
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>
        <h1>Thông tin cá nhân</h1>
        <p class="profile-email"><?= $_SESSION['email'] ?? 'Khách hàng' ?></p>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="success-message" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <span>Cập nhật thông tin thành công!</span>
            <button class="close-message"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="info">
            <i class="fas fa-user-circle"></i> Thông tin cơ bản
        </button>
        <button class="tab-btn" data-tab="security">
            <i class="fas fa-shield-alt"></i> Bảo mật
        </button>
        <button class="tab-btn" data-tab="orders">
            <i class="fas fa-shopping-bag"></i> Đơn hàng
        </button>
        <button class="tab-btn" data-tab="vouchers">
            <i class="fas fa-ticket-alt"></i> Voucher
        </button>
    </div>  

    <div class="tab-content active" id="info">
        <form method="POST" action="profile.php" id="profileForm">
            <div class="form-group">
                <label for="phone">
                    <i class="fas fa-phone"></i>
                    Số điện thoại
                </label>
                <input type="tel" id="phone" name="phone" 
                       value="<?= htmlspecialchars($user['phone']) ?>" 
                       pattern="[0-9]{10}" 
                       title="Vui lòng nhập số điện thoại hợp lệ"
                       required>
                <span class="form-message"></span>
            </div>

            <div class="form-group">
                <label for="address">
                    <i class="fas fa-home"></i>
                    Địa chỉ
                </label>
                <input type="text" id="address" name="address" 
                       value="<?= htmlspecialchars($user['address']) ?>" 
                       required>
                <span class="form-message"></span>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i>
                <span>Cập nhật thông tin</span>
            </button>
        </form>
    </div>

    <div class="tab-content" id="security">
        <div class="coming-soon">
            <i class="fas fa-lock"></i>
            <p>Tính năng đang được phát triển</p>
        </div>
    </div>

    <div class="tab-content" id="orders">
        <div class="coming-soon">
            <i class="fas fa-shopping-cart"></i>
            <p>Tính năng đang được phát triển</p>
        </div>
    </div>

    <!-- NEW: Vouchers tab content integrated from user_vouchers.php -->
    <div class="tab-content" id="vouchers">
        <div class="vouchers-list">
            <?php if ($vouchers_result && $vouchers_result->num_rows > 0): ?>
                <div class="row g-3">
                    <?php while ($v = $vouchers_result->fetch_assoc()):
                        $remaining = is_null($v['usage_limit']) ? 'Không giới hạn' : max(0, (int)$v['usage_limit'] - (int)$v['times_used']);
                        $is_percent = !empty($v['is_percent']);
                        $discount_label = $is_percent ? (float)$v['discount'].'%' : number_format((float)$v['discount'],0,',','.').' đ';
                        $min_order = (float)$v['min_order_amount'] > 0 ? number_format((float)$v['min_order_amount'],0,',','.').' đ' : 'Không giới hạn';
                        $expiry = $v['expiry_date'] ? date('d/m/Y', strtotime($v['expiry_date'])) : 'Không giới hạn';
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($v['code']) ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($v['description'] ?: 'Không có mô tả') ?></small>
                                    </div>
                                    <span class="badge bg-info"><?= $discount_label ?></span>
                                </div>

                                <ul class="list-unstyled mt-3 mb-3 small">
                                    <li><strong>Đơn tối thiểu:</strong> <?= $min_order ?></li>
                                    <li><strong>Giảm tối đa:</strong> <?= $v['max_discount'] ? number_format($v['max_discount'],0,',','.') . ' đ' : 'Không giới hạn' ?></li>
                                    <li><strong>Hạn dùng:</strong> <?= $expiry ?></li>
                                    <li><strong>Đã dùng:</strong> <?= (int)$v['times_used'] ?> <?= is_numeric($remaining) ? "/ {$v['usage_limit']}" : '' ?></li>
                                    <li><strong>Còn lại:</strong> <?= htmlspecialchars((string)$remaining) ?></li>
                                </ul>

                                <div class="mt-auto d-flex gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" onclick="copyCode('<?= htmlspecialchars($v['code'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-copy me-1"></i> Sao chép
                                    </button>

                                    <button class="btn btn-primary btn-sm" onclick="applyVoucher('<?= rawurlencode($v['code']) ?>')">
                                        <i class="fas fa-ticket-alt me-1"></i> Áp dụng
                                    </button>

                                    <a href="checkout.php?voucher=<?= rawurlencode($v['code']) ?>" class="btn btn-success btn-sm d-none" id="direct-apply-<?= htmlspecialchars($v['code'], ENT_QUOTES) ?>">Áp dụng (link)</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Hiện không có voucher hợp lệ.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.dataset.tab).classList.add('active');
        });
    });

    // Profile form validation (existing)
    const profileForm = document.getElementById('profileForm');
    const phoneInput = document.getElementById('phone');
    const addressInput = document.getElementById('address');

    if (phoneInput) phoneInput.addEventListener('input', () => validatePhone(phoneInput));
    if (addressInput) addressInput.addEventListener('input', () => validateAddress(addressInput));

    profileForm?.addEventListener('submit', function(e) {
        let isValid = true;
        if (!validatePhone(phoneInput)) isValid = false;
        if (!validateAddress(addressInput)) isValid = false;
        if (!isValid) {
            e.preventDefault();
            profileForm.classList.add('shake');
            setTimeout(() => profileForm.classList.remove('shake'), 500);
        }
    });

    const success = document.getElementById('successMessage');
    if (success) {
        document.querySelector('.close-message')?.addEventListener('click', () => { success.style.display = 'none'; });
        setTimeout(() => { success.style.opacity = '0'; }, 2500);
        setTimeout(() => { success.style.display = 'none'; }, 3200);
    }

    // Copy / apply voucher helpers
    window.copyCode = function(code) {
        navigator.clipboard?.writeText(code).then(() => {
            alert('Đã sao chép mã: ' + code);
        }).catch(() => {
            const tmp = document.createElement('input');
            tmp.value = code;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            tmp.remove();
            alert('Đã sao chép mã: ' + code);
        });
    };

    window.applyVoucher = function(codeEncoded) {
        const code = decodeURIComponent(codeEncoded);
        window.location.href = 'checkout.php?voucher=' + encodeURIComponent(code);
    };

    // Validation functions reused
    function validatePhone(input) {
        const phonePattern = /^[0-9]{10}$/;
        if (!input.value.trim()) { showError(input, 'Vui lòng nhập số điện thoại'); return false; }
        if (!phonePattern.test(input.value.trim())) { showError(input, 'Số điện thoại phải có 10 chữ số'); return false; }
        clearError(input); return true;
    }
    function validateAddress(input) {
        if (!input.value.trim()) { showError(input, 'Vui lòng nhập địa chỉ'); return false; }
        clearError(input); return true;
    }
    function showError(input, message) {
        const formMessage = input.nextElementSibling;
        formMessage.textContent = message;
        input.classList.add('error','shake');
        setTimeout(() => input.classList.remove('shake'), 500);
    }
    function clearError(input) {
        const formMessage = input.nextElementSibling;
        formMessage.textContent = '';
        input.classList.remove('error');
    }

    // Auto-open vouchers tab if URL has #vouchers or query ?tab=vouchers
    if (location.hash === '#vouchers' || new URLSearchParams(location.search).get('tab') === 'vouchers') {
        document.querySelector('.tab-btn[data-tab="vouchers"]')?.click();
    }
});
</script>

<?php require 'footer.php'; ?>
<?php include 'chatbox.php'; ?>
</body>
</html>

<?php
$conn->close();
?>
