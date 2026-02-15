<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chatbot User
    |--------------------------------------------------------------------------
    |
    | The dedicated user that owns all anonymous chat conversations.
    | The ChatbotUserSeeder creates this user, and the ChatController
    | resolves the user ID at runtime by looking up this email.
    |
    */

    'user' => [
        'name' => env('CHAT_USER_NAME', 'Chatbot'),
        'email' => env('CHAT_USER_EMAIL', 'chatbot@nickbell.dev'),
    ],

];
