<?php
/**
 * Helpers globaux disponibles partout.
 */

declare(strict_types=1);

use CityBus\Core\Config;
use CityBus\Core\Csrf;
use CityBus\Core\Auth;
use CityBus\Core\Session;
use CityBus\Core\Setting;
use CityBus\Core\View;
use CityBus\Core\App;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(Config::get('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('company_info')) {
    /**
     * Retourne les infos de la société (paramètres company.*) sous forme de tableau associatif.
     * Inclut un logo_base64 (data URI) si un logo est paramétré et que $withLogo est vrai.
     */
    function company_info(bool $withLogo = false): array
    {
        $info = [
            'name'       => Setting::getString('company.name', 'City Bus'),
            'legal_name' => Setting::getString('company.legal_name', ''),
            'address'    => Setting::getString('company.address', ''),
            'phone'      => Setting::getString('company.phone', ''),
            'email'      => Setting::getString('company.email', ''),
            'niu'        => Setting::getString('company.niu', ''),
            'rccm'       => Setting::getString('company.rccm', ''),
            'logo_path'  => Setting::getString('company.logo_path', ''),
        ];
        if ($withLogo && $info['logo_path']) {
            $full = BASE_PATH . '/public/' . ltrim($info['logo_path'], '/');
            if (is_file($full)) {
                $mime = mime_content_type($full) ?: 'image/png';
                $info['logo_base64'] = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($full));
            }
        }
        return $info;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $path = App::instance()->router->url($name, $params);
        return url(ltrim($path, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        \CityBus\Core\Response::redirect(str_starts_with($url, 'http') ? $url : url($url));
    }
}

if (!function_exists('back')) {
    function back(): never
    {
        \CityBus\Core\Response::back();
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $msg): void
    {
        Session::flash($type, $msg);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors(?string $field = null): mixed
    {
        $errors = Session::get('_errors', []);
        if ($field === null) {
            return array_map(
                fn($value) => is_array($value) ? implode(' ', $value) : (string)$value,
                $errors
            );
        }

        $value = $errors[$field] ?? [];
        return is_array($value) ? $value : [$value];
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return (new View())->render($template, $data);
    }
}

if (!function_exists('fcfa')) {
    /** Formate un montant en FCFA : 10000 → "10 000 FCFA" */
    function fcfa(int|float|null $amount): string
    {
        if ($amount === null) return '0 FCFA';
        return number_format((float)$amount, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('fcfa_short')) {
    function fcfa_short(int|float|null $amount): string
    {
        if ($amount === null) return '0';
        return number_format((float)$amount, 0, ',', ' ');
    }
}

if (!function_exists('date_fr')) {
    /** "lundi 27 avril 2026" */
    function date_fr(string|\DateTimeInterface|null $date, string $format = 'l j F Y'): string
    {
        if (!$date) return '';
        $ts = $date instanceof \DateTimeInterface ? $date->getTimestamp() : strtotime($date);
        if (class_exists('IntlDateFormatter')) {
            $fmt = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE,
                'Africa/Brazzaville', \IntlDateFormatter::GREGORIAN);
            return $fmt->format($ts);
        }
        // Fallback sans extension intl
        $jours = ['Sunday'=>'dimanche','Monday'=>'lundi','Tuesday'=>'mardi','Wednesday'=>'mercredi','Thursday'=>'jeudi','Friday'=>'vendredi','Saturday'=>'samedi'];
        $mois  = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
        return strtr(date($format, $ts), array_merge($jours, $mois));
    }
}

if (!function_exists('time_fr')) {
    function time_fr(string|null $time): string
    {
        if (!$time) return '';
        $ts = strtotime($time);
        return date('H\hi', $ts);
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        echo '<pre style="background:#0D1B2A;color:#42A5F5;padding:1rem;border-radius:8px;font-size:.85rem;">';
        foreach ($vars as $v) { var_dump($v); }
        echo '</pre>';
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never { dump(...$vars); exit; }
}

if (!function_exists('str_uuid')) {
    function str_uuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(Config::get('app.timezone', 'Africa/Brazzaville')));
    }
}
