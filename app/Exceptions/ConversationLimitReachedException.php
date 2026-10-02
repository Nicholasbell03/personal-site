<?php

namespace App\Exceptions;

/**
 * The conversation already has the maximum number of visitor messages (agent.portfolio.max_conversation_turns).
 */
class ConversationLimitReachedException extends \RuntimeException {}
