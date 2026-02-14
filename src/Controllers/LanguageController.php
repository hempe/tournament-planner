<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\Translator;
use TP\Core\Attributes\Route;

final class LanguageController
{
    /**
     * Switch language
     */
    #[Route('POST', '/language/switch')]
    public function switchLanguage(Request $request): Response
    {
        $data = $request->validate([
            'locale' => 'required',
            'redirect' => 'optional'
        ]);

        $locale = $data['locale'];
        $validLocales = ['de_CH', 'en_US', 'es_ES'];

        if (!in_array($locale, $validLocales, true)) {
            return Response::json(['error' => 'Invalid locale'], 400);
        }

        // Store language preference in session
        $_SESSION['locale'] = $locale;

        // Update translator instance
        Translator::getInstance()->setLocale($locale);

        // Redirect back to previous page or home
        $redirectUrl = $data['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/';
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
