<?php
session_start();
require 'header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ - Luxury Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="css/chatbox.css">
</head>
<body>
    <main class="contact-page">
        <section class="hero-section">
            <div class="hero-content" data-aos="fade-up">
                <h1>Liên Hệ Với Chúng Tôi</h1>
                <p>Chúng tôi luôn sẵn sàng lắng nghe và hỗ trợ bạn</p>
            </div>
        </section>

        <section class="contact-section">
            <div class="container">
                <div class="contact-wrapper">
                    <div class="contact-info" data-aos="fade-right" data-aos-delay="100">
                        <h2>Thông Tin Liên Hệ</h2>
                        <div class="info-card">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <h3>Địa Chỉ</h3>
                                    <p>123 Đường Luxury, Quận 1, TP.HCM</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <h3>Điện Thoại</h3>
                                    <p>0123-456-789</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <h3>Email</h3>
                                    <p>support@luxurystore.com</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h3>Giờ Làm Việc</h3>
                                    <p>8:00 - 18:00 (Thứ 2 - Thứ 7)</p>
                                </div>
                            </div>
                        </div>
                        <div class="social-links">
                            <a href="#" class="social-icon" aria-label="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-icon" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-icon" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-icon" aria-label="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>

                    <div class="contact-form-wrapper" data-aos="fade-left" data-aos-delay="200">
                        <div class="form-card">
                            <h2>Gửi Tin Nhắn</h2>
                            <form id="contactForm" action="auth/send_contact.php" method="POST" class="contact-form">
                                <div class="form-group">
                                <label for="name">Họ và tên</label>
                                    <input type="text" name="name" class="form-control" id="name" required>
                                    
                                </div>
                                <div class="form-group">
                                <label for="email">Email</label>
                                    <input type="email" name="email" class="form-control" id="email" required>
                                  
                                </div>
                                <div class="form-group">
                                <label for="message">Tin nhắn</label>
                                    <textarea name="message" class="form-control" id="message" rows="5" required></textarea>
                                    
                                </div>
                                <button type="submit" class="btn-submit">
                                    <span>Gửi Tin Nhắn</span>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="map-section" data-aos="fade-up">
            <div class="container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.4241674197956!2d106.69843661533433!3d10.775144162196!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTDCsDQ2JzMwLjUiTiAxMDbCsDQxJzU4LjQiRQ!5e0!3m2!1svi!2s!4v1635789876543!5m2!1svi!2s"
                    width="100%" 
                    height="450" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy"
                    title="Bản đồ địa chỉ của chúng tôi">
                </iframe>
            </div>
        </section>
    </main>

    <?php require 'footer.php'; ?>
    <?php include 'chatbox.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Thêm vào phần head -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Thay thế phần script cũ -->
    <script>
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
    
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.disabled = true;
            
            fetch('auth/send_contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công!',
                        text: 'Tin nhắn của bạn đã được gửi thành công.',
                        confirmButtonColor: '#3085d6'
                    });
                    this.reset();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: error.message || 'Không thể gửi tin nhắn. Vui lòng thử lại sau.',
                    confirmButtonColor: '#d33'
                });
            })
            .finally(() => {
                submitBtn.disabled = false;
            });
        });
    
        // Form input effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
    
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
    
            // Thêm class focused nếu input đã có giá trị
            if (input.value) {
                input.parentElement.classList.add('focused');
            }
        });
    </script>

    <?php include 'chatbox.php'; ?>
</body>
</html>