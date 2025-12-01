<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug_errors.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

function debug_log($message) {
    error_log("[DEBUG] " . $message);
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

try {
    debug_log("Starting request processing");
    
    require_once 'db.php';
    debug_log("Database connection loaded");
    
    require_once 'chatbot_database.php';
    debug_log("ChatbotDatabase class loaded");
    
    require_once 'ai_config.php';
    debug_log("AI config loaded");
    
    class DebugChatbotAPI {
        private $conn;
        private $db;
        
        public function __construct() {
            global $conn;
            $this->conn = $conn;
            $this->db = new ChatbotDatabase();
            debug_log("DebugChatbotAPI constructed");
        }
        
        public function processMessage($user_id, $message) {
            debug_log("Processing message: $message for user: $user_id");
            
            try {
                // Phát hiện ngôn ngữ
                $language = $this->detectLanguage($message);
                debug_log("Detected language: $language");
                
                // Lấy phản hồi từ AI với thông tin sản phẩm thực tế
                $ai_response = $this->getAIResponse($message, $language, $user_id);
                debug_log("AI response length: " . strlen($ai_response));
                
                $result = [
                    'success' => true,
                    'response' => $ai_response,
                    'language' => $language
                ];
                
                debug_log("Final result created successfully");
                return $result;
                
            } catch (Exception $e) {
                debug_log("Exception in processMessage: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Internal error: ' . $e->getMessage()
                ];
            }
        }
        
        public function getAIResponse($message, $language = 'vi', $user_id = null) {
            debug_log("Getting AI response for: $message");
            
            try {
                // Phân tích tin nhắn để tìm kiếm sản phẩm
                $product_info = $this->analyzeAndSearchProducts($message, $user_id);
                debug_log("Product info length: " . strlen($product_info));
                
                // Nếu có thông tin sản phẩm, trả về luôn thông tin đó
                if (!empty($product_info)) {
                    if ($language == 'vi') {
                        $response = "Dưới đây là các sản phẩm phù hợp với yêu cầu của bạn:\n\n" . $product_info;
                    } else {
                        $response = "Here are the products that match your request:\n\n" . $product_info;
                    }
                    debug_log("Returning product response");
                    return $response;
                }
                
                debug_log("No products found, returning fallback");
                return "Xin chào! Tôi là trợ lý ảo của cửa hàng. Tôi có thể giúp bạn tìm hiểu về sản phẩm, giá cả, và dịch vụ.";
                
            } catch (Exception $e) {
                debug_log("Exception in getAIResponse: " . $e->getMessage());
                return "Xin lỗi, có lỗi xảy ra khi xử lý yêu cầu của bạn.";
            }
        }
        
        public function analyzeAndSearchProducts($message, $user_id = null) {
            debug_log("Analyzing message: $message");
            
            try {
                $search_terms = $this->extractSearchTerms($message);
                debug_log("Search terms: " . implode(', ', $search_terms));
                
                $categories = $this->db->getCategories();
                debug_log("Categories loaded: " . count($categories));
                
                // Tìm kiếm kết hợp từ khóa và danh mục
                foreach ($search_terms as $term) {
                    foreach ($categories as $category) {
                        if (stripos($category['name'], $term) !== false) {
                            debug_log("Found category match: {$category['name']} for term: $term");
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
                                debug_log("Found " . count($filtered_products) . " filtered products");
                                return $this->formatProductResults($filtered_products, "KẾT QUẢ TÌM KIẾM '{$term}' TRONG DANH MỤC '{$category['name']}':");
                            }
                        }
                    }
                }
                
                // Tìm kiếm theo từ khóa
                foreach ($search_terms as $term) {
                    $products = $this->db->searchProducts($term);
                    debug_log("Found " . count($products) . " products for term: $term");
                    if (!empty($products)) {
                        return $this->formatProductResults($products, "KẾT QUẢ TÌM KIẾM '$term':");
                    }
                }
                
                debug_log("No products found");
                return '';
                
            } catch (Exception $e) {
                debug_log("Exception in analyzeAndSearchProducts: " . $e->getMessage());
                return '';
            }
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
    
    debug_log("Creating chatbot instance");
    $chatbot = new DebugChatbotAPI();
    
    // Xử lý request
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        debug_log("POST request received");
        
        $input = json_decode(file_get_contents('php://input'), true);
        debug_log("Input decoded: " . json_encode($input));
        
        if (!isset($input['message']) || empty(trim($input['message']))) {
            debug_log("Message is empty or missing");
            echo json_encode([
                'success' => false,
                'error' => 'Message is required'
            ]);
            exit;
        }
        
        $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $message = trim($input['message']);
        
        debug_log("Processing message: $message for user: $user_id");
        
        // Nếu không có user_id, tạo session tạm thời
        if ($user_id == 0) {
            session_start();
            if (!isset($_SESSION['temp_user_id'])) {
                $_SESSION['temp_user_id'] = time() . rand(1000, 9999);
            }
            $user_id = $_SESSION['temp_user_id'];
            debug_log("Using temp user ID: $user_id");
        }
        
        $response = $chatbot->processMessage($user_id, $message);
        debug_log("Response generated, checking JSON encoding");
        
        $json_output = json_encode($response, JSON_UNESCAPED_UNICODE);
        $json_error = json_last_error_msg();
        debug_log("JSON output length: " . strlen($json_output));
        debug_log("JSON error: " . $json_error);
        debug_log("Response structure: " . print_r($response, true));
        
        if ($json_error !== 'No error') {
            debug_log("JSON encoding failed: " . $json_error);
            echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . $json_error]);
        } else {
            echo $json_output;
            debug_log("Response sent successfully");
        }
        
    } else {
        debug_log("Non-POST request received: " . $_SERVER['REQUEST_METHOD']);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]);
    }
    
} catch (Exception $e) {
    debug_log("Fatal exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    debug_log("Fatal error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>