<?php
// выводим все ошибки для дебага :)
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '-1');
header('Access-Control-Allow-Origin: *');
header("Content-type: application/json; charset=utf-8");
ini_set("log_errors", 1);
ini_set("error_log", "index.txt"); // ну и логируем ошибки конечно же :)

include("base/base.php");
require_once('vendor/autoload.php');

use DigitalStar\vk_api\vk_api;

$vk_api_version = "5.131";
$token = "ТОКЕН";
$group_id = 212559159;

if (!isset($_GET['a']) || $_GET['a'] == "") { // Строка, которую должен вернуть сервер указывается в гет-запросе, в параметре 'a', ибо так проще переключать сервер, если произошла ошибка :)
    exit('There is no parameter "a".');
}

$emoji_mem = ["&#128569;", "&#128568;", "&#129315;", "&#128517;", "&#129315;", "&#128518;", "&#128515;", "&#129303;"];
$text_mem = ["Угарай!", "Смешно, реально?", "Ахахахахаах", "Ой, умора!", "Смотри со стула не упади!", "ХАХАХАХАХАХАХААХ", "Сейчас умру со смеха!"];

$text_q_1 = ["Откуда ты знаешь?🤔 ", "😎 Верно!", "Да-да, именно этот вариант ответа! 😻", "Правильно! 🥳", "🥳 Огонь, верно!", "Верный ответ! 🤪"];
$text_q_0 = ["🤔 Почему ты не знаешь? Это неправильно", "🙁 Неверно!", "😥Нет, неправильно!", "😬 Неправильно!", "😿 Нет, неверно!", "Неверный ответ!", "😕 Неверно! Ну ничего, в следующий раз повезёт!"];

$data2 = json_decode(file_get_contents('php://input'));

$secret = "jRmpA4SBjnYsheTPb5vKBofe6"; // для лучшей безопасности не забываем про секретный код

if (!isset($data2->secret) || $data2->secret != $secret) die("Invalid secret_key."); // проверочка №1
if (!isset($data2->group_id) || !isset($group_id)) die("Incorrect group_id."); // проверочка №2


$vk = vk_api::create($token, $vk_api_version)->setConfirm($_GET['a']); // подключаемся

$vk->debug();
$vk->initVars($id, $message_text, $payload, $user_id, $type, $data);

echo "ok";

if (!R::testConnection()) {
    $vk->reply("Профилактические работы. Нет соединения с базой данных!"); // ну это так, на всякий случай. Ну а вдруг! =)
    exit;
}

$keyboard_clean = json_encode(["buttons" => [], "one_time" => true]); // чистая клава


$button_stat = $vk->buttonText('Статистика', 'green');
$button_answer = $vk->buttonText('Вопросы', 'white');
$button_hi = $vk->buttonText('Привет', 'white');
$button_mem = $vk->buttonText('Мем', 'red');
$button_down = $vk->buttonText('Загрузить свой мем', 'blue');

$keyboard_menu = $vk->generateKeyboard([[$button_hi, $button_answer], [$button_stat, $button_mem], [$button_down]], false);
$keyboard_menu_hi = $vk->generateKeyboard([[$button_hi]], false);
$button_stop_q = $vk->buttonText('Хватит вопросиков', 'red');
$keyboard_menu_stop_q = $vk->generateKeyboard([[$button_stop_q]], false);

$time = date("H:i:s"); // пуская будет время, вдруг понадобиться :)

if ($type == "message_event") {
    $user_id = $data2->object->user_id;
    $user = regUser($user_id);
    $peer_id = $data2->object->peer_id;
    $event_id = $data2->object->event_id;
    $payload = $data2->object->payload;
    $conversation_message_id = $data2->object->conversation_message_id;

    if (isset($payload->command)) {
        // $payload = (object) $payload; // массив в объект превращаем, чтобы было красиво)
        // $vk->reply(print_r($payload, 1));
        if (isset($payload->id) && isset($payload->command) && isset($payload->q) && $payload->command == "ответ") return question($payload, [$peer_id, $user_id, $event_id]);
        if (isset($payload->id)) {
            $messages_getByConversationMessageId = $vk->request("messages.getByConversationMessageId", [
                "peer_id" => $peer_id,
                "conversation_message_ids" => $conversation_message_id,
                "extended" => 1
            ]);
            // $vk->sendMessage($peer_id, print_r($messages_getByConversationMessageId["items"][0]["attachments"][0], 1));
            $attach = $messages_getByConversationMessageId["items"][0]["attachments"][0]["photo"];
            unset($attach["sizes"]);
            // $vk->sendMessage($peer_id, print_r($attach, 1));
            $attach_photo = "photo" . $attach["owner_id"] . "_" . $attach["id"] . "_" . $attach["access_key"];
            $text_message = $messages_getByConversationMessageId["items"][0]["text"];
            if ($payload->command == "like_mem") {
                $findBeswMemUser = R::findOne("memsuser", "id = ? AND vk_id = ?", [$payload->id, $user_id]);
                if ($findBeswMemUser == null) {
                    eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Похоже, Вы тыкнули не на ту кнопочку.");
                } else {
                    if ($findBeswMemUser->vote == 1) {
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Вы уже оценили данный мем &#128077;");
                    } else if ($findBeswMemUser->vote == -1) {
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Вы уже оценили данный мем &#128078;");
                    } else {
                        $findBaseMem = R::findOne("mems", "mem_file = ?", [$findBeswMemUser->mem_file]);
                        $findBaseMem->like = $findBaseMem->like + 1;
                        $findBaseMem->date_update = date('Y-m-d H:i:s');
                        R::store($findBaseMem);

                        $findBeswMemUser->vote = 1;
                        $findBeswMemUser->date_update = date('Y-m-d H:i:s');
                        R::store($findBeswMemUser);
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "✅ Лайк зачтён! &#128077;");
                        $messages_edit = $vk->request("messages.edit", [
                            "message" => "{$text_message}\n\nВаша оценка: &#128077;",
                            "peer_id" => $peer_id,
                            "conversation_message_id" => $conversation_message_id,
                            "dont_parse_links" => 1,
                            "disable_mentions" => 1,
                            "attachment" => $attach_photo,
                        ]);
                    }
                }
            } else if ($payload->command == "dislike_mem") {
                $findBeswMemUser = R::findOne("memsuser", "id = ? AND vk_id = ?", [$payload->id, $user_id]);
                if ($findBeswMemUser == null) {
                    eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Похоже, Вы тыкнули не на ту кнопочку.");
                } else {
                    if ($findBeswMemUser->vote == 1) {
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Вы уже оценили данный мем &#128077;");
                    } else if ($findBeswMemUser->vote == -1) {
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "❗ Вы уже оценили данный мем &#128078;");
                    } else {
                        $findBaseMem = R::findOne("mems", "mem_file = ?", [$findBeswMemUser->mem_file]);
                        $findBaseMem->dislike = $findBaseMem->dislike + 1;
                        $findBaseMem->date_update = date('Y-m-d H:i:s');
                        R::store($findBaseMem);

                        $findBeswMemUser->vote = -1;
                        $findBeswMemUser->date_update = date('Y-m-d H:i:s');
                        R::store($findBeswMemUser);
                        eventAnswerSnackbar($event_id, $peer_id, $user_id, "✅ Дизлайк засчитан! &#128078;");
                        $messages_edit = $vk->request("messages.edit", [
                            "message" => "{$text_message}\n\nВаша оценка: &#128078;",
                            "peer_id" => $peer_id,
                            "conversation_message_id" => $conversation_message_id,
                            "dont_parse_links" => 1,
                            "disable_mentions" => 1,
                            "attachment" => $attach_photo,
                        ]);
                    }
                }
            }
        }
    }
}


if ($type == "message_new") {
    $peer_id = $id; // peer_id
    $message = mb_strtolower($message_text, 'utf-8'); // сообщение в простом виде, без регистра

    if ($user_id <= 0) exit(); // если написала группа (например, в чате) — посылаем!

    $user = regUser($user_id); // регаем юзера в базе (или обновляем данные о нём)

    if (in_array($message, ["привет"])) {
        $vk->reply("Привет вездекодерам!", ["keyboard" => $keyboard_menu]);
    } else if (in_array($message, ["хватит", "хватит вопросиков"])) {
        $vk->reply("Ладно-ладно. Так уж и быть =)", ["keyboard" => $keyboard_menu]);
    } else if (in_array($message, ["мем"])) {
        // ПРОВЕРКА НА МЕМ!
        try {
            $base_mem_user = 1;
            $photos = array_diff(scandir("photos"), array('..', '.'));
            while ($base_mem_user == 1) {
                $mem_file = $photos[rand(0, count($photos) - 1)];
                $mem = dirname(__FILE__) . '/photos/' . $mem_file;
                $check_mem = R::findOne("memsuser", "vk_id = ? AND mem = ?", [$user_id, $mem_file]);
                if ($check_mem != null && (in_array($check_mem->vote, [-1, 1]))) {
                    $base_mem_user = 1;
                } else {
                    $base_mem_user = 0;
                }
            }
            $upload_file = $vk->uploadImage($user_id, $mem); // загружаем фото на сервер вк
            // СПЕЦИАЛЬНАЯ БАЗА ДЛЯ МЕМОВ!
            $findBaseMem = R::findOne("mems", "mem_file = ?", [$mem_file]);
            if ($findBaseMem == null) {
                $newMem = R::dispense("mems");
                $newMem->mem_file = $mem_file;
                $newMem->like = 0; // количество лайков
                $newMem->dislike = 0;
                $newMem->date_create = date('Y-m-d H:i:s');
                $newMem->date_update = date('Y-m-d H:i:s');
                R::store($newMem);
                $findBaseMem = $newMem;
            }
            if ($check_mem == null) {
                $newMemUser = R::dispense("memsuser");
                $newMemUser->vk_id = $user_id;
                $newMemUser->mem_file = $mem_file;
                $newMemUser->vote = 0; // 0 - без голоса, 1 - лайк, -1 - диз
                $newMemUser->date_create = date('Y-m-d H:i:s');
                $newMemUser->date_update = date('Y-m-d H:i:s');
                R::store($newMemUser);
                $check_mem = $newMemUser;
            } else {
                $check_mem->date_update = date('Y-m-d H:i:s');
                R::store($check_mem);
            }
            if (isset($upload_file[0]) && isset($upload_file[0]["id"])) {
                $button_mem_like = $vk->buttonCallback("&#128077;", "green", ["command" => "like_mem", "id" => $check_mem->id]);
                $button_mem_dislike = $vk->buttonCallback("&#128078;", "red", ["command" => "dislike_mem", "id" => $check_mem->id]);
                $keyboard_like_mem = $vk->generateKeyboard([[$button_mem_like, $button_mem_dislike]], true);
                $text_message_mem = $emoji_mem[array_rand($emoji_mem)] . " " . $text_mem[array_rand($text_mem)];
                $vk->reply($text_message_mem, ["attachment" => "photo" . $upload_file[0]['owner_id'] . "_" . $upload_file[0]['id'], "keyboard" => $keyboard_like_mem]);
            } else {
                $vk->reply("⛔ Произошла ошиБОЧКА :(\nНапиши команду ещё разок!", ["keyboard" => $keyboard_menu]);
            }
        } catch (Exception $ex) {
            //Выводим сообщение об исключении.
            // echo $ex->getMessage();
            $vk->reply("&#9940; Произошла ошибка :(\nПопробуй ещё разок выполнить команду.");
            $vk->reply($ex->getMessage());
        }
    } else if (in_array($message, ["вопросы"])) {
        $button_ans_1 = $vk->buttonText('Отлично', 'green', ["command" => "ответ", "q" => true, "id" => 1]);
        $button_ans_2 = $vk->buttonText('Хорошо', 'blue', ["command" => "ответ", "q" => true, "id" => 1]);
        $button_ans_3 = $vk->buttonText('По-Вездекодерски', 'white', ["command" => "ответ", "q" => true, "id" => 1]);
        $button_ans_4 = $vk->buttonText('Мощно!', 'red', ["command" => "ответ", "q" => true, "id" => 1]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1, $button_ans_2], [$button_ans_3, $button_ans_4], [$button_hi]], true);

        $vk->reply("Ну-с, начнём!", ["keyboard" => $keyboard_menu_stop_q]);
        $vk->reply("1. Как дела?", ["keyboard" => $keyboard_ans]);
    } else if (in_array($message, ["статистика"])) {
        $countAllMemUser = R::count("memsuser", "vk_id = ?", [$user_id]);
        $countLikeMemUser = R::count("memsuser", "vk_id = ? AND vote = 1", [$user_id]);
        $countDisLikeMemUser = R::count("memsuser", "vk_id = ? AND vote = -1", [$user_id]);

        $allMemsGlobal = R::findAll("mems", "ORDER BY `mems`.`like` DESC");
        $countLikeMem = 0;
        $countDisLikeMem = 0;
        $memsLikes = [];
        foreach ($allMemsGlobal as $key => $value) {
            $countLikeMem = $countLikeMem + $value->like;
            $countDisLikeMem = $countDisLikeMem + $value->dislike;
            if ($value->like > 0) array_push($memsLikes, $value->mem_file);
        }

        $vk->reply("$user->first_name, Ваша статистика:

Всего посмотрели: " . num_word($countAllMemUser, ['мем', 'мема', 'мемов']) . "
&#128077; Поставили Лайк: " . num_word($countLikeMemUser, ['мему', 'мемам', 'мемам']) . "
&#128078; Поставили Дизлак: " . num_word($countDisLikeMemUser, ['мему', 'мемам', 'мемам']) . "
      
&#128202; Статистика всех пользователей:

&#128077; Поставили Лайк: " . num_word($countLikeMem, ['мему', 'мемам', 'мемам']) . "
&#128078; Поставили Дизлак: " . num_word($countDisLikeMem, ['мему', 'мемам', 'мемам']) . "
", ["keyboard" => $keyboard_menu]);
        if (count($memsLikes) <= 0) {
            $vk->reply("Здесь должен быть топ по мемам, но их пока что нет :(", ["keyboard" => $keyboard_menu]);
        } else {
            $attach = "";
            $dop_text = "";
            $memsLikes = array_slice($memsLikes, 0, 9);
            foreach ($memsLikes as $key => $value) {
                $mem = dirname(__FILE__) . '/photos/' . $value;
                $upload_file = $vk->uploadImage($user_id, $mem);
                if (isset($upload_file[0]) && isset($upload_file[0]["id"])) {
                    $attach .= "photo" . $upload_file[0]['owner_id'] . "_" . $upload_file[0]['id'] . ",";
                } else {
                    $dop_text .= "Ошибка при загрзке мема {$value}\n";
                }
            }
            $vk->reply("Вдохновитесь топ-" . count($memsLikes) . " самых залайканных всеми пользователями мемов!\n\n$dop_text", ["attachment" => $attach, "keyboard" => $keyboard_menu]);
        }
    } else if (in_array($message, ["загрузить свой мем"])) {
        $vk->reply("$user->first_name, отправьте сюда файл (можно сразу несколько в одном сообщении) с мемом с командой <<загрузить>>.\n\n*именно файл (документ), так мемчик будет чётким 🙃", ["keyboard" => $keyboard_menu]);
    } else if (in_array($message, ["загрузить"])) {
        $getAttachmentsPost = getAttachmentsPost();
        if ($getAttachmentsPost == []) {
            $vk->reply("В сообщении нет файлика 😑");
        } else {
            $vk->reply("Успешно загружено " . count($getAttachmentsPost) . " шт. мемов! 😍");
        }
    } else {
        $payload = (object) $payload; // массив в объект превращаем, чтобы было красиво)
        // $vk->reply(print_r($payload, 1));
        if (isset($payload->id) && isset($payload->command) && isset($payload->q) && $payload->command == "ответ") question($payload);
    }
}

function question($payload, $callback = [])
{
    global $vk, $keyboard_menu_hi, $button_hi, $keyboard_menu, $text_q_0, $text_q_1, $keyboard_menu_stop_q, $button_stop_q;
    if ($payload->q == true) {
        $text = $text_q_1[array_rand($text_q_1)];
        if ($callback != []) eventAnswerSnackbar($callback[2], $callback[0], $callback[1], $text);
        $vk->reply($text, ["keyboard" => $keyboard_menu_stop_q]);
    } else {
        $text = $text_q_0[array_rand($text_q_0)];
        if ($callback != []) eventAnswerSnackbar($callback[2], $callback[0], $callback[1], $text);
        $vk->reply($text, ["keyboard" => $keyboard_menu_stop_q]);
    }
    if ($payload->id == 8) {
        return $vk->reply("Воспросики закончились. Самое время поорать с мемов!", ["keyboard" => $keyboard_menu]);
    }
    if ($payload->id == 1) {
        $button_ans_1 = $vk->buttonText('Зеленого', 'green', ["command" => "ответ", "q" => true, "id" => 2]);
        $button_ans_2 = $vk->buttonText('Синего', 'blue', ["command" => "ответ", "q" => false, "id" => 2]);
        $button_ans_3 = $vk->buttonText('Белого', 'white', ["command" => "ответ", "q" => false, "id" => 2]);
        $button_ans_4 = $vk->buttonText('Красного', 'red', ["command" => "ответ", "q" => false, "id" => 2]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1], [$button_ans_2], [$button_ans_3], [$button_ans_4], [$button_stop_q]], false);

        $vk->reply("2. Какого цвета травушка-муравушка? :)", ["keyboard" => $keyboard_ans]);
    }

    if ($payload->id == 2) {
        $button_ans_1 = $vk->buttonCallback('Всеволод', 'blue', ["command" => "ответ", "q" => false, "id" => 3]);
        $button_ans_2 = $vk->buttonCallback('Никита', 'blue', ["command" => "ответ", "q" => false, "id" => 3]);
        $button_ans_3 = $vk->buttonCallback('Клим', 'red', ["command" => "ответ", "q" => true, "id" => 3]);
        $button_ans_4 = $vk->buttonCallback('Аня', 'blue', ["command" => "ответ", "q" => false, "id" => 3]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1, $button_ans_2], [$button_ans_3], [$button_ans_4], [$button_stop_q]], false);

        $vk->reply("3. Как зовут создателя данного бота?", ["keyboard" => $keyboard_ans]);
    }

    if ($payload->id == 3) {
        $button_ans_1 = $vk->buttonCallback('ЖЕСТИИИМ!', 'white', ["command" => "ответ", "q" => false, "id" => 4]);
        $button_ans_2 = $vk->buttonCallback('👻 VKANTUKTI!', 'blue', ["command" => "ответ", "q" => true, "id" => 4]);
        $button_ans_3 = $vk->buttonCallback('Химики', 'white', ["command" => "ответ", "q" => false, "id" => 4]);
        $button_ans_4 = $vk->buttonCallback('Умно-умно', 'green', ["command" => "ответ", "q" => false, "id" => 4]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1], [$button_ans_2], [$button_ans_3, $button_ans_4]], true);

        $vk->reply("4. Как называется команда, которая реализовывала данного бота? =)", ["keyboard" => $keyboard_ans]);
    }

    if ($payload->id == 4) {
        $button_ans_1 = $vk->buttonText('Лиза Подкладышева', 'red', ["command" => "ответ", "q" => false, "id" => 5]);
        $button_ans_2 = $vk->buttonText('Арсений Метелев', 'red', ["command" => "ответ", "q" => false, "id" => 5]);
        $button_ans_3 = $vk->buttonText('Аня', 'green', ["command" => "ответ", "q" => true, "id" => 5]);
        $button_ans_4 = $vk->buttonText('Стёпа', 'white', ["command" => "ответ", "q" => false, "id" => 5]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1], [$button_ans_2], [$button_ans_3, $button_ans_4], [$button_stop_q]], false);

        $vk->reply("5. Какой человечек из списка ниже есть у нас в команде?", ["keyboard" => $keyboard_ans]);
    }

    if ($payload->id == 5) {
        $button_ans_1 = $vk->buttonText('Лиза Подкладышева', 'red', ["command" => "ответ", "q" => false, "id" => 6]);
        $button_ans_2 = $vk->buttonText('Лиза Подкладышева', 'red', ["command" => "ответ", "q" => false, "id" => 6]);
        $button_ans_3 = $vk->buttonText('Лиза Подкладышев', 'green', ["command" => "ответ", "q" => false, "id" => 6]);
        $button_ans_4 = $vk->buttonText('Павел Дуров', 'red', ["command" => "ответ", "q" => true, "id" => 6]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1], [$button_ans_2], [$button_ans_3, $button_ans_4], [$button_stop_q]], false);

        $vk->reply("6. Кто является основателем ВКонтакте? Не, ну а вдруг не знаете)", ["keyboard" => $keyboard_ans]);
    }

    if ($payload->id == 6) {
        $button_ans_1 = $vk->buttonCallback('10²', 'green', ["command" => "ответ", "q" => true, "id" => 7]);
        $button_ans_2 = $vk->buttonCallback('100', 'red', ["command" => "ответ", "q" => true, "id" => 7]);
        $button_ans_3 = $vk->buttonCallback('999 — 899', 'blue', ["command" => "ответ", "q" => true, "id" => 7]);
        $button_ans_4 = $vk->buttonCallback('50 * 6 — 25 * 8', 'white', ["command" => "ответ", "q" => true, "id" => 7]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1], [$button_ans_2], [$button_ans_3, $button_ans_4], [$button_stop_q]], false);

        $vk->reply("А теперь математика :(\n7. Решите пример: 12⁶⁻⁴ — 44 = ?", ["keyboard" => $keyboard_ans]);
    }
    if ($payload->id == 7) {
        $button_ans_1 = $vk->buttonText('12', 'green', ["command" => "ответ", "q" => false, "id" => 8]);
        $button_ans_2 = $vk->buttonText('34', 'red', ["command" => "ответ", "q" => true, "id" => 8]);
        $button_ans_3 = $vk->buttonText('123', 'green', ["command" => "ответ", "q" => false, "id" => 8]);
        $button_ans_4 = $vk->buttonText('234', 'green', ["command" => "ответ", "q" => false, "id" => 8]);

        $keyboard_ans = $vk->generateKeyboard([[$button_ans_1, $button_ans_2], [$button_ans_3, $button_ans_4], [$button_stop_q]], false);

        $vk->reply("Наконец-то, последний вопрос!\n8. Расставьте все знаки препинания: укажите цифру(-ы), на месте которой(-ых) в предложении должна(-ы) стоять запятая(-ые).\n\nНаплескавшись вдоволь (1) и (2) попрыгав в воду с перевёрнутого ржавого кузова (3) неведомо как очутившегося в озере (4) мальчишки устроились с удочками возле камышей.", ["keyboard" => $keyboard_ans]);
    }
}

function getAttachmentsPost()
{
    global $vk, $user_id, $peer_id, $user, $data;
    $attachemnts = $vk->request("messages.getByConversationMessageId", ["peer_id" => $peer_id, "conversation_message_ids" => $data->object->conversation_message_id]);
    $attachemnts_array = [];
    if (!isset($attachemnts["error"])) {
        foreach ($attachemnts["items"][0]["attachments"] as $key => $value) {
            if ($value["type"] == "doc") {
                $attachment_owner_id = $value["doc"]["owner_id"];
                $attachment_id = $value["doc"]["id"];
                $attachment_title = $value["doc"]["title"];
                if (isset($value["doc"]["access_key"])) {
                    $attachment_access_key = "_" . $value["doc"]["access_key"];
                } else {
                    $attachment_access_key = "";
                }
                $doc_url = "doc{$attachment_owner_id}_{$attachment_id}{$attachment_access_key}";

                $hash = bin2hex(random_bytes(5));
                $path = "./photos/" . $hash . "." . $value["doc"]["ext"];

                file_put_contents($path, file_get_contents($value["doc"]["url"]));
                // $vk->reply($value["doc"]["ext"] . " — " . $value["doc"]["url"] . " — $path");

                array_push($attachemnts_array, ["type" => "doc", "value" => $doc_url]);
            }
        }
        return $attachemnts_array;
    } else {
        return $attachemnts_array;
    }
}

function eventAnswerSnackbar($event_id, $peer_id, $user_id, $text)
{
    global $vk;
    return $vk->request('messages.sendMessageEventAnswer', [
        'event_id' => $event_id,
        'user_id' => $user_id,
        'peer_id' => $peer_id,
        'event_data' => json_encode([
            'type' => 'show_snackbar',
            'text' => $text
        ])
    ]);
}

function regUser($user_id)
{
    global $vk;
    $user = R::findOne('users', 'vk_id = ?', [$user_id]);
    if ($user == null) {
        $userInfo = $vk->userInfo($user_id, [
            "fields" => "id,first_name,last_name,sex",
            "extended" => 1
        ]);

        $NewUser = R::dispense("users");
        $NewUser->vk_id = $user_id;
        $NewUser->sex = $userInfo["sex"];
        $NewUser->first_name = $userInfo["first_name"];
        $NewUser->last_name = $userInfo["last_name"];
        $NewUser->date_create = date('Y-m-d H:i:s');
        $NewUser->date_update = date('Y-m-d H:i:s');
        R::store($NewUser);
    } else {
        if (($user->time_update + 3600 * 24) > time()) { // обновляем раз в 24 часа (вроде верно подсчитал). Обновляем для того, чтобы получать актуальные имя и фамилию юзера
            $userInfo = $vk->userInfo($user_id, [
                "fields" => "id,first_name,last_name,sex",
                "extended" => 1
            ]);


            $user->sex = $userInfo["sex"];
            $user->first_name = $userInfo["first_name"];
            $user->last_name = $userInfo["last_name"];
            R::store($user);
        }
        $user->date_update = date('Y-m-d H:i:s');
        R::store($user);
    }
    $user = R::findOne('users', 'vk_id = ?', [$user_id]);
    return $user;
}

function num_word($value, $words, $show = true)
{
    $num = $value % 100;
    if ($num > 19) {
        $num = $num % 10;
    }

    $out = ($show) ?  $value . ' ' : '';
    switch ($num) {
        case 1:
            $out .= $words[0];
            break;
        case 2:
        case 3:
        case 4:
            $out .= $words[1];
            break;
        default:
            $out .= $words[2];
            break;
    }

    return $out;
}
