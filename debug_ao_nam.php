<?php
require_once 'chatbot_config.php';
require_once 'db.php';
require_once 'ai_config.php';
require_once 'chatbot_database.php';

// Tạo instance của ChatbotDatabase
$db_helper = new ChatbotDatabase();

// Test message
$message = "áo nam";
$message_lower = mb_strtolower($message, 'UTF-8');

echo "Testing message: $message\n";
echo "Message lower: $message_lower\n\n";

// Test category keywords
$category_keywords = [
    'áo' => ['áo', 'shirt', 'top', 'blouse', 'tshirt', 't-shirt'],
    'quần' => ['quần', 'pants', 'trousers', 'jeans', 'shorts'],
    'váy' => ['váy', 'dress', 'skirt'],
    'giày' => ['giày', 'shoes', 'sneakers', 'boots', 'sandals'],
    'túi' => ['túi', 'bag', 'handbag', 'backpack'],
    'phụ kiện' => ['phụ kiện', 'accessories', 'jewelry', 'necklace', 'earrings'],
    'nam' => ['nam', 'men', 'male', 'boy', 'gentleman'],
    'nữ' => ['nữ', 'women', 'female', 'girl', 'lady'],
    'trẻ em' => ['trẻ em', 'kids', 'children', 'baby', 'child']
];

echo "Category keywords:\n";
print_r($category_keywords);

// Test category detection
$category_found = null;
foreach ($category_keywords as $category => $keywords) {
    foreach ($keywords as $keyword) {
        if (strpos($message_lower, $keyword) !== false) {
            echo "Found keyword '$keyword' in category '$category'\n";
            $category_found = $category;
        }
    }
}

echo "\nCategory found: " . ($category_found ?: 'None') . "\n\n";

// Test search by category
if ($category_found) {
    echo "Searching products by category: $category_found\n";
    $category_products = $db_helper->getProductsByCategory($category_found, 5);
    echo "Products found: " . count($category_products) . "\n";
    
    if (!empty($category_products)) {
        echo "Sample products:\n";
        foreach (array_slice($category_products, 0, 3) as $product) {
            echo "- ID: {$product['id']}, Name: {$product['name']}, Category: {$product['category']}\n";
        }
    }
}

// Test search by keyword
echo "\nTesting keyword search for 'áo':\n";
$search_products = $db_helper->searchProducts('áo', 5);
echo "Products found by keyword search: " . count($search_products) . "\n";

if (!empty($search_products)) {
    echo "Sample products:\n";
    foreach (array_slice($search_products, 0, 3) as $product) {
        echo "- ID: {$product['id']}, Name: {$product['name']}, Category: {$product['category']}\n";
    }
}

// Test combined search
echo "\nTesting combined search (áo + nam):\n";
$combined_products = [];

// First get products by 'áo' category
$ao_products = $db_helper->getProductsByCategory('áo', 20);
echo "Products in 'áo' category: " . count($ao_products) . "\n";

// Then filter by 'nam'
$filtered_products = [];
foreach ($ao_products as $product) {
    if (stripos($product['name'], 'nam') !== false || 
        (isset($product['description']) && stripos($product['description'], 'nam') !== false) ||
        stripos($product['category'], 'nam') !== false) {
        $filtered_products[] = $product;
    }
}

echo "Products filtered for 'nam': " . count($filtered_products) . "\n";

if (!empty($filtered_products)) {
    echo "Sample filtered products:\n";
    foreach (array_slice($filtered_products, 0, 5) as $product) {
        echo "- ID: {$product['id']}, Name: {$product['name']}, Category: {$product['category']}\n";
    }
}
?>