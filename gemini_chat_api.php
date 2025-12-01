<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/ai_config.php';
require_once __DIR__ . '/db.php';

// Map id danh mục gốc theo giới tính từ DB
// Theo ảnh bạn cung cấp: Nam=3, Nữ=4, Trẻ Em=8
$GENDER_ROOT_IDS = [ 'nam' => 3, 'nu' => 4, 'tre_em' => 8 ];

// Fallback khi AI không phản hồi: tạo câu trả lời thân thiện theo ngữ cảnh
function buildFallbackReply($message){
  $t = normalize(trim($message ?? ''));
  // Tư vấn size
  if (strpos($t,'size') !== false || strpos($t,'kich thuoc') !== false || strpos($t,'kích thước') !== false){
    return "Hiện hệ thống AI chưa phản hồi. Gợi ý chọn size nhanh:\n"
         . "- Vui lòng cho biết chiều cao, cân nặng, và sở thích ôm/thoáng để tư vấn chính xác.\n"
         . "- Quy đổi tham khảo: S ~ 45–55kg / M ~ 55–65kg / L ~ 65–75kg / XL ~ 75–85kg (tuỳ form áo/quần).\n"
         . "- Nếu sản phẩm có size chart ở trang chi tiết, bạn đối chiếu vòng ngực/vòng eo để chọn.\n"
         . "Bạn có thể chọn màu/size trong thẻ sản phẩm rồi gửi câu hỏi để tôi đánh giá cụ thể hơn.";
  }
  // Hỏi tình trạng kho
  if (strpos($t,'kho') !== false || strpos($t,'con hang') !== false || strpos($t,'còn hàng') !== false || strpos($t,'stock') !== false){
    return "Hiện hệ thống AI chưa phản hồi. Về tình trạng kho: vui lòng chọn màu và size cụ thể trong thẻ sản phẩm, sau đó nhấn 'Gửi câu hỏi' hoặc 'Thêm vào giỏ'. Nếu hệ thống báo hết hàng, tôi sẽ gợi ý mẫu tương tự hoặc size/màu thay thế.";
  }
  // Mặc định
  return "Xin lỗi, hệ thống tư vấn AI đang bận. Bạn hãy cho tôi biết giới tính (nam/nữ/trẻ em), chiều cao, cân nặng và phong cách mong muốn để tôi gợi ý sản phẩm và size phù hợp.";
}

function getJsonInput(){
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function normalize($str){
  $str = mb_strtolower($str, 'UTF-8');
  $accents = ['á','à','ạ','ả','ã','â','ấ','ầ','ậ','ẩ','ẫ','ă','ắ','ằ','ặ','ẳ','ẵ','é','è','ẹ','ẻ','ẽ','ê','ế','ề','ệ','ể','ễ','í','ì','ị','ỉ','ĩ','ó','ò','ọ','ỏ','õ','ô','ố','ồ','ộ','ổ','ỗ','ơ','ớ','ờ','ợ','ở','ỡ','ú','ù','ụ','ủ','ũ','ư','ứ','ừ','ự','ử','ữ','ý','ỳ','ỵ','ỷ','ỹ','đ'];
  $plain   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
  return str_replace($accents, $plain, $str);
}

function buildConversationContents($message){
  if (!isset($_SESSION['cbx_history'])) $_SESSION['cbx_history'] = [];
  $history = $_SESSION['cbx_history'];
  // Giới hạn 10 lượt trao đổi gần nhất
  $history = array_slice($history, max(0, count($history) - 10));

  $contents = [];
  foreach ($history as $item){
    $contents[] = [ 'role' => $item['role'], 'parts' => [['text' => $item['text']]] ];
  }
  // Thêm câu hỏi mới của người dùng
  $contents[] = [ 'role' => 'user', 'parts' => [['text' => $message]] ];
  return $contents;
}

function callGeminiWithConversation($contents){
  $url = GEMINI_API_URL . '?key=' . urlencode(GEMINI_API_KEY);
  $payload = [
    'systemInstruction' => [
      'role' => 'system',
      'parts' => [[ 'text' => 'Bạn là AI trợ lý cho cửa hàng thời trang Luxury Store. Trả lời tự nhiên, súc tích bằng tiếng Việt; khi người dùng hỏi về áo/quần hãy hỏi họ thêm về giới tính (nam/nữ/trẻ em), phong cách và mức giá nếu chưa rõ.' ]]
    ],
    'contents' => $contents
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [ 'Content-Type: application/json' ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => GEMINI_TIMEOUT,
  ]);
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) return [ 'success' => false, 'message' => 'Curl error', 'error' => $err ];
  if ($status < 200 || $status >= 300) return [ 'success' => false, 'message' => 'Bad status', 'status' => $status, 'raw' => $resp ];

  $data = json_decode($resp, true);
  $text = '';
  if (isset($data['candidates'][0]['content']['parts'])){
    foreach ($data['candidates'][0]['content']['parts'] as $p){ if (isset($p['text'])) $text .= $p['text']; }
  }
  return [ 'success' => true, 'text' => $text ?: 'Không có phản hồi nội dung.', 'raw' => $data ];
}

function detectIntent($text){
  $t = normalize($text);
  $isAo   = strpos($t, 'ao') !== false;
  $isQuan = strpos($t, 'quan') !== false;
  $gender = null;
  if (strpos($t, 'nam') !== false) $gender = 'nam';
  if (strpos($t, 'nu') !== false)  $gender = 'nu';
  if (strpos($t, 'tre em') !== false || strpos($t, 'tre') !== false || strpos($t, 'be') !== false) $gender = 'tre_em';
  if (!$isAo && !$isQuan) return null;
  return [ 'type' => $isAo ? 'ao' : 'quan', 'gender' => $gender ];
}

function queryProductsByIntent(PDO $pdo, $intent, $limit = 6){
  if (!$intent) return [];
  $conds = [];
  $params = [];

  // Tìm theo tên danh mục HOẶC tên sản phẩm, và loại trừ ở cả hai phía.
  if ($intent['type'] === 'ao'){
    $conds[] = "LOWER(c.name) NOT LIKE :notQuan AND LOWER(p.name) NOT LIKE :notQuan_p AND LOWER(c.name) NOT LIKE :notQuan_noacc AND LOWER(p.name) NOT LIKE :notQuan_p_noacc";
    $params[':notQuan'] = '%quần%';
    $params[':notQuan_p'] = '%quần%';
    $params[':notQuan_noacc'] = '%quan%';
    $params[':notQuan_p_noacc'] = '%quan%';
  } else {
    $conds[] = "LOWER(c.name) NOT LIKE :notAo AND LOWER(p.name) NOT LIKE :notAo_p AND LOWER(c.name) NOT LIKE :notAo_noacc AND LOWER(p.name) NOT LIKE :notAo_p_noacc";
    $params[':notAo'] = '%áo%';
    $params[':notAo_p'] = '%áo%';
    $params[':notAo_noacc'] = '%ao%';
    $params[':notAo_p_noacc'] = '%ao%';
  }

  if ($intent['gender'] === 'nam'){
    // Không bắt buộc tên chứa 'nam'; ưu tiên theo parent_id và loại trừ chéo
    $conds[] = "LOWER(c.name) NOT LIKE :notTre1 AND LOWER(p.name) NOT LIKE :notTre1_p"; $params[':notTre1'] = '%trẻ%'; $params[':notTre1_p'] = '%trẻ%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTre2 AND LOWER(p.name) NOT LIKE :notTre2_p"; $params[':notTre2'] = '%em%';   $params[':notTre2_p'] = '%em%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNu AND LOWER(p.name) NOT LIKE :notNu_p"; $params[':notNu'] = '%nữ%'; $params[':notNu_p'] = '%nữ%';
    // Loại trừ thêm các từ khóa trẻ em
    $conds[] = "LOWER(c.name) NOT LIKE :notBe AND LOWER(p.name) NOT LIKE :notBe_p"; $params[':notBe'] = '%bé%'; $params[':notBe_p'] = '%bé%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBeNoacc AND LOWER(p.name) NOT LIKE :notBeNoacc_p"; $params[':notBeNoacc'] = '%be%'; $params[':notBeNoacc_p'] = '%be%';
    $conds[] = "LOWER(c.name) NOT LIKE :notKid AND LOWER(p.name) NOT LIKE :notKid_p"; $params[':notKid'] = '%kid%'; $params[':notKid_p'] = '%kid%';
    $conds[] = "LOWER(c.name) NOT LIKE :notChildren AND LOWER(p.name) NOT LIKE :notChildren_p"; $params[':notChildren'] = '%children%'; $params[':notChildren_p'] = '%children%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBoys AND LOWER(p.name) NOT LIKE :notBoys_p"; $params[':notBoys'] = '%boys%'; $params[':notBoys_p'] = '%boys%';
    $conds[] = "LOWER(c.name) NOT LIKE :notGirls AND LOWER(p.name) NOT LIKE :notGirls_p"; $params[':notGirls'] = '%girls%'; $params[':notGirls_p'] = '%girls%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNhi AND LOWER(p.name) NOT LIKE :notNhi_p"; $params[':notNhi'] = '%nhí%'; $params[':notNhi_p'] = '%nhí%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNhiNoacc AND LOWER(p.name) NOT LIKE :notNhiNoacc_p"; $params[':notNhiNoacc'] = '%nhi%'; $params[':notNhiNoacc_p'] = '%nhi%';
    $conds[] = "LOWER(c.name) NOT LIKE :notThieuNien AND LOWER(p.name) NOT LIKE :notThieuNien_p"; $params[':notThieuNien'] = '%thiếu niên%'; $params[':notThieuNien_p'] = '%thiếu niên%';
    $conds[] = "LOWER(c.name) NOT LIKE :notThieuNienNoacc AND LOWER(p.name) NOT LIKE :notThieuNienNoacc_p"; $params[':notThieuNienNoacc'] = '%thieu nien%'; $params[':notThieuNienNoacc_p'] = '%thieu nien%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBaby AND LOWER(p.name) NOT LIKE :notBaby_p"; $params[':notBaby'] = '%baby%'; $params[':notBaby_p'] = '%baby%';
    $conds[] = "LOWER(c.name) NOT LIKE :notYouth AND LOWER(p.name) NOT LIKE :notYouth_p"; $params[':notYouth'] = '%youth%'; $params[':notYouth_p'] = '%youth%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTeen AND LOWER(p.name) NOT LIKE :notTeen_p"; $params[':notTeen'] = '%teen%'; $params[':notTeen_p'] = '%teen%';
    // Ràng buộc theo danh mục Nam theo cả hai mô hình (id gốc 3 hoặc parent_id=1)
    $conds[] = "(c.id = :rootNam OR c.parent_id = :rootNam OR c.parent_id = 1)";
    $params[':rootNam'] = $GLOBALS['GENDER_ROOT_IDS']['nam'] ?? 3;
  } elseif ($intent['gender'] === 'nu'){
    // Không bắt buộc tên chứa 'nữ'; ưu tiên theo parent_id và loại trừ chéo
    $conds[] = "LOWER(c.name) NOT LIKE :notTre1 AND LOWER(p.name) NOT LIKE :notTre1_p"; $params[':notTre1'] = '%trẻ%'; $params[':notTre1_p'] = '%trẻ%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTre2 AND LOWER(p.name) NOT LIKE :notTre2_p"; $params[':notTre2'] = '%em%';   $params[':notTre2_p'] = '%em%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNam AND LOWER(p.name) NOT LIKE :notNam_p"; $params[':notNam'] = '%nam%'; $params[':notNam_p'] = '%nam%';
    // Loại trừ thêm các từ khóa trẻ em
    $conds[] = "LOWER(c.name) NOT LIKE :notBe AND LOWER(p.name) NOT LIKE :notBe_p"; $params[':notBe'] = '%bé%'; $params[':notBe_p'] = '%bé%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBeNoacc AND LOWER(p.name) NOT LIKE :notBeNoacc_p"; $params[':notBeNoacc'] = '%be%'; $params[':notBeNoacc_p'] = '%be%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTrai AND LOWER(p.name) NOT LIKE :notTrai_p"; $params[':notTrai'] = '%trai%'; $params[':notTrai_p'] = '%trai%';
    $conds[] = "LOWER(c.name) NOT LIKE :notGai AND LOWER(p.name) NOT LIKE :notGai_p"; $params[':notGai'] = '%gái%'; $params[':notGai_p'] = '%gái%';
    $conds[] = "LOWER(c.name) NOT LIKE :notGaiNoacc AND LOWER(p.name) NOT LIKE :notGaiNoacc_p"; $params[':notGaiNoacc'] = '%gai%'; $params[':notGaiNoacc_p'] = '%gai%';
    $conds[] = "LOWER(c.name) NOT LIKE :notKid AND LOWER(p.name) NOT LIKE :notKid_p"; $params[':notKid'] = '%kid%'; $params[':notKid_p'] = '%kid%';
    $conds[] = "LOWER(c.name) NOT LIKE :notChildren AND LOWER(p.name) NOT LIKE :notChildren_p"; $params[':notChildren'] = '%children%'; $params[':notChildren_p'] = '%children%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBoys AND LOWER(p.name) NOT LIKE :notBoys_p"; $params[':notBoys'] = '%boys%'; $params[':notBoys_p'] = '%boys%';
    $conds[] = "LOWER(c.name) NOT LIKE :notGirls AND LOWER(p.name) NOT LIKE :notGirls_p"; $params[':notGirls'] = '%girls%'; $params[':notGirls_p'] = '%girls%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNhi AND LOWER(p.name) NOT LIKE :notNhi_p"; $params[':notNhi'] = '%nhí%'; $params[':notNhi_p'] = '%nhí%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNhiNoacc AND LOWER(p.name) NOT LIKE :notNhiNoacc_p"; $params[':notNhiNoacc'] = '%nhi%'; $params[':notNhiNoacc_p'] = '%nhi%';
    $conds[] = "LOWER(c.name) NOT LIKE :notThieuNien AND LOWER(p.name) NOT LIKE :notThieuNien_p"; $params[':notThieuNien'] = '%thiếu niên%'; $params[':notThieuNien_p'] = '%thiếu niên%';
    $conds[] = "LOWER(c.name) NOT LIKE :notThieuNienNoacc AND LOWER(p.name) NOT LIKE :notThieuNienNoacc_p"; $params[':notThieuNienNoacc'] = '%thieu nien%'; $params[':notThieuNienNoacc_p'] = '%thieu nien%';
    $conds[] = "LOWER(c.name) NOT LIKE :notBaby AND LOWER(p.name) NOT LIKE :notBaby_p"; $params[':notBaby'] = '%baby%'; $params[':notBaby_p'] = '%baby%';
    $conds[] = "LOWER(c.name) NOT LIKE :notYouth AND LOWER(p.name) NOT LIKE :notYouth_p"; $params[':notYouth'] = '%youth%'; $params[':notYouth_p'] = '%youth%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTeen AND LOWER(p.name) NOT LIKE :notTeen_p"; $params[':notTeen'] = '%teen%'; $params[':notTeen_p'] = '%teen%';
    // Ràng buộc theo danh mục Nữ theo cả hai mô hình (id gốc 4 hoặc parent_id=2)
    $conds[] = "(c.id = :rootNu OR c.parent_id = :rootNu OR c.parent_id = 2)";
    $params[':rootNu'] = $GLOBALS['GENDER_ROOT_IDS']['nu'] ?? 4;
  } elseif ($intent['gender'] === 'tre_em'){
    // Không bắt buộc tên chứa 'trẻ'; ưu tiên theo parent_id
    $conds[] = "LOWER(c.name) NOT LIKE :exNam AND LOWER(p.name) NOT LIKE :exNam_p"; $params[':exNam'] = '%nam%'; $params[':exNam_p'] = '%nam%';
    $conds[] = "LOWER(c.name) NOT LIKE :exNu AND LOWER(p.name) NOT LIKE :exNu_p"; $params[':exNu'] = '%nữ%'; $params[':exNu_p'] = '%nữ%';
    // Ràng buộc theo danh mục Trẻ em theo cả hai mô hình (id gốc 8 hoặc parent_id=3)
    $conds[] = "(c.id = :rootTre OR c.parent_id = :rootTre OR c.parent_id = 3)";
    $params[':rootTre'] = $GLOBALS['GENDER_ROOT_IDS']['tre_em'] ?? 8;
  }

  $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
  $sql = "SELECT p.id, p.name, p.price, p.discount_price, p.image as image_url, c.name as category_name
          FROM products p LEFT JOIN categories c ON p.category_id = c.id
          $where
          ORDER BY p.created_at DESC LIMIT :limit";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k=>$v){ $stmt->bindValue($k, $v); }
  $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  // Chuẩn hóa ảnh nếu thiếu
  foreach ($rows as &$r){
    $raw = isset($r['image_url']) ? trim($r['image_url']) : '';
    $r['image_url'] = resolveImagePath($raw);
  }
  // Fallback: nếu không có kết quả, thử truy vấn chỉ theo giới tính (bỏ lọc loại áo/quần)
  if ((!$rows || count($rows)===0) && isset($intent['gender']) && $intent['gender']){
    $conds2=[];$params2=[];
    if ($intent['gender']==='nam'){
      $conds2[] = "(c.id = :rootNam OR c.parent_id = :rootNam OR c.parent_id = 1)";
      $params2[':rootNam'] = $GLOBALS['GENDER_ROOT_IDS']['nam'] ?? 3;
      $conds2[] = "LOWER(c.name) NOT LIKE :notNu AND LOWER(p.name) NOT LIKE :notNu_p"; $params2[':notNu'] = '%nữ%'; $params2[':notNu_p'] = '%nữ%';
      $conds2[] = "LOWER(c.name) NOT LIKE :notTre AND LOWER(p.name) NOT LIKE :notTre_p"; $params2[':notTre'] = '%trẻ%'; $params2[':notTre_p'] = '%trẻ%';
      $conds2[] = "LOWER(c.name) NOT LIKE :notEm AND LOWER(p.name) NOT LIKE :notEm_p"; $params2[':notEm'] = '%em%'; $params2[':notEm_p'] = '%em%';
    } elseif ($intent['gender']==='nu'){
      $conds2[] = "(c.id = :rootNu OR c.parent_id = :rootNu OR c.parent_id = 2)";
      $params2[':rootNu'] = $GLOBALS['GENDER_ROOT_IDS']['nu'] ?? 4;
      $conds2[] = "LOWER(c.name) NOT LIKE :notNam AND LOWER(p.name) NOT LIKE :notNam_p"; $params2[':notNam'] = '%nam%'; $params2[':notNam_p'] = '%nam%';
      $conds2[] = "LOWER(c.name) NOT LIKE :notTre AND LOWER(p.name) NOT LIKE :notTre_p"; $params2[':notTre'] = '%trẻ%'; $params2[':notTre_p'] = '%trẻ%';
      $conds2[] = "LOWER(c.name) NOT LIKE :notEm AND LOWER(p.name) NOT LIKE :notEm_p"; $params2[':notEm'] = '%em%'; $params2[':notEm_p'] = '%em%';
    } elseif ($intent['gender']==='tre_em'){
      $conds2[] = "(c.id = :rootTre OR c.parent_id = :rootTre OR c.parent_id = 3)";
      $params2[':rootTre'] = $GLOBALS['GENDER_ROOT_IDS']['tre_em'] ?? 8;
      $conds2[] = "LOWER(c.name) NOT LIKE :exNam AND LOWER(p.name) NOT LIKE :exNam_p"; $params2[':exNam'] = '%nam%'; $params2[':exNam_p'] = '%nam%';
      $conds2[] = "LOWER(c.name) NOT LIKE :exNu AND LOWER(p.name) NOT LIKE :exNu_p"; $params2[':exNu'] = '%nữ%'; $params2[':exNu_p'] = '%nữ%';
    }
    $where2 = $conds2 ? ('WHERE ' . implode(' AND ', $conds2)) : '';
    $sql2 = "SELECT p.id, p.name, p.price, p.discount_price, p.image as image_url, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where2 ORDER BY p.created_at DESC LIMIT :limit";
    $stmt2 = $pdo->prepare($sql2);
    foreach($params2 as $k=>$v){ $stmt2->bindValue($k,$v); }
    $stmt2->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt2->execute();
    $rows2 = $stmt2->fetchAll();
    foreach($rows2 as &$r){ $r['image_url'] = resolveImagePath($r['image_url'] ?? ''); }
    return $rows2;
  }
  return $rows;
}

// Chuẩn hóa đường dẫn ảnh từ DB thành URL tương đối hợp lệ cho chatbox
function resolveImagePath($raw){
  if (!$raw) return 'images/product-placeholder.jpg';
  if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) return $raw;
  if (str_contains($raw, 'admin/')) return $raw;
  if (str_contains($raw, 'uploads/')) return 'admin/' . ltrim($raw, '/');
  if (str_contains($raw, 'images/')) return $raw;
  $base = basename($raw);
  return $base ? ('admin/uploads/' . $base) : 'images/product-placeholder.jpg';
}

try {
  $in = getJsonInput();
  $message = trim($in['message'] ?? '');
  if ($message === '') { echo json_encode([ 'success' => false, 'message' => 'Thiếu trường message' ]); exit; }

  // Xây dựng hội thoại liên tục
  $contents = buildConversationContents($message);
  $ai = callGeminiWithConversation($contents);
  // Nếu AI lỗi, tạo fallback trả lời để UI không bị trống
  if (!$ai['success']){
    $ai = [ 'success' => true, 'text' => buildFallbackReply($message) ];
  }
  if ($ai['success']){
    // Lưu vào session history
    $_SESSION['cbx_history'][] = [ 'role' => 'user',  'text' => $message ];
    $_SESSION['cbx_history'][] = [ 'role' => 'model', 'text' => $ai['text'] ];
  }

  // Phân loại nhu cầu và gợi ý sản phẩm
  $intent = detectIntent($message);
  $products = queryProductsByIntent($GLOBALS['pdo'], $intent, 6);

  echo json_encode([ 'success' => $ai['success'], 'text' => $ai['text'] ?? '', 'products' => $products, 'intent' => $intent ]);
} catch (Throwable $e) {
  echo json_encode([ 'success' => false, 'message' => 'Server error', 'error' => $e->getMessage() ]);
}