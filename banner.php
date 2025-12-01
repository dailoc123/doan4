<style>
    .banner-container {
        position: relative;
        width: 100%;
        height: 500px;
        overflow: hidden;
        margin-bottom: 40px;
    }

    .banner-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 1s ease-in-out;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .banner-slide.active {
        opacity: 1;
    }

    .banner-content {
        position: absolute;
        top: 50%;
        left: 50px;
        transform: translateY(-50%);
        color: white;
        z-index: 2;
        max-width: 500px;
    }

    .banner-title {
        font-size: 48px;
        font-weight: bold;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        line-height: 1.2;
    }

    .banner-subtitle {
        font-size: 24px;
        margin-bottom: 10px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .banner-description {
        font-size: 16px;
        margin-bottom: 30px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        line-height: 1.4;
    }

    .banner-btn {
        display: inline-block;
        padding: 15px 30px;
        background: white;
        color: #222;
        text-decoration: none;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        border: 2px solid white;
    }

    .banner-btn:hover {
        background: transparent;
        color: white;
        text-decoration: none;
    }

    .banner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, rgba(0,0,0,0.3), rgba(0,0,0,0.1));
        z-index: 1;
    }

    .banner-dots {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 3;
    }

    .banner-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255,255,255,0.5);
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .banner-dot.active {
        background: white;
    }

    .banner-arrows {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 24px;
        width: 50px;
        height: 50px;
        cursor: pointer;
        transition: background 0.3s ease;
        z-index: 3;
    }

    .banner-arrows:hover {
        background: rgba(255,255,255,0.4);
    }

    .banner-prev {
        left: 20px;
    }

    .banner-next {
        right: 20px;
    }

    /* Sale Badge */
    .sale-badge {
        position: absolute;
        top: 30px;
        right: 30px;
        background: #e73c7e;
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 18px;
        z-index: 3;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .banner-container {
            height: 400px;
        }

        .banner-content {
            left: 20px;
            right: 20px;
            max-width: none;
        }

        .banner-title {
            font-size: 32px;
        }

        .banner-subtitle {
            font-size: 18px;
        }

        .banner-description {
            font-size: 14px;
        }

        .banner-btn {
            padding: 12px 24px;
            font-size: 14px;
        }

        .sale-badge {
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            font-size: 16px;
        }
    }
</style>

<div class="banner-container">
    <!-- Sale Badge -->
    <div class="sale-badge">WOLFSALE 50%</div>

    <!-- Banner Slide 1 -->
    <div class="banner-slide active" style="background-image: url('images/banner1.jpg')">
        <div class="banner-overlay"></div>
        <div class="banner-content">
            <div class="banner-subtitle">Lên đến</div>
            <div class="banner-title">50%</div>
            <div class="banner-description">VÀ ĐỒNG GIÁ CHỈ TỪ 49K</div>
            <a href="products.php" class="banner-btn">Mua Ngay</a>
        </div>
    </div>

    <!-- Banner Slide 2 -->
    <div class="banner-slide" style="background-image: url('images/banner2.jpg')">
        <div class="banner-overlay"></div>
        <div class="banner-content">
            <div class="banner-subtitle">BST Peaceful</div>
            <div class="banner-title">SUMMER</div>
            <div class="banner-description">Khám phá bộ sưu tập mùa hè với phong cách thoải mái và năng động</div>
            <a href="products.php?category_id=4" class="banner-btn">Khám Phá</a>
        </div>
    </div>

    <!-- Banner Slide 3 -->
    <div class="banner-slide" style="background-image: url('images/banner3.jpg')">
        <div class="banner-overlay"></div>
        <div class="banner-content">
            <div class="banner-subtitle">SMARTCOOL</div>
            <div class="banner-title">BUSINESS</div>
            <div class="banner-description">Tự do trong từng chuyển động - Phong cách công sở hiện đại</div>
            <a href="products.php?category_id=3" class="banner-btn">Xem Ngay</a>
        </div>
    </div>

    <!-- Banner Slide 4 -->
    <div class="banner-slide" style="background-image: url('images/banner4.jpg')">
        <div class="banner-overlay"></div>
        <div class="banner-content">
            <div class="banner-subtitle">BST Áo Gió</div>
            <div class="banner-title">THỜI TRANG</div>
            <div class="banner-description">Phong cách trẻ trung, năng động cho mọi hoạt động ngoài trời</div>
            <a href="products.php?category_id=8" class="banner-btn">Mua Sắm</a>
        </div>
    </div>

    <!-- Navigation Arrows -->
    <button class="banner-arrows banner-prev" onclick="prevSlide()">‹</button>
    <button class="banner-arrows banner-next" onclick="nextSlide()">›</button>

    <!-- Dots Navigation -->
    <div class="banner-dots">
        <span class="banner-dot active" onclick="currentSlide(1)"></span>
        <span class="banner-dot" onclick="currentSlide(2)"></span>
        <span class="banner-dot" onclick="currentSlide(3)"></span>
        <span class="banner-dot" onclick="currentSlide(4)"></span>
    </div>
</div>

<script>
let currentSlideIndex = 0;
const slides = document.querySelectorAll('.banner-slide');
const dots = document.querySelectorAll('.banner-dot');
const totalSlides = slides.length;

// Auto slide function
function autoSlide() {
    currentSlideIndex = (currentSlideIndex + 1) % totalSlides;
    showSlide(currentSlideIndex);
}

// Show specific slide
function showSlide(index) {
    // Remove active class from all slides and dots
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    // Add active class to current slide and dot
    slides[index].classList.add('active');
    dots[index].classList.add('active');
}

// Next slide function
function nextSlide() {
    currentSlideIndex = (currentSlideIndex + 1) % totalSlides;
    showSlide(currentSlideIndex);
}

// Previous slide function
function prevSlide() {
    currentSlideIndex = (currentSlideIndex - 1 + totalSlides) % totalSlides;
    showSlide(currentSlideIndex);
}

// Go to specific slide
function currentSlide(index) {
    currentSlideIndex = index - 1;
    showSlide(currentSlideIndex);
}

// Start auto slide
setInterval(autoSlide, 5000); // Change slide every 5 seconds

// Pause auto slide on hover
const bannerContainer = document.querySelector('.banner-container');
let autoSlideInterval;

function startAutoSlide() {
    autoSlideInterval = setInterval(autoSlide, 5000);
}

function stopAutoSlide() {
    clearInterval(autoSlideInterval);
}

bannerContainer.addEventListener('mouseenter', stopAutoSlide);
bannerContainer.addEventListener('mouseleave', startAutoSlide);

// Initialize auto slide
startAutoSlide();
</script>