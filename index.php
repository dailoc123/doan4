<?php
session_start();
// Chuyển hướng trực tiếp đến userhome.php
header('Location: userhome.php');
exit();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxury Store | Thời trang đẳng cấp</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Floating Geometric Shapes */
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            top: 20%;
            left: 10%;
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #d4af37, #f4d03f);
            border-radius: 50%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            top: 60%;
            right: 15%;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #bdc3c7, #ecf0f1);
            transform: rotate(45deg);
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            bottom: 30%;
            left: 20%;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, #d4af37, #f39c12);
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(10px) rotate(240deg); }
        }

        .hero {
            height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.5)), 
                        url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            color: white;
        }

        header {
            padding: 20px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        header:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo i {
            color: #d4af37;
            filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.5));
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.5)); }
            to { filter: drop-shadow(0 0 20px rgba(212, 175, 55, 0.8)); }
        }

        nav {
            display: flex;
            gap: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: 500;
            letter-spacing: 0.5px;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, #d4af37, #f4d03f);
            border-radius: 30px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        nav a:hover {
            color: #000;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        nav a:hover::before {
            opacity: 1;
        }

        .hero-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 80%;
            max-width: 800px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 4.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            animation: fadeDown 1s ease;
            background: linear-gradient(45deg, #fff, #d4af37);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: transform 0.3s ease;
        }

        h1:hover {
            transform: scale(1.02);
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            line-height: 1.8;
            animation: fadeUp 1s ease 0.3s forwards;
            opacity: 0;
            background: rgba(255, 255, 255, 0.9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 400;
        }

        .cta-buttons {
            display: flex;
            gap: 30px;
            justify-content: center;
            animation: fadeUp 1s ease 0.6s forwards;
            opacity: 0;
        }

        .btn {
            padding: 18px 45px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            cursor: pointer;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(45deg, #d4af37, #f4d03f);
            color: #000;
            border: 2px solid transparent;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #c4a030, #e6c200);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #d4af37;
            color: #d4af37;
        }

        .scroll-down {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 3s ease-in-out infinite;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 15px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .scroll-down:hover {
            transform: translateX(-50%) scale(1.2);
            background: rgba(212, 175, 55, 0.2);
            color: #d4af37;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateX(-50%); }
            40% { transform: translateY(-25px) translateX(-50%); }
            60% { transform: translateY(-15px) translateX(-50%); }
        }

        @keyframes fadeDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        footer {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            margin-top: auto;
            position: relative;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-bottom: 20px;
        }

        .social-icons a {
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            padding: 15px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .social-icons a:hover {
            color: #d4af37;
            transform: translateY(-5px) scale(1.1);
            background: rgba(212, 175, 55, 0.2);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3);
        }

        /* Particle Effect */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(212, 175, 55, 0.6);
            border-radius: 50%;
            animation: particleFloat 8s linear infinite;
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) translateX(0) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
                transform: scale(1);
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) translateX(100px) scale(0);
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            h1 {
                font-size: 2.8rem;
            }
            
            .hero-content p {
                font-size: 1.1rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                width: 90%;
                margin: 0 auto;
                gap: 20px;
            }
            
            nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            nav a {
                padding: 10px 16px;
                font-size: 0.9rem;
            }

            .btn {
                padding: 15px 35px;
                font-size: 1rem;
            }

            .floating-shapes {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Geometric Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <!-- Particle Effect -->
    <div class="particles" id="particles"></div>

    <div class="hero">
        <header>
            <div class="logo">
                <i class="fas fa-crown"></i> Luxury Store
            </div>
            
            <nav>
                <?php if (isset($_SESSION['user_name'])): ?>
                    <a href="../admin/dashboard.php"><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></a>
                    <a href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a>
                    <a href="register.php"><i class="fas fa-user-plus"></i> Đăng ký</a>
                <?php endif; ?>
            </nav>
        </header>
        
        <div class="hero-content">
            <h1>Thời trang đẳng cấp</h1>
            <p>Khám phá bộ sưu tập thời trang cao cấp, phong cách & độc quyền. Nơi định nghĩa lại phong cách của bạn với những thiết kế tinh tế và chất liệu hàng đầu.</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <a href="userhome.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Mua sắm ngay</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Mua sắm ngay</a>
                <?php endif; ?>
                <a href="search.php" class="btn btn-secondary"><i class="fas fa-search"></i> Khám phá sản phẩm</a>
            </div>
        </div>
        
        <div class="scroll-down">
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>

    <footer>
        <div class="social-icons">
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-pinterest"></i></a>
        </div>
        &copy; 2025 Luxury Store. All Rights Reserved.
    </footer>

    <script>
        // Create Particles
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 8 + 's';
            particle.style.animationDuration = (Math.random() * 3 + 5) + 's';
            document.getElementById('particles').appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 8000);
        }

        // Generate particles periodically
        setInterval(createParticle, 300);

        // Smooth scroll for scroll-down button
        document.querySelector('.scroll-down').addEventListener('click', () => {
            window.scrollTo({
                top: window.innerHeight,
                behavior: 'smooth'
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>