<?php

$config = include('config.php');
$botToken = $config['bot_token'];
$apiUrl = "https://api.telegram.org/bot{$botToken}/";
$timerFile = 'timer.json';

// Function to send a request to Telegram API
function sendRequest($method, $parameters) {
    global $apiUrl;
    $url = $apiUrl . $method;
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($parameters),
        ],
    ];
    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// Function to get the chat member's status using channel ID
function getChatMember($chat_id, $user_id) {
    global $apiUrl;
    $url = "{$apiUrl}getChatMember?chat_id={$chat_id}&user_id={$user_id}";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Function to check if user joined all required channels
function checkUserJoinedChannels($userId) {
    global $config;

    foreach ($config['channels_on_check'] as $channelId) {
        // Check user's membership in the channel using the channel ID
        $chatMember = getChatMember($channelId, $userId);
        
        if (!in_array($chatMember['result']['status'], ['member', 'administrator', 'creator'])) {
            return false;
        }
    }
    return true;
}

// Function to get and update user timers
function canSendLink($chatId) {
    global $timerFile;
    $currentTimestamp = time();

    // Load the timer file or initialize an empty array
    if (file_exists($timerFile)) {
        $timers = json_decode(file_get_contents($timerFile), true);
    } else {
        $timers = [];
    }

    // Check if the user exists in the timer file
    if (isset($timers[$chatId])) {
        $lastTimestamp = $timers[$chatId];
        $timeRemaining = 15 - ($currentTimestamp - $lastTimestamp);
        if ($timeRemaining > 0) {
            return $timeRemaining;  // Return remaining time in seconds
        }
    }

    // Update the timestamp for the user
    $timers[$chatId] = $currentTimestamp;
    file_put_contents($timerFile, json_encode($timers));
    return true;
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $firstName = $message['from']['first_name'];
    $username = isset($message['from']['username']) ? $message['from']['username'] : 'N/A';
    $text = $message['text'];

    // First check if the user has joined all required channels
    if (!checkUserJoinedChannels($chatId)) {
        // User hasn't joined all channels
        $keyboard = [];
        foreach (array_chunk($config['channels'], 3) as $chunk) {
            $row = [];
            foreach ($chunk as $channelUrl) {
                $row[] = ['text' => '↗️ Join', 'url' => $channelUrl];
            }
            $keyboard[] = $row;
        }
        $keyboard[] = [['text' => '✅ REFRESH', 'callback_data' => '/joined']];

        sendRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => "<b>✳️ Welcome to our bot! 🎉</b>\n\n⬇️ Click on the <b>Join</b> button below to join the channel. 📲\n\nThen, click the <b>Refresh</b> button 🔄 to proceed.\n\nOnce you're done, you can share any <b>Terabox video link</b> 📹 and enjoy watching unlimited videos without ads! 🚫🎬\n\nEnjoy and have fun! 😄",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        // User has joined all channels, now proceed with the normal message handling
        if (strpos($text, '/start') === 0) {
            sendRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => "<b>💁‍♂ Welcome! You have successfully joined the channels.</b>",
                'parse_mode' => 'HTML'
            ]);
        } elseif (filter_var($text, FILTER_VALIDATE_URL)) {
            // Check if the user can send a link
            $canSend = canSendLink($chatId);
            if ($canSend !== true) {
                // User cannot send link yet, show remaining time
                sendRequest('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "<b>⏳ Please wait {$canSend} seconds before sending another link.</b>",
                    'parse_mode' => 'HTML'
                ]);
            } else {
                // User sent a valid URL, send "Video ready" message with button
                $keyboard = [
                    [['text' => 'Watch Video', 'web_app' => ['url' => "https://url.errorflo.tech/v/?url=" . urlencode($text)]]],
                    [['text' => '🔥 ADVANCE TERABOT', 'url' => 'https://t.me/TeraLinkToVideo_bot']],
                    [['text' => 'Earn Money Online🪙', 'url' => 'https://t.me/PAWSOG_bot/PAWS?startapp=QrwnRPpw']],
                    [['text' => 'Invite Friend', 'url' => 'https://t.me/share/url?text=%0AWatch%20unlimited%20Terabox%20videos%20without%20ads%20and%20without%20the%20app%20for%20free!%20%F0%9F%8E%A5%F0%9F%9A%AB%F0%9F%93%BA%20Try%20it%20now%20at%20%40TeraBoxLink2Video_bot.%20Enjoy%20seamless%20video%20access!%20%F0%9F%8E%89&url=https%3A%2F%2Ft.me%2FTeraBoxLink2Video_bot']],
                    [['text' => 'Purchase Premium', 'url' => 'https://t.me/TeraBoxPremium_Robot']],
                    [['text' => 'Report a Problem', 'url' => 'https://t.me/TeraContact_bot']],
                    ];


                sendRequest('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "<b>Your video is ready to watch!</b>",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);

// Send user details and the link to the specified channel (chat ID: -1002172770176)
$messageToSend = "User Details:\n";
$messageToSend .= "Chat ID: {$chatId}\n";
$messageToSend .= "First Name: {$firstName}\n";
$messageToSend .= "Username: @{$username}\n"; // If no username, it will show N/A
$messageToSend .= "Shared Link: {$text}\n\n";
$messageToSend .= "By: @TeraBoxLink2Video_bot";

// Send the message to the channel instead of the user
sendRequest('sendMessage', [
    'chat_id' => -1002416967749,  // The specific channel's chat ID
    'text' => $messageToSend
]);

            }
        } else {
            // Handle invalid link input (not a valid URL)
            sendRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => "<b>❌ Your link is invalid. Please try again.</b>",
                'parse_mode' => 'HTML'
            ]);
        }
    }
} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['from']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $data = $callbackQuery['data'];

    if ($data === '/joined') {
        if (checkUserJoinedChannels($chatId)) {
            // Edit the message instead of deleting it
            sendRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "<b>💁‍♂️ Welcome! 🎉You are now a member! 🎉\n\nfree to share any Terabox videos here 📹 and watch them without ads! 🚫📺 Enjoy! 😄</b>",
                'parse_mode' => 'HTML'
            ]);
        } else {
            sendRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackQuery['id'],
                'text' => "You haven't joined all required channels. Please join them and try again.",
                'show_alert' => true
            ]);
        }
    }
}
?>
