<?php
// Cấu hình AI Gemini
// Lưu ý: API key này do bạn cung cấp. Hãy bảo vệ cẩn thận khi triển khai thật.

define('GEMINI_API_KEY', 'AIzaSyAvFrPAvDCiwzeOLlzOSI5RSYzGUVeH2jU'); // API key từ Google AI Studio
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

// Timeout cho gọi API (giây)
define('GEMINI_TIMEOUT', 12);