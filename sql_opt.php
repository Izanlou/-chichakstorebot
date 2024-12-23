<?php
define('servername', "localhost");
define('username', "");
define('password', "");
define('dbname', "");
define('SECRET_KEY', "");

function getUserData($username) {
    $conn = new mysqli(servername, username, password, dbname);
    $conn->set_charset("utf8");
    if ($conn->connect_error) {
        echo json_encode(["error" => "database_connection_failed"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM `fjmgu_users` WHERE `user_login` = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(["user_login" => $user['user_login'],"user_pass" => $user['user_pass']]);
    } else {
        echo json_encode(["error" => "user_not_found"]);
    }

    $stmt->close();
    $conn->close();
}

function getProductData() {
    $conn = new mysqli(servername, username, password, dbname);
    $conn->set_charset("utf8");
    if ($conn->connect_error) {
        echo json_encode(["error" => "database_connection_failed"]);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT `id`, `post_content`, `post_title`, `post_name` FROM `fjmgu_posts` WHERE `post_type` = 'product' AND `post_status` = 'publish'");
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $post_id = $row['id'];
        $stmt2 = $conn->prepare("SELECT `meta_value` FROM `fjmgu_postmeta` WHERE `meta_key` ='_thumbnail_id' AND `post_id` = ".'"'.$post_id.'"');
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $post_id_2 = $row2['meta_value'];
        $stmt2 = $conn->prepare("SELECT `meta_value` FROM `fjmgu_postmeta` WHERE `meta_key` ='_wp_attached_file' AND `post_id` = ".'"'.$post_id_2.'"');
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $pic_url = "https://chichakstore.com/wp-content/uploads/".$row2['meta_value'];
        $stmt2 = $conn->prepare("SELECT t.name FROM fjmgu_terms AS t INNER JOIN fjmgu_term_taxonomy AS tt ON t.term_id = tt.term_id INNER JOIN fjmgu_term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = ? AND tt.taxonomy = 'product_cat'");
        $stmt2->bind_param("i", $post_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $category = $row2['name'];
        $stmt2 = $conn->prepare("SELECT pm.meta_value AS image_id FROM fjmgu_postmeta pm INNER JOIN fjmgu_posts p ON pm.post_id = p.ID LEFT JOIN fjmgu_posts wp ON wp.ID = pm.meta_value WHERE p.post_type = 'product' AND p.ID = ".$post_id." AND pm.meta_key = '_product_image_gallery' AND wp.post_type = 'attachment'");
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $image_ids = explode(",", $row2["image_id"]);
        $img_urls = [];
        if($image_ids[0] !== ""){
            foreach ($image_ids as $img_id){
                $stmt2 = $conn->prepare("SELECT `guid` FROM `fjmgu_posts` WHERE `id` = ".$img_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $row2 = $result2->fetch_assoc();
                $img_urls [] = $row2['guid'];
            }
        }
        $img_urls [] = $pic_url;
        
        $stmt2->close();
        $products[] = [$row['post_content'],$row['post_title'],$row['post_name'], $pic_url, $category, $img_urls];
    }

    echo json_encode($products);
    
    $stmt->close();
    $conn->close();
}

header('Content-Type: application/json');

if ($_GET['key'] === SECRET_KEY && isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'getUser':
            getUserData($_GET['username']);
            break;
        case 'getProducts':
            getProductData();
            break;
        default:
            echo json_encode(["error" => "invalid_action"]);
            break;
    }
} else {
    echo json_encode(["error" => "access_denied"]);
}

//izanlou2024
?>
