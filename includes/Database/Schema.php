<?php
/**
 * Custom database table definitions for RankMySEO.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Database;

/**
 * Defines custom table names and dbDelta-compatible SQL schemas.
 */
final class Schema {
	/**
	 * Current database schema version.
	 */
	public const DB_VERSION = 2;

	/**
	 * Logical table keys mapped to their unprefixed suffixes.
	 *
	 * @var array<string, string>
	 */
	public const TABLE_SUFFIXES = [
		'redirects'      => 'rankmyseo_redirects',
		'404_logs'       => 'rankmyseo_404_logs',
		'ai_actions'     => 'rankmyseo_ai_actions',
		'ai_snapshots'   => 'rankmyseo_ai_snapshots',
		'audit_logs'     => 'rankmyseo_audit_logs',
		'sync_queue'     => 'rankmyseo_sync_queue',
		'import_backups' => 'rankmyseo_import_backups',
		'link_edges'     => 'rankmyseo_link_edges',
		'link_counts'    => 'rankmyseo_link_counts',
	];

	/**
	 * Return all prefixed custom table names keyed by logical identifier.
	 *
	 * @param string $prefix WordPress database table prefix (e.g. "wp_").
	 * @return array<string, string>
	 */
	public static function get_table_names( string $prefix ): array {
		$tables = [];

		foreach ( self::TABLE_SUFFIXES as $key => $suffix ) {
			$tables[ $key ] = $prefix . $suffix;
		}

		return $tables;
	}

	/**
	 * Return a single prefixed table name by logical key.
	 *
	 * @param string $key    Logical table key from TABLE_SUFFIXES.
	 * @param string $prefix WordPress database table prefix.
	 * @return string
	 * @throws \InvalidArgumentException When the logical table key is unknown.
	 */
	public static function get_table_name( string $key, string $prefix ): string {
		if ( ! isset( self::TABLE_SUFFIXES[ $key ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unknown RankMySEO table key "%s".', esc_html( $key ) )
			);
		}

		return $prefix . self::TABLE_SUFFIXES[ $key ];
	}

	/**
	 * Return dbDelta-compatible CREATE TABLE SQL statements for all plugin tables.
	 *
	 * Notes on dbDelta() formatting requirements:
	 * - Two spaces between PRIMARY KEY and the definition.
	 * - One column/index definition per line.
	 * - KEY (not INDEX) for secondary indexes.
	 *
	 * @param string $prefix          WordPress table prefix.
	 * @param string $charset_collate Database charset and collation clause.
	 * @return array<string, string> SQL statements keyed by logical table key.
	 */
	public static function get_schema_sql( string $prefix, string $charset_collate ): array {
		$tables = self::get_table_names( $prefix );

		return [
			'redirects'      => "CREATE TABLE {$tables['redirects']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_path varchar(512) NOT NULL,
				source_hash char(64) NOT NULL,
				match_type varchar(16) NOT NULL DEFAULT 'exact',
				target_url varchar(1024) DEFAULT NULL,
				status_code smallint(5) unsigned NOT NULL DEFAULT 301,
				is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_hit_at datetime DEFAULT NULL,
				source_origin varchar(24) NOT NULL DEFAULT 'manual',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY active_match_hash (is_active, match_type, source_hash),
				KEY active_match_type (is_active, match_type),
				KEY source_origin (source_origin)
			) {$charset_collate};",

			'404_logs'       => "CREATE TABLE {$tables['404_logs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				request_path varchar(512) NOT NULL,
				path_hash char(64) NOT NULL,
				query_string varchar(512) NOT NULL DEFAULT '',
				referer varchar(512) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				hits int(10) unsigned NOT NULL DEFAULT 1,
				status varchar(20) NOT NULL DEFAULT 'open',
				first_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY path_hash (path_hash),
				KEY status_last_seen (status, last_seen_at),
				KEY hits (hits)
			) {$charset_collate};",

			'ai_actions'     => "CREATE TABLE {$tables['ai_actions']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				cloud_action_id char(36) NOT NULL,
				source_kind varchar(32) NOT NULL DEFAULT 'page_edit',
				action_type varchar(48) NOT NULL,
				wp_post_id bigint(20) unsigned DEFAULT NULL,
				target_url varchar(1024) NOT NULL DEFAULT '',
				base_hash char(64) DEFAULT NULL,
				before_payload longtext DEFAULT NULL,
				proposed_payload longtext NOT NULL,
				rationale text DEFAULT NULL,
				risk_tier varchar(16) NOT NULL DEFAULT 'low',
				status varchar(24) NOT NULL DEFAULT 'pending',
				applied_by varchar(16) DEFAULT NULL,
				wp_actor_id bigint(20) unsigned DEFAULT NULL,
				wp_revision_id bigint(20) unsigned DEFAULT NULL,
				error_message text DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				resolved_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY cloud_action_id (cloud_action_id),
				KEY status_created (status, created_at),
				KEY post_status (wp_post_id, status),
				KEY risk_status (risk_tier, status)
			) {$charset_collate};",

			'ai_snapshots'   => "CREATE TABLE {$tables['ai_snapshots']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				action_id bigint(20) unsigned NOT NULL,
				wp_post_id bigint(20) unsigned DEFAULT NULL,
				wp_revision_id bigint(20) unsigned DEFAULT NULL,
				object_type varchar(24) NOT NULL DEFAULT 'post',
				snapshot_json longtext NOT NULL,
				restored_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY action_id (action_id),
				KEY wp_post_id (wp_post_id)
			) {$charset_collate};",

			'audit_logs'     => "CREATE TABLE {$tables['audit_logs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(64) NOT NULL,
				actor_type varchar(24) NOT NULL DEFAULT 'user',
				wp_user_id bigint(20) unsigned DEFAULT NULL,
				object_type varchar(32) NOT NULL DEFAULT 'site',
				object_id bigint(20) unsigned DEFAULT NULL,
				summary varchar(255) NOT NULL DEFAULT '',
				context_json longtext DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY event_created (event_type, created_at),
				KEY object_lookup (object_type, object_id)
			) {$charset_collate};",

			'sync_queue'     => "CREATE TABLE {$tables['sync_queue']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_type varchar(48) NOT NULL,
				entity_type varchar(32) NOT NULL DEFAULT 'post',
				entity_id bigint(20) unsigned DEFAULT NULL,
				dedupe_key char(64) DEFAULT NULL,
				payload_json longtext DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				last_error text DEFAULT NULL,
				scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				locked_at datetime DEFAULT NULL,
				completed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status_scheduled (status, scheduled_at),
				KEY dedupe_status (dedupe_key, status),
				KEY job_type (job_type)
			) {$charset_collate};",

			'import_backups' => "CREATE TABLE {$tables['import_backups']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				batch_id char(36) NOT NULL,
				source_plugin varchar(24) NOT NULL,
				object_type varchar(24) NOT NULL,
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				previous_data longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY batch_id (batch_id),
				KEY source_object (source_plugin, object_type, object_id)
			) {$charset_collate};",

			'link_edges'     => "CREATE TABLE {$tables['link_edges']} (
				source_id bigint(20) unsigned NOT NULL,
				target_id bigint(20) unsigned NOT NULL,
				PRIMARY KEY  (source_id, target_id),
				KEY target_id (target_id)
			) {$charset_collate};",

			'link_counts'    => "CREATE TABLE {$tables['link_counts']} (
				post_id bigint(20) unsigned NOT NULL,
				outgoing_count int(10) unsigned NOT NULL DEFAULT 0,
				incoming_count int(10) unsigned NOT NULL DEFAULT 0,
				is_cornerstone tinyint(1) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (post_id),
				KEY incoming_count (incoming_count)
			) {$charset_collate};",
		];
	}
}
