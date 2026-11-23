<?php
/**
 * Action types shared with RankMySEO Cloud.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

/**
 * Names, risk, and plain-language labels for queued actions.
 *
 * Cloud payloads use these actionType strings. Local aliases from older notes
 * are accepted and normalized before execution.
 */
final class ActionCatalog {
	public const UPDATE_TITLE            = 'update_title';
	public const UPDATE_META_DESCRIPTION = 'update_meta_description';
	public const UPDATE_CANONICAL        = 'update_canonical';
	public const UPDATE_SCHEMA           = 'update_schema';
	public const ADD_REDIRECT            = 'add_redirect';
	public const ADD_INTERNAL_LINK       = 'add_internal_link';
	public const CONTENT_PATCH           = 'content_patch';
	public const CREATE_DRAFT            = 'create_draft';

	public const ALIAS_META     = 'meta_update';
	public const ALIAS_SCHEMA   = 'schema_update';
	public const ALIAS_REDIRECT = 'redirect_create';
	public const ALIAS_DRAFT    = 'draft_article';

	/**
	 * Types the cloud API accepts.
	 *
	 * @var list<string>
	 */
	public const CLOUD_TYPES = array(
		self::UPDATE_TITLE,
		self::UPDATE_META_DESCRIPTION,
		self::UPDATE_CANONICAL,
		self::UPDATE_SCHEMA,
		self::ADD_REDIRECT,
		self::ADD_INTERNAL_LINK,
		self::CONTENT_PATCH,
		self::CREATE_DRAFT,
	);

	/**
	 * Types that may be stored on an action row, including local aliases.
	 *
	 * @var list<string>
	 */
	public const KNOWN_TYPES = array(
		self::UPDATE_TITLE,
		self::UPDATE_META_DESCRIPTION,
		self::UPDATE_CANONICAL,
		self::UPDATE_SCHEMA,
		self::ADD_REDIRECT,
		self::ADD_INTERNAL_LINK,
		self::CONTENT_PATCH,
		self::CREATE_DRAFT,
		self::ALIAS_META,
		self::ALIAS_SCHEMA,
		self::ALIAS_REDIRECT,
		self::ALIAS_DRAFT,
	);

	/**
	 * Whether a string is a known action type.
	 *
	 * @param string $type Raw type.
	 * @return bool
	 */
	public static function is_known( string $type ): bool {
		return in_array( $type, self::KNOWN_TYPES, true );
	}

	/**
	 * Map a local alias onto the cloud type used for execution.
	 *
	 * @param string $type Stored type.
	 * @return string
	 */
	public static function canonical( string $type ): string {
		$map = array(
			self::ALIAS_SCHEMA   => self::UPDATE_SCHEMA,
			self::ALIAS_REDIRECT => self::ADD_REDIRECT,
			self::ALIAS_DRAFT    => self::CREATE_DRAFT,
		);
		return $map[ $type ] ?? $type;
	}

	/**
	 * Risk computed on this site. Cloud risk is never allowed to lower this.
	 *
	 * @param string               $type  Action type.
	 * @param array<string, mixed> $after Proposed payload.
	 * @return string low, medium, or high.
	 */
	public static function risk_for( string $type, array $after ): string {
		$canonical = self::canonical( $type );
		if ( self::CONTENT_PATCH === $canonical || self::CREATE_DRAFT === $canonical ) {
			return 'high';
		}
		if ( self::UPDATE_TITLE === $canonical || self::UPDATE_META_DESCRIPTION === $canonical ) {
			return 'low';
		}
		if ( self::ALIAS_META === $type ) {
			if ( isset( $after['robots'] ) || isset( $after['canonical'] ) || isset( $after['seoCanonical'] ) ) {
				return 'medium';
			}
			return 'low';
		}
		return 'medium';
	}

	/**
	 * Keep the higher of the local risk and a cloud-supplied risk.
	 *
	 * @param string $local Local risk.
	 * @param string $cloud Cloud risk.
	 * @return string
	 */
	public static function stricter_risk( string $local, string $cloud ): string {
		$rank       = array(
			'low'    => 1,
			'medium' => 2,
			'high'   => 3,
		);
		$local_rank = $rank[ $local ] ?? 2;
		$cloud_rank = $rank[ $cloud ] ?? 0;
		if ( $cloud_rank > $local_rank ) {
			return $cloud;
		}
		return $local;
	}

	/**
	 * Plain-English card title.
	 *
	 * @param string $type Action type.
	 * @return string
	 */
	public static function label( string $type ): string {
		$canonical = self::canonical( $type );
		switch ( $canonical ) {
			case self::UPDATE_TITLE:
				return __( 'Update the search title', 'rankmyseo' );
			case self::UPDATE_META_DESCRIPTION:
				return __( 'Update the search description', 'rankmyseo' );
			case self::UPDATE_CANONICAL:
				return __( 'Update the preferred URL', 'rankmyseo' );
			case self::UPDATE_SCHEMA:
				return __( 'Update structured data', 'rankmyseo' );
			case self::ADD_REDIRECT:
				return __( 'Add a redirect', 'rankmyseo' );
			case self::ADD_INTERNAL_LINK:
				return __( 'Add an internal link', 'rankmyseo' );
			case self::CONTENT_PATCH:
				return __( 'Update the page content', 'rankmyseo' );
			case self::CREATE_DRAFT:
				return __( 'Create a draft', 'rankmyseo' );
			case self::ALIAS_META:
				return __( 'Update search details', 'rankmyseo' );
			default:
				return __( 'Review a suggested change', 'rankmyseo' );
		}
	}

	/**
	 * Whether this type always creates a draft and never publishes.
	 *
	 * @param string $type Action type.
	 * @return bool
	 */
	public static function is_draft( string $type ): bool {
		return self::CREATE_DRAFT === self::canonical( $type );
	}

	/**
	 * Whether this type is approval-only in v1.
	 *
	 * @param string $type Action type.
	 * @return bool
	 */
	public static function is_approval_only( string $type ): bool {
		return self::CONTENT_PATCH === self::canonical( $type );
	}
}
