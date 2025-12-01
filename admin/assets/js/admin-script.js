// Xử lý đăng xuất
function handleLogout() {
    if(confirm('Bạn có chắc muốn đăng xuất?')) {
        window.location.href = 'logout.php';
    }
}

// Xử lý responsive sidebar
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

// Khởi tạo hiệu ứng 3D background
function initBackground() {
    const container = document.getElementById('canvas-container');
    if (!container) return;
    
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);
    
    // Tạo các hạt 3D
    const particlesGeometry = new THREE.BufferGeometry();
    const particlesCount = 1500;
    
    const posArray = new Float32Array(particlesCount * 3);
    for(let i = 0; i < particlesCount * 3; i++) {
        posArray[i] = (Math.random() - 0.5) * 10;
    }
    
    particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
    
    const particlesMaterial = new THREE.PointsMaterial({
        size: 0.02,
        color: 0x4361ee,
        transparent: true,
        opacity: 0.8
    });
    
    const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
    scene.add(particlesMesh);
    
    camera.position.z = 5;
    
    // Animation
    function animate() {
        requestAnimationFrame(animate);
        particlesMesh.rotation.x += 0.0005;
        particlesMesh.rotation.y += 0.0005;
        renderer.render(scene, camera);
    }
    
    animate();
    
    // Resize handler
    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
}

// Hiệu ứng hover cho card-3d
function init3DCards() {
    document.querySelectorAll('.card-3d').forEach(card => {
        card.addEventListener('mousemove', e => {
            const cardRect = card.getBoundingClientRect();
            const cardCenterX = cardRect.left + cardRect.width / 2;
            const cardCenterY = cardRect.top + cardRect.height / 2;
            const angleX = (e.clientY - cardCenterY) / 15;
            const angleY = (cardCenterX - e.clientX) / 15;
            
            const inner = card.querySelector('.card-3d-inner');
            if (inner) {
                inner.style.transform = `rotateX(${angleX}deg) rotateY(${angleY}deg)`;
            }
        });
        
        card.addEventListener('mouseleave', () => {
            const inner = card.querySelector('.card-3d-inner');
            if (inner) {
                inner.style.transform = 'rotateX(0) rotateY(0)';
            }
        });
    });
}

// Khởi tạo tất cả các hiệu ứng khi trang đã tải xong
document.addEventListener('DOMContentLoaded', () => {
    // Kiểm tra xem THREE có được định nghĩa không
    if (typeof THREE !== 'undefined') {
        initBackground();
        
        // Khởi tạo globe nếu có container
        const globeContainer = document.getElementById('globe-container');
        if (globeContainer) {
            initGlobe();
        }
        
        // Khởi tạo product showcase nếu có container
        const productShowcase = document.getElementById('product-showcase');
        if (productShowcase) {
            initProductShowcase();
        }
    }
    
    // Khởi tạo hiệu ứng 3D cho các card
    init3DCards();
    
    // Khởi tạo các hiệu ứng khác
    initAnimations();
});

// Khởi tạo các hiệu ứng animation
function initAnimations() {
    // Thêm class fade-in cho các phần tử cần animation
    document.querySelectorAll('.card, .stat-card').forEach(el => {
        el.classList.add('fade-in');
    });
}

// Khởi tạo Globe 3D
function initGlobe() {
    const container = document.getElementById('globe-container');
    if (!container) return;
    
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, 1, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    
    renderer.setSize(200, 200);
    container.appendChild(renderer.domElement);
    
    // Tạo globe
    const geometry = new THREE.SphereGeometry(1, 32, 32);
    const material = new THREE.MeshBasicMaterial({
        color: 0x3949ab,
        wireframe: true
    });
    
    const globe = new THREE.Mesh(geometry, material);
    scene.add(globe);
    
    camera.position.z = 2;
    
    // Controls
    if (typeof THREE.OrbitControls !== 'undefined') {
        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.enableZoom = false;
        
        // Animation
        function animate() {
            requestAnimationFrame(animate);
            globe.rotation.y += 0.005;
            controls.update();
            renderer.render(scene, camera);
        }
        
        animate();
    } else {
        // Fallback nếu không có OrbitControls
        function animate() {
            requestAnimationFrame(animate);
            globe.rotation.y += 0.005;
            renderer.render(scene, camera);
        }
        
        animate();
    }
}

// Khởi tạo Product Showcase 3D
function initProductShowcase() {
    const container = document.getElementById('product-showcase');
    if (!container) return;
    
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    
    renderer.setSize(container.clientWidth, container.clientHeight);
    container.appendChild(renderer.domElement);
    
    // Tạo các khối 3D đại diện cho sản phẩm
    const products = [];
    const colors = [0x4361ee, 0x3949ab, 0x805dca, 0x10b981, 0xf59e0b];
    
    for(let i = 0; i < 5; i++) {
        const geometry = new THREE.BoxGeometry(1, 1, 1);
        const material = new THREE.MeshBasicMaterial({ color: colors[i] });
        const cube = new THREE.Mesh(geometry, material);
        cube.position.x = (i - 2) * 2;
        cube.position.y = Math.sin(i) * 0.5;
        scene.add(cube);
        products.push(cube);
    }
    
    camera.position.z = 6;
    
    // Controls
    if (typeof THREE.OrbitControls !== 'undefined') {
        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        
        // Animation
        function animate() {
            requestAnimationFrame(animate);
            
            products.forEach((product, index) => {
                product.rotation.x += 0.01;
                product.rotation.y += 0.01;
                product.position.y = Math.sin(Date.now() * 0.001 + index) * 0.5;
            });
            
            controls.update();
            renderer.render(scene, camera);
        }
        
        animate();
    } else {
        // Fallback nếu không có OrbitControls
        function animate() {
            requestAnimationFrame(animate);
            
            products.forEach((product, index) => {
                product.rotation.x += 0.01;
                product.rotation.y += 0.01;
                product.position.y = Math.sin(Date.now() * 0.001 + index) * 0.5;
            });
            
            renderer.render(scene, camera);
        }
        
        animate();
    }
    
    // Resize handler
    window.addEventListener('resize', () => {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    });
}