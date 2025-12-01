<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Luxury Store</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#9f7aea',secondary:'#b794f4'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <style>
        .logo-font {
            font-family: 'Pacifico', cursive;
        }
        
        .fashion-bg {
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
                url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }
        
        .fashion-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                rgba(159, 122, 234, 0.1) 0%, 
                rgba(183, 148, 244, 0.1) 50%, 
                rgba(102, 126, 234, 0.1) 100%);
            z-index: 1;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 2;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
        }
        
        .input-focus:focus {
            border-color: #9f7aea;
            box-shadow: 0 0 0 3px rgba(159, 122, 234, 0.1);
        }
        
        /* Floating fashion elements */
        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
            z-index: 1;
        }
        
        .floating-element:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            top: 20%;
            right: 15%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            bottom: 30%;
            right: 10%;
            animation-delay: 1s;
        }
        
        .floating-element:nth-child(5) {
            top: 50%;
            left: 5%;
            animation-delay: 3s;
        }
        
        .floating-element:nth-child(6) {
            top: 70%;
            right: 25%;
            animation-delay: 5s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            33% {
                transform: translateY(-20px) rotate(5deg);
            }
            66% {
                transform: translateY(10px) rotate(-5deg);
            }
        }
        
        .luxury-accent {
            background: linear-gradient(45deg, #d4af37, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="min-h-screen fashion-bg flex items-center justify-center p-4">
    <!-- Floating Fashion Elements -->
    <div class="floating-element text-6xl text-white">
        <i class="ri-shirt-line"></i>
    </div>
    <div class="floating-element text-5xl text-white">
        <i class="ri-handbag-line"></i>
    </div>
    <div class="floating-element text-4xl text-white">
        <i class="ri-glasses-line"></i>
    </div>
    <div class="floating-element text-5xl text-white">
        <i class="ri-high-heels-line"></i>
    </div>
    <div class="floating-element text-4xl text-white">
        <i class="ri-t-shirt-line"></i>
    </div>
    <div class="floating-element text-5xl text-white">
        <i class="ri-shopping-bag-line"></i>
    </div>
    
    <div class="content-wrapper w-full">
        <!-- Back to Home Button -->
        <a href="index.php" class="absolute top-6 left-6 text-white hover:text-gray-200 transition-colors duration-300 flex items-center gap-2 z-10">
            <i class="ri-arrow-left-line text-xl"></i>
            <span class="hidden sm:inline">Trở về trang chủ</span>
        </a>

        <!-- Register Container -->
        <div class="w-full max-w-md mx-auto">
            <div class="glass-effect rounded-3xl p-8 shadow-2xl">
                <!-- Logo -->
                <div class="text-center mb-8">
                    <div class="logo-font text-3xl luxury-accent mb-2">Luxury Store</div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Tạo tài khoản mới</h1>
                    <p class="text-gray-600 text-sm">
                        Đã có tài khoản? 
                        <a href="login.php" class="text-primary hover:text-secondary transition-colors duration-300 font-medium">Đăng nhập ngay</a>
                    </p>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500/20 border border-red-400/30 text-red-700 px-4 py-3 rounded-lg mb-6 backdrop-blur-sm flex items-center gap-2">
                        <i class="ri-error-warning-line"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-400/30 text-green-700 px-4 py-3 rounded-lg mb-6 backdrop-blur-sm flex items-center gap-2">
                        <i class="ri-check-circle-line"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Register Form -->
                <form action="auth/register.php" method="POST" class="space-y-6">
                    <!-- Username Input -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Tên người dùng</label>
                        <div class="relative">
                            <i class="ri-user-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="Nhập tên người dùng" 
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none input-focus transition-all duration-300"
                            >
                        </div>
                    </div>

                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <div class="relative">
                            <i class="ri-mail-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="Nhập email của bạn" 
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none input-focus transition-all duration-300"
                            >
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu</label>
                        <div class="relative">
                            <i class="ri-lock-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Nhập mật khẩu" 
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none input-focus transition-all duration-300"
                            >
                        </div>
                    </div>

                    <!-- Phone Input -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                        <div class="relative">
                            <i class="ri-phone-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                placeholder="Nhập số điện thoại" 
                                required
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none input-focus transition-all duration-300"
                            >
                        </div>
                    </div>

                    <!-- Register Button -->
                    <button 
                        type="submit" 
                        class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-secondary transition-all duration-300 transform hover:scale-[1.02] flex items-center justify-center gap-2"
                    >
                        <i class="ri-user-add-line"></i>
                        Đăng ký tài khoản
                    </button>
                </form>

                <!-- Divider -->
                <div class="my-6 flex items-center">
                    <div class="flex-1 border-t border-gray-300"></div>
                    <span class="px-4 text-sm text-gray-500">Hoặc đăng ký với</span>
                    <div class="flex-1 border-t border-gray-300"></div>
                </div>

                <!-- Social Login Buttons -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Google -->
                    <a href="auth/login_google.php" class="flex items-center justify-center p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-300 gap-2">
                        <i class="ri-google-fill text-xl text-red-500"></i>
                        <span class="text-sm font-medium">Google</span>
                    </a>
                    <!-- Facebook -->
                    <a href="auth/login_facebook.php" class="flex items-center justify-center p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-300 gap-2">
                        <i class="ri-facebook-fill text-xl text-blue-600"></i>
                        <span class="text-sm font-medium">Facebook</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-red-500\\/20, .bg-green-500\\/20');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-10px)';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
    </script>

    <?php include 'chatbox.php'; ?>
</body>
</html>
