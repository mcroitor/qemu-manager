<?php

class user
{
	private const TABLE = "users";
	private static bool $schemaReady = false;
	public const ROLE_ADMIN = "admin";
	public const ROLE_OPERATOR = "operator";
	public const ROLE_VIEWER = "viewer";

	private const ROLE_PRIORITY = [
		self::ROLE_VIEWER => 10,
		self::ROLE_OPERATOR => 20,
		self::ROLE_ADMIN => 30,
	];

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

	private static function now(): string
	{
		return date('Y-m-d H:i:s');
	}

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

	private static function sanitize(array $row): array
	{
		unset($row['password_hash']);
		return $row;
	}

	public static function roles(): array
	{
		return [
			self::ROLE_ADMIN,
			self::ROLE_OPERATOR,
			self::ROLE_VIEWER,
		];
	}

	public static function findById(int $id): ?array
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["id" => $id], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			return null;
		}
		return self::sanitize($rows[0]);
	}

	public static function findByUsername(string $username): ?array
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["*"], ["username" => $username], ["offset" => 0, "limit" => 1]);
		if (empty($rows)) {
			return null;
		}
		return self::sanitize($rows[0]);
	}

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

	public static function touchLastLogin(int $id): void
	{
		self::ensureSchema();
		\config::$db->update(self::TABLE, [
			'last_login_at' => self::now(),
			'updated_at' => self::now(),
		], ['id' => $id]);
	}

	public static function countUsers(): int
	{
		self::ensureSchema();
		$rows = \config::$db->select(self::TABLE, ["count(*) as count"]);
		if (empty($rows)) {
			return 0;
		}
		return (int)$rows[0]['count'];
	}

	public static function hasAnyAdmin(): bool
	{
		self::ensureSchema();
		return \config::$db->exists(self::TABLE, ["role" => self::ROLE_ADMIN, "is_active" => 1]);
	}

	public static function hasRole(array $user, string $requiredRole): bool
	{
		$actual = $user['role'] ?? self::ROLE_VIEWER;
		$actualPriority = self::ROLE_PRIORITY[$actual] ?? 0;
		$requiredPriority = self::ROLE_PRIORITY[$requiredRole] ?? 0;
		return $actualPriority >= $requiredPriority;
	}
}