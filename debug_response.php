<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'db.php';
require_once 'chatbot_database.php';
require_once 'ai_config.php';

class DebugChatbotAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->db = new ChatbotDatabase();
    }
    
    public function processMessage($user_id, $message) {
        error_log("DEBUG: Processing message: $message for user: $user_id");
        
        // Phát hiện ngôn ngữ
        $language = $this->detectLanguage($message);
        error_log("DEBUG: Detected language: $language");
        
        // Lấy phản hồi từ AI với thông tin sản phẩm thực tế
        $ai_response = $this->getAIResponse($message, $language, $user_id);
        error_log("DEBUG: AI response length: " . strlen($ai_response));
        
        $result = [
            'success' => true,
            'response' => $ai_response,
            'language' => $language
        ];
        
        error_log("DEBUG: Final result: " . json_encode($result));
        return $result;
    }
    
    public function getAIResponse($message, $language = 'vi', $user_id = null) {
        error_log("DEBUG: Getting AI response for: $message");
        
        // Phân tích tin nhắn để tìm kiếm sản phẩm
        $product_info = $this->analyzeAndSearchProducts($message, $user_id);
        error_log("DEBUG: Product info length: " . strlen($product_info));
        
        // Nếu có thông tin sản phẩm, trả về luôn thông tin đó
        if (!empty($product_info)) {
            if ($language == 'vi') {
                $response = "Dưới đây là các sản phẩm phù hợp với yêu cầu của bạn:\n\n" . $product_info;
            } else {
                $response = "Here are the products that match your request:\n\n" . $product_info;
            }
            error_log("DEBUG: Returning product response");
            return $response;
        }
        
        error_log("DEBUG: No products found, returning fallback");
        return "Xin chào! Tôi là trợ lý ảo của cửa hàng. Tôi có thể giúp bạn tìm hiểu về sản phẩm, giá cả, và dịch vụ.";
    }
    
    public function analyzeAndSearchProducts($message, $user_id = null) {
        error_log("DEBUG: Analyzing message: $message");
        
        $search_terms = $this->extractSearchTerms($message);
        error_log("DEBUG: Search terms: " . implode(', ', $search_terms));
        
        $categories = $this->db->getCategories();
        
        // Tìm kiếm kết hợp từ khóa và danh mục
        foreach ($search_terms as $term) {
            foreach ($categories as $category) {
                if (stripos($category['name'], $term) !== false) {
                    error_log("DEBUG: Found category match: {$category['name']} for term: $term");
                    $category_products = $this->db->getProductsByCategory($category['name']);
                    
                    $filtered_products = [];
                    foreach ($category_products as $product) {
                        foreach ($search_terms as $search_term) {
                            if (stripos($product['name'], $search_term) !== false || 
                                (isset($product['description']) && stripos($product['description'], $search_term) !== false)) {
                                $filtered_products[] = $product;
                                break;
                            }
                        }
                    }
                    
                    if (!empty($filtered_products)) {
                        error_log("DEBUG: Found " . count($filtered_products) . " filtered products");
                        return $this->formatProductResults($filtered_products, "KẾT QUẢ TÌM KIẾM '{$term}' TRONG DANH MỤC '{$category['name']}':");
                    }
                }
            }
        }
        
        // Tìm kiếm theo từ khóa
        foreach ($search_terms as $term) {
            $products = $this->db->searchProducts($term);
            error_log("DEBUG: Found " . count($products) . " products for term: $term");
            if (!empty($products)) {
                return $this->formatProductResults($products, "KẾT QUẢ TÌM KIẾM '$term':");
            }
        }
        
        error_log("DEBUG: No products found");
        return '';
    }
    
    private function extractSearchTerms($message) {
        $stop_words = ['tôi', 'tìm', 'muốn', 'cần', 'có', 'không', 'là', 'của', 'và', 'cho', 'với', 'về', 'trong', 'được', 'này', 'đó', 'một', 'các', 'những', 'để', 'từ', 'sản', 'phẩm', 'hàng', 'hóa'];
        $words = explode(' ', strtolower($message));
        $search_terms = [];
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?');
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $search_terms[] = $word;
            }
        }
        
        return array_slice($search_terms, 0, 3);
    }
    
    private function formatProductResults($products, $title) {
        $result = $title . "\n";
        $count = 0;
        foreach ($products as $product) {
            if ($count >= 5) break;
            $result .= $this->db->formatProductInfo($product) . "\n";
            $count++;
        }
        return $result;
    }
    
    private function detectLanguage($message) {
        if (preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/u', $message)) {
            return 'vi';
        }
        return 'en';
    }
}

$chatbot = new DebugChatbotAPI();

// Xử lý request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("DEBUG: POST request received");
    
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("DEBUG: Input data: " . json_encode($input));
    
    if (!isset($input['message']) || empty(trim($input['message']))) {
        error_log("DEBUG: Message is empty or missing");
        echo json_encode([
            'success' => false,
            'error' => 'Message is required'
        ]);
        exit;
    }
    
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $message = trim($input['message']);
    
    error_log("DEBUG: Processing message: $message for user: $user_id");
    
    // Nếu không có user_id, tạo session tạm thời
    if ($user_id == 0) {
        session_start();
        if (!isset($_SESSION['temp_user_id'])) {
            $_SESSION['temp_user_id'] = time() . rand(1000, 9999);
        }
        $user_id = $_SESSION['temp_user_id'];
        error_log("DEBUG: Using temp user ID: $user_id");
    }
    
    $response = $chatbot->processMessage($user_id, $message);
    error_log("DEBUG: About to output response: " . json_encode($response));
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} else {
    error_log("DEBUG: Non-POST request received: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
?>