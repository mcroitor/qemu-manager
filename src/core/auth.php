<?php

class auth
{
    private const TEMPLATE_PATH = \config::templates_dir . \config::sep . 'auth';

    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USERNAME = 'auth_username';
    private const SESSION_ROLE = 'auth_role';
    private const SESSION_LOGIN_AT = 'auth_login_at';

    private static function renderTemplate(string $templateName, array $data = []): string
    {
        return \mc\template::load(self::TEMPLATE_PATH . \config::sep . "{$templateName}.tpl.php", \mc\template::comment_modifiers)
            ->fill($data)
            ->value();
    }

    private static function renderCard(string $content): string
    {
        return self::renderTemplate('card', [
            'card-content' => $content,
        ]);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    #[\mc\route("auth/login")]
    public static function routeLogin(array $args): string
    {
        return self::loginPage();
    }

    #[\mc\route("auth/register")]
    public static function routeRegister(array $args): string
    {
        return self::registerPage();
    }

    #[\mc\route("auth/logout")]
    public static function routeLogout(array $args): string
    {
        if (self::isAuthenticated()) {
            self::logout();
        }

        return self::renderCard(self::renderTemplate('logout-success'));
    }

    #[\mc\route("auth/bootstrap-admin")]
    public static function routeBootstrapAdmin(array $args): string
    {
        return self::bootstrapAdminPage();
    }

    public static function login(string $username, string $password): bool
    {
        self::ensureSession();
        \user::ensureSchema();

        $user = user::verifyCredentials($username, $password);
        if ($user === null) {
            if (isset(\config::$logger) && \config::$logger !== null) {
                \config::$logger->warn("auth.login.failed username={$username}");
            }
            return false;
        }

        $_SESSION[self::SESSION_USER_ID] = (int)$user['id'];
        $_SESSION[self::SESSION_USERNAME] = (string)$user['username'];
        $_SESSION[self::SESSION_ROLE] = (string)$user['role'];
        $_SESSION[self::SESSION_LOGIN_AT] = date('Y-m-d H:i:s');

        user::touchLastLogin((int)$user['id']);

        if (isset(\config::$logger) && \config::$logger !== null) {
            \config::$logger->info("auth.login.success user_id={$user['id']} username={$user['username']}");
        }
        return true;
    }

    public static function logout(): void
    {
        self::ensureSession();

        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;
        $username = $_SESSION[self::SESSION_USERNAME] ?? '';

        unset($_SESSION[self::SESSION_USER_ID]);
        unset($_SESSION[self::SESSION_USERNAME]);
        unset($_SESSION[self::SESSION_ROLE]);
        unset($_SESSION[self::SESSION_LOGIN_AT]);

        if (isset(\config::$logger) && \config::$logger !== null) {
            \config::$logger->info("auth.logout user_id={$userId} username={$username}");
        }
    }

    public static function isAuthenticated(): bool
    {
        self::ensureSession();
        return isset($_SESSION[self::SESSION_USER_ID]);
    }

    public static function currentUserId(): ?int
    {
        self::ensureSession();
        if (!isset($_SESSION[self::SESSION_USER_ID])) {
            return null;
        }
        return (int)$_SESSION[self::SESSION_USER_ID];
    }

    public static function currentUsername(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::SESSION_USERNAME] ?? null;
    }

    public static function currentRole(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::SESSION_ROLE] ?? null;
    }

    public static function currentUser(): ?array
    {
        $id = self::currentUserId();
        if ($id === null) {
            return null;
        }
        return user::findById($id);
    }

    public static function hasRole(string $requiredRole): bool
    {
        $user = self::currentUser();
        if ($user === null) {
            return false;
        }
        return user::hasRole($user, $requiredRole);
    }

    public static function requireAuth(): bool
    {
        if (self::isAuthenticated()) {
            return true;
        }

        if (isset(\config::$logger) && \config::$logger !== null) {
            \config::$logger->warn('auth.require_auth.denied');
        }
        return false;
    }

    public static function needsBootstrapAdmin(): bool
    {
        \user::ensureSchema();
        return !\user::hasAnyAdmin();
    }

    public static function bootstrapAdminPage(): string
    {
        \user::ensureSchema();

        if (!self::needsBootstrapAdmin()) {
            return self::renderCard(self::renderTemplate('bootstrap-complete'));
        }

        if (!empty($_POST)) {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $password = (string)($_POST['password'] ?? '');
            $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

            if ($password !== $passwordConfirm) {
                return self::renderBootstrapAdminForm('Password confirmation does not match', $username, $email);
            }

            $id = \user::create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => \user::ROLE_ADMIN,
            ]);

            if ($id === false) {
                return self::renderBootstrapAdminForm('Failed to create admin user. Check input or duplicates.', $username, $email);
            }

            self::login($username, $password);

            return self::renderCard(self::renderTemplate('bootstrap-created'));
        }

        return self::renderCard(self::renderBootstrapAdminForm());
    }

    public static function requireRole(string $requiredRole): bool
    {
        if (!self::requireAuth()) {
            return false;
        }

        if (self::hasRole($requiredRole)) {
            return true;
        }

        if (isset(\config::$logger) && \config::$logger !== null) {
            \config::$logger->warn("auth.require_role.denied required={$requiredRole} user_id=" . self::currentUserId());
        }
        return false;
    }

    public static function loginPage(): string
    {
        if (self::isAuthenticated()) {
            $username = htmlspecialchars((string)self::currentUsername());
            return self::renderCard(self::renderTemplate('already-authenticated', [
                'username' => $username,
            ]));
        }

        if (!empty($_POST)) {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $password = (string)($_POST['password'] ?? '');

            if (self::login($username, $password)) {
                $safeUsername = htmlspecialchars($username);
                return self::renderCard(self::renderTemplate('login-success', [
                    'username' => $safeUsername,
                ]));
            }

            return self::renderCard(self::renderLoginForm("Invalid username or password", $username));
        }

        return self::renderCard(self::renderLoginForm());
    }

    public static function registerPage(): string
    {
        if (!empty($_POST)) {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $password = (string)($_POST['password'] ?? '');
            $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

            if ($password !== $passwordConfirm) {
                return self::renderCard(self::renderRegisterForm("Password confirmation does not match", $username, $email));
            }

            $id = \user::create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => \user::ROLE_VIEWER,
            ]);

            if ($id === false) {
                return self::renderCard(self::renderRegisterForm("Failed to create user. Check input or duplicates.", $username, $email));
            }

            return self::renderCard(self::renderTemplate('registration-success'));
        }

        return self::renderCard(self::renderRegisterForm());
    }

    private static function renderLoginForm(string $error = '', string $username = ''): string
    {
        $safeUsername = htmlspecialchars($username);
        $form = self::renderTemplate('login-form', [
            'username' => $safeUsername,
        ]);

        if ($error !== '') {
            return self::renderTemplate('form-error-wrapper', [
                'error-message' => htmlspecialchars($error),
                'form-content' => $form,
            ]);
        }

        return $form;
    }

    private static function renderRegisterForm(string $error = '', string $username = '', string $email = ''): string
    {
        $safeUsername = htmlspecialchars($username);
        $safeEmail = htmlspecialchars($email);
        $form = self::renderTemplate('register-form', [
            'username' => $safeUsername,
            'email' => $safeEmail,
        ]);

        if ($error !== '') {
            return self::renderTemplate('form-error-wrapper', [
                'error-message' => htmlspecialchars($error),
                'form-content' => $form,
            ]);
        }

        return $form;
    }

    private static function renderBootstrapAdminForm(string $error = '', string $username = '', string $email = ''): string
    {
        $safeUsername = htmlspecialchars($username);
        $safeEmail = htmlspecialchars($email);
        $form = self::renderTemplate('bootstrap-admin-form', [
            'username' => $safeUsername,
            'email' => $safeEmail,
        ]);

        if ($error !== '') {
            return self::renderTemplate('form-error-wrapper', [
                'error-message' => htmlspecialchars($error),
                'form-content' => $form,
            ]);
        }

        return $form;
    }
}
