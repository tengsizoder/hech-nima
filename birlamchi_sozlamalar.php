<?php
// ==========================================
// 📑 BIRLAMCHI SOZLAMALAR — TO'LIQ ISHLAYDIGAN KOD
// ==========================================
// MySQL jadvallar:
//   payment_methods (id, name, wallet, is_active, created_at)
//   users (id ustuni = Telegram ID, step, temp_data)
//   settings (id, start)
// ==========================================
// Bu faylni include qilishdan OLDIN kodingizda bo'lishi kerak:
//
// function get_step($connect, $telegram_id) {
//     $telegram_id = mysqli_real_escape_string($connect, $telegram_id);
//     $r = mysqli_query($connect, "SELECT step FROM users WHERE id = '$telegram_id'");
//     if ($r && $row = mysqli_fetch_assoc($r)) return trim($row['step']);
//     return "";
// }
// function set_step($connect, $telegram_id, $step_value) {
//     $telegram_id = mysqli_real_escape_string($connect, $telegram_id);
//     $step_value = mysqli_real_escape_string($connect, $step_value);
//     mysqli_query($connect, "UPDATE users SET step = '$step_value' WHERE id = '$telegram_id'");
// }
// function clear_step($connect, $telegram_id) {
//     $telegram_id = mysqli_real_escape_string($connect, $telegram_id);
//     mysqli_query($connect, "UPDATE users SET step = '' WHERE id = '$telegram_id'");
// }
//
// $step = get_step($connect, $cid);
// $aort = json_encode(['keyboard'=>[[['text'=>'❌ Bekor qilish']]],'resize_keyboard'=>true]);
//
// O'zgaruvchilar: $cid, $text, $data, $connect, $admin, $panel, $aort,
//   $chat_id, $message_id, $qid
// ==========================================


// ==========================================
// 📑 BIRLAMCHI SOZLAMALAR — TUGMA BOSILDI
// ==========================================
if (isset($text) && mb_stripos($text, "Birlamchi sozlamalar") !== false && $cid == $admin) {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "⚙️ <b>ASOSIY SOZLAMALAR</b>\n━━━━━━━━━━━━━━━━━━━━\n\nBo'limni tanlang:",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📝 Start xabarini sozlash", 'callback_data' => "birlamchi_start"]],
                [['text' => "💳 To'lov tizimlari", 'callback_data' => "birlamchi_paylist"]],
            ]
        ])
    ]);
    exit;
}


// ==========================================
// ⬅️ ORQAGA — ASOSIY SOZLAMALAR MENYUSI
// ==========================================
if (isset($data) && $data == "birlamchi_back") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);
    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "⚙️ <b>ASOSIY SOZLAMALAR</b>\n━━━━━━━━━━━━━━━━━━━━\n\nBo'limni tanlang:",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "📝 Start xabarini sozlash", 'callback_data' => "birlamchi_start"]],
                [['text' => "💳 To'lov tizimlari", 'callback_data' => "birlamchi_paylist"]],
            ]
        ])
    ]);
    exit;
}


// ==========================================
// 📝 START XABARINI SOZLASH — CALLBACK
// ==========================================
if (isset($data) && $data == "birlamchi_start") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $settings = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM settings WHERE id = 1"));
    $current = "";
    if ($settings && isset($settings['start']) && !empty($settings['start'])) {
        if (function_exists('enc')) {
            $current = enc("decode", $settings['start']);
        } else {
            $current = $settings['start'];
        }
    }
    if (empty($current)) $current = "O'rnatilmagan";

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "📝 <b>START XABARI SOZLASH</b>\n━━━━━━━━━━━━━━━━━━━━\n\n⚙️ <b>O'zgaruvchilar:</b>\n<code>{balance}</code> — balans\n<code>{name}</code> — ism\n<code>{time}</code> — vaqt\n\n━━━━━━━━━━━━━━━━━━━━\n📄 <b>Hozirgi matn:</b>\n$current\n\n━━━━━━━━━━━━━━━━━━━━\n✍️ <b>Yangi matnni yozing:</b>",
        'parse_mode' => 'html',
        'reply_markup' => $aort,
    ]);
    set_step($connect, $cid, "birlamchi_start_text");
    exit;
}


// ==========================================
// 📝 START XABARI — MATN KIRITILDI
// ==========================================
if (isset($step) && $step == "birlamchi_start_text" && $cid == $admin) {
    if (function_exists('enc')) {
        $value = enc("encode", $text);
    } else {
        $value = mysqli_real_escape_string($connect, $text);
    }
    mysqli_query($connect, "UPDATE settings SET `start` = '$value' WHERE id = 1");

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "✅ <b>Start xabari saqlandi!</b>",
        'parse_mode' => 'html',
        'reply_markup' => $panel,
    ]);
    clear_step($connect, $cid);
    exit;
}


// ==========================================
// 💳 TO'LOV TIZIMLARI — RO'YXAT
// ==========================================
if (isset($data) && $data == "birlamchi_paylist") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $result = mysqli_query($connect, "SELECT * FROM payment_methods ORDER BY id ASC");
    $count = mysqli_num_rows($result);

    $msg = "💳 <b>TO'LOV TIZIMLARI</b>\n━━━━━━━━━━━━━━━━━━━━\n\n";
    $keyboard = [];

    if ($count > 0) {
        $i = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $status = $row['is_active'] ? "🟢" : "🔴";
            $msg .= "$status $i. <b>{$row['name']}</b>\n   📱 <code>{$row['wallet']}</code>\n\n";
            $keyboard[] = [['text' => "$status {$row['name']}", 'callback_data' => "birlamchi_payview_{$row['id']}"]];
            $i++;
        }
        $msg .= "━━━━━━━━━━━━━━━━━━━━\n🟢 Faol  🔴 O'chirilgan";
    } else {
        $msg .= "⚠️ To'lov tizimlari mavjud emas.";
    }

    $keyboard[] = [['text' => "➕ Yangi qo'shish", 'callback_data' => "birlamchi_payadd"]];
    $keyboard[] = [['text' => "⬅️ Orqaga", 'callback_data' => "birlamchi_back"]];

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $msg,
        'parse_mode' => 'html',
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ]);
    exit;
}


// ==========================================
// ➕ YANGI TO'LOV TIZIMI — TUGMA BOSILDI
// ==========================================
if (isset($data) && $data == "birlamchi_payadd") {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "➕ <b>YANGI TO'LOV TIZIMI</b>\n━━━━━━━━━━━━━━━━━━━━\n\n🔠 To'lov tizimi nomini kiriting:\n\n<i>Masalan: Click, Payme, Uzum</i>",
        'parse_mode' => 'html',
        'reply_markup' => $aort,
    ]);
    set_step($connect, $cid, "birlamchi_payname");
    exit;
}


// ==========================================
// ➕ NOM KIRITILDI — KARTA SO'RASH
// ==========================================
if (isset($step) && $step == "birlamchi_payname" && $cid == $admin) {
    $name = trim($text);

    if (mb_strlen($name) < 2) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ <b>Nom juda qisqa! Kamida 2 belgi bo'lsin.</b>",
            'parse_mode' => 'html',
        ]);
        exit;
    }

    if (mb_strlen($name) > 50) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ <b>Nom juda uzun! 50 belgidan oshmasin.</b>",
            'parse_mode' => 'html',
        ]);
        exit;
    }

    $name_esc = mysqli_real_escape_string($connect, $name);
    $check = mysqli_query($connect, "SELECT id FROM payment_methods WHERE name = '$name_esc'");
    if ($check && mysqli_num_rows($check) > 0) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "⚠️ <b>\"$name\" allaqachon mavjud!</b>\n\nBoshqa nom kiriting:",
            'parse_mode' => 'html',
        ]);
        exit;
    }

    mysqli_query($connect, "UPDATE users SET temp_data = '$name_esc' WHERE id = '$cid'");

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "✅ Nom: <b>$name</b>\n\n📱 Endi karta yoki hamyon raqamini kiriting:\n\n<i>Masalan: 8600 1234 5678 9012</i>",
        'parse_mode' => 'html',
        'reply_markup' => $aort,
    ]);
    set_step($connect, $cid, "birlamchi_paycard");
    exit;
}


// ==========================================
// ➕ KARTA KIRITILDI — BAZAGA SAQLASH
// ==========================================
if (isset($step) && $step == "birlamchi_paycard" && $cid == $admin) {
    $wallet = trim($text);

    if (mb_strlen($wallet) < 5) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ <b>Raqam juda qisqa! Kamida 5 belgi.</b>",
            'parse_mode' => 'html',
        ]);
        exit;
    }

    $user_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT temp_data FROM users WHERE id = '$cid'"));
    $name = isset($user_row['temp_data']) ? trim($user_row['temp_data']) : "";

    if (empty($name)) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ <b>Xatolik! Qayta boshlang.</b>",
            'parse_mode' => 'html',
            'reply_markup' => $panel,
        ]);
        clear_step($connect, $cid);
        exit;
    }

    $name_esc   = mysqli_real_escape_string($connect, $name);
    $wallet_esc = mysqli_real_escape_string($connect, $wallet);
    $ins = mysqli_query($connect, "INSERT INTO payment_methods (name, wallet, is_active) VALUES ('$name_esc', '$wallet_esc', 1)");

    if ($ins) {
        mysqli_query($connect, "UPDATE users SET temp_data = '' WHERE id = '$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "✅ <b>TO'LOV TIZIMI QO'SHILDI!</b>\n━━━━━━━━━━━━━━━━━━━━\n\n💳 <b>$name</b>\n📱 <code>$wallet</code>\n📌 🟢 Faol",
            'parse_mode' => 'html',
            'reply_markup' => $panel,
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ <b>Saqlashda xatolik!</b>",
            'parse_mode' => 'html',
            'reply_markup' => $panel,
        ]);
    }

    clear_step($connect, $cid);
    exit;
}


// ==========================================
// 💳 TO'LOV TIZIMI — KO'RISH / BOSHQARISH
// ==========================================
if (isset($data) && preg_match('/^birlamchi_payview_(\d+)$/', $data, $matches)) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $pay_id = intval($matches[1]);
    $row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM payment_methods WHERE id = '$pay_id'"));

    if (!$row) {
        bot('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => "❌ Topilmadi!", 'show_alert' => true]);
        exit;
    }

    $status_text = $row['is_active'] ? "🟢 Faol" : "🔴 O'chirilgan";
    $toggle_text = $row['is_active'] ? "🔴 O'chirish" : "🟢 Yoqish";

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💳 <b>{$row['name']}</b>\n━━━━━━━━━━━━━━━━━━━━\n\n📱 <b>Hamyon:</b> <code>{$row['wallet']}</code>\n📌 <b>Holati:</b> $status_text\n\n━━━━━━━━━━━━━━━━━━━━\nAmalni tanlang:",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "✏️ Nom o'zgartirish", 'callback_data' => "birlamchi_payrename_$pay_id"],
                    ['text' => "📱 Karta o'zgartirish", 'callback_data' => "birlamchi_paywallet_$pay_id"],
                ],
                [['text' => $toggle_text, 'callback_data' => "birlamchi_paytoggle_$pay_id"]],
                [['text' => "🗑️ O'chirish", 'callback_data' => "birlamchi_paydelete_$pay_id"]],
                [['text' => "⬅️ Orqaga", 'callback_data' => "birlamchi_paylist"]],
            ]
        ])
    ]);
    exit;
}


// ==========================================
// ✏️ NOM O'ZGARTIRISH — CALLBACK
// ==========================================
if (isset($data) && preg_match('/^birlamchi_payrename_(\d+)$/', $data, $matches)) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $pay_id = intval($matches[1]);
    $row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM payment_methods WHERE id = '$pay_id'"));
    if (!$row) exit;

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "✏️ <b>NOM O'ZGARTIRISH</b>\n━━━━━━━━━━━━━━━━━━━━\n\n📌 Hozirgi nom: <b>{$row['name']}</b>\n\n✍️ Yangi nomni kiriting:",
        'parse_mode' => 'html',
        'reply_markup' => $aort,
    ]);
    set_step($connect, $cid, "birlamchi_rename_$pay_id");
    exit;
}


// ==========================================
// ✏️ NOM O'ZGARTIRISH — YANGI NOM KIRITILDI
// ==========================================
if (isset($step) && strpos($step, "birlamchi_rename_") === 0 && $cid == $admin) {
    $pay_id = intval(substr($step, 17));
    $new_name = trim($text);

    if (mb_strlen($new_name) < 2) {
        bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ <b>Nom juda qisqa!</b>", 'parse_mode' => 'html']);
        exit;
    }

    $name_esc = mysqli_real_escape_string($connect, $new_name);
    mysqli_query($connect, "UPDATE payment_methods SET name = '$name_esc' WHERE id = '$pay_id'");

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "✅ Nom o'zgartirildi: <b>$new_name</b>",
        'parse_mode' => 'html',
        'reply_markup' => $panel,
    ]);
    clear_step($connect, $cid);
    exit;
}


// ==========================================
// 📱 KARTA O'ZGARTIRISH — CALLBACK
// ==========================================
if (isset($data) && preg_match('/^birlamchi_paywallet_(\d+)$/', $data, $matches)) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $pay_id = intval($matches[1]);
    $row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM payment_methods WHERE id = '$pay_id'"));
    if (!$row) exit;

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "📱 <b>KARTA O'ZGARTIRISH</b>\n━━━━━━━━━━━━━━━━━━━━\n\n💳 <b>{$row['name']}</b>\n📱 Hozirgi: <code>{$row['wallet']}</code>\n\n✍️ Yangi raqamni kiriting:",
        'parse_mode' => 'html',
        'reply_markup' => $aort,
    ]);
    set_step($connect, $cid, "birlamchi_wallet_$pay_id");
    exit;
}


// ==========================================
// 📱 KARTA O'ZGARTIRISH — YANGI RAQAM KIRITILDI
// ==========================================
if (isset($step) && strpos($step, "birlamchi_wallet_") === 0 && $cid == $admin) {
    $pay_id = intval(substr($step, 17));
    $new_wallet = trim($text);

    if (mb_strlen($new_wallet) < 5) {
        bot('sendMessage', ['chat_id' => $cid, 'text' => "❌ <b>Raqam juda qisqa!</b>", 'parse_mode' => 'html']);
        exit;
    }

    $wallet_esc = mysqli_real_escape_string($connect, $new_wallet);
    mysqli_query($connect, "UPDATE payment_methods SET wallet = '$wallet_esc' WHERE id = '$pay_id'");

    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "✅ Karta yangilandi: <code>$new_wallet</code>",
        'parse_mode' => 'html',
        'reply_markup' => $panel,
    ]);
    clear_step($connect, $cid);
    exit;
}


// ==========================================
// 🟢🔴 YOQISH / O'CHIRISH (TOGGLE)
// ==========================================
if (isset($data) && preg_match('/^birlamchi_paytoggle_(\d+)$/', $data, $matches)) {
    $pay_id = intval($matches[1]);
    $row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM payment_methods WHERE id = '$pay_id'"));

    if (!$row) {
        bot('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => "❌ Topilmadi!", 'show_alert' => true]);
        exit;
    }

    $new_status = $row['is_active'] ? 0 : 1;
    mysqli_query($connect, "UPDATE payment_methods SET is_active = '$new_status' WHERE id = '$pay_id'");

    $status_msg = $new_status ? "🟢 yoqildi" : "🔴 o'chirildi";
    bot('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => "{$row['name']} $status_msg!", 'show_alert' => true]);

    $status_text = $new_status ? "🟢 Faol" : "🔴 O'chirilgan";
    $toggle_text = $new_status ? "🔴 O'chirish" : "🟢 Yoqish";

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💳 <b>{$row['name']}</b>\n━━━━━━━━━━━━━━━━━━━━\n\n📱 <b>Hamyon:</b> <code>{$row['wallet']}</code>\n📌 <b>Holati:</b> $status_text\n\n━━━━━━━━━━━━━━━━━━━━\nAmalni tanlang:",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "✏️ Nom o'zgartirish", 'callback_data' => "birlamchi_payrename_$pay_id"],
                    ['text' => "📱 Karta o'zgartirish", 'callback_data' => "birlamchi_paywallet_$pay_id"],
                ],
                [['text' => $toggle_text, 'callback_data' => "birlamchi_paytoggle_$pay_id"]],
                [['text' => "🗑️ O'chirish", 'callback_data' => "birlamchi_paydelete_$pay_id"]],
                [['text' => "⬅️ Orqaga", 'callback_data' => "birlamchi_paylist"]],
            ]
        ])
    ]);
    exit;
}


// ==========================================
// 🗑️ O'CHIRISH — TASDIQLASH
// ==========================================
if (isset($data) && preg_match('/^birlamchi_paydelete_(\d+)$/', $data, $matches)) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $pay_id = intval($matches[1]);
    $row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM payment_methods WHERE id = '$pay_id'"));
    if (!$row) exit;

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🗑️ <b>{$row['name']}</b> ni o'chirmoqchimisiz?\n\n📱 <code>{$row['wallet']}</code>\n\n❗ Bu amalni qaytarib bo'lmaydi!",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "✅ Ha, o'chirish", 'callback_data' => "birlamchi_paydelyes_$pay_id"],
                    ['text' => "❌ Yo'q", 'callback_data' => "birlamchi_payview_$pay_id"],
                ],
            ]
        ])
    ]);
    exit;
}


// ==========================================
// 🗑️ O'CHIRISH — TASDIQLANDI
// ==========================================
if (isset($data) && preg_match('/^birlamchi_paydelyes_(\d+)$/', $data, $matches)) {
    bot('answerCallbackQuery', ['callback_query_id' => $qid]);

    $pay_id = intval($matches[1]);
    mysqli_query($connect, "DELETE FROM payment_methods WHERE id = '$pay_id'");

    bot('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "✅ <b>To'lov tizimi o'chirildi!</b>",
        'parse_mode' => 'html',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "⬅️ To'lov tizimlari", 'callback_data' => "birlamchi_paylist"]],
            ]
        ])
    ]);
    exit;
}
