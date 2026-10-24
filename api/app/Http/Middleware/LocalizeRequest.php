<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every API request:
 *  1. Locale: `ar` (default) or `en` from the SPA's X-Locale / Accept-Language,
 *     so validation messages come back in the user's language.
 *  2. Arabic-Indic / Persian digits (٠-٩ ۰-۹) in any input become 0-9, so a
 *     number typed on an Arabic keyboard is not rejected as "not a number".
 *  3. Phone-like fields (phone, phone2, whatsapp, *_phone) must contain digits
 *     only (plus + spaces - ( )) — checked here once for every form.
 */
class LocalizeRequest
{
    private const AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٫', '،'];
    private const EN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', ','];

    public function handle(Request $request, Closure $next): Response
    {
        $lang = strtolower(substr((string) ($request->header('X-Locale') ?: $request->header('Accept-Language', 'ar')), 0, 2));
        app()->setLocale($lang === 'en' ? 'en' : 'ar');

        $input = $request->all();
        array_walk_recursive($input, function (&$v) {
            if (is_string($v) && preg_match('/[٠-٩۰-۹]/u', $v)) {
                $v = str_replace(self::AR, self::EN, $v);
            }
        });
        $request->merge($input);

        $errors = [];
        foreach (Arr::dot($input) as $key => $value) {
            $field = last(explode('.', $key));
            if (! preg_match('/^(phone2?|whatsapp|[a-z_]+_phone)$/', $field) || $value === null || $value === '') {
                continue;
            }
            if (! is_string($value) || ! preg_match('/^\+?[0-9\s\-()]{3,25}$/', $value)) {
                $attr = trans()->has("validation.attributes.$key") ? trans("validation.attributes.$key") : (trans()->has("validation.attributes.$field") ? trans("validation.attributes.$field") : $field);
                $errors[$key] = [app()->getLocale() === 'ar'
                    ? trans('validation.phone', ['attribute' => $attr])
                    : "The $attr must be a valid phone number (digits only)."];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }
}
