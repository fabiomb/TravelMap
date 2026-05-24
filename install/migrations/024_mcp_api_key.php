<?php
/**
 * Migration 024: Add users.mcp_api_key
 *
 * Stores a static Bearer token (tmk_<64 hex chars>) per user for
 * authenticating requests to the remote MCP HTTP endpoint.
 */
class Migration_024_mcp_api_key
{
    public static function id(): string
    {
        return '024_mcp_api_key';
    }

    public static function description(): string
    {
        return 'users: add mcp_api_key column for MCP remote authentication';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'mcp_api_key'");
        return $stmt->fetch() !== false;
    }

    public static function up(PDO $db): void
    {
        $db->exec("ALTER TABLE users
            ADD COLUMN mcp_api_key VARCHAR(68) DEFAULT NULL,
            ADD UNIQUE INDEX idx_mcp_api_key (mcp_api_key)");
    }
}
