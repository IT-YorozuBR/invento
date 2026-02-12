<?php
declare(strict_types=1);

namespace App\Core;

class Security
{
    private const CSRF_KEY = 'csrf_token';

    // -----------------------------------------------------------------------
    // CSRF
    // -----------------------------------------------------------------------

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    // -----------------------------------------------------------------------
    // SANITIZAÇÃO
    // -----------------------------------------------------------------------

    public static function sanitize(string $input): string
    {
        return htmlspecialchars(stripslashes(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeArray(array $input): array
    {
        return array_map(fn($v) => is_string($v) ? self::sanitize($v) : $v, $input);
    }

    // -----------------------------------------------------------------------
    // VALIDAÇÃO
    // -----------------------------------------------------------------------

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateInt($value, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        return $filtered !== false ? $filtered : null;
    }

    public static function validateString(string $value, int $min = 1, int $max = 255): bool
    {
        $len = mb_strlen($value, 'UTF-8');
        return $len >= $min && $len <= $max;
    }

    public static function validateDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    // -----------------------------------------------------------------------
    // SESSÃO
    // -----------------------------------------------------------------------

    public static function checkSessionTimeout(): void
    {
        $timeout = (int) ($_ENV['SESSION_TIMEOUT'] ?? 3600);
        if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            session_unset();
            session_destroy();
            header('Location: ?pagina=login&timeout=1');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    public static function requireAuth(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ?pagina=login');
            exit;
        }
        self::checkSessionTimeout();
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
            header('Location: ?pagina=dashboard');
            exit;
        }
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['usuario_tipo'] ?? '') === 'admin';
    }

    public static function currentUserId(): int
    {
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    public static function currentUserName(): string
    {
        return $_SESSION['usuario_nome'] ?? '';
    }

    public static function currentUserType(): string
    {
        return $_SESSION['usuario_tipo'] ?? '';
    }
}
