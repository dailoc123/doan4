<?php
// Khởi tạo session và import header
session_start();
require 'header.php';
?>

<!-- Custom CSS cho trang about -->
<style>
    @import url('css/about.css');
    @import url('css/chatbox.css');
    @import url('https://unpkg.com/aos@2.3.1/dist/aos.css');
</style>
    <!-- Main content -->
    <main class="about-page">
        <!-- Hero section với hiệu ứng fade-down -->
        <section class="hero-section" data-aos="fade-down">
            <div class="hero-content">
                <h1>Luxury Store</h1>
                <p class="subtitle">Nơi đẳng cấp gặp gỡ phong cách</p>
                <!-- Nút scroll xuống với animation -->
                <div class="scroll-down">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </section>

        <!-- Story section với layout 2 cột -->
        <section class="story-section" data-aos="fade-up">
            <div class="container">
                <div class="row">
                    <!-- Cột hình ảnh -->
                    <div class="col-md-6" data-aos="fade-right">
                        <img src="images/about/store-front.jpg" alt="Luxury Store" class="img-fluid rounded-lg shadow">
                    </div>
                    <!-- Cột nội dung -->
                    <div class="col-md-6" data-aos="fade-left">
                        <h2>Câu chuyện của chúng tôi</h2>
                        <p>Được thành lập vào năm 2023, Luxury Store đã không ngừng phát triển và khẳng định vị thế là một trong những thương hiệu thời trang cao cấp hàng đầu tại Việt Nam.</p>
                        <p>Chúng tôi tự hào mang đến những bộ sưu tập độc đáo, kết hợp giữa xu hướng thời trang quốc tế và văn hóa Việt Nam.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Values section với grid 3 cột -->
        <section class="values-section" data-aos="fade-up">
            <div class="container">
                <h2 class="text-center mb-5">Giá trị cốt lõi</h2>
                <div class="row values-grid">
                    <!-- Value card 1 -->
                    <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                        <div class="value-card">
                            <i class="fas fa-gem"></i>
                            <h3>Chất lượng</h3>
                            <p>Cam kết mang đến những sản phẩm cao cấp nhất</p>
                        </div>
                    </div>
                    <!-- Value card 2 -->
                    <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                        <div class="value-card">
                            <i class="fas fa-heart"></i>
                            <h3>Tận tâm</h3>
                            <p>Luôn đặt trải nghiệm khách hàng lên hàng đầu</p>
                        </div>
                    </div>
                    <!-- Value card 3 -->
                    <div class="col-md-4" data-aos="zoom-in" data-aos-delay="300">
                        <div class="value-card">
                            <i class="fas fa-lightbulb"></i>
                            <h3>Sáng tạo</h3>
                            <p>Không ngừng đổi mới và phát triển</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact section -->
        <section class="contact-section" data-aos="fade-up">
            <div class="container">
                <div class="contact-card">
                    <h2>Liên hệ với chúng tôi</h2>
                    <div class="contact-info">
                        <!-- Email contact -->
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>support@luxurystore.com</p>
                            </div>
                        </div>
                        <!-- Phone contact -->
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Hotline</h4>
                                <p>1900 123 456</p>
                            </div>
                        </div>
                        <!-- Address contact -->
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Địa chỉ</h4>
                                <p>123 Đường Thời Trang, Quận 1, TP. Hồ Chí Minh</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Import footer và chatbox -->
    <?php require 'footer.php'; ?>
    <?php include 'chatbox.php'; ?>

    <!-- Initialize AOS library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Cấu hình AOS
        AOS.init({
            duration: 1000, // Thời gian animation
            once: true      // Chỉ chạy animation một lần
        });
    </script>

    <?php include 'chatbox.php'; ?>