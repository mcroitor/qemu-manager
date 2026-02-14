<?php

/**
 * Authentication and authorization service.
 *
 * Handles session-based login/logout, role checks, and auth-related page routes.
 */
class auth
{
    /** @var string Viewer role identifier. */
    public const ROLE_VIEWER = 'viewer';
    /** @var string Operator role identifier. */
    public const ROLE_OPERATOR = 'operator';
    /** @var string Administrator role identifier. */
    public const ROLE_ADMIN = 'admin';

    /** @var string User service class name for dynamic static calls. */
    private const USER_CLASS = 'user';

    private const TEMPLATE_PATH = \config::templates_dir . \config::sep . 'auth';

    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USERNAME = 'auth_username';
    private const SESSION_ROLE = 'auth_role';
    private const SESSION_LOGIN_AT = 'auth_login_at';

    /**
     * Renders auth template by name.
     *
     * @param string $templateName Template base name.
     * @param array $data Placeholder values.
     * @return string Rendered HTML.
     */
    private static function renderTemplate(string $templateName, array $data = []): string
    {
        return \mc\template::load(self::TEMPLATE_PATH . \config::sep . "{$templateName}.tpl.php", \mc\template::comment_modifiers)
            ->fill($data)
            ->value();
    }

    /**
     * Wraps content inside shared auth card template.
     *
     * @param string $content Inner HTML.
     * @return string Rendered card HTML.
     */
    private static function renderCard(string $content): string
    {
        return self::renderTemplate('card', [
            'card-content' => $content,
        ]);
    }

    /**
     * Starts session if not started.
     *
     * @return void
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Invokes static method on user service class.
     *
     * @param string $method Method name.
     * @param array $args Method arguments.
     * @return mixed Method return value.
     */
    private static function userCall(string $method, array $args = [])
    {
        return forward_static_call_array([self::USER_CLASS, $method], $args);
    }

    /**
     * Route handler for login page.
     *
     * @param array $args Route arguments.
     * @return string Rendered login response.
     */
    #[\mc\route("auth/login")]
    public static function routeLogin(array $args): string
    {
        return self::loginPage();
    }

    /**
     * Route handler for registration page.
     *
     * @param array $args Route arguments.
     * @return string Rendered registration response.
     */
    #[\mc\route("auth/register")]
    public static function routeRegister(array $args): string
    {
        return self::registerPage();
    }

    /**
     * Route handler for logout action.
     *
     * @param array $args Route arguments.
     * @return string Rendered logout confirmation.
     */
    #[\mc\route("auth/logout")]
    public static function routeLogout(array $args): string
    {
        if (self::isAuthenticated()) {
            self::logout();
        }

        return self::renderCard(self::renderTemplate('logout-success'));
    }

    /**
     * Route handler for first-admin bootstrap page.
     *
     * @param array $args Route arguments.
     * @return string Rendered bootstrap response.
     */
    #[\mc\route("auth/bootstrap-admin")]
    public static function routeBootstrapAdmin(array $args): string
    {
        return self::bootstrapAdminPage();
    }

    /**
     * Attempts user authentication and initializes auth session.
     *
     * @param string $username Username.
     * @param string $password Plain password.
     * @return bool True on successful authentication.
     */
    public static function login(string $username, string $password): bool
    {
        self::ensureSession();
        self::userCall('ensureSchema');

        $user = self::userCall('verifyCredentials', [$username, $password]);
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

        self::userCall('touchLastLogin', [(int)$user['id']]);

        if (isset(\config::$logger) && \config::$logger !== null) {
            \config::$logger->info("auth.login.success user_id={$user['id']} username={$user['username']}");
        }
        return true;
    }

    /**
     * Logs out current user by clearing session auth keys.
     *
     * @return void
     */
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

    /**
     * Checks whether user is authenticated.
     *
     * @return bool True when auth session exists.
     */
    public static function isAuthenticated(): bool
    {
        self::ensureSession();
        return isset($_SESSION[self::SESSION_USER_ID]);
    }

    /**
     * Returns authenticated user ID.
     *
     * @return int|null User ID or null when unauthenticated.
     */
    public static function currentUserId(): ?int
    {
        self::ensureSession();
        if (!isset($_SESSION[self::SESSION_USER_ID])) {
            return null;
        }
        return (int)$_SESSION[self::SESSION_USER_ID];
    }

    /**
     * Returns authenticated username.
     *
     * @return string|null Username or null.
     */
    public static function currentUsername(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::SESSION_USERNAME] ?? null;
    }

    /**
     * Returns authenticated user role.
     *
     * @return string|null Role name or null.
     */
    public static function currentRole(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::SESSION_ROLE] ?? null;
    }

    /**
     * Returns current authenticated user profile.
     *
     * @return array|null User data or null.
     */
    public static function currentUser(): ?array
    {
        $id = self::currentUserId();
        if ($id === null) {
            return null;
        }
        return self::userCall('findById', [$id]);
    }

    /**
     * Checks whether current user has required role.
     *
     * @param string $requiredRole Required role.
     * @return bool True when role requirement is satisfied.
     */
    public static function hasRole(string $requiredRole): bool
    {
        $user = self::currentUser();
        if ($user === null) {
            return false;
        }
        return self::userCall('hasRole', [$user, $requiredRole]);
    }

    /**
     * Ensures user is authenticated.
     *
     * @return bool True when authenticated.
     */
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

    /**
     * Checks whether first admin bootstrap is required.
     *
     * @return bool True when no active admin exists.
     */
    public static function needsBootstrapAdmin(): bool
    {
        self::userCall('ensureSchema');
        return !self::userCall('hasAnyAdmin');
    }

    /**
     * Renders or processes first-admin bootstrap form.
     *
     * @return string Rendered bootstrap page or result.
     */
    public static function bootstrapAdminPage(): string
    {
        self::userCall('ensureSchema');

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

            $id = self::userCall('create', [[
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => self::ROLE_ADMIN,
            ]]);

            if ($id === false) {
                return self::renderBootstrapAdminForm('Failed to create admin user. Check input or duplicates.', $username, $email);
            }

            self::login($username, $password);

            return self::renderCard(self::renderTemplate('bootstrap-created'));
        }

        return self::renderCard(self::renderBootstrapAdminForm());
    }

    /**
     * Ensures user has required role.
     *
     * @param string $requiredRole Required role.
     * @return bool True when access is allowed.
     */
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

    /**
     * Returns standardized access denied block.
     *
     * @param string $resourceLabel Human-readable protected resource name.
     * @return string Rendered access denied HTML.
     */
    public static function renderAccessDenied(string $resourceLabel): string
    {
        $safeResourceLabel = htmlspecialchars($resourceLabel);
        return "<div style='color: red; background: #ffe6e6; padding: 15px; border: 1px solid #ff0000; margin-bottom: 10px;'>" .
               "<h3>Access denied</h3>" .
               "<p>You must be authenticated as operator or admin to access {$safeResourceLabel}.</p>" .
               "<p><a href='/?q=auth/login' class='button button-primary'>Login</a></p>" .
               "</div>";
    }

    /**
     * Renders or processes login form.
     *
     * @return string Rendered login page or result.
     */
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

    /**
     * Renders or processes registration form.
     *
     * @return string Rendered registration page or result.
     */
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

            $id = self::userCall('create', [[
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => self::ROLE_VIEWER,
            ]]);

            if ($id === false) {
                return self::renderCard(self::renderRegisterForm("Failed to create user. Check input or duplicates.", $username, $email));
            }

            return self::renderCard(self::renderTemplate('registration-success'));
        }

        return self::renderCard(self::renderRegisterForm());
    }

    /**
     * Renders login form and optional error wrapper.
     *
     * @param string $error Optional error message.
     * @param string $username Pre-filled username.
     * @return string Rendered form HTML.
     */
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

    /**
     * Renders registration form and optional error wrapper.
     *
     * @param string $error Optional error message.
     * @param string $username Pre-filled username.
     * @param string $email Pre-filled email.
     * @return string Rendered form HTML.
     */
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

    /**
     * Renders bootstrap-admin form and optional error wrapper.
     *
     * @param string $error Optional error message.
     * @param string $username Pre-filled username.
     * @param string $email Pre-filled email.
     * @return string Rendered form HTML.
     */
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
