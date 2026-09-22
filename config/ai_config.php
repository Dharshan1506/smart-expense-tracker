<?php
/**
 * AI Assistant & Gemini API Configuration
 * Secure server-side configuration for Ask Me Help AI Chatbot
 * 
 * IMPORTANT SECURITY:
 * Never expose the Gemini API key to client-side JavaScript.
 * This file is only read server-side by PHP.
 */
declare(strict_types=1);

// Attempt to load API key from environment variable or define it here
if (!defined('GEMINI_API_KEY')) {
    $envKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
    
    // Check for optional .env in config directory if present
    if (empty($envKey) && file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) continue;
            if (str_starts_with($line, 'GEMINI_API_KEY=')) {
                $envKey = trim(substr($line, strlen('GEMINI_API_KEY=')));
                break;
            }
        }
    }
    
    define('GEMINI_API_KEY', $envKey);
}

// Model configuration
define('GEMINI_MODEL', 'gemini-1.5-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
