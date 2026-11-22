<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\ClientErrorReport;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\RateLimiter;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Middleware\CsrfMiddleware;
use TP\Models\User;

/** Unexpected frontend errors, written to the app log so they are not invisible. */
#[RoutePrefix('/api/client-errors')]
#[Middleware(CsrfMiddleware::class)]
final class ClientErrorController
{
    private const MAX_REPORTS = 20;
    private const WINDOW_SECONDS = 600;

    #[Post('')]
    #[Body(ClientErrorReport::class)]
    public function report(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('message', ['required', 'string']),
            new ValidationRule('stack', ['string']),
            new ValidationRule('url', ['required', 'string']),
            new ValidationRule('appVersion', ['required', 'string']),
        ]);
        if (!$validation->isValid) {
            return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $validation->fieldErrors());
        }

        $ip = $request->clientIp();
        // Beyond the limit the report is dropped without telling the client
        if (!RateLimiter::fromEnvironment()->hit('client-error:' . $ip, self::MAX_REPORTS, self::WINDOW_SECONDS)) {
            return Response::noContent();
        }

        $stack = $request->getString('stack');
        logger()->error('Client error', [
            'message' => $this->clean($request->getString('message'), 500),
            'stack' => $stack === '' ? null : $this->clean($stack, 4000, true),
            'url' => $this->clean($request->getString('url'), 300),
            'appVersion' => $this->clean($request->getString('appVersion'), 50),
            'clientIp' => $ip,
            'userId' => User::id(),
            'userAgent' => $this->clean($request->getUserAgent(), 200),
        ]);

        return Response::noContent();
    }

    /** Strip control characters (optionally keeping line breaks and tabs) and truncate. */
    private function clean(string $value, int $maxLength, bool $keepLineBreaks = false): string
    {
        $pattern = $keepLineBreaks ? '/[\x00-\x08\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
        $value = (string) preg_replace($pattern, '', mb_scrub($value));

        return mb_substr($value, 0, $maxLength);
    }
}
