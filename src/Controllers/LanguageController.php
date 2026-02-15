<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\Translator;
use TP\Core\Attributes\Route;
use TP\Core\ValidationRule;

final class LanguageController
{
    /**
     * Switch language
     */
    #[Route('POST', '/language/switch')]
    public function switchLanguage(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('locale', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }

        $locale = $request->getString('locale');
        $validLocales = ['de', 'en', 'es'];

        if (!in_array($locale, $validLocales, true)) {
            return Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }

        // Store language preference in session
        $_SESSION['locale'] = $locale;

        // Update translator instance
        Translator::getInstance()->setLocale($locale);

        // Redirect back to previous page or home
        $redirectUrl = $request->getString('redirect', $_SERVER['HTTP_REFERER'] ?? '/');
        return Response::redirect($redirectUrl);
    }

    /**
     * Get current language
     */
    #[Route('GET', '/language/current')]
    public function getCurrentLanguage(Request $request): Response
    {
        $currentLocale = Translator::getInstance()->getLocale();
        return Response::json([
            'locale' => $currentLocale,
            'name' => __("languages.{$currentLocale}")
        ]);
    }
}
