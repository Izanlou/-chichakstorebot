<?php
require_once './phpass.php';
//@izanlou_2024

// BLOCK NON-TG IPS
$telegram_ip_ranges = [
    ["lower" => "149.154.160.0", "upper" => "149.154.175.255"],
    ["lower" => "91.108.4.0", "upper" => "91.108.7.255"],
];
$ip_dec = (float) sprintf("%u", ip2long(@$_SERVER["REMOTE_ADDR"]));
$ok = false;
foreach ($telegram_ip_ranges as $telegram_ip_range) {
    if (!$ok) {
        $lower_dec = (float) sprintf(
            "%u",
            ip2long($telegram_ip_range["lower"])
        );
        $upper_dec = (float) sprintf(
            "%u",
            ip2long($telegram_ip_range["upper"])
        );
        if ($ip_dec >= $lower_dec and $ip_dec <= $upper_dec) {
            $ok = true;
        }
    }
}
if (!$ok) {
    die("403-Forbidden");
}

// HELPER FUNCTIONS
function getUserData($username) {
    $response = file_get_contents("https://bot.chichakstore.com/sql.php?username=" . urlencode($username) . "&key=[]&action=getUser");

    $user_data = json_decode($response, true);

    if (isset($user_data["error"])) {
        return null;
    } else {
        return $user_data;
    }
}

function getProductData(){
    $response = file_get_contents("https://bot.chichakstore.com/sql.php?key=[]&action=getProducts");

    $product_data = json_decode($response, true);

    if (isset($product_data["error"])) {
        return null;
    } else {
        return $product_data;
    }
}

function generateQRCode($data, $size = '200x200') {
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/';
    $url = $apiUrl . '?data=' . urlencode($data) . '&size=' . $size;
    $qrCodeImage = file_get_contents($url);
    
    if ($qrCodeImage === false) {
        return false;
    }

    $filePath = 'qrcode.png';
    file_put_contents($filePath, $qrCodeImage);
    return $filePath;
}

// TEMPORARY FILE MANAGEMENT
function wrTempFile($chat_id, $data) {
    $tempFilePath = __DIR__ . "/tmp/temp_" . $chat_id . ".txt";
    file_put_contents($tempFilePath, $data);
}

function reTempFile($chat_id) {
    $tempFilePath = __DIR__ . "/tmp/temp_" . $chat_id . ".txt";
    return file_exists($tempFilePath) ? file_get_contents($tempFilePath) : null;
}

function clTempFile($chat_id) {
    $tempFilePath = __DIR__ . "/tmp/temp_" . $chat_id . ".txt";
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
}

// TELEGRAM BOT SETUP AND API HANDLING
$token = 'bot-token';
define("API_KEY", $token);

function deploy($method, $data = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        error_log("cURL error: " . curl_error($ch));
    }
    return json_decode($res);
}

// INITIAL KEYBOARD LAYOUTS
$init_keyboard = [
    [["text" => "ورود به ربات 🎈"], ["text" => "ثبت نام همکار"]],
];

$main_keyboard = [
    [["text" => "دریافت محصولات 🛒"]],
    [["text" => "پشتیبانی 📞"],["text" => "شناسۀ من 🔗"]],
];

// SORT TELEGRAM UPDATES
$update = json_decode(file_get_contents("php://input"));
$message = isset($update->message) ? $update->message : "";
$chat_id = isset($message->chat->id) ? $message->chat->id : "";
$from_id = isset($message->from->id) ? $message->from->id : "";
$text = isset($update->message->text) ? $update->message->text : "";
$message_id = isset($message->message_id) ? $message->message_id : "";
$type = isset($update->message->chat->type) ? $update->message->chat->type : "";
$data_query = isset($update->callback_query->data) ? $update->callback_query->data : "";
$querymsg = isset($update->callback_query->message) ? $update->callback_query->message : "";
$audio = isset($update->message->audio->file_id) ? $update->message->audio->file_id : "";
$caption = isset($update->message->caption) ? $update->message->caption : "";
$document = isset($update->message->document->file_id) ? $update->message->document->file_id : "";
$photo = isset($update->message->photo[1]->file_id) ? $update->message->photo[1]->file_id : "";
$sticker = isset($update->message->sticker->file_id) ? $update->message->sticker->file_id : "";
$video = isset($update->message->video->file_id) ? $update->message->video->file_id : "";
$voice = isset($update->message->voice->file_id) ? $update->message->voice->file_id : "";
$chatid = isset($update->callback_query->message->chat->id) ? $update->callback_query->message->chat->id : "";
$fromid = isset($update->callback_query->from->id) ? $update->callback_query->from->id : "";
$from_id = isset($update->inline_query->from->id) ? $update->inline_query->from->id : "";
$inline_query = isset($update->inline_query) ? $update->inline_query : null;

function sendProductAlbum($product, $chat_id) {
    $pr_link = "https://chichakstore.com/product/" . $product[2];

    $product_desc = str_replace("&nbsp;", "", $product[0]);
    $product_desc = str_replace("\r\n\r\n", "\n", $product_desc);
    $aff_link = "https://chichakstore.com/product/" . $product[2] . "/?ref=" . explode("&&88", reTempFile($chat_id))[1];
    $media_caption = "<b>عنوان محصول:</b> " . htmlspecialchars($product[2]) . "\n\n<b>محتوای محصول:</b>\n\n " . htmlspecialchars($product_desc) . "\n\n🔗 لینک خرید سریع :\n<i> " . htmlspecialchars($aff_link) . "</i>";

    $media[] = [
        'type' => 'photo',
        'media' => $product[5][0],
        'caption' => $media_caption,
        'parse_mode' => 'HTML'
    ];
    for ($i = 1; $i < count($product[5]); $i++) {
        $media[] = [
            'type' => 'photo',
            'media' => $product[5][$i]
        ];
    }

    deploy("sendMediaGroup", [
        "chat_id" => $chat_id,
        "media" => json_encode($media)
    ]);
}

if ($inline_query) {
    $query_id = $inline_query->id;
    $query_text = $inline_query->query; 

    if($query_text === "show_products"){
        $product_data = getProductData();
        $results = [];
        $flag = 0;
        
        foreach ($product_data as $product) {
            $product_desc = str_replace("&nbsp;", "", $product[0]);
            $aff_link = "https://chichakstore.com/product/" . $product[2] . "/?ref=" . explode("&&88", reTempFile($from_id))[1];
            $flag++;
            if($flag == 50){break;}
            $results[] = [
                'type' => 'article',
                'id' => md5($product[2]),
                'title' => $product[1],
                'thumb_url' => $product[3],
                'input_message_content' => [
                    'message_text' => "محصول موردنظر انتخاب شد! 👇🏻",
                ],
                'description' => $product_desc,
                'reply_markup' => [
                'inline_keyboard' => [
                        [
                            [
                                'text' => "نمایش کامل محصول",
                                "callback_data" => $product[2]."&&88".$from_id,
                            ]
                        ]
                    ]
                ]
            ];
        }
        
        deploy("answerInlineQuery", [
            'inline_query_id' => $query_id,
            'results' => json_encode($results),
            'cache_time' => 0
        ]);
    } else {
        $product_data = getProductData();
        $results = [];
        $flag = 0;
        
        foreach ($product_data as $product) {
            if($product[4] == $query_text){
                $product_desc = str_replace("&nbsp;", "", $product[0]);
                $aff_link = "https://chichakstore.com/product/" . $product[2] . "/?ref=" . explode("&&88", reTempFile($from_id))[1];
                $flag++;
                if($flag == 50){break;}
                $results[] = [
                    'type' => 'article',
                    'id' => md5($product[2]),
                    'title' => $product[1],
                    'thumb_url' => $product[3],
                    'input_message_content' => [
                        'message_text' => "محصول موردنظر انتخاب شد! 👇🏻",
                    ],
                    'description' => $product_desc,
                    'reply_markup' => [
                    'inline_keyboard' => [
                            [
                                [
                                    'text' => "نمایش کامل محصول",
                                    'callback_data' => $product[2]."&&88".$from_id,
                                ]
                            ]
                        ]
                    ]
                ];
            }
        }
        
        deploy("answerInlineQuery", [
            'inline_query_id' => $query_id,
            'results' => json_encode($results),
            'cache_time' => 0
        ]);
    }
    exit;
}

// SIMPLE BOT RESPONSE FLOW
if ($chat_id) {
    $user_data = reTempFile($chat_id);

    if ($user_data == null) {
        if ($text === "/start") {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "لطفا برای ادامه وارد شوید یا ثبت نام کنید.",
                "reply_markup" => json_encode(["keyboard" => $init_keyboard, "resize_keyboard" => true])
            ]);
            exit;
        } elseif ($text === "ثبت نام همکار") {
            $signup_link = "https://chichakstore.com/affiliate-marketing/";
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "برای ثبت نام به عنوان همکار از این لینک استفاده کنید:\n" . $signup_link
            ]);
            exit;
        } elseif ($text === "ورود به ربات 🎈") {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "لطفا نام کاربری خود را وارد کنید:"
            ]);
            wrTempFile($chat_id, "0");
            exit;
        }
    } elseif ($user_data === "0") {
        $entered_username = $text;
        $sql_user_data = getUserData($entered_username);

        if ($sql_user_data) {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "نام کاربری شما دریافت شد. لطفا رمز عبور خود را وارد کنید:"
            ]);
            wrTempFile($chat_id, "1&&88" . $entered_username);
        } else {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "این نام کاربری در سیستم موجود نیست. لطفا دوباره تلاش کنید.\n" . "/start 👈🏻 شروع مجدد",
                "reply_markup" => json_encode(["hide_keyboard" => true])
            ]);
            clTempFile($chat_id);
        }
        exit;
    } elseif (explode("&&88", $user_data)[0] === "1") {
        $entered_password = $text;
        $entered_username = explode("&&88", $user_data)[1];
        $sql_user_data = getUserData($entered_username);
        $wp_hasher = new PasswordHash(8, true);

        if ($wp_hasher->CheckPassword($entered_password, $sql_user_data['user_pass'])) {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "خوش آمدید " . $entered_username . ", ورود موفقیت‌آمیز بود. ✌️",
                "reply_markup" => json_encode(["keyboard" => $main_keyboard, "resize_keyboard" => true])
            ]);
            wrTempFile($chat_id, "2&&88" . $entered_username);
        } else {
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "رمز عبور نادرست است. لطفا دوباره تلاش کنید.\n" . "/start 👈🏻 شروع مجدد",
                "reply_markup" => json_encode(["hide_keyboard" => true])
            ]);
            clTempFile($chat_id);
        }
        exit;
    } elseif (explode("&&88", $user_data)[0] === "2") {
        if($text === "/start"){
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "چه کاری می‌تونم براتون انجام بدم؟! 😊",
                "reply_markup" => json_encode(["keyboard" => $main_keyboard, "resize_keyboard" => true])
            ]);
            exit;
        } elseif ($text === "پشتیبانی 📞"){
            $whatsapp_link = "https://wa.me/989055183424";
            $rubika_link = "https://rubika.ir/chichakstore";
        
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "راه های ارتباطی جهت پشتیبانی:\n\n" .
                          "📞 تلفن : 09055183424 - 02835233770\n" .
                          "📧 ایمیل : mohamadra807@gmail.com\n\n" .
                          "واتساپ : [WhatsApp Link]($whatsapp_link)\n" .
                          "تلگرام : +989055183424\n" .
                          "روبیکا : [Rubika Link]($rubika_link)\n\n" .
                          "ساعات پاسخگویی 10 صبح تا 8 شب. ✌️",
            ]);
            exit;
        } elseif ($text === "شناسۀ من 🔗"){
            deploy("sendPhoto", [
                "chat_id" => $chat_id,
                'photo' => new CURLFile(realpath(generateQRCode('https://chichakstore.com/?ref="' . explode("&&88", $user_data)[1]))),
                "caption" => "کد همکاری در فروش: " . explode("&&88", $user_data)[1] .
                          "\nشناسه منحصر به فرد شما برای پیگیری موارد جدید استفاده می شود." .
                          "\n\nلینک ارجاع پیش فرض: https://chichakstore.com/?ref=" . explode("&&88", $user_data)[1],
            ]);
            exit;
        } elseif ($text === "دریافت محصولات 🛒"){
            deploy("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "لطفا انتخاب کنید... 👇🏻",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            [
                                "text" => "نمایش 50 محصول اخیر 🛒",
                                'switch_inline_query_current_chat' => 'show_products',
                            ],
                        ],
                        [
                            [
                                "text" => "نمایش تمامی محصولات",
                                "callback_data" => "&&88show_all_products",
                            ],
                        ],
                        [
                            [
                                "text" => "سرچ با کد-محصول 🔍",
                                "callback_data" => "&&88search_by_id",
                            ],
                        ],
                        [
                            [   
                                "text" => "انتخاب دسته‌بندی 🛍️",
                                "callback_data" => "&&88choose_cate",
                            ],
                        ]
                    ],
                ]),
            ]);
            exit;
        }
    } elseif (explode("&&88", $user_data)[0] === "3") {
        wrTempFile($chat_id, "2&&88" . explode("&&88", $user_data)[1]);
        deploy("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "در حال جستجوی محصولات... 🔍",
        ]);
        $product_data = getProductData();
        foreach ($product_data as $product) {
            if(strpos($product[0], $text)){
                sendProductAlbum($product, $chat_id);
                exit;
            }
        }
        deploy("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "محصول مورد نظر پیدا نشد! 😞",
        ]);
    }
} else { //chatid
    $user_data = reTempFile($chatid);

    if($data_query === "&&88search_by_id"){
        deploy("answercallbackquery", ["callback_query_id" => $update->callback_query->id,]);
        deploy("sendMessage", [
            "chat_id" => $chatid,
            "text" => "لطفا کد محصول مورد نظر را وارد کنید:"
        ]);
        wrTempFile($chatid, "3&&88" . explode("&&88", $user_data)[1]);
        exit;
    } elseif ($data_query === "&&88choose_cate"){
        deploy("answercallbackquery", ["callback_query_id" => $update->callback_query->id,]);
        $cates = [];
        $product_data = getProductData();
        foreach ($product_data as $product) {
            $category = $product[4];
            if (!isset($cates[$category])) {
                $cates[$category] = true;
            }
        }
        $cates = array_keys($cates);
        $inline_keyboard = [];
        foreach ($cates as $cate){
            $inline_keyboard [] = [["text" => $cate,'switch_inline_query_current_chat' => $cate]];
        }
        deploy("sendMessage", [
            "chat_id" => $chatid,
            "text" => "لطفا انتخاب کنید... 👇🏻",
            "reply_markup" => json_encode(["inline_keyboard" => $inline_keyboard]),
        ]);
        exit;
    } elseif ($data_query === "&&88show_all_products"){
        deploy("answercallbackquery", ["callback_query_id" => $update->callback_query->id,]);
        $inline_keyboard = [];
        $product_data = getProductData();
        foreach ($product_data as $product) {
            $inline_keyboard [] = [["text" => $product[1],"callback_data" => $product[2]]];
        }
        deploy("sendMessage", [
            "chat_id" => $chatid,
            "text" => "لطفا انتخاب کنید... 👇🏻",
            "reply_markup" => json_encode(["inline_keyboard" => $inline_keyboard]),
        ]);
        exit;
    } else {
        if($chatid == ""){$chatid = explode("&&88", $data_query)[1];}
        deploy("answercallbackquery", ["callback_query_id" => $update->callback_query->id,]);
        deploy("sendMessage", [
            "chat_id" => $chatid,
            "text" => "در حال دریافت اطلاعات... 📩",
        ]);
        $product_data = getProductData();
        foreach ($product_data as $product) {
            if($product[2] === explode("&&88", $data_query)[0]){
                sendProductAlbum($product, $chatid);
                exit;
            }
        }
    }
}

//izanlou2024
?>
