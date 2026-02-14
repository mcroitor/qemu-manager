<?php

/**
 * User domain service.
 *
 * Provides schema bootstrap, user CRUD-related operations,
 * role checks, credential verification, and audit logging.
 */
class user
{
	/** @var string Users table name. */
	private const TABLE = "users";
	/** @var bool Schema initialization guard. */
	private static bool $schemaReady = false;
	/** @var string Administrator role. */
	public const ROLE_ADMIN = "admin";
	/** @var string Operator role. */
	public const ROLE_OPERATOR = "operator";
	/** @var string Viewer role. */
	public const ROLE_VIEWER = "viewer";

	private const ROLE_PRIORITY = [
		self::ROLE_VIEWER => 10,
		self::ROLE_OPERATOR => 20,
		self::ROLE_ADMIN => 30,
	];

	/**
	 * Writes user-related audit entry to logger.
	 *
	 * @param string $action Audit action key.
	 * @param array $context Additional structured context.
	 * @param string $level Log level (`info`, `warn`, `error`).
	 * @return void
	 */
	private static function audit(string $action, array $context = [], string $level = 'info'): void
	{
		if (!isset(\config::$logger) || \config::$logger === null) {
			return;
		}

		$payload = [
			'component' => 'user',
			'action' => $action,
			'context' => $context,
		];
		$message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($level === 'warn') {
			\config::$logger->warn($message);
			return;
		}
		if ($level === 'error') {
			\config::$logger->error($message);
			return;
		}

		\config::$logger->info($message);
	}

	/**
	 * Returns current timestamp in application format.
	 *
	 * @return string Timestamp string.
	 */
	private static function now(): string
	{
		return date('Y-m-d H:i:s');
	}

	/**
	 * Ensures `users` table exists.
	 *
	 * @return void
	 */
	public static function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}

		\config::$db->query(
			"CREATE TABLE IF NOT EXISTS users (" .
			"id INTEGER PRIMARY KEY AUTOINCREMENT," .
			"username TEXT NOT NULL UNIQUE," .
			"email TEXT NOT NULL UNIQUE," .
			"password_hash TEXT NOT NULL," .
			"role TEXT NOT NULL DEFAULT 'viewer'," .
			"is_active INTEGER NOT NULL DEFAULT 1," .
			"created_at TEXT NOT NULL," .
			"updated_at TEXT NOT NULL," .
			"last_login_at TEXT DEFAULT NULL" .
			")",
			"Error creating users table:",
			false
		);

		self::$schemaReady = true;
	}

	/**
	 * Removes sensitive fields from user row.
	 *
	 * @param array $row Raw database row.
	 * @return array Sanitized user row.
	 */
	private static function sanitize(array $row): array
	{
		unset($row['password_hash']);
		return $row;
	}

	/**
	 * Returns supported role identifiers.
	 *
	 * @return array<int, string> Role list.
	 */
	public static function roles(): array
	{
		return [
			self::ROLE_ADMIN,
			self::ROLE_OPERATOR,
			self::ROLE_VIEWER,
		];
	}

	/**
	 * Finds user by numeric identifier.
	 *
	 * @param int $id User ID.
	 * @return array|null Sanitized user data or null.
	 */
	public static function findById(int $id): ?array
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["id" => $id], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			return null;
		}
		return self::sanitize($rows[0]);
	}

	/**
	 * Finds user by username.
	 *
	 * @param string $username Username.
	 * @return array|null Sanitized user data or null.
	 */
	public static function findByUsername(string $username): ?array
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["username" => $username], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			return null;
		}
		return self::sanitize($rows[0]);
	}

	/**
	 * Creates a new user account.
	 *
	 * @param array $data User payload (`username`, `email`, `password`, optional `role`).
	 * @return string|false New user ID on success, otherwise false.
	 */
	public static function create(array $data): string|false
	{
		self::ensureSchema();
		$validator = new \mc\Validator($data);
		$validator
			->required('username', 'Username is required')
			->pattern('username', '/^[a-zA-Z0-9_.-]{3,32}$/', 'Username must be 3-32 chars and contain only letters, numbers, dot, underscore, hyphen')
			->required('email', 'Email is required')
			->email('email')
			->required('password', 'Password is required')
			->minLength('password', 8, 'Password must be at least 8 characters')
			->in('role', self::roles(), 'Invalid user role');

		if ($validator->hasErrors()) {
			self::audit('create.validation_failed', ['errors' => $validator->getErrors()], 'warn');
			return false;
		}

		$username = $data['username'];
		$email = $data['email'];
		$password = $data['password'];
		$role = $data['role'] ?? self::ROLE_VIEWER;

		if (\config::$db->exists(self::TABLE, ['username' => $username])) {
			self::audit('create.duplicate_username', ['username' => $username], 'warn');
			return false;
		}
		if (\config::$db->exists(self::TABLE, ['email' => $email])) {
			self::audit('create.duplicate_email', ['email' => $email], 'warn');
			return false;
		}

		$now = self::now();
		$id = \config::$db->insert(self::TABLE, [
			'username' => $username,
			'email' => $email,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'role' => $role,
			'is_active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
			'last_login_at' => null,
		]);

		self::audit('create.success', ['user_id' => $id, 'username' => $username, 'role' => $role]);
		return $id;
	}

	/**
	 * Verifies login credentials for active user.
	 *
	 * @param string $username Username.
	 * @param string $password Plain password.
	 * @return array|null Sanitized user data on success, otherwise null.
	 */
	public static function verifyCredentials(string $username, string $password): ?array
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["username" => $username, "is_active" => 1], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			self::audit('auth.login_user_not_found', ['username' => $username], 'warn');
			return null;
		}

		$user = $rows[0];
		if (!password_verify($password, $user['password_hash'])) {
			self::audit('auth.login_invalid_password', ['username' => $username], 'warn');
			return null;
		}

		self::audit('auth.login_verified', ['user_id' => $user['id'], 'username' => $user['username']]);
		return self::sanitize($user);
	}

	/**
	 * Updates user profile fields.
	 *
	 * @param int $id User ID.
	 * @param array $data Allowed fields: `email`, `role`, `is_active`.
	 * @return bool True on success, false on validation or persistence failure.
	 */
	public static function updateProfile(int $id, array $data): bool
	{
		self::ensureSchema();
		$currentRows = \config::$db->select(self::TABLE, ["*"], ["id" => $id], ["offset" => 0, "limit" => 1]);
		if (empty($currentRows)) {
			self::audit('update_profile.not_found', ['user_id' => $id], 'warn');
			return false;
		}

		$current = $currentRows[0];
		$newEmail = $data['email'] ?? $current['email'];
		$newRole = $data['role'] ?? $current['role'];
		$newActive = array_key_exists('is_active', $data) ? (int)$data['is_active'] : (int)$current['is_active'];

		$validator = new \mc\Validator([
			'email' => $newEmail,
			'role' => $newRole,
			'is_active' => $newActive,
		]);
		$validator
			->required('email', 'Email is required')
			->email('email')
			->in('role', self::roles(), 'Invalid user role')
			->in('is_active', [0, 1], 'is_active must be 0 or 1');

		if ($validator->hasErrors()) {
			self::audit('update_profile.validation_failed', ['user_id' => $id, 'errors' => $validator->getErrors()], 'warn');
			return false;
		}

		if ($newEmail !== $current['email'] && \config::$db->exists(self::TABLE, ['email' => $newEmail])) {
			self::audit('update_profile.duplicate_email', ['user_id' => $id, 'email' => $newEmail], 'warn');
			return false;
		}

		\config::$db->update(self::TABLE, [
			'email' => $newEmail,
			'role' => $newRole,
			'is_active' => $newActive,
			'updated_at' => self::now(),
		], ['id' => $id]);

		self::audit('update_profile.success', ['user_id' => $id]);
		return true;
	}

	/**
	 * Changes user password after old password verification.
	 *
	 * @param int $id User ID.
	 * @param string $oldPassword Current password.
	 * @param string $newPassword New password.
	 * @return bool True on success, otherwise false.
	 */
	public static function changePassword(int $id, string $oldPassword, string $newPassword): bool
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["id" => $id], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			self::audit('change_password.not_found', ['user_id' => $id], 'warn');
			return false;
		}
		$user = $rows[0];

		if (!password_verify($oldPassword, $user['password_hash'])) {
			self::audit('change_password.invalid_old_password', ['user_id' => $id], 'warn');
			return false;
		}

		$validator = new \mc\Validator(['password' => $newPassword]);
		$validator->minLength('password', 8, 'Password must be at least 8 characters');
		if ($validator->hasErrors()) {
			self::audit('change_password.validation_failed', ['user_id' => $id, 'errors' => $validator->getErrors()], 'warn');
			return false;
		}

		\config::$db->update(self::TABLE, [
			'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
			'updated_at' => self::now(),
		], ['id' => $id]);

		self::audit('change_password.success', ['user_id' => $id]);
		return true;
	}

	/**
	 * Updates last-login timestamp for user.
	 *
	 * @param int $id User ID.
	 * @return void
	 */
	public static function touchLastLogin(int $id): void
	{
		self::ensureSchema();
		\config::$db->update(self::TABLE, [
			'last_login_at' => self::now(),
			'updated_at' => self::now(),
		], ['id' => $id]);
	}

	/**
	 * Returns total user count.
	 *
	 * @return int Number of users.
	 */
	public static function countUsers(): int
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["count(*) as count"]);
		if (empty($rows)) {
			return 0;
		}
		return (int)$rows[0]['count'];
	}

	/**
	 * Checks whether at least one active admin exists.
	 *
	 * @return bool True when active admin exists.
	 */
	public static function hasAnyAdmin(): bool
	{
		self::ensureSchema();
		return \config::$db->exists(self::TABLE, ["role" => self::ROLE_ADMIN, "is_active" => 1]);
	}

	/**
	 * Compares user role against required role using priority table.
	 *
	 * @param array $user User data containing `role`.
	 * @param string $requiredRole Required role.
	 * @return bool True when user role satisfies required role.
	 */
	public static function hasRole(array $user, string $requiredRole): bool
	{
		$actual = $user['role'] ?? self::ROLE_VIEWER;
		$actualPriority = self::ROLE_PRIORITY[$actual] ?? 0;
		$requiredPriority = self::ROLE_PRIORITY[$requiredRole] ?? 0;
		return $actualPriority >= $requiredPriority;
	}
}