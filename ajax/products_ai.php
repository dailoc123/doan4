<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../db.php';

// Map id danh mục gốc theo giới tính từ DB của bạn
// Theo ảnh bạn cung cấp: Nam=3, Nữ=4, Trẻ Em=8
$GENDER_ROOT_IDS = [ 'nam' => 3, 'nu' => 4, 'tre_em' => 8 ];

function normalize($str){
  $str = mb_strtolower($str, 'UTF-8');
  $accents = ['á','à','ạ','ả','ã','â','ấ','ầ','ậ','ẩ','ẫ','ă','ắ','ằ','ặ','ẳ','ẵ','é','è','ẹ','ẻ','ẽ','ê','ế','ề','ệ','ể','ễ','í','ì','ị','ỉ','ĩ','ó','ò','ọ','ỏ','õ','ô','ố','ồ','ộ','ổ','ỗ','ơ','ớ','ờ','ợ','ở','ỡ','ú','ù','ụ','ủ','ũ','ư','ứ','ừ','ự','ử','ữ','ý','ỳ','ỵ','ỷ','ỹ','đ'];
  $plain   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
  return str_replace($accents, $plain, $str);
}

function detectIntent($q){
  $t = normalize($q);
  $isAo   = strpos($t,'ao') !== false;
  $isQuan = strpos($t,'quan') !== false;
  $gender = null;
  if (strpos($t,'nam') !== false) $gender = 'nam';
  if (strpos($t,'nu') !== false)  $gender = 'nu';
  if (strpos($t,'tre em') !== false || strpos($t,'tre') !== false || strpos($t,'be') !== false) $gender = 'tre_em';
  if (!$isAo && !$isQuan) return null;
  return [ 'type' => $isAo ? 'ao' : 'quan', 'gender' => $gender ];
}

function queryByIntent(PDO $pdo, $intent, $limit=8){
  if (!$intent) return [];
  $conds = [];$params = [];

  // Tìm theo tên danh mục HOẶC tên sản phẩm. Đồng thời loại trừ chính xác ở cả hai phía.
  if ($intent['type']==='ao'){
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

  if ($intent['gender']==='nam'){
    // Không bắt buộc tên chứa 'nam'; ưu tiên theo parent_id và loại trừ chéo
    // Loại trừ trẻ em và danh mục nữ
    $conds[] = "LOWER(c.name) NOT LIKE :notTre1 AND LOWER(p.name) NOT LIKE :notTre1_p"; $params[':notTre1'] = '%trẻ%'; $params[':notTre1_p'] = '%trẻ%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTre2 AND LOWER(p.name) NOT LIKE :notTre2_p"; $params[':notTre2'] = '%em%';   $params[':notTre2_p'] = '%em%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNu AND LOWER(p.name) NOT LIKE :notNu_p"; $params[':notNu'] = '%nữ%'; $params[':notNu_p'] = '%nữ%';
    // Loại trừ thêm các từ khóa trẻ em phổ biến (không dấu và tiếng Anh)
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
    // Ràng buộc theo danh mục Nam theo cả hai mô hình:
    // (1) cây danh mục: id gốc 3 hoặc con của 3
    // (2) trường parent_id dùng làm giới tính: parent_id = 1
    $conds[] = "(c.id = :rootNam OR c.parent_id = :rootNam OR c.parent_id = 1)";
    $params[':rootNam'] = $GLOBALS['GENDER_ROOT_IDS']['nam'] ?? 3;
  } elseif ($intent['gender']==='nu'){
    // Không bắt buộc tên chứa 'nữ'; ưu tiên theo parent_id và loại trừ chéo
    // Loại trừ trẻ em và danh mục nam
    $conds[] = "LOWER(c.name) NOT LIKE :notTre1 AND LOWER(p.name) NOT LIKE :notTre1_p"; $params[':notTre1'] = '%trẻ%'; $params[':notTre1_p'] = '%trẻ%';
    $conds[] = "LOWER(c.name) NOT LIKE :notTre2 AND LOWER(p.name) NOT LIKE :notTre2_p"; $params[':notTre2'] = '%em%';   $params[':notTre2_p'] = '%em%';
    $conds[] = "LOWER(c.name) NOT LIKE :notNam AND LOWER(p.name) NOT LIKE :notNam_p"; $params[':notNam'] = '%nam%'; $params[':notNam_p'] = '%nam%';
    // Loại trừ thêm các từ khóa trẻ em phổ biến
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
    // Ràng buộc theo danh mục Nữ theo cả hai mô hình:
    // (1) cây danh mục: id gốc 4 hoặc con của 4
    // (2) trường parent_id dùng làm giới tính: parent_id = 2
    $conds[] = "(c.id = :rootNu OR c.parent_id = :rootNu OR c.parent_id = 2)";
    $params[':rootNu'] = $GLOBALS['GENDER_ROOT_IDS']['nu'] ?? 4;
  } elseif ($intent['gender']==='tre_em'){
    // Không bắt buộc tên chứa 'trẻ'; ưu tiên theo parent_id
    // Ưu tiên danh mục trẻ em: loại trừ nam/nữ nếu có
    $conds[] = "LOWER(c.name) NOT LIKE :exNam AND LOWER(p.name) NOT LIKE :exNam_p"; $params[':exNam'] = '%nam%'; $params[':exNam_p'] = '%nam%';
    $conds[] = "LOWER(c.name) NOT LIKE :exNu AND LOWER(p.name) NOT LIKE :exNu_p"; $params[':exNu'] = '%nữ%'; $params[':exNu_p'] = '%nữ%';
    // Ràng buộc theo danh mục Trẻ em theo cả hai mô hình:
    // (1) cây danh mục: id gốc 8 hoặc con của 8
    // (2) trường parent_id dùng làm giới tính: parent_id = 3
    $conds[] = "(c.id = :rootTre OR c.parent_id = :rootTre OR c.parent_id = 3)";
    $params[':rootTre'] = $GLOBALS['GENDER_ROOT_IDS']['tre_em'] ?? 8;
  }

  $where = $conds ? ('WHERE '.implode(' AND ',$conds)) : '';
  $sql = "SELECT p.id,p.name,p.price,p.discount_price,p.image as image_url,c.name as category_name
          FROM products p LEFT JOIN categories c ON p.category_id=c.id
          $where ORDER BY p.created_at DESC LIMIT :limit";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k=>$v){ $stmt->bindValue($k,$v); }
  $stmt->bindValue(':limit',(int)$limit,PDO::PARAM_INT);
  $stmt->execute();
  $rows=$stmt->fetchAll();
  // Chuẩn hóa đường dẫn ảnh để hiển thị đúng trong chatbox
  foreach($rows as &$r){
    $raw = isset($r['image_url']) ? trim($r['image_url']) : '';
    $r['image_url'] = resolveImagePath($raw);
  }
  // Fallback: nếu không có kết quả, thử truy vấn chỉ theo giới tính (bỏ lọc loại áo/quần)
  if ((!$rows || count($rows)===0) && isset($intent['gender']) && $intent['gender']){
    $conds2 = [];$params2 = [];
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
    $where2 = $conds2 ? ('WHERE '.implode(' AND ',$conds2)) : '';
    $sql2 = "SELECT p.id,p.name,p.price,p.discount_price,p.image as image_url,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where2 ORDER BY p.created_at DESC LIMIT :limit";
    $stmt2 = $pdo->prepare($sql2);
    foreach($params2 as $k=>$v){ $stmt2->bindValue($k,$v); }
    $stmt2->bindValue(':limit',(int)$limit,PDO::PARAM_INT);
    $stmt2->execute();
    $rows2 = $stmt2->fetchAll();
    foreach($rows2 as &$r){ $r['image_url'] = resolveImagePath($r['image_url'] ?? ''); }
    return $rows2;
  }
  return $rows;
}

// Chuẩn hóa đường dẫn ảnh từ DB thành URL tương đối hợp lệ
function resolveImagePath($raw){
  if (!$raw) return 'images/product-placeholder.jpg';
  // Nếu là URL tuyệt đối hoặc đường dẫn gốc, dùng nguyên bản
  if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, '/')) return $raw;
  // Nếu đã có "admin/" thì giữ nguyên
  if (str_contains($raw, 'admin/')) return $raw;
  // Nếu là kiểu 'uploads/filename.ext' thì thêm prefix 'admin/'
  if (str_contains($raw, 'uploads/')) return 'admin/' . ltrim($raw, '/');
  // Nếu là 'images/...' thì giữ nguyên vì tương đối từ backend/
  if (str_contains($raw, 'images/')) return $raw;
  // Mặc định: lấy basename và trỏ tới admin/uploads
  $base = basename($raw);
  return $base ? ('admin/uploads/' . $base) : 'images/product-placeholder.jpg';
}

// Truy vấn theo tham số rõ ràng (type, gender_id) nếu được cung cấp từ UI
function queryByExplicit(PDO $pdo, $type, $genderId, $limit=8){
  $conds = [];$params = [];
  // Lọc theo loại
  if ($type === 'ao'){
    $conds[] = "LOWER(c.name) NOT LIKE :notQuan AND LOWER(p.name) NOT LIKE :notQuan_p AND LOWER(c.name) NOT LIKE :notQuan_noacc AND LOWER(p.name) NOT LIKE :notQuan_p_noacc";
    $params[':notQuan'] = '%quần%';
    $params[':notQuan_p'] = '%quần%';
    $params[':notQuan_noacc'] = '%quan%';
    $params[':notQuan_p_noacc'] = '%quan%';
  } elseif ($type === 'quan'){
    $conds[] = "LOWER(c.name) NOT LIKE :notAo AND LOWER(p.name) NOT LIKE :notAo_p AND LOWER(c.name) NOT LIKE :notAo_noacc AND LOWER(p.name) NOT LIKE :notAo_p_noacc";
    $params[':notAo'] = '%áo%';
    $params[':notAo_p'] = '%áo%';
    $params[':notAo_noacc'] = '%ao%';
    $params[':notAo_p_noacc'] = '%ao%';
  }

  // Lọc theo giới tính 1 tầng: id gốc 3/4/8;
  // Đồng thời hỗ trợ mô hình cũ dùng parent_id=1/2/3.
  if ($genderId){
    if ((int)$genderId === ($GLOBALS['GENDER_ROOT_IDS']['nam'] ?? 3)){
      $conds[] = "(c.id = :rootNam OR c.parent_id = :rootNam OR c.parent_id = 1)";
      $params[':rootNam'] = $GLOBALS['GENDER_ROOT_IDS']['nam'] ?? 3;
      // Loại trừ phổ biến cho trẻ em & nữ
      $conds[] = "LOWER(c.name) NOT LIKE :exNu AND LOWER(p.name) NOT LIKE :exNu_p"; $params[':exNu']='%nữ%'; $params[':exNu_p']='%nữ%';
      $conds[] = "LOWER(c.name) NOT LIKE :exTre AND LOWER(p.name) NOT LIKE :exTre_p"; $params[':exTre']='%trẻ%'; $params[':exTre_p']='%trẻ%';
      $conds[] = "LOWER(c.name) NOT LIKE :exEm AND LOWER(p.name) NOT LIKE :exEm_p"; $params[':exEm']='%em%'; $params[':exEm_p']='%em%';
    } elseif ((int)$genderId === ($GLOBALS['GENDER_ROOT_IDS']['nu'] ?? 4)){
      $conds[] = "(c.id = :rootNu OR c.parent_id = :rootNu OR c.parent_id = 2)";
      $params[':rootNu'] = $GLOBALS['GENDER_ROOT_IDS']['nu'] ?? 4;
      $conds[] = "LOWER(c.name) NOT LIKE :exNam AND LOWER(p.name) NOT LIKE :exNam_p"; $params[':exNam']='%nam%'; $params[':exNam_p']='%nam%';
      $conds[] = "LOWER(c.name) NOT LIKE :exTre AND LOWER(p.name) NOT LIKE :exTre_p"; $params[':exTre']='%trẻ%'; $params[':exTre_p']='%trẻ%';
      $conds[] = "LOWER(c.name) NOT LIKE :exEm AND LOWER(p.name) NOT LIKE :exEm_p"; $params[':exEm']='%em%'; $params[':exEm_p']='%em%';
    } elseif ((int)$genderId === ($GLOBALS['GENDER_ROOT_IDS']['tre_em'] ?? 8)){
      $conds[] = "(c.id = :rootTre OR c.parent_id = :rootTre OR c.parent_id = 3)";
      $params[':rootTre'] = $GLOBALS['GENDER_ROOT_IDS']['tre_em'] ?? 8;
      $conds[] = "LOWER(c.name) NOT LIKE :exNam AND LOWER(p.name) NOT LIKE :exNam_p"; $params[':exNam']='%nam%'; $params[':exNam_p']='%nam%';
      $conds[] = "LOWER(c.name) NOT LIKE :exNu AND LOWER(p.name) NOT LIKE :exNu_p"; $params[':exNu']='%nữ%'; $params[':exNu_p']='%nữ%';
    }
  }

  $where = $conds ? ('WHERE '.implode(' AND ',$conds)) : '';
  $sql = "SELECT p.id,p.name,p.price,p.discount_price,p.image as image_url,c.name as category_name
          FROM products p LEFT JOIN categories c ON p.category_id=c.id
          $where ORDER BY p.created_at DESC LIMIT :limit";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k=>$v){ $stmt->bindValue($k,$v); }
  $stmt->bindValue(':limit',(int)$limit,PDO::PARAM_INT);
  $stmt->execute();
  $rows=$stmt->fetchAll();
  foreach($rows as &$r){ $r['image_url']=resolveImagePath($r['image_url'] ?? ''); }
  return $rows;
}

try{
  $query = $_GET['q'] ?? ($_POST['q'] ?? '');
  $limit = (int)($_GET['limit'] ?? ($_POST['limit'] ?? 8));
  $explicitType = $_GET['type'] ?? ($_POST['type'] ?? ''); // 'ao' | 'quan'
  $explicitGenderId = isset($_GET['gender_id']) ? (int)$_GET['gender_id'] : (isset($_POST['gender_id']) ? (int)$_POST['gender_id'] : 0);

  if ($explicitType || $explicitGenderId){
    // Nếu UI gửi tham số rõ ràng, bỏ qua NLP để tránh sai lệch
    $products = queryByExplicit($GLOBALS['pdo'], $explicitType ?: null, $explicitGenderId ?: null, $limit);
    echo json_encode(['success'=>true,'products'=>$products,'intent'=>['type'=>$explicitType?:null,'gender_id'=>$explicitGenderId?:null,'source'=>'explicit']]);
    return;
  }

  // Ngược lại, dùng phân tích câu hỏi tự nhiên như trước
  $intent = detectIntent($query);
  $products = queryByIntent($GLOBALS['pdo'], $intent, $limit);
  echo json_encode(['success'=>true,'products'=>$products,'intent'=>$intent]);
}catch(Throwable $e){
  echo json_encode(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()]);
}