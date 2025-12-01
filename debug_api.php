<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    public function analyzeAndSearchProducts($message, $user_id = null) {
        echo "=== ANALYZE AND SEARCH PRODUCTS ===\n";
        echo "Message: $message\n";
        
        $search_terms = $this->extractSearchTerms($message);
        echo "Search terms: " . implode(', ', $search_terms) . "\n";
        
        $categories = $this->db->getCategories();
        echo "Available categories: " . implode(', ', array_column($categories, 'name')) . "\n";
        
        // Tìm kiếm kết hợp từ khóa và danh mục
        foreach ($search_terms as $term) {
            foreach ($categories as $category) {
                if (stripos($category['name'], $term) !== false) {
                    echo "Found category match: {$category['name']} for term: $term\n";
                    $category_products = $this->db->getProductsByCategory($category['name']);
                    echo "Products in category {$category['name']}: " . count($category_products) . "\n";
                    
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
                        echo "Filtered products: " . count($filtered_products) . "\n";
                        $result = $this->formatProductResults($filtered_products, "KẾT QUẢ TÌM KIẾM '{$term}' TRONG DANH MỤC '{$category['name']}':");
                        echo "Returning result (length: " . strlen($result) . ")\n";
                        return $result;
                    }
                }
            }
        }
        
        // Tìm kiếm theo từ khóa
        foreach ($search_terms as $term) {
            $products = $this->db->searchProducts($term);
            echo "Products for term '$term': " . count($products) . "\n";
            if (!empty($products)) {
                $result = $this->formatProductResults($products, "KẾT QUẢ TÌM KIẾM '$term':");
                echo "Returning result (length: " . strlen($result) . ")\n";
                return $result;
            }
        }
        
        echo "No products found, returning empty string\n";
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
    
    public function getAIResponse($message, $language = 'vi', $user_id = null) {
        echo "\n=== GET AI RESPONSE ===\n";
        echo "Message: $message\n";
        echo "Language: $language\n";
        
        // Phân tích tin nhắn để tìm kiếm sản phẩm
        $product_info = $this->analyzeAndSearchProducts($message, $user_id);
        
        echo "\nProduct info result:\n";
        echo "Length: " . strlen($product_info) . "\n";
        echo "Empty: " . (empty($product_info) ? 'YES' : 'NO') . "\n";
        
        // Nếu có thông tin sản phẩm, trả về luôn thông tin đó
        if (!empty($product_info)) {
            if ($language == 'vi') {
                $response = "Dưới đây là các sản phẩm phù hợp với yêu cầu của bạn:\n\n" . $product_info;
            } else {
                $response = "Here are the products that match your request:\n\n" . $product_info;
            }
            echo "Returning product response (length: " . strlen($response) . ")\n";
            return $response;
        }
        
        echo "No product info, returning fallback\n";
        return "Xin chào! Tôi là trợ lý ảo của cửa hàng. Tôi có thể giúp bạn tìm hiểu về sản phẩm, giá cả, và dịch vụ.";
    }
    
    public function processMessage($user_id, $message) {
        echo "\n=== PROCESS MESSAGE ===\n";
        echo "User ID: $user_id\n";
        echo "Message: $message\n";
        
        // Phát hiện ngôn ngữ
        $language = $this->detectLanguage($message);
        echo "Detected language: $language\n";
        
        // Lấy phản hồi từ AI với thông tin sản phẩm thực tế
        $ai_response = $this->getAIResponse($message, $language, $user_id);
        
        echo "\nFinal response:\n";
        echo "Length: " . strlen($ai_response) . "\n";
        echo "Content preview: " . substr($ai_response, 0, 100) . "...\n";
        
        return [
            'success' => true,
            'response' => $ai_response,
            'language' => $language
        ];
    }
    
    private function detectLanguage($message) {
        // Kiểm tra ký tự tiếng Việt
        if (preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/u', $message)) {
            return 'vi';
        }
        return 'en';
    }
}

echo "=== DEBUG CHATBOT API ===\n\n";

$debug = new DebugChatbotAPI();
$result = $debug->processMessage(12345, "tìm áo nam");

echo "\n=== FINAL RESULT ===\n";
print_r($result);
echo "\n=== JSON OUTPUT ===\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>