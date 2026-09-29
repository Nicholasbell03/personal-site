<?php

namespace App\Exceptions;

/**
 * The chatbot user that owns anonymous conversations doesn't exist (run ChatbotUserSeeder).
 */
class ChatUnavailableException extends \RuntimeException {}
